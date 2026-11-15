<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use App\Support\Cpf;
use App\Support\Crypto;
use App\Support\Session;
use PDOException;

/**
 * Cadastro, login e sessão do cliente. Valida os dados, cifra CPF e telefone
 * por meio do repositório e nunca devolve mensagem que revele se um e-mail
 * existe.
 */
final class AuthService
{
    private const MIN_PASSWORD = 8;
    private const MAX_NAME = 120;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginThrottle $throttle
    ) {
    }

    /**
     * @param array<string, mixed> $entrada
     * @return array<string, string> erros por campo; vazio quando cadastrou
     */
    public function register(array $entrada): array
    {
        $nome = trim((string) ($entrada['name'] ?? ''));
        $cpf = Cpf::digits((string) ($entrada['cpf'] ?? ''));
        $telefone = Crypto::normalizePhone((string) ($entrada['phone'] ?? ''));
        $email = mb_strtolower(trim((string) ($entrada['email'] ?? '')));
        $senha = (string) ($entrada['password'] ?? '');
        $confirmacao = (string) ($entrada['password_confirmation'] ?? '');

        $erros = [];

        if (mb_strlen($nome) < 3) {
            $erros['name'] = 'Informe seu nome completo.';
        } elseif (mb_strlen($nome) > self::MAX_NAME) {
            $erros['name'] = 'O nome pode ter no máximo 120 caracteres.';
        }

        if (!Cpf::isValid($cpf)) {
            $erros['cpf'] = 'CPF inválido. Confira os 11 dígitos.';
        }

        if (strlen($telefone) < 10 || strlen($telefone) > 11) {
            $erros['phone'] = 'Informe o telefone com DDD.';
        }

        if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erros['email'] = 'Informe um e-mail válido.';
        }

        if (mb_strlen($senha) < self::MIN_PASSWORD) {
            $erros['password'] = 'A senha precisa ter ao menos 8 caracteres.';
        }

        if ($senha !== $confirmacao) {
            $erros['password_confirmation'] = 'As senhas não conferem.';
        }

        if (($entrada['terms'] ?? '') === '') {
            $erros['terms'] = 'É preciso aceitar os termos para criar a conta.';
        }

        if ($erros === [] && $this->users->emailExists($email)) {
            $erros['email'] = 'Este e-mail já está cadastrado.';
        }

        if ($erros === [] && $this->users->cpfExists($cpf)) {
            $erros['cpf'] = 'Este CPF já está cadastrado.';
        }

        if ($erros !== []) {
            return $erros;
        }

        try {
            $id = $this->users->create([
                'name' => $nome,
                'cpf' => $cpf,
                'phone' => $telefone,
                'email' => $email,
                'password' => password_hash($senha, PASSWORD_ARGON2ID),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['email' => 'Não foi possível concluir o cadastro. Revise os dados informados.'];
            }

            throw $e;
        }

        Session::login($id);

        return [];
    }

    /**
     * @param array<string, mixed> $entrada
     * @return string|null mensagem de erro, ou null quando entrou
     */
    public function login(array $entrada, string $ip): ?string
    {
        $email = mb_strtolower(trim((string) ($entrada['email'] ?? '')));
        $senha = (string) ($entrada['password'] ?? '');

        if ($email === '' || $senha === '') {
            return 'Informe e-mail e senha.';
        }

        $bloqueio = $this->throttle->blockedFor($email, $ip);

        if ($bloqueio !== null) {
            return LoginThrottle::describe($bloqueio);
        }

        $usuario = $this->users->findByEmail($email);

        if ($usuario === null || !password_verify($senha, $usuario['password'])) {
            $this->throttle->recordFailure($email, $ip);

            return 'E-mail ou senha inválidos.';
        }

        if (!$usuario['is_active'] || $usuario['deleted_at'] !== null) {
            return 'Conta indisponível. Fale com o atendimento.';
        }

        if (password_needs_rehash($usuario['password'], PASSWORD_ARGON2ID)) {
            $this->users->updatePasswordHash($usuario['id'], password_hash($senha, PASSWORD_ARGON2ID));
        }

        $this->throttle->clear($email);
        $this->users->touchLastLogin($usuario['id']);
        Session::login($usuario['id']);

        return null;
    }

    public function logout(): void
    {
        Session::logout();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentUser(): ?array
    {
        $id = Session::userId();

        if ($id === null) {
            return null;
        }

        $usuario = $this->users->findById($id);

        if ($usuario === null || !$usuario['is_active'] || $usuario['deleted_at'] !== null) {
            Session::logout();

            return null;
        }

        return $usuario;
    }
}
