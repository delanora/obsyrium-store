<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AddressRepository;
use App\Repositories\UserRepository;
use App\Support\Crypto;

/**
 * Regras da área do cliente: perfil (nome e telefone) e endereços.
 *
 * A validação mora aqui, e não no controller, porque o checkout também vai
 * precisar das mesmas regras quando o pedido existir. Os erros saem por campo,
 * no formato que as views já usam (`$errors['campo']`).
 */
final class AccountService
{
    /**
     * @var list<string>
     */
    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly AddressRepository $addresses
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(int $id): ?array
    {
        return $this->users->findById($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function addressesFor(int $userId): array
    {
        return $this->addresses->allForUser($userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function address(int $id, int $userId): ?array
    {
        return $this->addresses->findForUser($id, $userId);
    }

    /**
     * @return list<string>
     */
    public function ufs(): array
    {
        return self::UFS;
    }

    /**
     * @param array<string, mixed> $entrada
     *
     * @return array<string, string> erros por campo; vazio quando gravou
     */
    public function saveProfile(int $userId, array $entrada): array
    {
        $nome = trim((string) ($entrada['name'] ?? ''));
        $telefone = Crypto::normalizePhone((string) ($entrada['phone'] ?? ''));
        $erros = [];

        if ($this->tamanho($nome) < 3) {
            $erros['name'] = 'Informe seu nome completo.';
        } elseif ($this->tamanho($nome) > 120) {
            $erros['name'] = 'Use no máximo 120 caracteres no nome.';
        }

        if ($telefone !== '' && strlen($telefone) < 10) {
            $erros['phone'] = 'Informe o telefone com DDD.';
        } elseif (strlen($telefone) > 11) {
            $erros['phone'] = 'O telefone deve ter no máximo 11 dígitos.';
        }

        if ($erros !== []) {
            return $erros;
        }

        $this->users->updateProfile($userId, $nome, $telefone !== '' ? $telefone : null);

        return [];
    }

    /**
     * Cria ou atualiza um endereço do próprio cliente. O `address_id` vem do
     * formulário, mas o dono é conferido no repositório antes de qualquer
     * escrita.
     *
     * @param array<string, mixed> $entrada
     *
     * @return array<string, string> erros por campo; vazio quando gravou
     */
    public function saveAddress(int $userId, array $entrada): array
    {
        $id = (int) ($entrada['address_id'] ?? 0);

        $dados = [
            'label' => trim((string) ($entrada['label'] ?? '')),
            'recipient' => trim((string) ($entrada['recipient'] ?? '')),
            'postal_code' => preg_replace('/\D+/', '', (string) ($entrada['postal_code'] ?? '')) ?? '',
            'street' => trim((string) ($entrada['street'] ?? '')),
            'number' => trim((string) ($entrada['number'] ?? '')),
            'complement' => trim((string) ($entrada['complement'] ?? '')),
            'district' => trim((string) ($entrada['district'] ?? '')),
            'city' => trim((string) ($entrada['city'] ?? '')),
            'state' => strtoupper(trim((string) ($entrada['state'] ?? ''))),
            'is_default' => !empty($entrada['is_default']),
        ];

        if ($dados['label'] === '') {
            $dados['label'] = 'Principal';
        }

        $erros = $this->validarEndereco($dados);

        if ($erros !== []) {
            return $erros;
        }

        if ($id > 0) {
            if ($this->addresses->findForUser($id, $userId) === null) {
                return ['address' => 'Endereço não encontrado.'];
            }

            $this->addresses->update($id, $userId, $dados);

            return [];
        }

        $this->addresses->create($userId, $dados);

        return [];
    }

    public function removeAddress(int $id, int $userId): bool
    {
        return $this->addresses->remove($id, $userId);
    }

    public function makeDefault(int $id, int $userId): bool
    {
        return $this->addresses->makeDefault($id, $userId);
    }

    public function phoneLabel(string $phone): string
    {
        $digitos = Crypto::normalizePhone($phone);
        $tamanho = strlen($digitos);

        if ($tamanho === 11) {
            return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7));
        }

        if ($tamanho === 10) {
            return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6));
        }

        return $phone;
    }


    /**
     * O CPF nunca volta inteiro para a tela: só os dois últimos dígitos.
     */
    public function cpfLabel(string $cpf): string
    {
        $digitos = Crypto::normalizeCpf($cpf);

        return strlen($digitos) === 11 ? '***.***.***-' . substr($digitos, 9) : '***';
    }

    /**
     * @param array<string, mixed> $dados
     *
     * @return array<string, string>
     */
    private function validarEndereco(array $dados): array
    {
        $erros = [];

        if ($this->tamanho($dados['label']) > 40) {
            $erros['label'] = 'Use até 40 caracteres no nome do endereço.';
        }

        if ($dados['recipient'] === '') {
            $erros['recipient'] = 'Informe quem recebe a entrega.';
        } elseif ($this->tamanho($dados['recipient']) > 120) {
            $erros['recipient'] = 'Use até 120 caracteres no destinatário.';
        }

        if (strlen($dados['postal_code']) !== 8) {
            $erros['postal_code'] = 'Informe o CEP com 8 dígitos.';
        }

        if ($dados['street'] === '') {
            $erros['street'] = 'Informe a rua.';
        } elseif ($this->tamanho($dados['street']) > 160) {
            $erros['street'] = 'Use até 160 caracteres na rua.';
        }

        if ($dados['number'] === '') {
            $erros['number'] = 'Informe o número (use “s/n” quando não houver).';
        } elseif ($this->tamanho($dados['number']) > 20) {
            $erros['number'] = 'Use até 20 caracteres no número.';
        }

        if ($this->tamanho($dados['complement']) > 120) {
            $erros['complement'] = 'Use até 120 caracteres no complemento.';
        }

        if ($dados['district'] === '') {
            $erros['district'] = 'Informe o bairro.';
        } elseif ($this->tamanho($dados['district']) > 80) {
            $erros['district'] = 'Use até 80 caracteres no bairro.';
        }

        if ($dados['city'] === '') {
            $erros['city'] = 'Informe a cidade.';
        } elseif ($this->tamanho($dados['city']) > 80) {
            $erros['city'] = 'Use até 80 caracteres na cidade.';
        }

        if (!in_array($dados['state'], self::UFS, true)) {
            $erros['state'] = 'Escolha a UF.';
        }

        return $erros;
    }

    /**
     * Conta caracteres, e não bytes, para nome e endereço com acento.
     */
    private function tamanho(string $valor): int
    {
        return function_exists('mb_strlen') ? mb_strlen($valor, 'UTF-8') : strlen($valor);
    }
}
