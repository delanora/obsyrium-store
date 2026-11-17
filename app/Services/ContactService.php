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
 *
 * As mensagens do formulário vivem em `contact_messages` e são lidas na aba
 * Mensagens. `is_read` é o "lida" e `deleted_at` é a lixeira: apagar só marca a
 * data, então dá para restaurar; a exclusão definitiva só vale a partir dela.
 *
 * O formulário público tem duas defesas contra robô: o campo armadilha
 * `website`, que gente não vê e robô preenche, e o limite de envios por IP em
 * `contact_attempts` (o IP entra só como hash). Os links de resposta são
 * montados aqui para a view não repetir a formatação de e-mail e WhatsApp.
 */
final class ContactService
{
    public const WHATSAPP_KEY = 'contact_whatsapp';

    public const INSTAGRAM_KEY = 'contact_instagram';

    public const MAX_NAME = 120;

    public const MAX_EMAIL = 160;

    public const MAX_PHONE = 30;

    public const MAX_MESSAGE = 4000;

    public const FILTER_ALL = 'todas';

    public const FILTER_UNREAD = 'nao_lidas';

    public const FILTER_TRASH = 'lixeira';

    /** Quantas mensagens cada aba mostra, das mais novas para as mais antigas. */
    private const LIST_LIMIT = 100;

    /** Envios aceitos por IP dentro da janela abaixo. */
    public const MAX_SENDS_PER_WINDOW = 5;

    public const WINDOW_MINUTES = 60;

    /** Campo escondido que só robô preenche. */
    public const HONEYPOT_FIELD = 'website';

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
            'whatsappLabel' => self::phoneLabel($whatsapp),
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
     * Recebe o formulário. `spam` é o robô que caiu na armadilha (o controlador
     * finge sucesso e não grava nada) e `throttled` é o IP que passou do limite.
     *
     * @param array<string, mixed> $dados
     *
     * @return array{errors: list<string>, values: array{name: string, email: string, phone: string, message: string}, spam: bool, throttled: bool}
     */
    public function send(array $dados, string $ip = ''): array
    {
        $nome = trim((string) ($dados['name'] ?? ''));
        $email = trim((string) ($dados['email'] ?? ''));
        $telefone = trim((string) ($dados['phone'] ?? ''));
        $mensagem = trim((string) ($dados['message'] ?? ''));
        $valores = ['name' => $nome, 'email' => $email, 'phone' => $telefone, 'message' => $mensagem];
        $erros = [];

        if (trim((string) ($dados[self::HONEYPOT_FIELD] ?? '')) !== '') {
            return ['errors' => [], 'values' => $valores, 'spam' => true, 'throttled' => false];
        }

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > self::MAX_NAME) {
            $erros[] = 'Nome: escreva de 2 a 120 caracteres.';
        }

        if ($email === '' || mb_strlen($email) > self::MAX_EMAIL || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erros[] = 'E-mail: informe um endereço válido.';
        }

        $telefoneDigitos = $this->digits($telefone);

        if (strlen($telefoneDigitos) < 10 || strlen($telefoneDigitos) > 13) {
            $erros[] = 'Telefone: informe DDD e número, só dígitos (ex.: 11999999999).';
        }

        if (mb_strlen($mensagem) < 10 || mb_strlen($mensagem) > self::MAX_MESSAGE) {
            $erros[] = 'Mensagem: escreva de 10 a 4000 caracteres.';
        }

        if ($erros !== []) {
            return ['errors' => $erros, 'values' => $valores, 'spam' => false, 'throttled' => false];
        }

        if ($this->tooManyAttempts($ip)) {
            return [
                'errors' => ['Muitas mensagens em pouco tempo. Aguarde alguns minutos e tente de novo.'],
                'values' => $valores,
                'spam' => false,
                'throttled' => true,
            ];
        }

        $this->pdo->prepare(
            'INSERT INTO contact_messages (name, email, phone, message) VALUES (:nome, :email, :telefone, :mensagem)'
        )->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':telefone' => $telefoneDigitos,
            ':mensagem' => $mensagem,
        ]);

        $this->recordAttempt($ip);

        return [
            'errors' => [],
            'values' => ['name' => '', 'email' => '', 'phone' => '', 'message' => ''],
            'spam' => false,
            'throttled' => false,
        ];
    }

    /**
     * Mensagens de uma aba: ativas, não lidas ou na lixeira.
     *
     * @return list<array{id: int, name: string, email: string, phone: string, message: string, is_read: bool, created_at: string, deleted_at: string|null}>
     */
    public function messages(string $filtro = self::FILTER_ALL): array
    {
        $condicao = match ($filtro) {
            self::FILTER_UNREAD => 'deleted_at IS NULL AND is_read = 0',
            self::FILTER_TRASH => 'deleted_at IS NOT NULL',
            default => 'deleted_at IS NULL',
        };

        $consulta = $this->pdo->prepare(
            'SELECT id, name, email, phone, message, is_read, created_at, deleted_at
             FROM contact_messages
             WHERE ' . $condicao . '
             ORDER BY created_at DESC, id DESC
             LIMIT ' . self::LIST_LIMIT
        );
        $consulta->execute();

        return array_map(
            static fn (array $linha): array => [
                'id' => (int) $linha['id'],
                'name' => (string) $linha['name'],
                'email' => (string) $linha['email'],
                'phone' => (string) $linha['phone'],
                'message' => (string) $linha['message'],
                'is_read' => (int) $linha['is_read'] === 1,
                'created_at' => (string) $linha['created_at'],
                'deleted_at' => $linha['deleted_at'] === null ? null : (string) $linha['deleted_at'],
            ],
            $consulta->fetchAll()
        );
    }

    /**
     * Quantidades para as abas do painel.
     *
     * @return array{todas: int, nao_lidas: int, lixeira: int}
     */
    public function counts(): array
    {
        $linha = $this->pdo->query(
            'SELECT
                COALESCE(SUM(deleted_at IS NULL), 0) AS ativas,
                COALESCE(SUM(deleted_at IS NULL AND is_read = 0), 0) AS nao_lidas,
                COALESCE(SUM(deleted_at IS NOT NULL), 0) AS lixeira
             FROM contact_messages'
        )->fetch();

        return [
            'todas' => (int) ($linha['ativas'] ?? 0),
            'nao_lidas' => (int) ($linha['nao_lidas'] ?? 0),
            'lixeira' => (int) ($linha['lixeira'] ?? 0),
        ];
    }

    /**
     * Marca como lida ou não lida. Devolve o erro, ou null quando deu certo.
     */
    public function markRead(int $id, bool $lida): ?string
    {
        if (!$this->activeExists($id)) {
            return 'Mensagem não encontrada.';
        }

        $this->pdo->prepare('UPDATE contact_messages SET is_read = :lida WHERE id = :id')
            ->execute([':lida' => $lida ? 1 : 0, ':id' => $id]);

        return null;
    }

    /**
     * Move para a lixeira (soft delete).
     */
    public function trash(int $id): ?string
    {
        $consulta = $this->pdo->prepare(
            'UPDATE contact_messages SET deleted_at = CURRENT_TIMESTAMP WHERE id = :id AND deleted_at IS NULL'
        );
        $consulta->execute([':id' => $id]);

        return $consulta->rowCount() === 1 ? null : 'Mensagem não encontrada na caixa de entrada.';
    }

    /**
     * Tira da lixeira.
     */
    public function restore(int $id): ?string
    {
        $consulta = $this->pdo->prepare(
            'UPDATE contact_messages SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL'
        );
        $consulta->execute([':id' => $id]);

        return $consulta->rowCount() === 1 ? null : 'Mensagem não encontrada na lixeira.';
    }

    /**
     * Exclusão definitiva, só para o que já está na lixeira.
     */
    public function destroy(int $id): ?string
    {
        $consulta = $this->pdo->prepare(
            'DELETE FROM contact_messages WHERE id = :id AND deleted_at IS NOT NULL'
        );
        $consulta->execute([':id' => $id]);

        return $consulta->rowCount() === 1 ? null : 'Mensagem não encontrada na lixeira.';
    }

    /**
     * Links de resposta com a mensagem já citada, para o painel.
     *
     * O texto é cortado para a URL não virar um romance: quem responde já tem a
     * mensagem inteira na tela. O WhatsApp só existe quando há telefone.
     *
     * @return array{mail: string, whatsapp: string|null}
     */
    public static function replyLinks(string $nome, string $email, string $telefone, string $mensagem): array
    {
        $primeiro = explode(' ', trim($nome))[0];
        $saudacao = $primeiro === '' ? 'Olá!' : 'Olá, ' . $primeiro . '!';
        $resumo = mb_strlen($mensagem) > 600 ? mb_substr($mensagem, 0, 600) . '…' : $mensagem;
        $citada = str_replace(["\r\n", "\r"], "\n", $resumo);

        $corpoEmail = $saudacao . "\n\n"
            . "Sobre a sua mensagem:\n"
            . '"' . $citada . '"' . "\n\n"
            . "Obsyrium Store\n";

        $textoWhatsapp = $saudacao . " Sobre a sua mensagem: \"" . $citada . '"';
        $digitos = preg_replace('/\D+/', '', $telefone) ?? '';

        return [
            'mail' => 'mailto:' . $email . '?' . http_build_query(
                ['subject' => 'Resposta — Obsyrium Store', 'body' => $corpoEmail],
                '',
                '&',
                PHP_QUERY_RFC3986
            ),
            'whatsapp' => $digitos === '' ? null : 'https://wa.me/' . $digitos . '?' . http_build_query(
                ['text' => $textoWhatsapp],
                '',
                '&',
                PHP_QUERY_RFC3986
            ),
        ];
    }

    /**
     * Limite por IP: conta os envios bem-sucedidos dentro da janela.
     */
    private function tooManyAttempts(string $ip): bool
    {
        $consulta = $this->pdo->prepare(
            'SELECT COUNT(*) FROM contact_attempts
             WHERE ip_hash = :ip AND created_at > (NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)'
        );
        $consulta->execute([':ip' => $this->ipHash($ip)]);

        return (int) $consulta->fetchColumn() >= self::MAX_SENDS_PER_WINDOW;
    }

    /**
     * Registra o envio e, de passagem, limpa o que já saiu da janela.
     */
    private function recordAttempt(string $ip): void
    {
        $this->pdo->exec('DELETE FROM contact_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');

        $this->pdo->prepare('INSERT INTO contact_attempts (ip_hash) VALUES (:ip)')
            ->execute([':ip' => $this->ipHash($ip)]);
    }

    private function ipHash(string $ip): string
    {
        return hash('sha256', $ip === '' ? 'desconhecido' : $ip);
    }

    /**
     * Formata um telefone brasileiro; números de fora ficam com o +.
     */
    public static function phoneLabel(string $digitos): string
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

    private function activeExists(int $id): bool
    {
        $consulta = $this->pdo->prepare('SELECT id FROM contact_messages WHERE id = :id AND deleted_at IS NULL');
        $consulta->execute([':id' => $id]);

        return $consulta->fetchColumn() !== false;
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
}
