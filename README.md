# Obsyrium Store — Documentação Técnica

## 1. Visão geral

Aplicação web desenvolvida em PHP, com arquitetura MVC, banco de dados relacional e interface responsiva em HTML, CSS e JavaScript.

Este repositório contém o código-fonte da aplicação, scripts de banco de dados, testes e documentação técnica.

## 2. Stack tecnológica

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2+ |
| Arquitetura | MVC |
| Banco de dados | MySQL 8+ ou MariaDB compatível |
| Acesso a dados | PDO |
| Frontend | HTML5, CSS3 e JavaScript |
| Servidor web | Nginx ou Apache |
| Controle de versão | Git |
| Dependências PHP | Composer, quando necessário |

As versões definitivas devem ser registradas neste documento quando o ambiente de produção estiver definido.

## 3. Requisitos

- PHP 8.2 ou superior.
- Extensões PHP: `pdo`, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` e `json`.
- MySQL 8+ ou versão compatível do MariaDB.
- Nginx ou Apache.
- Composer.
- Git.

Extensões adicionais devem ser documentadas quando forem introduzidas pelo projeto.

## 4. Estrutura de diretórios

```text
.
├── app/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Helpers/
├── config/
├── database/
│   ├── migrations/
│   └── seeds/
├── public/
│   ├── assets/
│   │   ├── css/
│   │   ├── images/
│   │   └── js/
│   └── index.php
├── resources/
│   └── views/
│       ├── layouts/
│       ├── home/
│       ├── products/
│       ├── cart/
│       ├── checkout/
│       └── admin/
├── routes/
├── storage/
│   ├── logs/
│   └── uploads/
├── tests/
├── .env.example
├── .gitignore
├── composer.json
├── AGENTS.md
└── README.md
```

A estrutura pode evoluir conforme a implementação. Alterações estruturais devem manter a separação entre regras de negócio, acesso a dados, apresentação e configuração.

## 5. Configuração do ambiente

1. Clone o repositório.
2. Instale as dependências PHP, quando houver.
3. Copie `.env.example` para `.env`.
4. Preencha as variáveis de ambiente.
5. Crie o banco de dados e um usuário com permissões restritas.
6. Execute as migrations disponíveis.
7. Configure o servidor web para utilizar `public/` como document root.
8. Garanta permissões de escrita somente nos diretórios que realmente precisam, como `storage/` e diretórios de upload.

Exemplo:

```bash
git clone <URL_DO_REPOSITORIO>
cd <DIRETORIO_DO_PROJETO>

cp .env.example .env
composer install
```

Os comandos de migration e inicialização devem ser adicionados aqui quando a ferramenta correspondente estiver implementada.

## 6. Variáveis de ambiente

Não armazene credenciais no código-fonte. Utilize `.env` localmente e variáveis de ambiente no servidor.

Exemplo de `.env.example`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Integrações externas — preencher somente quando implementadas
PAYMENT_PROVIDER=
PAYMENT_ACCESS_TOKEN=
PAYMENT_WEBHOOK_SECRET=

MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
```

`.env` não deve ser versionado. O arquivo `.env.example` deve conter apenas nomes de variáveis e valores fictícios ou vazios.

## 7. Arquitetura e responsabilidades

- **Controllers:** recebem requisições, validam a entrada básica e coordenam a resposta.
- **Models / Repositories:** concentram o acesso e a persistência de dados.
- **Services:** implementam regras de negócio e integrações externas.
- **Middleware:** executa verificações transversais, como autenticação e autorização.
- **Views:** renderizam a interface; não devem conter regras de negócio ou consultas SQL.
- **Routes:** definem os caminhos HTTP e seus respectivos handlers.
- **Config:** centraliza configurações não secretas e leitura das variáveis de ambiente.

Controllers devem permanecer pequenos. Regras de negócio não devem ser duplicadas entre controllers.

## 8. Diretrizes de desenvolvimento

- Utilizar `declare(strict_types=1);` nos arquivos PHP quando aplicável.
- Seguir PSR-12 para estilo de código PHP.
- Utilizar autoload PSR-4 via Composer.
- Utilizar PDO com prepared statements.
- Validar dados no servidor, mesmo quando houver validação no frontend.
- Escapar conteúdo ao renderizar HTML.
- Utilizar transações em operações que alterem múltiplos registros dependentes.
- Armazenar datas em formato consistente no banco e definir explicitamente o fuso de apresentação.
- Não misturar SQL, regras de negócio e HTML na mesma camada.
- Preferir alterações pequenas e testáveis.

## 9. Segurança

- Senhas devem ser armazenadas com `password_hash()` e verificadas com `password_verify()`.
- Proteger formulários e operações de alteração com tokens CSRF.
- Aplicar autorização no servidor em todas as rotas restritas.
- Não confiar em valores enviados pelo navegador, incluindo preço, desconto, estoque ou status de pagamento.
- Validar tipo, tamanho e conteúdo de arquivos enviados; não utilizar o nome original como caminho.
- Impedir execução de arquivos enviados.
- Configurar cookies de sessão com `HttpOnly`, `Secure` em HTTPS e `SameSite` apropriado.
- Utilizar HTTPS em produção.
- Não registrar senhas, tokens ou dados sensíveis nos logs.
- Validar a autenticidade de notificações recebidas de serviços externos.
- Aplicar rate limiting onde houver risco de abuso, como login e recuperação de senha.

## 10. Pagamentos e operações assíncronas

A integração de pagamento deve ser encapsulada em um serviço, sem acoplar regras do provedor aos controllers.

Regras mínimas:

- O estado do pedido deve ser controlado no backend.
- O retorno do navegador não comprova pagamento.
- A confirmação deve ocorrer por notificação validada do provedor ou consulta autenticada à API.
- Webhooks devem ser idempotentes.
- Registrar identificadores externos e eventos relevantes sem armazenar credenciais.
- Não duplicar pedidos ou baixas de estoque ao receber notificações repetidas.

## 11. Banco de dados

- Alterações de schema devem ser versionadas em migrations.
- Seeds devem conter apenas dados de desenvolvimento ou demonstração.
- Utilizar chaves estrangeiras e índices conforme as consultas e relações.
- Definir restrições para campos obrigatórios e valores únicos.
- Evitar exclusão física de registros que precisem permanecer para histórico ou auditoria.
- Não executar alterações manuais em produção sem registrar a migration correspondente.

## 12. Testes e validação

Antes de concluir uma alteração:

1. Executar verificações de sintaxe PHP nos arquivos modificados.
2. Executar os testes automatizados disponíveis.
3. Validar os fluxos afetados manualmente.
4. Verificar logs e erros de console do navegador.
5. Confirmar que nenhuma credencial ou arquivo local foi incluído no commit.

Os testes devem cobrir principalmente autenticação, autorização, cálculo de valores, estoque, criação de pedidos e processamento de notificações externas.

## 13. Deploy

- O document root do servidor web deve apontar exclusivamente para `public/`.
- Arquivos `.env`, código-fonte, logs e diretórios internos não devem ser acessíveis diretamente pela web.
- Utilizar usuário de sistema dedicado para a aplicação.
- Conceder permissões mínimas necessárias.
- Manter backups do banco de dados e dos arquivos persistentes.
- Desabilitar `APP_DEBUG` em produção.
- Registrar procedimento de rollback para deploys e migrations.
- Não executar migrations destrutivas sem backup e plano de reversão.

## 14. Integrações previstas

As integrações devem ser implementadas somente quando seus requisitos e credenciais estiverem definidos.

- Gateway de pagamento.
- Serviço de cálculo e acompanhamento de frete.
- Serviço de envio de e-mails.

Cada integração deve possuir configuração própria, tratamento de falhas, logs sem dados sensíveis e testes isolados.

## 15. Documentação complementar

- [`AGENTS.md`](./AGENTS.md): instruções para agentes de desenvolvimento.
- `database/migrations/`: histórico de alterações do banco.
- `tests/`: testes automatizados.
