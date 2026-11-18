<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Páginas institucionais: o texto das páginas do rodapé e do Sobre, editado no
 * painel em vez de morar na view.
 *
 * Cada página tem um cabeçalho (linha de cima, título, introdução e a nota do
 * fim) e uma lista de blocos na ordem em que aparecem. O texto de cada bloco é
 * texto puro: linha em branco separa parágrafos, linha começando com "- " vira
 * item de lista e `[rótulo](/caminho)` vira link interno. Quem transforma isso
 * em HTML é `renderBody()`, que escapa todo o texto antes de montar as tags.
 *
 * O Contato continua fora daqui: ele lê os canais do banco e tem formulário.
 */
final class PageService
{
    public const MAX_EYEBROW = 60;

    public const MAX_TITLE = 140;

    public const MAX_INTRO = 400;

    public const MAX_NOTE = 500;

    public const MAX_HEADING = 160;

    public const MAX_BODY = 6000;

    public const MAX_LINK_LABEL = 80;

    public const MAX_LINK_HREF = 255;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Páginas na ordem de cadastro, com a contagem de blocos — é o que a aba do
     * painel lista nas abas.
     *
     * @return list<array{id: int, slug: string, title: string, eyebrow: string, layout: string, blocks: int}>
     */
    public function all(): array
    {
        $consulta = $this->pdo->query(
            'SELECT p.id, p.slug, p.title, p.eyebrow, p.layout, COUNT(b.id) AS blocks
               FROM pages p
               LEFT JOIN page_blocks b ON b.page_id = p.id
              GROUP BY p.id, p.slug, p.title, p.eyebrow, p.layout
              ORDER BY p.id'
        );

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'slug' => (string) $linha['slug'],
                'title' => (string) $linha['title'],
                'eyebrow' => (string) $linha['eyebrow'],
                'layout' => (string) $linha['layout'],
                'blocks' => (int) $linha['blocks'],
            ],
            $consulta->fetchAll()
        );
    }

    /**
     * @return array{id: int, slug: string, eyebrow: string, title: string, intro: string, note: string|null, layout: string, blocks: list<array<string, mixed>>}|null
     */
    public function find(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, slug, eyebrow, title, intro, note, layout FROM pages WHERE id = :id LIMIT 1'
        );
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        return $this->montar($consulta->fetch());
    }

    /**
     * @return array{id: int, slug: string, eyebrow: string, title: string, intro: string, note: string|null, layout: string, blocks: list<array<string, mixed>>}|null
     */
    public function findBySlug(string $slug): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, slug, eyebrow, title, intro, note, layout FROM pages WHERE slug = :slug LIMIT 1'
        );
        $consulta->bindValue(':slug', $slug);
        $consulta->execute();

        return $this->montar($consulta->fetch());
    }

    /**
     * Salva o cabeçalho da página.
     *
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array<string, string>}
     */
    public function save(int $id, array $dados): array
    {
        $pagina = $this->find($id);
        $eyebrow = trim((string) ($dados['eyebrow'] ?? ''));
        $titulo = trim((string) ($dados['title'] ?? ''));
        $intro = trim((string) ($dados['intro'] ?? ''));
        $nota = trim((string) ($dados['note'] ?? ''));
        $valores = [
            'eyebrow' => $eyebrow,
            'title' => $titulo,
            'intro' => $intro,
            'note' => $nota,
        ];

        if ($pagina === null) {
            return ['errors' => ['Página não encontrada.'], 'values' => $valores];
        }

        $erros = [];

        foreach ([
            'Linha de cima' => [$eyebrow, self::MAX_EYEBROW],
            'Título' => [$titulo, self::MAX_TITLE],
            'Introdução' => [$intro, self::MAX_INTRO],
            'Nota do fim' => [$nota, self::MAX_NOTE],
        ] as $campo => [$valor, $limite]) {
            if (mb_strlen($valor) > $limite) {
                $erros[] = sprintf('%s: use até %d caracteres.', $campo, $limite);
            }
        }

        if (mb_strlen($titulo) < 2) {
            $erros[] = 'O título precisa ter ao menos 2 caracteres.';
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores];
        }

        $this->pdo->prepare(
            'UPDATE pages SET eyebrow = :eyebrow, title = :title, intro = :intro, note = :note WHERE id = :id'
        )->execute([
            ':eyebrow' => $eyebrow,
            ':title' => $titulo,
            ':intro' => $intro,
            ':note' => $nota === '' ? null : $nota,
            ':id' => $id,
        ]);

        return ['errors' => [], 'values' => $valores];
    }

    /**
     * Cria um bloco vazio no fim da página, para o painel preencher.
     */
    public function addBlock(int $pageId): ?string
    {
        if ($this->find($pageId) === null) {
            return 'Página não encontrada.';
        }

        $posicao = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(position) + 1, 0) FROM page_blocks WHERE page_id = ' . $pageId
        )->fetchColumn();

        $this->pdo->prepare(
            'INSERT INTO page_blocks (page_id, position) VALUES (:page, :position)'
        )->execute([':page' => $pageId, ':position' => $posicao]);

        return null;
    }

    /**
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array<string, string>}
     */
    public function saveBlock(int $pageId, int $blockId, array $dados): array
    {
        $heading = trim((string) ($dados['heading'] ?? ''));
        $body = trim(str_replace("\r\n", "\n", (string) ($dados['body'] ?? '')));
        $linkLabel = trim((string) ($dados['link_label'] ?? ''));
        $linkHref = trim((string) ($dados['link_href'] ?? ''));
        $valores = [
            'heading' => $heading,
            'body' => $body,
            'link_label' => $linkLabel,
            'link_href' => $linkHref,
        ];

        if ($this->blockBelongs($pageId, $blockId) === false) {
            return ['errors' => ['Bloco não encontrado nesta página.'], 'values' => $valores];
        }

        $erros = [];

        if (mb_strlen($heading) > self::MAX_HEADING) {
            $erros[] = sprintf('Título do bloco: use até %d caracteres.', self::MAX_HEADING);
        }

        if (mb_strlen($body) > self::MAX_BODY) {
            $erros[] = sprintf('Texto do bloco: use até %d caracteres.', self::MAX_BODY);
        }

        if (mb_strlen($linkLabel) > self::MAX_LINK_LABEL) {
            $erros[] = sprintf('Texto do link: use até %d caracteres.', self::MAX_LINK_LABEL);
        }

        if (mb_strlen($linkHref) > self::MAX_LINK_HREF) {
            $erros[] = sprintf('Link: use até %d caracteres.', self::MAX_LINK_HREF);
        } elseif ($linkHref !== '' && preg_match('#^(/|\#|https?://)#i', $linkHref) !== 1) {
            $erros[] = 'Link: comece com /, # ou http.';
        }

        if (($linkLabel === '') !== ($linkHref === '')) {
            $erros[] = 'Link: preencha o texto e o endereço, ou deixe os dois vazios.';
        }

        if ($heading === '' && $body === '') {
            $erros[] = 'Escreva um título ou um texto para o bloco.';
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores];
        }

        $this->pdo->prepare(
            'UPDATE page_blocks
                SET heading = :heading, body = :body, link_label = :link_label, link_href = :link_href
              WHERE id = :block AND page_id = :page'
        )->execute([
            ':heading' => $heading,
            ':body' => $body,
            ':link_label' => $linkLabel,
            ':link_href' => $linkHref,
            ':block' => $blockId,
            ':page' => $pageId,
        ]);

        return ['errors' => [], 'values' => $valores];
    }

    public function removeBlock(int $pageId, int $blockId): ?string
    {
        if ($this->blockBelongs($pageId, $blockId) === false) {
            return 'Bloco não encontrado nesta página.';
        }

        $this->pdo->prepare('DELETE FROM page_blocks WHERE id = :block AND page_id = :page')
            ->execute([':block' => $blockId, ':page' => $pageId]);

        $this->renumber($pageId);

        return null;
    }

    /**
     * Sobe (-1) ou desce (+1) o bloco na ordem da página.
     */
    public function moveBlock(int $pageId, int $blockId, int $direction): ?string
    {
        if ($direction !== -1 && $direction !== 1) {
            return 'Direção inválida para mover o bloco.';
        }

        $ids = array_map(
            'intval',
            $this->pdo->query(
                'SELECT id FROM page_blocks WHERE page_id = ' . $pageId . ' ORDER BY position, id'
            )->fetchAll(PDO::FETCH_COLUMN)
        );

        $atual = array_search($blockId, $ids, true);

        if ($atual === false) {
            return 'Bloco não encontrado nesta página.';
        }

        $alvo = $atual + $direction;

        if ($alvo < 0 || $alvo >= count($ids)) {
            return null;
        }

        [$ids[$atual], $ids[$alvo]] = [$ids[$alvo], $ids[$atual]];

        $this->pdo->beginTransaction();

        try {
            $atualizar = $this->pdo->prepare(
                'UPDATE page_blocks SET position = :position WHERE id = :block AND page_id = :page'
            );

            foreach ($ids as $posicao => $id) {
                $atualizar->execute([':position' => $posicao, ':block' => $id, ':page' => $pageId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $erro) {
            $this->pdo->rollBack();

            throw $erro;
        }

        return null;
    }

    /**
     * Converte o texto de um bloco em HTML.
     *
     * Tudo é escapado antes; depois só os links no formato `[rótulo](destino)`
     * viram `<a>`, e apenas quando o destino é interno, âncora ou http(s).
     */
    public function renderBody(string $body): string
    {
        $paragrafos = preg_split('/\n\s*\n/', trim($body)) ?: [];
        $html = '';

        foreach ($paragrafos as $paragrafo) {
            $linhas = array_values(array_filter(
                array_map('trim', explode("\n", $paragrafo)),
                static fn (string $linha): bool => $linha !== ''
            ));

            if ($linhas === []) {
                continue;
            }

            $itens = array_filter($linhas, static fn (string $linha): bool => str_starts_with($linha, '- '));

            if (count($itens) === count($linhas)) {
                $html .= '<ul>';

                foreach ($linhas as $linha) {
                    $html .= '<li>' . $this->inline(mb_substr($linha, 2)) . '</li>';
                }

                $html .= '</ul>';

                continue;
            }

            $html .= '<p>' . implode('<br>', array_map([$this, 'inline'], $linhas)) . '</p>';
        }

        return $html;
    }

    /**
     * @param mixed $linha
     *
     * @return array{id: int, slug: string, eyebrow: string, title: string, intro: string, note: string|null, layout: string, blocks: list<array<string, mixed>>}|null
     */
    private function montar(mixed $linha): ?array
    {
        if (!is_array($linha)) {
            return null;
        }

        $blocos = $this->pdo->prepare(
            'SELECT id, heading, body, link_label, link_href
               FROM page_blocks
              WHERE page_id = :id
              ORDER BY position, id'
        );
        $blocos->bindValue(':id', (int) $linha['id'], PDO::PARAM_INT);
        $blocos->execute();

        return [
            'id' => (int) $linha['id'],
            'slug' => (string) $linha['slug'],
            'eyebrow' => (string) $linha['eyebrow'],
            'title' => (string) $linha['title'],
            'intro' => (string) $linha['intro'],
            'note' => $linha['note'] !== null ? (string) $linha['note'] : null,
            'layout' => (string) $linha['layout'],
            'blocks' => array_map(
                fn (array $bloco): array => [
                    'id' => (int) $bloco['id'],
                    'heading' => (string) $bloco['heading'],
                    'body' => (string) $bloco['body'],
                    'link_label' => (string) $bloco['link_label'],
                    'link_href' => (string) $bloco['link_href'],
                    'html' => $this->renderBody((string) $bloco['body']),
                ],
                $blocos->fetchAll()
            ),
        ];
    }

    private function blockBelongs(int $pageId, int $blockId): bool
    {
        $consulta = $this->pdo->prepare(
            'SELECT COUNT(*) FROM page_blocks WHERE id = :block AND page_id = :page'
        );
        $consulta->execute([':block' => $blockId, ':page' => $pageId]);

        return (int) $consulta->fetchColumn() > 0;
    }

    private function renumber(int $pageId): void
    {
        $ids = $this->pdo->query(
            'SELECT id FROM page_blocks WHERE page_id = ' . $pageId . ' ORDER BY position, id'
        )->fetchAll(PDO::FETCH_COLUMN);

        $atualizar = $this->pdo->prepare(
            'UPDATE page_blocks SET position = :position WHERE id = :block AND page_id = :page'
        );

        foreach ($ids as $posicao => $id) {
            $atualizar->execute([':position' => $posicao, ':block' => (int) $id, ':page' => $pageId]);
        }
    }

    private function inline(string $texto): string
    {
        $escapado = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

        return preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/u',
            static function (array $achado): string {
                $destino = html_entity_decode($achado[2], ENT_QUOTES, 'UTF-8');

                if (preg_match('#^(/|\#|https?://)#i', $destino) !== 1) {
                    return $achado[0];
                }

                return sprintf(
                    '<a class="page__link" href="%s">%s</a>',
                    htmlspecialchars($destino, ENT_QUOTES, 'UTF-8'),
                    $achado[1]
                );
            },
            $escapado
        ) ?? $escapado;
    }
}
