<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Price;
use PDO;
use Throwable;

/**
 * Edição do produto pelo painel: leitura do formulário, gravação dos campos e
 * as fotos que aparecem na página do item.
 *
 * As fotos ficam em `public/uploads/produtos` e a tabela `product_images`
 * guarda o caminho público (`/uploads/produtos/...`), o texto alternativo e a
 * posição — a menor posição é a foto principal. O placeholder do seed é
 * compartilhado: ele nunca é apagado do disco, só a linha dele sai quando a
 * primeira foto de verdade é enviada.
 */
final class ProductAdminService
{
    public const MAX_IMAGES = 8;

    private const PLACEHOLDER = '/assets/images/placeholder.svg';

    private const UPLOAD_PATH = '/uploads/produtos';

    private const MIME_EXTENSOES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Produto com categoria, fotos e tamanhos, do jeito que a tela precisa.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $consulta = $this->pdo->prepare(
            'SELECT p.id,
                    p.name,
                    p.slug,
                    p.category_id,
                    p.color,
                    p.material,
                    p.description,
                    p.price_cents,
                    p.compare_price_cents,
                    p.is_active,
                    c.name AS category_name
               FROM products p
               INNER JOIN categories c ON c.id = p.category_id
              WHERE p.id = :id
              LIMIT 1'
        );

        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        $linha = $consulta->fetch();

        if ($linha === false) {
            return null;
        }

        return [
            'id' => (int) $linha['id'],
            'name' => (string) $linha['name'],
            'slug' => (string) $linha['slug'],
            'category_id' => (int) $linha['category_id'],
            'category' => (string) $linha['category_name'],
            'color' => (string) $linha['color'],
            'material' => (string) $linha['material'],
            'description' => trim((string) ($linha['description'] ?? '')),
            'price_cents' => (int) $linha['price_cents'],
            'compare_price_cents' => $linha['compare_price_cents'] !== null ? (int) $linha['compare_price_cents'] : null,
            'is_active' => (int) $linha['is_active'] === 1,
            'images' => $this->images($id),
            'variants' => $this->variants($id),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $consulta = $this->pdo->query('SELECT id, name FROM categories ORDER BY position, name');

        return array_map(
            static fn (array $linha): array => ['id' => (int) $linha['id'], 'name' => (string) $linha['name']],
            $consulta->fetchAll()
        );
    }

    /**
     * Grava o formulário do produto.
     *
     * @param array<string, mixed> $dados campos crus do POST
     *
     * @return array{errors: list<string>, values: array<string, string>}
     */
    public function save(int $id, array $dados): array
    {
        $atual = $this->find($id);

        if ($atual === null) {
            return ['errors' => ['Produto não encontrado.'], 'values' => []];
        }

        $nome = trim((string) ($dados['name'] ?? ''));
        $slug = trim((string) ($dados['slug'] ?? ''));
        $cor = trim((string) ($dados['color'] ?? ''));
        $material = trim((string) ($dados['material'] ?? ''));
        $descricao = trim((string) ($dados['description'] ?? ''));
        $precoBruto = trim((string) ($dados['price'] ?? ''));
        $comparativoBruto = trim((string) ($dados['compare_price'] ?? ''));
        $categoriaId = (int) ($dados['category_id'] ?? 0);
        $ativo = isset($dados['is_active']) ? 1 : 0;

        $valores = [
            'name' => $nome,
            'slug' => $slug,
            'color' => $cor,
            'material' => $material,
            'description' => $descricao,
            'price' => $precoBruto,
            'compare_price' => $comparativoBruto,
            'category_id' => (string) $categoriaId,
            'is_active' => (string) $ativo,
        ];

        $erros = [];

        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 120) {
            $erros[] = 'O nome precisa ter de 3 a 120 caracteres.';
        }

        if ($slug === '') {
            $slug = $this->slugify($nome);
            $valores['slug'] = $slug;
        }

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1 || mb_strlen($slug) > 140) {
            $erros[] = 'O endereço do produto aceita apenas letras minúsculas, números e hífen (até 140).';
        } elseif ($this->slugEmUso($slug, $id)) {
            $erros[] = sprintf('O endereço "%s" já é de outro produto.', $slug);
        }

        if (mb_strlen($cor) < 2 || mb_strlen($cor) > 40) {
            $erros[] = 'A cor precisa ter de 2 a 40 caracteres.';
        }

        if (mb_strlen($material) < 2 || mb_strlen($material) > 80) {
            $erros[] = 'O material precisa ter de 2 a 80 caracteres.';
        }

        if (mb_strlen($descricao) > 2000) {
            $erros[] = 'A descrição vai até 2000 caracteres.';
        }

        if ($categoriaId <= 0 || !$this->categoriaExiste($categoriaId)) {
            $erros[] = 'Escolha uma categoria da lista.';
        }

        $preco = $this->cents($precoBruto);

        if ($preco === null || $preco <= 0) {
            $erros[] = 'Informe o preço em reais, por exemplo 129,90.';
        }

        $comparativo = null;

        if ($comparativoBruto !== '') {
            $comparativo = $this->cents($comparativoBruto);

            if ($comparativo === null || $comparativo <= 0) {
                $erros[] = 'O preço cheio precisa ser um valor em reais, ou ficar vazio.';
            } elseif ($preco !== null && $comparativo <= $preco) {
                $erros[] = 'O preço cheio precisa ser maior que o preço promocional.';
            }
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores];
        }

        $atualizar = $this->pdo->prepare(
            'UPDATE products
                SET category_id = :category,
                    name = :name,
                    slug = :slug,
                    color = :color,
                    material = :material,
                    description = :description,
                    price_cents = :price,
                    compare_price_cents = :compare,
                    is_active = :active
              WHERE id = :id'
        );

        $atualizar->bindValue(':category', $categoriaId, PDO::PARAM_INT);
        $atualizar->bindValue(':name', $nome);
        $atualizar->bindValue(':slug', $slug);
        $atualizar->bindValue(':color', $cor);
        $atualizar->bindValue(':material', $material);
        $atualizar->bindValue(':description', $descricao === '' ? null : $descricao, $descricao === '' ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $atualizar->bindValue(':price', (int) $preco, PDO::PARAM_INT);
        $atualizar->bindValue(':compare', $comparativo, $comparativo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $atualizar->bindValue(':active', $ativo, PDO::PARAM_INT);
        $atualizar->bindValue(':id', $id, PDO::PARAM_INT);
        $atualizar->execute();

        return ['errors' => [], 'values' => $valores];
    }

    /**
     * Guarda uma foto enviada pelo formulário e devolve o caminho público.
     *
     * Sem GD/Imagick no servidor, a conferência é por conteúdo: `getimagesize`
     * reconhece o formato e o finfo confirma o tipo real do arquivo. O nome é
     * sorteado por nós, então nada do cliente vira caminho no disco.
     *
     * @param array<string, mixed> $arquivo entrada de $_FILES
     *
     * @return array{path?: string, error?: string}
     */
    public function upload(int $productId, array $arquivo): array
    {
        $produto = $this->find($productId);

        if ($produto === null) {
            return ['error' => 'Produto não encontrado.'];
        }

        $quantidade = count($produto['images']);

        if ($quantidade >= self::MAX_IMAGES) {
            return ['error' => sprintf('Este produto já tem %d fotos: apague uma antes de enviar outra.', self::MAX_IMAGES)];
        }

        $codigo = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($codigo !== UPLOAD_ERR_OK) {
            return ['error' => match ($codigo) {
                UPLOAD_ERR_NO_FILE => 'Escolha uma imagem antes de enviar.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf('A imagem passa do limite de %s.', $this->tamanhoLimite()),
                UPLOAD_ERR_PARTIAL => 'O envio da imagem foi interrompido. Tente de novo.',
                default => 'Não foi possível receber a imagem. Tente de novo.',
            }];
        }

        $temporario = (string) ($arquivo['tmp_name'] ?? '');

        if ($temporario === '' || !is_uploaded_file($temporario)) {
            return ['error' => 'Não foi possível receber a imagem. Tente de novo.'];
        }

        $bytes = (int) ($arquivo['size'] ?? 0);

        if ($bytes <= 0 || $bytes > $this->limiteBytes()) {
            return ['error' => sprintf('A imagem precisa ter até %s.', $this->tamanhoLimite())];
        }

        $medidas = @getimagesize($temporario);

        if ($medidas === false) {
            return ['error' => 'O arquivo enviado não é uma imagem válida.'];
        }

        $mime = (string) ($medidas['mime'] ?? '');

        if (!isset(self::MIME_EXTENSOES[$mime])) {
            return ['error' => 'Envie uma imagem JPG, PNG ou WebP.'];
        }

        $mimeReal = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($temporario);

        if ($mimeReal !== $mime && !($mime === 'image/jpeg' && $mimeReal === 'image/jpg')) {
            return ['error' => 'O conteúdo do arquivo não corresponde a uma imagem.'];
        }

        $diretorio = $this->diretorio();

        if (!is_dir($diretorio) && !@mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
            return ['error' => 'Não foi possível preparar a pasta das fotos.'];
        }

        $nomeArquivo = bin2hex(random_bytes(8)) . '.' . self::MIME_EXTENSOES[$mime];

        if (!move_uploaded_file($temporario, $diretorio . '/' . $nomeArquivo)) {
            return ['error' => 'Não foi possível guardar a imagem.'];
        }

        $caminho = self::UPLOAD_PATH . '/' . $nomeArquivo;

        $this->pdo->beginTransaction();

        try {
            // A primeira foto de verdade assume o lugar do placeholder, que não
            // pode continuar como principal.
            $this->pdo->prepare('DELETE FROM product_images WHERE product_id = :id AND path = :path')
                ->execute([':id' => $productId, ':path' => self::PLACEHOLDER]);

            $posicao = (int) $this->pdo->query(
                'SELECT COALESCE(MAX(position) + 1, 0) FROM product_images WHERE product_id = ' . $productId
            )->fetchColumn();

            $this->pdo->prepare(
                'INSERT INTO product_images (product_id, path, alt, position) VALUES (:id, :path, :alt, :position)'
            )->execute([
                ':id' => $productId,
                ':path' => $caminho,
                ':alt' => sprintf('%s, %s, na cor %s', (string) $produto['name'], (string) $produto['material'], (string) $produto['color']),
                ':position' => $posicao,
            ]);

            $this->pdo->commit();
        } catch (Throwable $erro) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            @unlink($diretorio . '/' . $nomeArquivo);

            throw $erro;
        }

        return ['path' => $caminho];
    }

    /**
     * Apaga uma foto do produto. Só mexe em arquivo da pasta de uploads: o
     * placeholder e qualquer caminho de fora ficam onde estão.
     */
    public function removeImage(int $productId, int $imageId): ?string
    {
        $consulta = $this->pdo->prepare(
            'SELECT path FROM product_images WHERE id = :image AND product_id = :product LIMIT 1'
        );
        $consulta->execute([':image' => $imageId, ':product' => $productId]);

        $caminho = $consulta->fetchColumn();

        if ($caminho === false) {
            return 'Foto não encontrada.';
        }

        $this->pdo->prepare('DELETE FROM product_images WHERE id = :image AND product_id = :product')
            ->execute([':image' => $imageId, ':product' => $productId]);

        $arquivo = (string) $caminho;

        if (str_starts_with($arquivo, self::UPLOAD_PATH . '/') && !str_contains($arquivo, '..')) {
            @unlink(dirname(__DIR__, 2) . '/public' . $arquivo);
        }

        return null;
    }

    /**
     * Resumo de preço para o cabeçalho da tela.
     *
     * @param array<string, mixed> $produto
     *
     * @return array{price: string, old: string|null, discount: int}
     */
    public function priceTags(array $produto): array
    {
        $preco = (int) $produto['price_cents'];
        $antigo = $produto['compare_price_cents'];

        return [
            'price' => Price::format($preco),
            'old' => $antigo !== null ? Price::format((int) $antigo) : null,
            'discount' => $antigo !== null ? Price::discountPercent((int) $antigo, $preco) : 0,
        ];
    }

    /**
     * Limite de tamanho de cada foto, em texto ("2 MB").
     */
    public function photoLimitLabel(): string
    {
        return $this->tamanhoLimite();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function images(int $productId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, path, alt FROM product_images WHERE product_id = :id ORDER BY position, id'
        );
        $consulta->bindValue(':id', $productId, PDO::PARAM_INT);
        $consulta->execute();

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'path' => (string) $linha['path'],
                'alt' => (string) $linha['alt'],
            ],
            $consulta->fetchAll()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function variants(int $productId): array
    {
        $consulta = $this->pdo->prepare(
            "SELECT v.id, v.size, v.sku, COALESCE(s.quantity, 0) AS quantity
               FROM product_variants v
               LEFT JOIN stock s ON s.variant_id = v.id
              WHERE v.product_id = :id
              ORDER BY FIELD(v.size, 'PP', 'P', 'M', 'G', 'GG', 'XG', 'XGG'), v.size"
        );
        $consulta->bindValue(':id', $productId, PDO::PARAM_INT);
        $consulta->execute();

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'size' => (string) $linha['size'],
                'sku' => (string) $linha['sku'],
                'quantity' => (int) $linha['quantity'],
            ],
            $consulta->fetchAll()
        );
    }

    private function slugEmUso(string $slug, int $id): bool
    {
        $consulta = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :slug AND id <> :id');
        $consulta->bindValue(':slug', $slug);
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        return (int) $consulta->fetchColumn() > 0;
    }

    private function categoriaExiste(int $id): bool
    {
        $consulta = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = :id');
        $consulta->bindValue(':id', $id, PDO::PARAM_INT);
        $consulta->execute();

        return (int) $consulta->fetchColumn() > 0;
    }

    /**
     * "R$ 1.234,56", "1234,56" e "1234.56" viram 123456 centavos.
     */
    private function cents(string $valor): ?int
    {
        $limpo = preg_replace('/[^0-9,.]/', '', $valor) ?? '';

        if ($limpo === '' || preg_match('/\d/', $limpo) !== 1) {
            return null;
        }

        if (str_contains($limpo, ',')) {
            $limpo = str_replace('.', '', $limpo);
            $limpo = str_replace(',', '.', $limpo);
        }

        if (substr_count($limpo, '.') > 1) {
            return null;
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $limpo) !== 1) {
            return null;
        }

        return (int) round((float) $limpo * 100);
    }

    private function slugify(string $texto): string
    {
        $minusculo = mb_strtolower(trim($texto));
        $semAcento = strtr($minusculo, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        $slug = preg_replace('/[^a-z0-9]+/', '-', $semAcento) ?? '';

        return trim($slug, '-');
    }

    private function diretorio(): string
    {
        return dirname(__DIR__, 2) . '/public' . self::UPLOAD_PATH;
    }

    private function limiteBytes(): int
    {
        $enviado = $this->iniBytes('upload_max_filesize');

        if ($enviado <= 0) {
            return self::MAX_IMAGE_BYTES;
        }

        return min($enviado, self::MAX_IMAGE_BYTES);
    }

    private function tamanhoLimite(): string
    {
        $megabytes = $this->limiteBytes() / (1024 * 1024);

        return number_format($megabytes, $megabytes >= 1 ? 0 : 1, ',', '.') . ' MB';
    }

    private function iniBytes(string $chave): int
    {
        $valor = trim((string) ini_get($chave));

        if ($valor === '' || $valor === '-1') {
            return 0;
        }

        return match (strtolower(substr($valor, -1))) {
            'g' => (int) $valor * 1024 * 1024 * 1024,
            'm' => (int) $valor * 1024 * 1024,
            'k' => (int) $valor * 1024,
            default => (int) $valor,
        };
    }
}
