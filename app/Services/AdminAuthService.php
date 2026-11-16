<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminRepository;
use App\Support\Session;

/**
 * Login da administração.
 *
 * A sessão do administrador é separada da sessão do cliente (`admin_id` vs
 * `user_id`): entrar no painel não transforma o navegador num cliente logado e
 * sair do painel não derruba a conta da loja. O mesmo bloqueio por tentativas
 * do login do cliente é reaproveitado.
 */
final class AdminAuthService
{
    public function __construct(
        private readonly AdminRepository $admins,
        private readonly LoginThrottle $throttle
    ) {
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

        $admin = $this->admins->findByEmail($email);

        if ($admin === null || !password_verify($senha, (string) $admin['password'])) {
            $this->throttle->recordFailure($email, $ip);

            return 'E-mail ou senha inválidos.';
        }

        if (!$admin['is_active']) {
            return 'Esta conta de administração está desativada.';
        }

        if (password_needs_rehash((string) $admin['password'], PASSWORD_ARGON2ID)) {
            $this->admins->updatePassword((int) $admin['id'], password_hash($senha, PASSWORD_ARGON2ID));
        }

        $this->throttle->clear($email);
        $this->admins->touchLastLogin((int) $admin['id']);
        Session::loginAdmin((int) $admin['id']);

        return null;
    }

    public function logout(): void
    {
        Session::adminLogout();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentAdmin(): ?array
    {
        $id = Session::adminId();

        if ($id === null) {
            return null;
        }

        $admin = $this->admins->findById($id);

        if ($admin === null || !$admin['is_active']) {
            Session::adminLogout();

            return null;
        }

        return $admin;
    }
}
