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
│   ├── Repositories/
│   ├── Routing/
│   ├── Services/
│   ├── Support/
│   └── Helpers/
├── config/
├── database/
│   ├── migrations/
│   ├── migrate.php
│   └── seed.php
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
│       ├── product/
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

# Chave de cifra dos dados sensíveis: 32 bytes em base64.
APP_KEY=
APP_KEY_VERSION=1
APP_KEY_PREVIOUS=
APP_INDEX_KEY=

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

### 6.2 Chave de cifra (APP_KEY)

`App\Support\Crypto` exige `APP_KEY` **e** `APP_INDEX_KEY`, cada uma com 32 bytes em base64. O índice cego não é derivado da `APP_KEY`: sem `APP_INDEX_KEY`, cifrar continua funcionando, mas gerar ou consultar índices falha. Para gerar uma chave:

```bash
php -r 'echo "base64:", base64_encode(random_bytes(32)), PHP_EOL;'
```

Sempre que possível, faça backup da chave junto do backup do banco: sem ela os dados cifrados ficam irrecuperáveis.

### 6.3 Rotação de chave

O payload cifrado carrega a versão da chave (`v1:`, `v2:`...), então a `APP_KEY` pode ser trocada sem perder o que já está gravado.

| Variável | Para que serve |
|---|---|
| `APP_KEY` | Chave atual, 32 bytes em base64. |
| `APP_KEY_VERSION` | Versão da chave atual (padrão `1`). |
| `APP_KEY_PREVIOUS` | Chaves antigas, apenas para decifrar: `1:base64:...,2:base64:...`. |
| `APP_INDEX_KEY` | Chave do índice cego (CPF). Obrigatória; independente da rotação para não invalidar buscas. |

Para rotacionar:

1. Gere uma chave nova e coloque em `APP_KEY`.
2. Suba `APP_KEY_VERSION`.
3. Mova a chave antiga para `APP_KEY_PREVIOUS` com a versão dela (`1:base64:...`).
4. As leituras antigas continuam funcionando; as gravações novas saem com a versão nova.
5. Quando nenhum registro antigo restar, remova a entrada de `APP_KEY_PREVIOUS`.

Sem `APP_KEY` válida, qualquer gravação de dado sensível falha em vez de gravar em texto puro.

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
- **Repositories:** `App\Repositories\ProductRepository` concentra as consultas da vitrine (produto, categoria, estoque somado e imagem principal).
- **Support:** `App\Support\Price` converte centavos em reais e monta o texto de parcela e do valor no Pix.

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
- Dados pessoais sensíveis (CPF, telefone e endereço) ficam cifrados em repouso com AES-256-GCM em `App\Support\Crypto`. A chave vive em `APP_KEY`, nunca no repositório.
- O CPF é conferido (11 dígitos e dígitos verificadores) em `App\Support\Cpf` **antes** de ser cifrado: como o banco só guarda o texto cifrado, não existe validação de formato por `CHECK` na coluna.
- Formulários de escrita usam token CSRF por sessão (`App\Support\Csrf`), comparado com `hash_equals`; envio sem token recebe 403.
- A sessão usa cookie `HttpOnly` e `SameSite=Lax` (`Secure` sob HTTPS) e o identificador é regenerado no login com `session_regenerate_id(true)`.
- Campos cifrados que precisam de unicidade ou busca (o CPF) recebem um índice cego (HMAC-SHA256) no lugar do valor em texto puro.
- Hash de senha não é cifra: uma vez gravado, não existe caminho de volta para a senha original.
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
- O login tem bloqueio temporário: 5 falhas por conta ou 20 por IP em 15 minutos travam novas tentativas até a falha mais antiga da janela expirar. O recorte de tempo usa `NOW()` do banco, não o relógio do PHP — os dois podem estar em fusos diferentes. Um login bem-sucedido limpa as falhas da conta.

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
- O diretório `database/migrations/` guarda um arquivo por mudança de schema, nomeado `AAAA_MM_DD_NNNNNN_descricao.php`. Cada arquivo devolve um `callable(PDO)` que executa o DDL.
- `php database/migrate.php` aplica as pendentes e registra o nome na tabela `migrations`; `php database/migrate.php --status` mostra o estado de cada arquivo. Atalhos: `composer db:migrate` e `composer db:seed`.
- `php database/seed.php` popula dados de demonstração (2 categorias, 8 estampas, 5 tamanhos por peça, estoque e imagem). Ele limpa as tabelas da vitrine antes de inserir e recusa rodar com `APP_ENV=production`.
- Tabelas atuais: `categories`, `products`, `product_variants`, `stock`, `product_images`, `users`, `addresses`, `carts`, `cart_items`, `login_attempts` e `migrations`.
- `carts` guarda um carrinho aberto por cliente (chave única em `user_id`) e `cart_items` um item por variação, com a quantidade. O preço não é copiado para o carrinho: o total é sempre lido de `products.price_cents`, e é o pedido que guarda o preço praticado no fechamento.
- `login_attempts` guarda só as falhas de login: o e-mail entra como índice cego (HMAC) e o IP em texto puro, porque é a chave do bloqueio por origem. Registros com mais de 24 horas são apagados a cada nova falha.
- Em `users`, o CPF e o telefone são gravados cifrados (`cpf_encrypted`, `phone_encrypted`) e o CPF tem um índice cego único (`cpf_index`) para a regra de unicidade e para busca. A conferência dos dígitos verificadores é feita em `App\Support\Cpf`, antes de cifrar — o banco não tem como validar o formato. `password` guarda apenas o hash de `password_hash()` com Argon2id. O e-mail é único e comparado sem diferenciar maiúsculas.
- `addresses` guarda os endereços de entrega do cliente para reuso: rua, número, complemento, bairro, cidade e destinatário cifrados; CEP e UF em texto puro porque alimentam o cálculo de frete; `is_default` marca o endereço preferido e `deleted_at` permite remover sem perder histórico.
- Preços ficam em centavos (`price_cents`, `compare_price_cents`) para não depender de arredondamento de `float`. O estoque é por variação de tamanho e `stock.reserved` guarda unidades presas em carrinho/checkout.

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

1. **Cabeçalho** (`.site-header`): barra em largura total (sem `.container`), com margem lateral de `--gutter`, em três colunas — emblema e nome "Obsyrium" à esquerda, menu (Lançamentos, Catálogo, Sobre e Contato) centralizado e busca, conta e carrinho à direita.
2. **Hero** (`.hero`): dois slides em faixa horizontal com `scroll-snap` e marcadores (`.hero__dot`) que ancoram em cada slide. Sem JavaScript.
3. **Vitrine "Lançamentos"** (`.section` + `.product-grid`): oito cards com selo, cor, preço, valor no Pix e parcelamento, seguidos da `.pagination`.
4. **Bloco de conteúdo** (`.section--alt` + `.content-block`): H2 e dois parágrafos institucionais.
5. **Newsletter** (`.newsletter`): título, texto de apoio, campo de e-mail e botão desabilitado até o envio existir.
6. **Rodapé** (`.site-footer`): colunas de políticas, atendimento, redes sociais e formas de pagamento, mais a linha legal.

### 17.2 Arquivos

- `resources/views/layouts/site.php`: documento HTML e `<head>` com os quatro CSS na ordem `tokens` → `base` → `components` → `layout`; inclui o cabeçalho, o `<main id="conteudo">` com a view de conteúdo e o rodapé.
- `resources/views/partials/header.php`: cabeçalho com marca, menu e ações. Links de categoria e botões de ação ainda apontam para `#`.
- `resources/views/partials/footer.php`: colunas do rodapé e linha legal.
- `resources/views/home/index.php`: hero, vitrine, bloco de conteúdo e newsletter. A vitrine recebe do `HomeController` os cards já formatados; os produtos saem do banco por `App\Repositories\ProductRepository` (o seed cria 8 de demonstração).
- `public/assets/css/layout.css`: cabeçalho, navegação, hero, seções, paginação, newsletter, rodapé e `.skip-link`.
- `public/assets/css/animations.css`: camada de movimento (entradas ao rolar, hover e transições).
- `public/assets/js/site.js`: menu do celular, sombra do cabeçalho e entradas ao rolar, sem dependências.

Cabeçalho e rodapé são reaproveitados por qualquer página renderizada pelo layout `site`.

### 17.3 Regras de manutenção

- O JavaScript do site é `public/assets/js/site.js`: menu do celular, sombra do cabeçalho e revelação dos blocos. Hero e estados continuam resolvidos em CSS; sem JavaScript o menu aparece em duas linhas e nada é escondido.
- Toda classe nova parte de `tokens.css`; cor e espaçamento fixos não entram nos componentes.
- `HomeController::index()` define `$title`, `$content` e inclui `layouts/site.php`. Novas páginas repetem esse contrato em vez de duplicar o HTML do documento.
- Um único `<h1>` por página (o do primeiro slide do hero); as seções usam `<h2>`.

### 17.4 Telas pequenas

O ajuste responsivo vive em `layout.css` e `components.css`, em duas faixas — `max-width: 60em` (tablet e celular) e `max-width: 40em` (celular) — mais um refinamento em `max-width: 26em` para telas estreitas.

- **Cabeçalho**: uma linha com marca, ações e o menu recolhido no hamburger (`.nav-toggle`, 44px, com `aria-expanded` e `aria-controls`). O painel abre logo abaixo do cabeçalho, em lista vertical com 44px por item, e fecha com Escape, clique fora ou escolha de um item — o ícone vira X. Sem JavaScript, o menu volta ao formato de duas linhas em faixa rolável (`justify-content: safe center`). `--header-height` no celular é 4rem e `html { scroll-padding-block-start }` usa o token, então âncoras como `#lancamentos` não ficam sob o cabeçalho fixo.
- **Hero**: sem altura mínima no celular, título em `clamp(1.75rem, 7vw, 2.25rem)`, botões empilhados com 100% de largura e marcadores com área de toque de 44px — o ponto visual é desenhado em `.hero__dot::after`. A faixa usa `scroll-behavior: smooth` (desligado por `prefers-reduced-motion`) e `overscroll-behavior-x: contain`.
- **Vitrine**: duas colunas com card compacto (título 0.875rem, preço em coluna, selos menores). O card inteiro é área de toque: o link do título se estende por `::after` com `inset: 0`.
- **Newsletter**: campo e botão empilhados em coluna, ambos com 100% de largura e 3rem de altura mínima.
- **Rodapé**: duas colunas de links no celular, com marca e formas de pagamento ocupando a linha inteira; cada link tem 44px de altura.
- **Toque e rolagem**: todo alvo usa `--target-min` (44px); `body` recebe `overflow-x: clip` para nenhum elemento gerar rolagem horizontal; `-webkit-tap-highlight-color: transparent` remove o realce azul do toque; o efeito de zoom do card só existe em `@media (hover: hover)`.

### 17.5 Movimento

A camada de animação fica em `public/assets/css/animations.css`, carregada por último, e em `public/assets/js/site.js`.

- **Entrada ao rolar**: blocos com `data-reveal` sobem 1.25rem e aparecem em 600ms; `data-reveal-stagger` aplica o mesmo aos filhos em cascata de 70ms. A vitrine escalona por linha (`4n+2`, `4n+3`, `4n+4`). Quem dispara é um `IntersectionObserver` que adiciona `.is-visible` uma única vez e para de observar o elemento.
- **Sem JavaScript nada fica escondido**: os estados iniciais dependem de `html.js`, marcado por script inline no `<head>`. Se `site.js` não carregar, um fallback no evento `load` remove a classe e o conteúdo aparece estático.
- **Cabeçalho**: ganha sombra e fundo mais opaco ao sair do topo (`.is-scrolled`). No celular o painel do menu entra com `menu-panel-in` e os itens em cascata de 40ms.
- **Microinterações**: sublinhado que cresce no menu (desktop), botões com elevação no hover e afundamento no toque, cards com `translateY(-4px)` e sombra (só em `hover: hover`), imagem com zoom de 500ms, foco do campo de newsletter com halo dourado e brilho do hero pulsando em 9s.
- **Rolagem suave**: `html { scroll-behavior: smooth }` para as âncoras internas.
- Tudo respeita `prefers-reduced-motion`: o bloco em `base.css` zera as durações e desliga a pulsação do hero.

## 18. Página do produto

Rota `GET /produto/{slug}`, atendida por `App\Controllers\ProductController::show`. Slug inexistente ou produto com `is_active = 0` devolve 404.

- O `Router` entende segmentos entre chaves: o caminho é convertido em regex ancorada e os valores capturados chegam ao handler como array nomeado (`['slug' => '...']`).
- Os dados vêm de `App\Repositories\ProductRepository`: `findBySlug()` (produto + categoria), `images()` (galeria na ordem de `position`) e `variantsWithStock()` (tamanhos com a quantidade de cada um, de PP a XG).
- A view `resources/views/product/show.php` apenas apresenta: o controller resolve preço, valor no Pix, parcelas, desconto e o estado do estoque (`in`, `low`, `out`).
- Tamanho sem estoque é renderizado com `disabled`, e a folha de estilo risca a opção por `.size-option__input:disabled + .size-option`. É o mesmo componente `.size-picker` usado na home.
- O estilo fica em `public/assets/css/product.css`, carregado junto das demais folhas no layout.
- O botão de compra segue desabilitado até o carrinho existir; a dica abaixo dele informa isso ao visitante.
- Os cards da vitrine (`HomeController::toCard`) apontam para `/produto/{slug}`.

## 19. Cadastro e login

| Rota | Método | Ação |
|---|---|---|
| `/cadastro` | GET | Formulário de cadastro |
| `/cadastro` | POST | Cria a conta e já abre a sessão |
| `/login` | GET | Formulário de login |
| `/login` | POST | Autentica e abre a sessão |
| `/logout` | POST | Encerra a sessão |

- `App\Controllers\AuthController` é fino: valida CSRF, chama `App\Services\AuthService` e renderiza. A regra fica no serviço, o acesso ao banco em `App\Repositories\UserRepository`.
- Cadastro exige nome, CPF válido, telefone com DDD, e-mail, senha de 8+ caracteres, confirmação e aceite dos termos. O aceite é gravado em `terms_accepted_at`.
- CPF e telefone são normalizados (só dígitos) e cifrados antes de ir para o banco; o CPF ganha um índice cego para a regra de unicidade.
- Login compara a senha com `password_verify` e reaproveita `password_needs_rehash` para atualizar o hash Argon2id quando necessário. Mensagem de erro é sempre "E-mail ou senha inválidos." — não revela se o e-mail existe.
- `App\Support\Session` cuida do cookie (`HttpOnly`, `SameSite=Lax`, `Secure` sob HTTPS) e regenera o identificador no login. Contas com `is_active = 0` ou `deleted_at` preenchido não entram.
- O token CSRF é trocado no login (`Csrf::rotate()` dentro de `Session::login`): um token obtido no formulário de login não vale para o logout depois de autenticado.
- `App\Services\LoginThrottle` aplica o bloqueio: 5 falhas por conta, 20 por IP, janela de 15 minutos, chaveada pelo índice cego do e-mail e pelo IP de origem.
- O cabeçalho mostra "entrar" para visitante e um botão de sair (POST com CSRF) para quem está conectado. Mensagens de retorno usam flash e aparecem no topo do conteúdo.
- Estilo em `public/assets/css/auth.css` (card, campos, erros) e a mensagem flash em `layout.css`.

- As telas de login e cadastro mostram os botões “Continuar com Google” e “Continuar com Apple” (`resources/views/partials/social-login.php`). Eles são apenas visuais: ficam desabilitados e com o aviso “Login social em breve”, porque o OAuth ainda não foi implementado.

## 20. Carrinho

O login só é exigido na hora da compra: o visitante monta o carrinho sem conta. Para ele o carrinho fica na sessão do navegador (`$_SESSION['guest_cart']`, `SessionCartStorage`); para o cliente autenticado fica no banco (`carts`, chave única em `user_id`, e um item por variação em `cart_items`, `DatabaseCartStorage`). As duas implementações seguem a interface `App\Services\CartStorage`; as regras ficam em `CartService` e valem para as duas.

| Rota | Ação |
| --- | --- |
| `GET /carrinho` | página do carrinho com os itens, quantidade editável e remoção |
| `POST /carrinho/adicionar` | soma a quantidade ao item (`variant_id`, `quantity`, com `slug` opcional) |
| `POST /carrinho/atualizar` | define a quantidade absoluta do item |
| `POST /carrinho/remover` | apaga o item |
| `GET /checkout` | inicia a finalização da compra (visitante vai para `/login`) |

- Toda escrita exige token CSRF, responde `303` e volta para `/produto/{slug}` quando o formulário manda o slug (sem slug, vai para `/carrinho`). O cabeçalho `Referer` não é usado.
- Regras em `App\Services\CartService`: no máximo **5 unidades** do mesmo item e nunca acima do estoque disponível (`stock.quantity - stock.reserved`).
- A conferência do estoque roda dentro de uma transação, com a linha do estoque travada por `SELECT ... FOR UPDATE`, para que dois envios simultâneos não passem os dois pela mesma validação.
- O preço não é guardado no carrinho: cada item mostra o preço atual de `products.price_cents` e o total é somado nas views (preço × quantidade de cada linha). Frete e checkout também são itens futuros.
- `resources/views/cart/index.php` monta a página com imagem, tamanho, preço, campo de quantidade (máximo `CartService::MAX_PER_ITEM`) e botão de remover — cada ação tem o próprio formulário e token CSRF. A quantidade não tem botão de enviar: o `site.js` manda o formulário sozinho no evento `change` do campo e atualiza contador, lista, totais e a própria página com a resposta, sem recarregar. Sem itens, mostra o estado vazio com link para a vitrine. O estilo fica em `public/assets/css/cart.css`.
- O ícone do carrinho no cabeçalho mostra a soma das quantidades de quem está navegando, vinda de `CartService::itemsForDisplay()` (sessão para visitante, banco para cliente). No computador ele abre a gaveta lateral (`resources/views/partials/cart-drawer.php`, estilo em `cart.css`, comportamento em `site.js`); no celular o mesmo link navega para `/carrinho`. A gaveta lista os itens, permite remover (o formulário manda `voltar` com o caminho atual, aceito só se for `/`, `/carrinho` ou `/produto/{slug}`) e traz “Finalizar compra” e “Ver carrinho completo”. Sem JavaScript a gaveta não aparece e o link continua funcionando.
- Ao entrar ou se cadastrar, `CartService::mergeGuestCart()` soma o carrinho da sessão ao do banco (respeitando o limite de 5 e o estoque disponível) e esvazia a sessão; quem já tinha itens no banco recebe a soma. O logout limpa a sessão e preserva o carrinho do banco.
- Produto inativo, variação inexistente, quantidade zero, item fora do carrinho e estoque insuficiente são recusados com mensagem, sem gravar nada.
- Adicionar ao carrinho devolve o cliente à página em que ele estava antes de abrir o produto, para seguir comprando: `App\Support\Navegacao` guarda na sessão a última página visitada (`/`, `/carrinho` ou `/produto/{slug}`) e o formulário do produto manda o campo `voltar`. Sem histórico, a volta é a home, e o controller só aceita os caminhos dessa lista.
- A confirmação de item adicionado é um pop-up (`.cart-toast`, em `cart.css`) ancorado no ícone do carrinho, não uma faixa no topo: traz ícone de confirmado, o texto e o atalho “Ver carrinho”, e fecha sozinho em 6 segundos ou pelo botão (comportamento em `site.js`). O layout deixa de desenhar a faixa quando o tipo da mensagem é `cart`. Remover um item na página `/carrinho` usa o mesmo pop-up (`Session::flash('cart', 'Item removido do carrinho :/', 'removido')`), só no cabeçalho: ali a gaveta não abre. Erros de estoque, de limite e de remoção continuam na faixa do topo.
- Remover pela gaveta não recarrega a página: o `site.js` envia o formulário com `fetch`, o próprio item vira uma faixa dourada (`--color-accent`, texto escuro) com “Item removido do carrinho :/”, fica 0,5 s, encolhe e os itens de baixo sobem. O contador do cabeçalho, o total do rodapé e o estado vazio vêm do HTML da própria resposta (o flash é consumido ali, então não sobra aviso para a próxima página). Na `/carrinho` o caminho é o mesmo: depois da resposta, a seção `.cart-page` é trocada pela que veio no HTML, então a lista da página acompanha a gaveta sem recarregar. Navegador sem `fetch`, `FormData` ou `DOMParser` continua no envio tradicional.
- Na gaveta a quantidade é ajustada por um contador `−` / `+` (`.cart-drawer__quantity`, 1,75 rem por botão), com o número entre os dois e os extremos desativados: `−` no 1 e `+` no limite de `CartService::MAX_PER_ITEM`. Cada toque envia o formulário por `fetch` como o campo da página, então valor, total e contagem acompanham sem recarregar. Se o servidor recusar (estoque), o envio tradicional assume e a página volta com a mensagem — por isso a gaveta não recarrega no caso normal e mostra o motivo no caso recusado.
- A gaveta e a página `/carrinho` fecham com o total do carrinho, somando preço unitário × quantidade de cada linha. `CartService::itemsForDisplay()` expõe `price_cents` para isso, e as duas views calculam com `Price::format()` (`cart-page__total` e `cart-drawer__total`). Depois da remoção por `fetch`, o rodapé da gaveta é trocado pelo que veio na resposta, então total e contagem acompanham sem recarregar.
- No computador (≥ 64rem) o item adicionado também abre a gaveta do carrinho automaticamente: `site.js` move o pop-up para dentro do painel e remove o atalho “Ver carrinho”, já que a lista fica à vista. No celular o comportamento continua o mesmo: o pop-up fica no cabeçalho, com o atalho, e a gaveta não abre. Na própria `/carrinho` a gaveta também não abre (a lista já está à vista): o `site.js` confere o caminho antes de mover o aviso e abrir a lateral, então ali o pop-up fica no cabeçalho, sem o atalho.
- Essa abertura automática se recolhe sozinha depois de 3 segundos: a gaveta encolhe na direção do ícone do carrinho (`cart-drawer-collapse`, com origem no canto superior direito) e o ícone dá um pulso dourado no fim, deixando claro onde clicar para abrir de novo. O fechamento por clique, Escape ou fundo usa a mesma animação. Se o cliente interage com a gaveta (mouse, clique ou teclado), o fechamento automático é cancelado; uma rede de segurança em `fecharComAnimacao` fecha mesmo sem `animationend` (movimento reduzido ou aba em segundo plano). A abertura automática não rouba o foco do teclado; a aberta pelo ícone foca o botão de fechar.
- A gaveta aberta não esconde a barra de rolagem nem trava a página: o `body` continua rolando, nada pula de largura e o cliente pode rolar o fundo com a gaveta à vista (o `overflow: hidden` saiu de `cart.css`). Rolar a página com ela aberta é o gesto que recolhe a gaveta: o `site.js` escuta `scroll` na janela e chama `fecharComAnimacao()`, ignorando os 300ms seguintes à abertura para não confundir com a posição restaurada pelo navegador. A rolagem do painel não conta, porque fica presa nele (`overscroll-behavior: contain`). Ao fechar, o foco volta para quem abriu com `focus({preventScroll: true})`: o ícone do carrinho fica no topo do documento e o foco sem essa opção puxava a página para lá, o que aparecia como um pulo para cima no fim da animação. A barra do site é fina (6px no Chromium, `thin` no Firefox), com o polegar no mármore da borda e dourado no hover (`base.css`).
- A página `/carrinho` e a gaveta têm o botão “Finalizar compra”, que aponta para `GET /checkout`: carrinho vazio volta para `/carrinho` com aviso e visitante vai para `/login` com a mensagem “Entre na sua conta para finalizar a compra.”. A página em si está descrita na seção 22.
- O carrinho de visitante dura o tempo da sessão do navegador; o do banco fica gravado.
## 21. Área do cliente

O ícone de pessoa no cabeçalho aponta para `/conta` quando há sessão aberta; sem sessão ele continua levando ao login, e o botão de sair ganhou ícone próprio.

- Rotas: `GET /conta` (meus dados), `POST /conta` (salvar nome e telefone), `GET /conta/enderecos`, `POST /conta/enderecos/salvar` (cria ou atualiza), `POST /conta/enderecos/remover`, `POST /conta/enderecos/padrao`, `GET /conta/enderecos/{id}` (formulário de edição) e `GET /conta/compras`. Todas exigem sessão: sem cliente logado a resposta é 303 para `/login` com aviso.
- `App\Controllers\AccountController` monta as três telas sobre um menu único (`resources/views/partials/account-nav.php`). Cada aba é uma rota própria, então o item atual fica marcado com `aria-current` e o botão voltar do navegador funciona.
- Layout (`public/assets/css/account.css`): no computador o menu é uma coluna de 15 rem à esquerda e o painel de conteúdo fica à direita; abaixo de 64 rem a mesma lista vira uma faixa de abas que rola na horizontal. É o padrão das lojas grandes — todas as seções à vista no desktop e troca por abas no celular — e deixa o lugar pronto para uma quarta entrada quando as avaliações entrarem.
- "Meus dados" edita nome e telefone. O e-mail aparece só para leitura (é o login) e o CPF chega mascarado do controller (`AccountService::cpfLabel()`), no formato `***.***.***-25`.
- "Endereços" lista cartões com nome do endereço, destinatário, rua/número/complemento, bairro, cidade/UF e CEP, marca o principal e oferece editar, remover e tornar principal. O formulário serve para cadastrar e editar; o `address_id` que vem do formulário é conferido contra o dono antes de qualquer gravação.
- `App\Repositories\AddressRepository` cifra destinatário, rua, número, complemento, bairro e cidade (AES-256-GCM, o mesmo `Crypto` do CPF); CEP e UF ficam em texto puro porque são a base do frete. Toda gravação que mexe em mais de uma linha (trocar o principal, remover o principal e promover outro) roda em transação.
- Endereços usam exclusão lógica (`deleted_at`): um pedido antigo pode precisar do endereço depois.
- O primeiro endereço do cliente vira principal sozinho e remover o principal promove o mais antigo que sobrou. Desmarcar a caixa em quem já é principal não tira a marca: o cadastro nunca fica sem endereço de referência.
- O campo de CEP aceita só dígitos e coloca o hífen sozinho (`01310-100`): a máscara é só estética, o valor gravado continua sendo os 8 números, e backspace logo depois do hífen apaga o quinto dígito em vez de ficar preso nele.
- O CEP do formulário de endereço consulta a [BrasilAPI](https://brasilapi.com.br) direto do navegador (`https://brasilapi.com.br/api/cep/v2/{cep}`, que libera CORS) assim que os oito dígitos estão completos: rua, bairro, cidade e UF são preenchidos automaticamente e número e complemento continuam manuais. Uma consulta por CEP é feita e o resultado aparece em `[data-cep-aviso]` (`aria-live`), separando os casos: `404` da API vira “CEP não encontrado. Confira os números.”, enquanto falha de rede ou erro do serviço vira “Não foi possível buscar o CEP agora. Preencha manualmente.” — nenhum dos dois bloqueia o envio, e o campo volta a consultar quando o CEP muda. Sem JavaScript o formulário funciona como antes.
- `App\Services\AccountService` valida perfil e endereço (nome, telefone com DDD, CEP de 8 dígitos, UF na lista oficial e limites de tamanho) e devolve os erros por campo, no formato que as views já usam.
- "Compras" lista os pedidos do mais novo para o mais antigo, com número, data, situação, itens e o endereço de entrega registrado na compra (seção 23). A tabela de avaliações e a moderação no painel ficaram registradas no roadmap como tarefas futuras.
## 22. Finalização da compra

`/checkout` deixou de ser só um portão de login e virou página: resumo dos itens, totais e escolha do endereço de entrega.

- `GET /checkout` exige carrinho com itens (senão volta para `/carrinho` com aviso) e cliente logado (senão vai para `/login`). A página mostra cada linha do carrinho com imagem, nome, tamanho, quantidade e valor da linha, o resumo de valores (peças, subtotal, frete “a calcular” e total) e os endereços salvos no perfil em cartões de rádio, com o principal marcado.
- `POST /checkout` confere o CSRF e se o endereço escolhido pertence ao cliente; endereço de outra conta ou inexistente volta com “Escolha um endereço de entrega da sua conta.” e mantém a escolha anterior. Confirmar grava o pedido (seção 23), esvazia o carrinho e leva para a confirmação em `/pedido/{número}/confirmacao` (seção 31). Sem endereço salvo, a página mostra o estado vazio com link para `/conta/enderecos`.
- `CheckoutController` monta os dados: `CartService::itemsForDisplay()` para as linhas, `AccountService::addressesFor()` para os endereços, e o total somando `price_cents × quantity`. O endereço principal já vem marcado; na falta dele, o primeiro da lista.
- Layout em `public/assets/css/checkout.css`: uma coluna no celular e duas no computador (conteúdo à esquerda, resumo de valores numa coluna de 20 rem que acompanha a rolagem). O endereço marcado ganha a borda dourada e o fundo do acento.
- Pagamento não existe ainda: o botão grava o pedido como `aguardando_pagamento` e o texto avisa que nada é cobrado agora. Carrinho e checkout continuam sem frete calculado.

## 23. Pedidos e histórico de compras

O fechamento do carrinho virou pedido: o checkout grava `orders` + `order_items`, baixa o estoque e esvazia o carrinho numa transação só; a aba Compras lista o histórico.

- Migration `database/migrations/2026_11_16_000011_create_orders_tables.php` cria `orders`, `order_items` e `payments`. `payments` nasce vazia: o gateway da Fase 6 grava ali a cobrança ligada ao pedido.
- `orders` guarda o retrato da venda: número legível (`OBS-000042`, derivado do id), situação (`aguardando_pagamento`, `pago`, `enviado`, `entregue`, `cancelado`), peças, subtotal, frete (0 por enquanto), total e o endereço de entrega copiado cifrado (`ship_*`). Mudar o endereço no perfil depois não altera pedido antigo.
- `order_items` copia nome, slug, tamanho, SKU, preço unitário e total da linha. `variant_id` aceita `NULL` (`ON DELETE SET NULL`) para o histórico sobreviver à exclusão de uma variação.
- `App\Services\OrderService::place()` cuida do fechamento dentro da transação: trava as linhas do carrinho (`FOR UPDATE`, em ordem de `variant_id` para não cruzar com o `CartService`), confere produto ativo e estoque disponível, grava pedido e itens, debita `stock.quantity` com a mesma condição no `WHERE` e apaga os itens do carrinho. Qualquer falha desfaz tudo e o aviso diz qual item precisa de ajuste.
- Preço, quantidade e estoque saem sempre do banco; o formulário só manda o `address_id`, conferido contra o dono. Somamos sempre em centavos inteiros.
- `POST /checkout` virou `CheckoutController::place()`: grava o pedido e manda para a confirmação em `/pedido/{número}/confirmacao`, com o número e o resumo. O pedido nasce `aguardando_pagamento` — nada é cobrado.
- `GET /conta/compras` usa `OrderService::ordersForUser()` (50 mais recentes, itens em uma consulta só) e mostra um cartão por pedido com número, data, peças, situação, itens, endereço de entrega e totais. O cartão fica no partial `resources/views/partials/order-card.php`, reaproveitado pela confirmação do checkout. A situação aparece por extenso (“Aguardando pagamento”) e a cor só reforça.
- Estilos em `public/assets/css/account.css` (bloco `order*`): cartões no mesmo desenho dos endereços, itens em lista e endereço/totais lado a lado a partir de 40 rem.
- O estoque é baixado na hora da compra. Reserva que expira e pagamento pendente ficaram para as próximas etapas.

## 24. Painel administrativo de pedidos

A administração tem barra, layout e sessão próprios em `/admin` — nada do cabeçalho da loja, e `noindex`.

- Contas ficam em `admins` (migration `2026_11_16_000012_create_admins_table.php`), separadas de `users`: administrador não compra, não tem CPF e não herda o perfil do cliente. A conta nasce pelo terminal, nunca por formulário público:
  - `php database/admin.php criar "Nome" email@daloja.com "senha com 10+"`
  - `php database/admin.php senha email@daloja.com "nova senha"`
  - `php database/admin.php listar`
- `App\Services\AdminAuthService` usa Argon2id, rehash automático, o mesmo `LoginThrottle` do cliente (5 falhas por conta ou 20 por IP em 15 minutos) e mensagem de erro genérica. A sessão do painel é a chave `admin_id` (`Session::loginAdmin()`/`adminLogout()`), então abrir o painel não transforma o navegador em cliente logado e sair do painel não derruba a conta da loja.
- Rotas: `GET /admin` (dashboard) e `GET /admin/pedidos` (lista com filtro por situação), `GET /admin/pedidos/{number}` (detalhe), `POST /admin/pedidos/{number}/status` (troca de situação), `GET|POST /admin/login`, `POST /admin/logout`. Sem sessão de administrador, tudo além do login responde 303 para `/admin/login` com aviso.
- `OrderService::adminOrders()` lista os 100 pedidos mais recentes com nome e e-mail do cliente; `adminOrder()` monta o detalhe com itens, endereço decifrado e totais.
- Transições em `OrderService::NEXT_STATUSES`: aguardando pagamento → pago/cancelado, pago → enviado/cancelado, enviado → entregue, e entregue/cancelado são finais. O formulário só oferece o que o servidor aceita e o servidor confere de novo.
- `changeStatus()` roda em transação com a linha do pedido travada (`FOR UPDATE`): dois cliques em "cancelar" não somam estoque duas vezes. Cancelar devolve as peças com um `UPDATE stock ... + oi.quantity` a partir de `order_items`, ignorando itens cuja variação já não existe.
- Views em `resources/views/admin/` (`login`, `orders`, `order`) sobre `layouts/admin.php`; estilos em `public/assets/css/admin.css`. A tabela rola na horizontal abaixo de 56 rem e o detalhe empilha itens e colunas laterais.
- Situação aparece por extenso; a cor do selo só reforça. O aviso de cancelamento (“Cancelar devolve as peças deste pedido ao estoque.”) fica visível junto do formulário.
- O painel ainda não cobre produtos, estoque, cupons nem pagamentos — só pedidos, como primeiro passo.

## 25. Dashboard e navegação do painel

`/admin` abre no dashboard e a navegação virou barra lateral com dashboard, pedidos e estoque.

- `App\Services\DashboardService::summary()` devolve os seis últimos meses (meses sem venda entram zerados), o total de peças vendidas, o faturamento acumulado e quantos clientes ativos existem. Só entra nas contas o pedido `pago`, `enviado` ou `entregue` (lista em `DashboardService::VENDIDOS`): `aguardando_pagamento` e `cancelado` ficam de fora do faturamento, das peças e da contagem de pedidos, tanto no total quanto no mês.
- Gráficos em SVG montado na view (`resources/views/admin/dashboard.php`), sem biblioteca: barras para pedidos por mês e linha com área para o faturamento mensal, ambos com `viewBox` fixo e valores calculados a partir do dado. Cada ponto tem o valor em texto na legenda abaixo, então o gráfico não depende só da cor nem do traço.
- Cards numéricos: peças vendidas e clientes, mais o faturamento acumulado. `public/assets/css/admin.css` traz `admin-stats`, `admin-stat`, `admin-charts` e `chart*`.
- `layouts/admin.php` ganhou `.admin-shell` em grid: barra no topo, lateral de 13 rem e conteúdo. Abaixo de 56 rem a lateral vira faixa de abas que rola na horizontal, com o item atual marcado por `aria-current="page"`.
- `GET /admin/estoque` lista as variações com saldo, reservado e disponível, e marca a situação: disponível, estoque baixo (até 2) ou esgotado. É só leitura — a edição de saldo entra com o cadastro de produtos.
- Os estilos de campo (`.field`, `.field__input`, `.field__label`, `.field__hint`, `.field__error`) saíram do `auth.css` para o `components.css`, carregado por todas as páginas: antes o login do painel e o seletor de situação ficavam sem estilo porque o layout admin não carrega `auth.css`.

## 26. Estoque editável

`/admin/estoque` deixou de ser só leitura: cada variação tem um campo com o saldo e um botão `Salvar`.

- `POST /admin/estoque/saldo` recebe `variant_id`, `quantity` e `atual` (o saldo que estava na tela). Exige sessão de administrador e CSRF, e o redirecionamento é 303.
- `App\Services\StockService::update()` valida no servidor: variação existente, saldo em inteiro de 0 a 99999 e nunca abaixo das peças reservadas (a tabela `stock` também tem o `CHECK reserved <= quantity`). O ajuste é absoluto — o administrador digita o saldo, não a diferença.
- Concorrência: a linha é travada (`FOR UPDATE`) e a gravação só acontece se o saldo do banco ainda for o `atual` enviado. Se uma compra mudou o saldo enquanto a página estava aberta, o painel avisa “O saldo mudou enquanto você editava. Recarregue a página e tente de novo.” em vez de sobrescrever a venda.
- `INSERT … ON DUPLICATE KEY UPDATE` cobre a variação que ainda não tem linha em `stock`.
- A leitura do estoque passou de `DashboardService` para `StockService::all()`; o dashboard ficou só com os indicadores.
- Cada campo tem `label` associado (visualmente escondido, porque a coluna já diz o que é), e o formulário manda o token CSRF junto.

## 27. Estoque com abas, paginação e linha

`/admin/estoque` virou a central de estoque: abas por situação, colunas ordenáveis, 10 linhas por página e o interruptor de linha do produto.

- Abas (`StockService::TABS`): **Em estoque** (`is_active = 1` e disponível > 0), **Esgotados** (`is_active = 1` e disponível ≤ 0), **Fora de linha** (`is_active = 0`) e **Todos** (`is_active = 1`, sem os fora de linha). Cada aba mostra o contador no botão.
- Ordenação: `?ordem=…&direcao=asc|desc` com lista branca em `StockService::SORTABLE` — produto, tamanho (ordem real PP/P/M/G/GG via `FIELD`), SKU, preço, promocional, reservado, saldo e disponível. O cabeçalho é um link que inverte a direção e marca `aria-sort`; nada de SQL vindo da URL.
- Paginação de 10 (`PER_PAGE`) com `LIMIT/OFFSET`, contagem total e “Mostrando 1–10 de 24”. Página fora do intervalo cai na última; os links preservam aba, coluna e direção.
- Colunas de preço: **Preço** = valor cheio (`compare_price_cents`, ou `price_cents` quando não há comparação) e **Promocional** = valor cobrado (`price_cents`), com o selo de desconto quando os dois diferem. É o mesmo par que a loja usa no produto.
- A lixeira de cada linha age no **modelo**: `products.is_active = 0`. O produto sai da loja e das abas de produtos ativos, e o saldo não muda. Na aba Fora de linha o ícone vira **carrinho**, que devolve o produto (`is_active = 1`): se não houver saldo ele volta esgotado, como esperado.
- Ações em `POST /admin/estoque/fora-de-linha` e `POST /admin/estoque/voltar`, com CSRF e redirecionamento 303 para a mesma aba/ordem/página de origem (reconstruída só com valores da lista branca).
## 28. Edição do produto e fotos

O lápis ao lado da lixeira (e do carrinho, na aba fora de linha) abre `/admin/estoque/produto/{id}`: a mesma composição da página do item na loja — galeria à esquerda, informações à direita — só que com tudo editável.

- Campos: nome, endereço (slug), categoria, cor, material, descrição, preço promocional, preço cheio e o interruptor "na loja" (o mesmo `products.is_active` da lixeira). O slug em branco é gerado pelo nome; endereço repetido é recusado.
- Preços entram em reais, com vírgula (`129,90`), e são gravados em centavos. O preço cheio é opcional e precisa ser maior que o promocional — é ele que vira o "de" na loja.
- Erros de validação voltam para a mesma tela com a lista de problemas e os valores digitados preservados; nada é gravado pela metade.
- Fotos: upload em `public/uploads/produtos` (JPG, PNG ou WebP, até o limite do PHP — 2 MB neste servidor), com nome sorteado por nós, conferência de conteúdo por `getimagesize` + `finfo` e gravação em `product_images` (`path`, `alt`, `position`). A primeira da lista é a foto principal da vitrine e da página do item.
- A primeira foto de verdade apaga a linha do placeholder compartilhado (`/assets/images/placeholder.svg`), que nunca é removido do disco; o placeholder também nunca é apagado como arquivo, só a linha dele.
- Remover foto apaga a linha e o arquivo — apenas quando o caminho está dentro de `/uploads/produtos/`.
- O "Voltar" e o retorno depois de salvar preservam aba, coluna, direção e página do estoque.
- Tamanhos: o saldo de cada tamanho é editável na própria tela do produto (`POST /admin/estoque/produto/{id}/saldo`), com a mesma trava otimista da tela de estoque — se o saldo mudar enquanto a página está aberta, o POST é recusado com aviso e nada é gravado.
MD
## 29. Categorias, subcategorias e cadastro de modelo

O estoque ganhou o botão **Novo modelo** e a lateral ganhou **Categorias**.

- **Novo modelo** (`/admin/estoque/novo`) usa a mesma tela do produto, com os tamanhos (PP a GG) e um saldo inicial aplicado a cada tamanho marcado no lugar da galeria. Ao criar, ele cai direto na edição para receber as fotos; o SKU de cada tamanho é gerado como `SLUG-TAMANHO`.
- **Categorias** (`/admin/categorias`) segue o desenho do estoque: abas por categoria, os modelos da categoria escolhida em tabela (com situação e atalho para a edição) e, quando a categoria tem filhas, uma segunda linha de abas para cada subcategoria. A escolha fica na URL (`?categoria=ID`).
- O formulário de nova categoria tem a marca **Subcategoria**: marcada, aparece o "Subcategoria de", que só lista categorias principais (subcategoria não pode ter subcategoria); desmarcada, a categoria é criada como principal e o seletor é ignorado pelo servidor.
- **Mais de uma categoria por produto**: `product_categories` guarda todos os vínculos. `products.category_id` continua sendo a principal (a primeira marcada), que é o que a vitrine e a página do item mostram — por isso nada mudou nas consultas da loja.
- **Subcategoria no formulário**: cada categoria raiz marcada abre o seletor "Subcategoria de …"; escolhendo uma, o vínculo é com a subcategoria, sem escolha fica na raiz. `public/assets/js/admin.js` só mostra o seletor quando a categoria está marcada; sem JavaScript ele fica visível e o servidor ignora subcategoria de categoria não marcada.
- A tela de categorias conta só os vínculos diretos: um produto na subcategoria aparece na subcategoria, não na mãe.
- Migration `2026_11_16_000013_add_category_tree_and_product_categories.php` cria `categories.parent_id`, a tabela `product_categories` e copia os vínculos antigos de `products.category_id`.
MD

## 30. Catálogo

`/catalogo` lista todos os produtos ativos, sem depender da vitrine de oito da home. Cabeçalho centrado — título, sem sobretítulo, e um texto sobre a marca (lotes pequenos, estoque real) — e, no computador, filtros numa coluna de 17 rem à esquerda com a grade à direita.

- **Filtros** num formulário `GET`: categoria e subcategoria em `select` (a subcategoria só aparece quando a categoria escolhida tem filhas), **cor** em `select` com a contagem de cada uma, tamanho e disponibilidade em pílulas de rádio e **faixa de preço digitada** em dois campos (De/Até, em reais, cada ponta opcional). O botão "Aplicar filtros" funciona sem JavaScript; com script, o formulário se envia sozinho ao mudar um campo (`data-filter-form` em `site.js`).
- **Ordenação**: novidades (padrão), menor preço, maior preço e nome de A a Z, num menu recolhível com ícone que mostra a ordem atual; clicar escolhe a ordem e o menu fecha ao clicar fora ou com Escape.
- **Filtros ativos**: uma faixa de pílulas removíveis acima da grade — cada uma tira só aquele filtro — mais o atalho "Limpar tudo".
- **Estado na URL**: `/catalogo?categoria=camisetas&cor=Preta&tamanho=M&preco_min=100&preco_max=149.90&ordem=preco-asc&pagina=2`. Valores padrão não entram na URL, então `/catalogo` é o catálogo inteiro. O servidor valida tudo: categoria, subcategoria (precisa ser filha da escolhida), tamanho, cor (comparada sem diferenciar maiúsculas, só entre as cores existentes), preço (convertido de reais para centavos, com vírgula ou ponto, teto de R$ 100 mil) e ordenação que não existem são ignorados. Faixa invertida é trocada de lado.
- **Cor** sai da coluna `products.color` (já existia, preenchida no painel em Produto → Cor); o filtro só lista cores de produtos ativos.
- **Paginação** de 12 por página, com a faixa "1–12 de 14" e o resumo das páginas (primeira, atual, última e vizinhas).
- **Contagem das categorias** usa `COUNT(DISTINCT ...)` com as filhas na mesma subconsulta: produto ligado à categoria e a uma subcategoria conta uma vez só.
- **Celular**: o painel de filtros é um `<details>` que nasce recolhido (`site.js` fecha quando não há filtro ativo) e abre com o toque; com filtro ligado já vem aberto e mostra um selo com a quantidade.
- O card da vitrine virou o partial `resources/views/partials/product-card.php` e o formato do card saiu para `App\Support\ProductCard`, usados pela home e pelo catálogo.
- O menu ganhou **Catálogo** (marcado como página atual) e o "Ver todos" da vitrine aponta para ele. `Navegacao` passou a aceitar `/catalogo` como origem, então adicionar ao carrinho a partir do catálogo devolve o cliente para o catálogo.

## 31. Confirmação do pedido e páginas institucionais

Fechar a compra termina numa página própria: `/pedido/OBS-000004/confirmacao`.

- `POST /checkout` responde 303 para `/pedido/{número}/confirmacao`; o aviso de sucesso que antes aparecia em Compras virou a própria página.
- `GET /pedido/{number}/confirmacao` (`CheckoutController::confirmation()`) exige cliente logado (senão 303 para `/login`) e procura o pedido com `OrderService::orderForUser()`, que filtra por `user_id` **e** pelo número. Pedido de outra conta responde 404 igual a um número inexistente, sem revelar de quem é.
- A página traz o número em destaque, o mesmo cartão de pedido de Compras (`partials/order-card.php`) com itens, endereço e totais, a lista de próximos passos (pagamento, separação e envio, acompanhamento em Compras) e os atalhos "Ir para Compras" e "Continuar comprando".
- `public/assets/css/checkout.css` ganhou o bloco `confirm*`: uma coluna no celular e duas no computador (resumo à esquerda, passos numa coluna de 20 rem à direita), com o check dourado no topo.
- O cartão de pedido saiu de `resources/views/account/orders.php` para o partial, usado pelas duas telas — mesmo desenho, uma fonte só.

### Páginas Sobre e Contato

- `GET /sobre` e `GET /contato` (`App\Controllers\PageController`) montam páginas fixas no layout do site: `resources/views/pages/about.php` e `resources/views/pages/contact.php`.
- Sobre apresenta a marca, o trabalho em lotes pequenos, como a compra funciona e o cuidado com a estampa; Contato reúne o horário de atendimento (segunda a sexta, 9h às 18h) e os atalhos que já funcionam (Compras, endereços e catálogo). O e-mail e os perfis oficiais entram quando o domínio estiver no ar.
- O menu do topo ganhou os links reais, com `aria-current` nas duas páginas, e o "Fale conosco" do rodapé aponta para `/contato`. Nenhum link do menu ficou em `#`.
- Estilos em `public/assets/css/pages.css`, carregado no layout do site: blocos em duas colunas a partir de 48 rem.
