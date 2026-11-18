<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Topo da página inicial: fotos com texto opcional.
 *
 * Cada linha de `hero_slides` é um slide do carrossel da home. A foto é
 * obrigatória e os textos são opcionais: com todos vazios, a loja mostra só a
 * imagem, sem véu escuro e sem botão — é o "sem texto nenhum" que o painel
 * permite salvar.
 *
 * As fotos ficam em `public/uploads/hero`, no mesmo esquema das fotos de
 * produto: nome aleatório, tipo conferido pelo conteúdo e arquivo apagado
 * junto com a linha. A ordem é a coluna `position`, sempre a próxima livre, e
 * a primeira foto ativa é a que abre a página.
 */
final class HomeHeroService
{
    /** Quantas fotos o topo aceita de uma vez. */
    public const MAX_SLIDES = 6;

    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public const UPLOAD_PATH = '/uploads/hero';

    public const MAX_ALT = 160;

    public const MAX_EYEBROW = 80;

    public const MAX_TITLE = 160;

    public const MAX_TEXT = 400;

    public const MAX_BUTTON_LABEL = 60;

    public const MAX_BUTTON_HREF = 255;

    private const MIME_EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Todas as fotos, inclusive as escondidas — é o que o painel lista.
     *
     * @return list<array{id: int, image: string, image_alt: string, eyebrow: string, title: string, text: string, button_label: string, button_href: string, is_active: bool, position: int}>
     */
    public function all(): array
    {
        $consulta = $this->pdo->query(
            'SELECT id, image, image_alt, eyebrow, title, text, button_label, button_href, is_active, position
             FROM hero_slides
             ORDER BY position, id'
        );

        return array_map([self::class, 'hydrate'], $consulta->fetchAll());
    }

    /**
     * O que a loja mostra: só as fotos ativas, na ordem.
     *
     * @return list<array{id: int, image: string, image_alt: string, eyebrow: string, title: string, text: string, button_label: string, button_href: string, is_active: bool, position: int}>
     */
    public function slides(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (array $slide): bool => $slide['is_active']
        ));
    }

    /**
     * @return array{id: int, image: string, image_alt: string, eyebrow: string, title: string, text: string, button_label: string, button_href: string, is_active: bool, position: int}|null
     */
    public function find(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, image, image_alt, eyebrow, title, text, button_label, button_href, is_active, position
             FROM hero_slides
             WHERE id = :id
             LIMIT 1'
        );
        $consulta->execute([':id' => $id]);

        $linha = $consulta->fetch();

        return $linha === false ? null : self::hydrate($linha);
    }

    /**
     * Recebe a foto enviada e cria um slide sem texto, para o painel preencher.
     *
     * @param array<string, mixed> $arquivo
     *
     * @return array{error?: string}
     */
    public function upload(array $arquivo): array
    {
        if (count($this->all()) >= self::MAX_SLIDES) {
            return ['error' => sprintf('O topo já tem %d fotos: apague uma antes de enviar outra.', self::MAX_SLIDES)];
        }

        $codigo = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($codigo !== UPLOAD_ERR_OK) {
            return ['error' => match ($codigo) {
                UPLOAD_ERR_NO_FILE => 'Escolha uma foto antes de enviar.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf('A foto passa do limite de %s.', $this->limitLabel()),
                UPLOAD_ERR_PARTIAL => 'O envio da foto foi interrompido. Tente de novo.',
                default => 'Não foi possível receber a foto. Tente de novo.',
            }];
        }

        $temporario = (string) ($arquivo['tmp_name'] ?? '');

        if ($temporario === '' || !is_uploaded_file($temporario)) {
            return ['error' => 'Não foi possível receber a foto. Tente de novo.'];
        }

        $bytes = (int) ($arquivo['size'] ?? 0);

        if ($bytes <= 0 || $bytes > self::MAX_IMAGE_BYTES) {
            return ['error' => sprintf('A foto precisa ter até %s.', $this->limitLabel())];
        }

        $medidas = @getimagesize($temporario);

        if ($medidas === false) {
            return ['error' => 'O arquivo enviado não é uma imagem válida.'];
        }

        $mime = (string) ($medidas['mime'] ?? '');

        if (!isset(self::MIME_EXTENSOES[$mime])) {
            return ['error' => 'Envie uma foto JPG, PNG ou WebP.'];
        }

        $mimeReal = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($temporario);

        if ($mimeReal !== $mime && !($mime === 'image/jpeg' && $mimeReal === 'image/jpg')) {
            return ['error' => 'O conteúdo do arquivo não corresponde a uma imagem.'];
        }

        $diretorio = $this->directory();

        if (!is_dir($diretorio) && !@mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
            return ['error' => 'Não foi possível preparar a pasta das fotos.'];
        }

        $nomeArquivo = bin2hex(random_bytes(8)) . '.' . self::MIME_EXTENSOES[$mime];

        if (!move_uploaded_file($temporario, $diretorio . '/' . $nomeArquivo)) {
            return ['error' => 'Não foi possível guardar a foto.'];
        }

        $posicao = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(position) + 1, 0) FROM hero_slides'
        )->fetchColumn();

        $this->pdo->prepare(
            'INSERT INTO hero_slides (image, position) VALUES (:image, :position)'
        )->execute([':image' => self::UPLOAD_PATH . '/' . $nomeArquivo, ':position' => $posicao]);

        return [];
    }

    /**
     * Salva os textos do slide. Campos vazios já significam "não mostrar".
     *
     * @param array<string, mixed> $dados
     *
     * @return string|null mensagem de erro, ou null quando salvou
     */
    public function save(int $id, array $dados): ?string
    {
        if ($this->find($id) === null) {
            return 'Foto não encontrada no topo da loja.';
        }

        $alt = trim((string) ($dados['image_alt'] ?? ''));
        $eyebrow = trim((string) ($dados['eyebrow'] ?? ''));
        $titulo = trim((string) ($dados['title'] ?? ''));
        $texto = trim((string) ($dados['text'] ?? ''));
        $rotuloBotao = trim((string) ($dados['button_label'] ?? ''));
        $linkBotao = trim((string) ($dados['button_href'] ?? ''));
        $ativo = (string) ($dados['is_active'] ?? '') === '1';
        $erros = [];

        $limites = [
            'Descrição da foto' => [$alt, self::MAX_ALT],
            'Linha de cima' => [$eyebrow, self::MAX_EYEBROW],
            'Título' => [$titulo, self::MAX_TITLE],
            'Texto' => [$texto, self::MAX_TEXT],
            'Texto do botão' => [$rotuloBotao, self::MAX_BUTTON_LABEL],
        ];

        foreach ($limites as $campo => [$valor, $limite]) {
            if (mb_strlen($valor) > $limite) {
                $erros[] = sprintf('%s: use até %d caracteres.', $campo, $limite);
            }
        }

        if (mb_strlen($linkBotao) > self::MAX_BUTTON_HREF) {
            $erros[] = sprintf('Link do botão: use até %d caracteres.', self::MAX_BUTTON_HREF);
        } elseif ($linkBotao !== '' && preg_match('#^(/|\#|https?://)#i', $linkBotao) !== 1) {
            $erros[] = 'Link do botão: comece com /, # ou http.';
        }

        if (($rotuloBotao === '') !== ($linkBotao === '')) {
            $erros[] = 'Botão: preencha o texto e o link, ou deixe os dois vazios.';
        }

        if ($erros !== []) {
            return implode(' ', $erros);
        }

        $this->pdo->prepare(
            'UPDATE hero_slides
             SET image_alt = :alt, eyebrow = :eyebrow, title = :title, text = :text,
                 button_label = :button_label, button_href = :button_href, is_active = :active
             WHERE id = :id'
        )->execute([
            ':alt' => $alt,
            ':eyebrow' => $eyebrow,
            ':title' => $titulo,
            ':text' => $texto,
            ':button_label' => $rotuloBotao,
            ':button_href' => $linkBotao,
            ':active' => $ativo ? 1 : 0,
            ':id' => $id,
        ]);

        return null;
    }

    /**
     * Apaga a foto da loja e o arquivo dela.
     */
    public function remove(int $id): ?string
    {
        $slide = $this->find($id);

        if ($slide === null) {
            return 'Foto não encontrada no topo da loja.';
        }

        $this->pdo->prepare('DELETE FROM hero_slides WHERE id = :id')->execute([':id' => $id]);
        $this->deleteFile($slide['image']);

        return null;
    }

    /**
     * Rótulo do limite de tamanho, como aparece no painel.
     */
    public function limitLabel(): string
    {
        return sprintf('%d MB', (int) round(self::MAX_IMAGE_BYTES / 1048576));
    }

    /**
     * @param array<string, mixed> $linha
     *
     * @return array{id: int, image: string, image_alt: string, eyebrow: string, title: string, text: string, button_label: string, button_href: string, is_active: bool, position: int}
     */
    private static function hydrate(array $linha): array
    {
        return [
            'id' => (int) $linha['id'],
            'image' => (string) $linha['image'],
            'image_alt' => (string) $linha['image_alt'],
            'eyebrow' => (string) $linha['eyebrow'],
            'title' => (string) $linha['title'],
            'text' => (string) $linha['text'],
            'button_label' => (string) $linha['button_label'],
            'button_href' => (string) $linha['button_href'],
            'is_active' => (int) $linha['is_active'] === 1,
            'position' => (int) $linha['position'],
        ];
    }

    /**
     * Só apaga arquivo que está dentro da pasta das fotos do topo.
     */
    private function deleteFile(string $caminho): void
    {
        $nome = basename($caminho);

        if ($nome === '' || $nome === '.' || $nome === '..') {
            return;
        }

        $arquivo = $this->directory() . '/' . $nome;

        if (is_file($arquivo)) {
            @unlink($arquivo);
        }
    }

    private function directory(): string
    {
        return dirname(__DIR__, 2) . '/public' . self::UPLOAD_PATH;
    }
}
