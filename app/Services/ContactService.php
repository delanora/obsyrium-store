<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Canais de atendimento e mensagens da página Contato.
 *
 * O WhatsApp e o @ do Instagram ficam em `settings`, editáveis pelo painel. O
 * número é guardado só com dígitos (país + DDD + número), que é o formato que o
 * link `wa.me` exige; o rótulo bonito é montado na leitura. O Instagram é
 * guardado sem o @ e sem a URL, para o link e o texto saírem sempre iguais.
 */
final class ContactService
{
    public const WHATSAPP_KEY = 'contact_whatsapp';

    public const INSTAGRAM_KEY = 'contact_instagram';

    public const MAX_NAME = 120;

    public const MAX_EMAIL = 160;

    public const MAX_MESSAGE = 4000;

    /** Quantas mensagens o painel mostra, das mais novas para as mais antigas. */
    private const LIST_LIMIT = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Valores crus, como estão gravados.
     *
     * @return array{whatsapp: string, instagram: string}
     */
    public function settings(): array
    {
        $valores = [];

        foreach ($this->pdo->query('SELECT name, value FROM settings') as $linha) {
            $valores[(string) $linha['name']] = (string) $linha['value'];
        }

        return [
            'whatsapp' => $valores[self::WHATSAPP_KEY] ?? '',
            'instagram' => $valores[self::INSTAGRAM_KEY] ?? '',
        ];
    }

    /**
     * Canais prontos para a página pública: link e rótulo, ou null quando o
     * canal ainda não foi configurado no painel.
     *
     * @return array{whatsapp: string|null, whatsappLabel: string, instagram: string|null, instagramLabel: string}
     */
    public function channels(): array
    {
        $config = $this->settings();
        $whatsapp = $this->digits($config['whatsapp']);
        $instagram = $config['instagram'];

        return [
            'whatsapp' => $whatsapp === '' ? null : 'https://wa.me/' . $whatsapp,
            'whatsappLabel' => $this->whatsappLabel($whatsapp),
            'instagram' => $instagram === '' ? null : 'https://instagram.com/' . rawurlencode($instagram),
            'instagramLabel' => $instagram === '' ? '' : '@' . $instagram,
        ];
    }

    /**
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array{whatsapp: string, instagram: string}}
     */
    public function saveSettings(array $dados): array
    {
        $erros = [];
        $whatsappBruto = trim((string) ($dados['whatsapp'] ?? ''));
        $whatsapp = $this->digits($whatsappBruto);

        if ($whatsappBruto !== '' && (strlen($whatsapp) < 10 || strlen($whatsapp) > 13)) {
            $erros[] = 'WhatsApp: informe DDD e número, só dígitos (ex.: 11999999999).';
        }

        $instagramBruto = trim((string) ($dados['instagram'] ?? ''));
        $instagram = $this->instagram($instagramBruto);

        if ($instagram === null) {
            $erros[] = 'Instagram: use até 30 caracteres entre letras, números, ponto e sublinhado.';
        }

        if ($erros !== []) {
            return [
                'errors' => $erros,
                'values' => ['whatsapp' => $whatsappBruto, 'instagram' => $instagramBruto],
            ];
        }

        $this->store(self::WHATSAPP_KEY, $whatsapp);
        $this->store(self::INSTAGRAM_KEY, (string) $instagram);

        return [
            'errors' => [],
            'values' => ['whatsapp' => $whatsapp, 'instagram' => (string) $instagram],
        ];
    }

    /**
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array{name: string, email: string, message: string}}
     */
    public function send(array $dados): array
    {
        $nome = trim((string) ($dados['name'] ?? ''));
        $email = trim((string) ($dados['email'] ?? ''));
        $mensagem = trim((string) ($dados['message'] ?? ''));
        $valores = ['name' => $nome, 'email' => $email, 'message' => $mensagem];
        $erros = [];

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > self::MAX_NAME) {
            $erros[] = 'Nome: escreva de 2 a 120 caracteres.';
        }

        if ($email === '' || mb_strlen($email) > self::MAX_EMAIL || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erros[] = 'E-mail: informe um endereço válido.';
        }

        if (mb_strlen($mensagem) < 10 || mb_strlen($mensagem) > self::MAX_MESSAGE) {
            $erros[] = 'Mensagem: escreva de 10 a 4000 caracteres.';
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores];
        }

        $this->pdo->prepare(
            'INSERT INTO contact_messages (name, email, message) VALUES (:nome, :email, :mensagem)'
        )->execute([':nome' => $nome, ':email' => $email, ':mensagem' => $mensagem]);

        return ['errors' => [], 'values' => ['name' => '', 'email' => '', 'message' => '']];
    }

    /**
     * Mensagens recebidas, das mais novas para as mais antigas.
     *
     * @return list<array{id: int, name: string, email: string, message: string, created_at: string}>
     */
    public function messages(): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT id, name, email, message, created_at
             FROM contact_messages
             ORDER BY created_at DESC, id DESC
             LIMIT ' . self::LIST_LIMIT
        );
        $consulta->execute();

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'name' => (string) $linha['name'],
                'email' => (string) $linha['email'],
                'message' => (string) $linha['message'],
                'created_at' => (string) $linha['created_at'],
            ],
            $consulta->fetchAll()
        );
    }

    private function store(string $nome, string $valor): void
    {
        $this->pdo->prepare(
            'INSERT INTO settings (name, value) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([':name' => $nome, ':value' => $valor]);
    }

    private function digits(string $valor): string
    {
        return preg_replace('/\D+/', '', $valor) ?? '';
    }

    /**
     * Aceita só o @; recusa qualquer coisa que não seja um nome de usuário.
     * Devolve null quando o valor não serve.
     */
    private function instagram(string $valor): ?string
    {
        $handle = preg_replace('#^https?://(?:www\.)?instagram\.com/#i', '', trim($valor)) ?? '';
        $handle = rtrim(ltrim($handle, '@'), '/');

        if ($handle === '') {
            return '';
        }

        return preg_match('/^[A-Za-z0-9._]{1,30}$/', $handle) === 1 ? $handle : null;
    }

    /**
     * Mostra o telefone no formato brasileiro; números de fora ficam com o +.
     */
    private function whatsappLabel(string $digitos): string
    {
        if ($digitos === '') {
            return '';
        }

        $local = strlen($digitos) > 11 && str_starts_with($digitos, '55') ? substr($digitos, 2) : $digitos;

        if (strlen($local) === 11) {
            return sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 5), substr($local, 7, 4));
        }

        if (strlen($local) === 10) {
            return sprintf('(%s) %s-%s', substr($local, 0, 2), substr($local, 2, 4), substr($local, 6, 4));
        }

        return '+' . $digitos;
    }
}
