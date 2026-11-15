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

As versões efetivamente instaladas no ambiente de desenvolvimento atual estão registradas na seção [2.1](#21-versões-instaladas-no-ambiente-de-desenvolvimento). As versões definitivas de produção devem ser registradas neste documento quando esse ambiente estiver definido.

### 2.1 Versões instaladas no ambiente de desenvolvimento

Ambiente verificado em 15/11/2026 na VM de desenvolvimento, com o projeto em `/var/www/html/obsyrium-store`.

| Componente | Versão instalada |
|---|---|
| Sistema operacional | Debian GNU/Linux 12 (bookworm), kernel 6.1.0-54-amd64 |
| PHP (CLI e `libapache2-mod-php8.2`) | 8.2.35 |
| Apache HTTP Server | 2.4.71-1~deb12u1 |
| MariaDB Server / Client | 10.11.19-MariaDB-0+deb12u1 |
| Composer | 2.5.5 |

Extensões PHP disponíveis e usadas pela aplicação: `pdo_mysql`, `mysqli`, `mbstring`, `intl`, `fileinfo`, `json` e `openssl`. Driver PDO ativo: `mysql`.

O `intl` e as bibliotecas `php-symfony-*` foram instalados como dependências do pacote Debian `composer`, não como requisito direto da aplicação.

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
│   ├── Database/
│   ├── Middleware/
│   ├── Models/
│   ├── Routing/
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
│       ├── admin/
│       └── errors/
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

### 5.1 Servidor web neste ambiente

O document root aponta para `public/`, nunca para a raiz do repositório, conforme a seção 13. Configuração atual da VM:

- Virtual host: `/etc/apache2/sites-available/obsyrium-store.conf`, habilitado como `/etc/apache2/sites-enabled/obsyrium-store.conf`.
- `DocumentRoot /var/www/html/obsyrium-store/public`.
- `ServerName obsyrium-store.local` e `ServerAlias obsyrium.local` — nomes provisórios, sem DNS configurado; o acesso atual é pelo IP `10.8.0.9`.
- `<Directory>` do document root com `Options +FollowSymLinks -Indexes` e `AllowOverride All`, permitindo `.htaccess` para o roteamento por front controller.
- Logs próprios em `/var/log/apache2/obsyrium-store-access.log` e `/var/log/apache2/obsyrium-store-error.log`.
- O site padrão `000-default` (`DocumentRoot /var/www/html`) está desabilitado, para que a porta 80 sirva somente o projeto.
- `mod_rewrite` habilitado. PHP executa como módulo do Apache (`libapache2-mod-php8.2`); PHP-FPM não é utilizado.
- `public/.htaccess` reescreve para `index.php` as requisições que não correspondem a arquivos ou diretórios existentes, entregando tudo ao front controller.
- `storage/logs/` é o diretório de escrita da aplicação (dono `www-data`, permissão `750`); o log fica em `storage/logs/app.log`, criado em tempo de execução e não versionado.
- Composer instalado via pacote Debian. Ao ser executado como `root`, ele avisa sobre uso de superusuário e desabilita plugins; para permitir plugins, definir `COMPOSER_ALLOW_SUPERUSER=1`.
- A raiz é atendida pelo front controller `public/index.php` e responde **200**. Caminhos sem rota registrada respondem **404** pela view `resources/views/errors/404.php`.

Comandos executados na preparação:

```bash
apt-get install -y php8.2-mysql php8.2-mbstring composer mariadb-server
a2enmod rewrite
a2ensite obsyrium-store
a2dissite 000-default
apache2ctl configtest && systemctl reload apache2
systemctl enable --now mariadb
```

Rollback do virtual host: `a2dissite obsyrium-store && a2ensite 000-default && a2dismod rewrite && systemctl reload apache2`.

### 5.2 Banco de dados neste ambiente

- MariaDB 10.11.19 ativo e habilitado no boot (`systemctl enable --now mariadb`).
- Escuta somente em `127.0.0.1:3306` (`bind-address = 127.0.0.1`), sem exposição na rede.
- `datadir` em `/var/lib/mysql/`.
- Autenticação do usuário `root` do SGBD via soquete Unix (`unix_socket`), sem senha em texto plano.
- Banco `obsyrium_store` criado com `utf8mb4` / `utf8mb4_unicode_ci` e usuário dedicado `obsyrium`@`localhost`, com privilégios restritos ao próprio banco (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `ALTER`, `INDEX`, `DROP`, `REFERENCES`, `CREATE TEMPORARY TABLES`, `LOCK TABLES`). A senha existe apenas no `.env` da VM (permissão `640`, dono `root:www-data`) e nunca deve ser versionada ou exibida.
- A conexão usa PDO com `utf8mb4`, `ERRMODE_EXCEPTION`, `FETCH_ASSOC` e `EMULATE_PREPARES` desligado, montada por `config/Database.php` e entregue por `app/Database/Connection.php`.

### 5.3 Proxy

Esta VM **não possui proxy** configurado: nenhum módulo `proxy_*` do Apache habilitado, nenhuma diretiva `ProxyPass`/`ProxyPassMatch` nos sites habilitados, nenhuma variável de ambiente `*_proxy` e nenhuma configuração de proxy em `apt`, `git`, `curl` ou `wget`. O virtual host da aplicação não deve receber configuração de proxy; esse ajuste será feito manualmente em outro momento.

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

### 6.1 Leitura do `.env`

O `.env` é lido por `Config\Env::load()`, chamado no bootstrap de `public/index.php`: leitura única por requisição, linhas em branco e comentários com `#` ignorados, valores entre aspas e comentário no fim da linha suportados. O arquivo não é obrigatório — chaves ausentes caem nos valores padrão de `config/`. Valores do `.env` nunca são enviados ao navegador nem gravados em log.

Chaves ausentes no `.env` — ou presentes com valor vazio — são buscadas nas variáveis de ambiente reais do processo, nesta ordem: `getenv()`, `$_ENV` e `$_SERVER`. Isso permite definir credenciais e parâmetros direto no servidor, sem `.env`, como previsto na seção 6. **O `.env` tem precedência** sobre a variável de ambiente de mesmo nome. Os mesmos nomes valem para `Env::int()` e `Env::bool()`.

## 7. Arquitetura e responsabilidades

- **Controllers:** recebem requisições, validam a entrada básica e coordenam a resposta.
- **Models / Repositories:** concentram o acesso e a persistência de dados.
- **Services:** implementam regras de negócio e integrações externas.
- **Middleware:** executa verificações transversais, como autenticação e autorização.
- **Views:** renderizam a interface; não devem conter regras de negócio ou consultas SQL.
- **Routes:** definem os caminhos HTTP e seus respectivos handlers.
- **Routing:** `routes/web.php` registra as rotas e `App\Routing\Router` casa método e caminho, instancia o controller e executa a ação; o despacho parte de `public/index.php`.
- **Config:** centraliza configurações não secretas e leitura das variáveis de ambiente (`Config\Env`, `Config\Database`).
- **Env:** `Config\Env` expõe os valores do `.env` de forma tipada (`get`, `int`, `bool`); `Config\Database` monta DSN e opções de conexão.
- **Database:** `App\Database\Connection` fornece a conexão PDO (`mysql`, `utf8mb4`) sob demanda, reaproveitada na mesma requisição.

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
- Nenhum erro pode expor stack trace, caminho interno, SQL ou credencial ao usuário: `display_errors` fica desligado e exceções e erros fatais são registrados em `storage/logs/app.log`, com resposta apenas das páginas genéricas de `resources/views/errors/`.
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
- O diretório `database/migrations/` está criado e versionado (`.gitkeep`). Nenhuma migration foi escrita ainda e não existe ferramenta de execução implementada; os comandos devem ser documentados aqui quando existirem.

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
- Seção [2.1](#21-versões-instaladas-no-ambiente-de-desenvolvimento): versões instaladas no ambiente de desenvolvimento.
- Seções [5.1](#51-servidor-web-neste-ambiente), [5.2](#52-banco-de-dados-neste-ambiente) e [5.3](#53-proxy): configuração atual da VM (servidor web, banco e ausência de proxy).

## 16. Identidade visual e componentes base

Estilos em `public/assets/css/`, carregados nesta ordem: `tokens.css` (variáveis), `base.css` (reset, tipografia e utilitários) e `components.css` (componentes). Nenhum framework CSS é utilizado.

### 16.1 Emblema e paleta

A identidade parte do emblema da marca: disco escuro com textura de mármore e uma estrela facetada em dourado bronze. A paleta foi extraída do próprio arquivo do logo.

- Acento único: `--color-accent: #c9a063`, com `--color-accent-hover` (#e3bc84), `--color-accent-active` (#a8844c) e `--color-accent-strong` (#8a6a3a) tirados dos realces e sombras do emblema. Gradiente opcional em `--gradient-accent`.
- O acento é reservado para ação, preço em destaque, foco e estado selecionado — nunca para grandes áreas de fundo.
- Pretos quentes: `--color-bg` (#080707), `--color-bg-alt` (#0e0c0b), `--color-surface` (#141110), `--color-surface-raised` (#1d1917); bordas `--color-border` e `--color-border-strong`.
- Textos: `--color-text` (#f2ece3), `--color-text-muted` (#a89b8b) e `--color-text-subtle` (#7a6f63).
- Semânticas: `--color-danger`, `--color-success`, `--color-warning`.

Arquivos do logo em `public/assets/images/`: `logo-512.png` e `logo-128.png`, ambos derivados do arquivo original de 1254×1254, que permanece fora do repositório. Cabeçalho usa a versão de 128px; o emblema grande usa a de 512px.

### 16.2 Tipografia

- `--font-display`: display lapidada em caixa alta com tracking largo — `Cinzel`, `Trajan Pro`, `Cormorant Garamond`, `Playfair Display`, `Bodoni MT`, `Georgia`. Usada em títulos e no seletor de tamanho.
- `--font-display-condensed`: `Oswald`, `Archivo Narrow`, `Roboto Condensed`, `Arial Narrow`, `Impact` — reservada para manchetes de impacto (drop, campanha).
- `--font-body`: sans neutra — `Inter`, `Segoe UI`, `system-ui`. Usada em texto corrido, preço e nome do produto.
- Escala de `--font-size-xs` a `--font-size-3xl`, com `clamp()` nos tamanhos maiores.
- **Nenhuma fonte web é carregada ainda**: vale a primeira fonte instalada na pilha do sistema. Para fixar a display lapidada em qualquer dispositivo, o arquivo da fonte precisa ser hospedado e declarado com `@font-face`.

### 16.3 Espaçamento, raios e movimento

- Escala de espaçamento com base 4px: `--space-1` (0.25rem) a `--space-10` (5rem).
- Raios: `--radius-xs`, `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-pill`.
- Movimento: `--duration-fast`, `--duration-base` e `--ease-standard`; `prefers-reduced-motion` desativa transições.
- Acessibilidade: `--target-min: 44px` para alvos de toque e anel de foco em `--focus-ring-width`, `--focus-ring-offset` e `--focus-ring-color`.

### 16.4 Componentes

- **Seletor de tamanho** (`.size-picker`): `fieldset` + `legend` + `input[type="radio"]` visualmente oculto com `label.size-option` associado. Selecionado usa fundo de acento, esgotado usa `input:disabled` com texto riscado, e o foco aparece em `:focus-visible`. Não usar `<select>` para tamanho.
- **Card de produto** (`.product-card`, dentro de `.product-grid`): mídia com proporção 3:4, selos em `.product-card__badges`, título, metadados, preço com valor antigo/atual e notas de Pix/parcelas. Variante `.product-card--sold-out`.
- **Faixa de confiança** (`.trust-bar`): grade responsiva de itens com ícone SVG inline herdando `currentColor`, rótulo e texto de apoio.

## 17. Layout da home

A estrutura da página inicial segue a da referência Crown Training — a referência é de **layout** (seções, ordem e hierarquia), não de design. Cores, tipografia e ornamentos continuam os da identidade descrita na seção 16.

### 17.1 Ordem das seções

1. **Barra de benefícios** (`.topbar`): três itens com ícone — parcelamento, atendimento e site seguro.
2. **Cabeçalho** (`.site-header`): barra em largura total (sem `.container`), com margem lateral de `--gutter`, em três colunas — emblema e nome "Obsyrium" à esquerda, menu (Lançamentos, Camisetas, Sobre, Contato) centralizado e busca, conta e carrinho à direita.
3. **Hero** (`.hero`): dois slides em faixa horizontal com `scroll-snap` e marcadores (`.hero__dot`) que ancoram em cada slide. Sem JavaScript.
4. **Vitrine "Lançamentos"** (`.section` + `.product-grid`): oito cards com selo, cor, preço, valor no Pix e parcelamento, seguidos da `.pagination`.
5. **Bloco de conteúdo** (`.section--alt` + `.content-block`): H2 e dois parágrafos institucionais.
6. **Newsletter** (`.newsletter`): título, texto de apoio, campo de e-mail e botão desabilitado até o envio existir.
7. **Rodapé** (`.site-footer`): colunas de políticas, atendimento, redes sociais e formas de pagamento, mais a linha legal.

### 17.2 Arquivos

- `resources/views/layouts/site.php`: documento HTML e `<head>` com os quatro CSS na ordem `tokens` → `base` → `components` → `layout`; inclui o cabeçalho, o `<main id="conteudo">` com a view de conteúdo e o rodapé.
- `resources/views/partials/header.php`: barra de benefícios e cabeçalho. Links de categoria e botões de ação ainda apontam para `#`.
- `resources/views/partials/footer.php`: colunas do rodapé e linha legal.
- `resources/views/home/index.php`: hero, vitrine, bloco de conteúdo e newsletter. Os oito produtos são de demonstração, definidos em um array na própria view.
- `public/assets/css/layout.css`: topbar, cabeçalho, navegação, hero, seções, paginação, newsletter, rodapé e `.skip-link`.

Cabeçalho e rodapé são reaproveitados por qualquer página renderizada pelo layout `site`.

### 17.3 Regras de manutenção

- O layout não carrega JavaScript: hero e estados são resolvidos em CSS. Comportamento interativo (dots sincronizados, carrinho, busca) entra junto com o JavaScript do projeto.
- Toda classe nova parte de `tokens.css`; cor e espaçamento fixos não entram nos componentes.
- `HomeController::index()` define `$title`, `$content` e inclui `layouts/site.php`. Novas páginas repetem esse contrato em vez de duplicar o HTML do documento.
- Um único `<h1>` por página (o do primeiro slide do hero); as seções usam `<h2>`.
