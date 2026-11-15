# AGENTS.md — Instruções para agentes de desenvolvimento

## 1. Objetivo

Estas instruções definem como agentes de IA devem analisar, modificar, testar e documentar este repositório.

O agente deve priorizar correção, segurança, simplicidade e consistência com a arquitetura existente. Não deve presumir que funcionalidades, integrações ou infraestrutura já estejam implementadas.

## 2. Antes de modificar o projeto

1. Leia este arquivo e o `README.md`.
2. Inspecione a estrutura real do repositório.
3. Verifique `composer.json`, rotas, migrations, configurações e testes existentes.
4. Identifique padrões já utilizados antes de propor novos.
5. Confirme quais funcionalidades estão implementadas; não trate itens planejados como existentes.
6. Se faltar informação essencial ou houver risco de alteração destrutiva, faça uma pergunta antes de prosseguir.

Não recrie arquivos, estruturas ou funcionalidades sem verificar se já existem.

## 3. Stack e arquitetura

Stack prevista:

- PHP 8.2+.
- MVC.
- MySQL 8+ ou MariaDB compatível.
- PDO para acesso ao banco.
- HTML, CSS e JavaScript sem framework obrigatório.
- Composer para dependências e autoload.
- Nginx ou Apache.

Respeite a arquitetura encontrada no repositório. Não introduza frameworks, bibliotecas, serviços ou dependências sem justificativa técnica e aprovação quando houver impacto relevante.

Responsabilidades:

- `Controllers/`: entrada HTTP e coordenação do fluxo.
- `Models/` ou `Repositories/`: persistência e consultas.
- `Services/`: regras de negócio e integrações.
- `Middleware/`: autenticação, autorização e verificações transversais.
- `resources/views/`: apresentação.
- `routes/`: definição de rotas.
- `config/`: configuração da aplicação.
- `database/migrations/`: evolução versionada do schema.
- `tests/`: testes automatizados.

Não coloque regras de negócio em views. Evite controllers extensos e duplicação de lógica.

## 4. Padrões de código

- Seguir PSR-12.
- Utilizar `declare(strict_types=1);` quando aplicável.
- Utilizar autoload PSR-4 do Composer.
- Preferir nomes claros e consistentes em inglês no código, seguindo o padrão já estabelecido no repositório.
- Manter mensagens apresentadas ao usuário em português, salvo padrão diferente já definido.
- Utilizar tipos de parâmetros e retornos quando apropriado.
- Manter funções e classes com responsabilidade única.
- Evitar abstrações prematuras e código genérico sem necessidade.
- Não adicionar comentários que apenas repitam o que o código faz.
- Não remover código existente sem entender seu propósito.

## 5. Banco de dados

- Utilizar PDO e prepared statements.
- Nunca concatenar dados externos diretamente em SQL.
- Versionar alterações por migrations.
- Preservar integridade referencial.
- Utilizar transações quando uma operação envolver múltiplas gravações dependentes.
- Avaliar índices e constraints para novas tabelas e consultas.
- Não alterar schema de produção diretamente.
- Não apagar ou recriar dados reais durante testes.
- Não incluir credenciais ou dados pessoais reais em seeds, fixtures ou logs.

Ao modificar uma tabela, verifique os Models/Repositories, Services, validações e testes que dependem dela.

## 6. Segurança obrigatória

- Validar toda entrada no backend.
- Escapar dados dinâmicos na saída HTML conforme o contexto.
- Usar `password_hash()` e `password_verify()` para senhas.
- Implementar proteção CSRF nas operações aplicáveis.
- Verificar autenticação e autorização no servidor em cada rota restrita.
- Não confiar em preço, desconto, frete, estoque, permissões ou status enviados pelo cliente.
- Validar uploads por tipo, tamanho e conteúdo; gerar nomes internos e impedir execução.
- Proteger sessões e cookies.
- Não expor stack traces, SQL, caminhos internos ou segredos ao usuário.
- Não registrar senhas, tokens, dados de cartão ou outros segredos.
- Usar HTTPS em produção.
- Tratar falhas de integrações externas sem revelar detalhes internos.

Nunca enfraqueça controles de segurança para facilitar testes ou concluir uma tarefa rapidamente.

## 7. Pagamentos, pedidos e estoque

Ao trabalhar nessas áreas:

- Calcular valores no servidor com base nos dados persistidos.
- Utilizar representação monetária precisa; não depender de ponto flutuante para cálculos financeiros.
- Não considerar o redirecionamento do navegador como confirmação de pagamento.
- Validar notificações/webhooks conforme o mecanismo oficial do provedor.
- Implementar idempotência no processamento de eventos externos.
- Registrar identificadores externos e transições de estado.
- Evitar duplicidade de pedidos, pagamentos ou movimentações de estoque.
- Utilizar transações para operações que precisem ser atômicas.
- Definir explicitamente o comportamento para pagamento pendente, aprovado, recusado, cancelado e estornado, conforme os estados suportados.

Não invente endpoints, campos ou mecanismos de autenticação de provedores. Consulte a documentação oficial quando a integração for implementada.

## 8. Frontend e acessibilidade

- Manter HTML semântico.
- Garantir funcionamento em telas pequenas e grandes.
- Utilizar labels associados aos campos.
- Garantir foco visível e navegação por teclado nos controles interativos.
- Não depender exclusivamente de cor para comunicar estados.
- Validar formulários também no servidor.
- Evitar JavaScript inline quando a estrutura existente permitir separar os arquivos.
- Não inserir dados sensíveis no HTML ou em variáveis JavaScript.

## 9. Configuração e segredos

- Ler configurações sensíveis de variáveis de ambiente.
- Nunca versionar `.env`, tokens, senhas, chaves privadas ou credenciais.
- Manter `.env.example` atualizado com nomes de variáveis e valores fictícios/vazios.
- Não imprimir valores secretos durante diagnóstico.
- Não alterar configurações do servidor ou da VM sem solicitação explícita.

## 10. Processo de trabalho

Para cada tarefa:

1. Resuma o objetivo e identifique os arquivos envolvidos.
2. Inspecione o código relacionado.
3. Apresente dúvidas ou riscos relevantes antes de alterações irreversíveis.
4. Implemente a menor alteração coerente com a tarefa.
5. Preserve compatibilidade com as funcionalidades existentes.
6. Adicione ou atualize testes.
7. Execute verificações aplicáveis.
8. Informe arquivos alterados, decisões técnicas, testes executados e limitações.

Não afirme que testes passaram se não foram executados. Informe claramente qualquer teste que não pôde ser realizado.

## 11. Comandos de validação

Use os comandos disponíveis no projeto. No mínimo, quando aplicável:

```bash
# Verificar sintaxe de um arquivo PHP
php -l caminho/do/arquivo.php

# Instalar dependências conforme o lockfile
composer install

# Executar testes, se houver configuração
composer test
```

Não presuma que `composer test` esteja configurado. Verifique `composer.json` antes de executar ou recomendar scripts.

## 12. Restrições

O agente não deve:

- Executar comandos destrutivos sem autorização.
- Apagar banco de dados, tabelas, uploads ou arquivos persistentes.
- Alterar credenciais, DNS, firewall, serviços ou configurações da VM sem autorização.
- Fazer deploy ou modificar produção sem solicitação explícita.
- Instalar dependências sem necessidade técnica.
- Atualizar versões principais de dependências sem avaliar compatibilidade.
- Criar funcionalidades não solicitadas apenas por parecerem úteis.
- Inventar resultados de testes ou comportamento de APIs.
- Substituir implementações existentes sem avaliar impactos.

## 13. Atualização da documentação

Quando uma alteração mudar arquitetura, configuração, variáveis de ambiente, migrations, comandos ou procedimentos operacionais:

- Atualize o `README.md` ou a documentação específica.
- Atualize `.env.example` quando novas variáveis forem necessárias.
- Documente novas migrations e integrações.
- Mantenha este `AGENTS.md` focado em regras permanentes para agentes; não o transforme em histórico de tarefas.

## 14. Formato da resposta ao concluir

Apresente:

- **Implementado:** resumo objetivo.
- **Arquivos alterados:** lista dos caminhos.
- **Validação:** comandos e testes executados, com resultados reais.
- **Pendências:** riscos, limitações ou etapas não executadas.

Se nenhuma alteração foi feita, explique o motivo.
