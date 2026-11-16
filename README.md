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

1. **Cabeçalho** (`.site-header`): barra em largura total (sem `.container`), com margem lateral de `--gutter`, em três colunas — emblema e nome "Obsyrium" à esquerda, menu (Lançamentos, Camisetas, Sobre, Contato) centralizado e busca, conta e carrinho à direita.
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
- O CEP do formulário de endereço consulta a [BrasilAPI](https://brasilapi.com.br) direto do navegador (`https://brasilapi.com.br/api/cep/v2/{cep}`, que libera CORS) assim que os oito dígitos estão completos: rua, bairro, cidade e UF são preenchidos automaticamente e número e complemento continuam manuais. Uma consulta por CEP é feita, o resultado aparece em `[data-cep-aviso]` (`aria-live`) e, se a busca falhar, o aviso pede para preencher à mão sem bloquear o envio. Sem JavaScript o formulário funciona como antes.
- `App\Services\AccountService` valida perfil e endereço (nome, telefone com DDD, CEP de 8 dígitos, UF na lista oficial e limites de tamanho) e devolve os erros por campo, no formato que as views já usam.
- "Compras" está no menu com estado vazio: a lista de pedidos entra nela quando as tabelas de pedido existirem (Fase 3). A tabela de avaliações e a moderação no painel ficaram registradas no roadmap como tarefas futuras.
## 22. Finalização da compra

`/checkout` deixou de ser só um portão de login e virou página: resumo dos itens, totais e escolha do endereço de entrega.

- `GET /checkout` exige carrinho com itens (senão volta para `/carrinho` com aviso) e cliente logado (senão vai para `/login`). A página mostra cada linha do carrinho com imagem, nome, tamanho, quantidade e valor da linha, o resumo de valores (peças, subtotal, frete “a calcular” e total) e os endereços salvos no perfil em cartões de rádio, com o principal marcado.
- `POST /checkout` confere o CSRF e se o endereço escolhido pertence ao cliente; endereço de outra conta ou inexistente volta com “Escolha um endereço de entrega da sua conta.” e mantém a escolha anterior. A escolha fica na sessão (`checkout_address`) para a etapa de pagamento aproveitar. Sem endereço salvo, a página mostra o estado vazio com link para `/conta/enderecos`.
- `CheckoutController` monta os dados: `CartService::itemsForDisplay()` para as linhas, `AccountService::addressesFor()` para os endereços, e o total somando `price_cents × quantity`. A escolha guardada só vale enquanto o endereço existir; senão cai para o principal e, na falta dele, para o primeiro da lista.
- Layout em `public/assets/css/checkout.css`: uma coluna no celular e duas no computador (conteúdo à esquerda, resumo de valores numa coluna de 20 rem que acompanha a rolagem). O endereço marcado ganha a borda dourada e o fundo do acento.
- Pagamento não existe ainda: o botão da página confirma o endereço e o texto avisa que nada é cobrado agora. Carrinho e checkout continuam sem frete calculado.
