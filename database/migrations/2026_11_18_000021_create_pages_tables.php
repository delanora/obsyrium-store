<?php

declare(strict_types=1);

/*
 * Páginas institucionais editáveis no painel.
 *
 * `pages` guarda o cabeçalho de cada página (slug, linha de cima, título,
 * introdução e a nota do fim) e `page_blocks` guarda os blocos na ordem em que
 * aparecem. O texto de cada bloco é texto puro: linha em branco separa
 * parágrafos, linha começando com "- " vira item de lista e `[rótulo](/caminho)`
 * vira link interno. A renderização é feita no `PageService`.
 *
 * A migration traz o conteúdo que estava nas views, para a loja não perder o
 * texto no dia em que ele passou a morar no banco.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE pages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(100) NOT NULL,
            eyebrow VARCHAR(60) NOT NULL DEFAULT '',
            title VARCHAR(140) NOT NULL,
            intro VARCHAR(400) NOT NULL DEFAULT '',
            note VARCHAR(500) NULL,
            layout VARCHAR(20) NOT NULL DEFAULT 'blocks',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY pages_slug_unique (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE page_blocks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            page_id INT UNSIGNED NOT NULL,
            position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            heading VARCHAR(160) NOT NULL DEFAULT '',
            body TEXT NULL,
            link_label VARCHAR(80) NOT NULL DEFAULT '',
            link_href VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_blocks_page_index (page_id, position),
            CONSTRAINT page_blocks_page_foreign FOREIGN KEY (page_id)
                REFERENCES pages (id) ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $paginas = [
        [
            'slug' => 'sobre',
            'eyebrow' => 'A marca',
            'title' => 'Sobre a Obsyrium',
            'intro' => 'Camisetas de estampa autoral, feitas com atenção a cada detalhe e conferidas antes de sair daqui.',
            'note' => null,
            'layout' => 'blocks',
            'blocks' => [
                [
                    'heading' => 'O que fazemos',
                    'body' => 'Camisetas com estampas próprias, em modelagem reta e tecido confortável para o dia a dia. Cada modelo é produzido com cuidado e revisado antes de entrar na loja — o que está no site é o que existe de verdade.',
                ],
                [
                    'heading' => 'Como trabalhamos',
                    'body' => 'O estoque é atualizado a cada venda: quando um tamanho acaba, ele sai do seletor da página do produto — nada de vender o que não está disponível. Assim o seletor mostra sempre a disponibilidade real de cada peça.',
                ],
                [
                    'heading' => 'Compra e entrega',
                    'body' => 'Você monta o carrinho sem precisar de conta; o login só é pedido na hora de finalizar. O pedido fica registrado em Minha conta com os itens, o endereço de entrega e a situação da compra, do fechamento até a entrega.',
                    'link_label' => 'Ver minhas compras',
                    'link_href' => '/conta/compras',
                ],
                [
                    'heading' => 'Cuidado com a estampa',
                    'body' => 'Lave a camiseta do avesso, em água fria, com sabão neutro. Não use alvejante e não passe o ferro em cima da estampa: seque à sombra e passe pelo lado de dentro. Assim a cor dura muito mais.',
                ],
            ],
        ],
        [
            'slug' => 'perguntas-frequentes',
            'eyebrow' => 'Atendimento',
            'title' => 'Perguntas frequentes',
            'intro' => 'As dúvidas que mais chegam por aqui: pedido, conta, tamanho, pagamento, entrega e troca. Se a sua não estiver na lista, fale com a loja.',
            'note' => null,
            'layout' => 'faq',
            'blocks' => [
                [
                    'heading' => 'Como faço um pedido?',
                    'body' => "Escolha a camiseta no catálogo, selecione o tamanho disponível e adicione ao carrinho. No fechamento você entra na conta (ou cria uma em segundos), confere o endereço de entrega e conclui o pedido.\n\nDepois disso, a compra aparece em Minha conta → Compras, com os itens, o endereço e a situação do pedido.",
                    'link_label' => 'Ver o catálogo',
                    'link_href' => '/catalogo',
                ],
                [
                    'heading' => 'Preciso de conta para comprar?',
                    'body' => 'Não. O carrinho funciona sem login e você pode montá-lo com calma. A conta só é pedida na hora de finalizar, porque é nela que o pedido e o endereço de entrega ficam guardados.',
                    'link_label' => 'Entrar ou criar conta',
                    'link_href' => '/login',
                ],
                [
                    'heading' => 'Como sei se o tamanho está disponível?',
                    'body' => 'O estoque é atualizado a cada venda. Quando um tamanho acaba, ele sai do seletor da página do produto — não vendemos o que não está disponível.',
                ],
                [
                    'heading' => 'Quais são as formas de pagamento?',
                    'body' => 'Pix, cartão de crédito e boleto são os meios previstos. O pagamento entra no ar junto com a abertura da loja, e cada meio é confirmado na página de formas de pagamento.',
                    'link_label' => 'Ver formas de pagamento',
                    'link_href' => '/pagamento',
                ],
                [
                    'heading' => 'Qual é o prazo de entrega?',
                    'body' => 'Cada pedido é separado e conferido antes de sair. O prazo depende do endereço e da transportadora, e é confirmado antes do envio.',
                    'link_label' => 'Ver política de entrega',
                    'link_href' => '/entrega',
                ],
                [
                    'heading' => 'Posso trocar ou devolver a peça?',
                    'body' => "Sim. Compras pela internet podem ser desfeitas em até 7 dias corridos depois do recebimento, sem precisar justificar. Peça com defeito também é trocada.\n\nBasta falar com a loja pelo formulário de contato, informando o número do pedido. A peça volta sem uso e com a etiqueta.",
                    'link_label' => 'Falar com a loja',
                    'link_href' => '/contato',
                ],
                [
                    'heading' => 'Como cuido da estampa?',
                    'body' => 'Lave do avesso, em água fria, com sabão neutro. Nada de alvejante e nada de ferro em cima da estampa: seque à sombra e passe pelo lado de dentro.',
                ],
                [
                    'heading' => 'Como acompanho o meu pedido?',
                    'body' => 'Em Minha conta → Compras você vê a situação de cada pedido, do fechamento até a entrega, com os itens e o endereço escolhido.',
                    'link_label' => 'Ver minhas compras',
                    'link_href' => '/conta/compras',
                ],
                [
                    'heading' => 'Meus dados ficam seguros?',
                    'body' => 'Sim. A senha é guardada só como hash, e CPF, telefone e endereço ficam cifrados no banco. O site inteiro trafega por HTTPS quando publicado.',
                    'link_label' => 'Ver política de privacidade',
                    'link_href' => '/privacidade',
                ],
                [
                    'heading' => 'Como falo com a loja?',
                    'body' => 'Pelo formulário da página de contato, pelo WhatsApp ou pelo Instagram. Atendemos de segunda a sexta, das 9h às 18h.',
                    'link_label' => 'Abrir o contato',
                    'link_href' => '/contato',
                ],
            ],
        ],
        [
            'slug' => 'entrega',
            'eyebrow' => 'Políticas',
            'title' => 'Política de entrega',
            'intro' => 'Camisetas enviadas para todo o Brasil, com cada pedido separado e conferido individualmente. Aqui está como funciona do fechamento do pedido até a sua casa.',
            'note' => 'Enquanto o cálculo de frete não está no checkout, valor e prazo são combinados pelo atendimento antes do envio. Esta página é atualizada quando essa etapa entrar no ar.',
            'layout' => 'blocks',
            'blocks' => [
                [
                    'heading' => 'Separação',
                    'body' => 'Cada pedido é conferido antes de ser embalado: modelo, tamanho, estampa e endereço. O que você comprou é reservado no estoque assim que o pedido é confirmado.',
                ],
                [
                    'heading' => 'Frete e prazo',
                    'body' => 'O valor do frete e o prazo dependem do CEP de entrega e da transportadora. O cálculo entra no fechamento do pedido quando essa etapa for publicada; até lá, valor e prazo são confirmados pelo atendimento antes do envio — nada é cobrado sem essa confirmação.',
                ],
                [
                    'heading' => 'Envio e rastreio',
                    'body' => 'Depois de postado, o pedido recebe um código de rastreio, que é enviado pelo canal de atendimento e fica registrado junto do pedido em Minha conta → Compras.',
                ],
                [
                    'heading' => 'Endereço e ausência',
                    'body' => 'Confira o endereço antes de concluir: ele não pode ser alterado depois do envio. Se ninguém estiver no local para receber, a transportadora deixa o aviso de tentativa e o pedido volta para a agência; pedidos devolvidos por endereço incorreto ou por ausência podem ter novo envio cobrado.',
                ],
                [
                    'heading' => 'Atraso, avaria ou extravio',
                    'body' => 'Se o rastreio parar de andar ou a embalagem chegar danificada, fale com a loja pelo formulário de contato com o número do pedido. Reclamamos junto à transportadora e, confirmado o extravio, você escolhe entre o reenvio e a devolução do valor pago.',
                ],
                [
                    'heading' => 'Prazo de postagem',
                    'body' => 'A postagem acontece depois da confirmação do pagamento. O prazo que vale é o que aparece no fechamento do pedido, contado a partir da postagem — não do dia da compra.',
                ],
            ],
        ],
        [
            'slug' => 'pagamento',
            'eyebrow' => 'Pagamento',
            'title' => 'Formas de pagamento',
            'intro' => 'Pix, cartão e boleto são os meios previstos para a loja. Todos os preços do site são em reais e já incluem impostos — nenhuma taxa é somada no fechamento.',
            'note' => 'A loja ainda não está processando pagamentos: os meios acima são os previstos e cada um é confirmado nesta página quando entrar no ar. Nada é cobrado antes disso.',
            'layout' => 'blocks',
            'blocks' => [
                [
                    'heading' => 'Pix',
                    'body' => 'Pagamento à vista, com confirmação rápida. O pedido é separado assim que o valor cai.',
                ],
                [
                    'heading' => 'Cartão de crédito',
                    'body' => 'Visa, Mastercard, Elo e Hipercard, com parcelamento informado no checkout no momento da compra.',
                ],
                [
                    'heading' => 'Boleto bancário',
                    'body' => 'Emitido no fechamento do pedido, com prazo de vencimento mostrado antes da confirmação.',
                ],
                [
                    'heading' => 'Confirmação do pedido',
                    'body' => 'O pedido só é separado e enviado depois que o pagamento é confirmado. Pix e cartão confirmam na hora; boleto pode levar até dois dias úteis depois do pagamento, prazo do próprio banco.',
                ],
                [
                    'heading' => 'Segurança',
                    'body' => 'Os dados do cartão são digitados no ambiente do meio de pagamento e não passam pelo servidor da loja: a Obsyrium não guarda número de cartão nem código de segurança. CPF, telefone e endereço ficam cifrados no banco.',
                ],
                [
                    'heading' => 'Preços e disponibilidade',
                    'body' => 'Os preços valem enquanto durarem os estoques. Se um pedido for feito com erro evidente de preço ou com peça que acabou de esgotar, a loja avisa pelo canal de atendimento e devolve integralmente o valor pago, sem cobrar nada pelo cancelamento.',
                ],
            ],
        ],
        [
            'slug' => 'termos',
            'eyebrow' => 'Políticas',
            'title' => 'Termos de uso',
            'intro' => 'Estas condições valem para o uso do site e para as compras feitas na Obsyrium Store. Ao navegar ou comprar, você concorda com elas.',
            'note' => 'Antes da abertura da loja este texto passa por revisão, quando entram os dados da empresa e as condições comerciais definitivas. Dúvidas sobre qualquer cláusula podem ser enviadas pelo formulário de contato.',
            'layout' => 'blocks',
            'blocks' => [
                [
                    'heading' => 'Quem somos',
                    'body' => 'A Obsyrium Store é uma marca de camisetas de estampa autoral, com estoque atualizado no site. Os dados da empresa (razão social, CNPJ e endereço) são publicados nesta página antes da abertura da loja.',
                ],
                [
                    'heading' => 'Cadastro',
                    'body' => "Para comprar é preciso uma conta com nome, CPF, telefone, e-mail e senha. Os dados precisam ser verdadeiros e completos, porque é com eles que o pedido é emitido e entregue.\n\nA conta é pessoal: quem a usa responde pelas compras feitas nela. A senha é guardada só como hash e não é visível para a loja.",
                ],
                [
                    'heading' => 'Pedidos e preços',
                    'body' => "Os preços são em reais e valem enquanto durarem os estoques. O pedido só é considerado confirmado depois da confirmação do pagamento.\n\nEm caso de erro evidente de preço, de peça esgotada ou de suspeita de fraude, a loja pode cancelar o pedido e devolve integralmente o valor pago, avisando pelo canal de atendimento.",
                ],
                [
                    'heading' => 'Pagamento e entrega',
                    'body' => 'Os meios previstos e a confirmação de cada um estão na [página de formas de pagamento](/pagamento). Prazos, frete e rastreio estão na [política de entrega](/entrega).',
                ],
                [
                    'heading' => 'Troca e devolução',
                    'body' => "Compras feitas pela internet podem ser desfeitas em até 7 dias corridos depois do recebimento, sem justificativa, conforme o artigo 49 do Código de Defesa do Consumidor. O valor é devolvido pelo mesmo meio de pagamento.\n\nPeça com defeito de fabricação é trocada ou devolvida em até 30 dias, também sem custo. Para os dois casos, a peça volta sem uso e com a etiqueta, e o pedido é aberto pelo formulário de contato com o número da compra.",
                ],
                [
                    'heading' => 'Uso do site',
                    'body' => 'Nome, marca, textos, fotos e estampas são da Obsyrium Store e não podem ser usados comercialmente sem autorização escrita. É proibido tentar burlar a segurança do site, automatizar compras em massa ou usar os dados de outras pessoas.',
                ],
                [
                    'heading' => 'Responsabilidade',
                    'body' => 'A loja responde por vício do produto e por dano que causar conforme o Código de Defesa do Consumidor. O site pode ficar fora do ar por manutenção ou falha técnica; nesses casos, nenhum pedido já confirmado é perdido.',
                ],
                [
                    'heading' => 'Lei e foro',
                    'body' => 'Estas condições seguem a legislação brasileira. Fica eleito o foro do domicílio do consumidor para resolver qualquer questão que não se resolva pelo atendimento.',
                ],
            ],
        ],
        [
            'slug' => 'privacidade',
            'eyebrow' => 'Políticas',
            'title' => 'Política de privacidade',
            'intro' => 'Como a Obsyrium Store trata os seus dados pessoais, conforme a Lei Geral de Proteção de Dados (Lei 13.709/2018).',
            'note' => 'Revisão de 17 de novembro de 2026. O contato do controlador e do encarregado de dados é publicado aqui antes da abertura da loja; até lá, pedidos sobre dados pessoais são recebidos pelo formulário de contato.',
            'layout' => 'blocks',
            'blocks' => [
                [
                    'heading' => 'Dados que coletamos',
                    'body' => "- Cadastro: nome, e-mail, senha, CPF e telefone.\n- Endereço de entrega: destinatário, rua, número, complemento, bairro, cidade, UF e CEP.\n- Pedidos: itens, valores, situação e datas.\n- Contato: nome, e-mail, telefone e o texto da mensagem enviada pelo formulário.\n- Técnicos: cookie de sessão (login e carrinho) e o endereço IP, guardado apenas como hash, usado no limite de envios do contato e no bloqueio de tentativas de login.",
                ],
                [
                    'heading' => 'Para que usamos',
                    'body' => "- Concluir a compra, separar e entregar o pedido.\n- Responder mensagens e resolver trocas, devoluções e dúvidas.\n- Cumprir obrigações fiscais e legais ligadas à venda.\n- Proteger a loja e quem compra: evitar spam, fraude e acesso indevido ao painel.",
                ],
                [
                    'heading' => 'Base legal',
                    'body' => 'Tratamos os dados para executar o contrato de compra, cumprir obrigação legal ou regulatória e atender interesses legítimos de segurança da loja. Quando a lei exigir consentimento, ele é pedido de forma clara e pode ser retirado a qualquer momento.',
                ],
                [
                    'heading' => 'Com quem compartilhamos',
                    'body' => "Com a transportadora, para entregar o pedido (nome, endereço e telefone); com o meio de pagamento, quando ele entrar no ar, para processar a cobrança; e com os provedores de infraestrutura que hospedam o site e o banco de dados.\n\nNão vendemos nem cedemos dados pessoais para publicidade.",
                ],
                [
                    'heading' => 'Como protegemos',
                    'body' => 'A senha é guardada apenas como hash com Argon2id. CPF, telefone e dados de endereço ficam cifrados no banco com AES-256-GCM, e o CPF usa um índice cego para conferir duplicidade sem guardá-lo em texto puro. O acesso ao painel é restrito às contas administrativas.',
                ],
                [
                    'heading' => 'Por quanto tempo guardamos',
                    'body' => 'Os dados da conta ficam enquanto ela existir. Registros de pedidos são mantidos pelos prazos fiscais e legais, mesmo depois de uma exclusão, e nesse caso ficam sem os dados de contato do cliente. Mensagens do formulário são apagadas quando o assunto é resolvido.',
                ],
                [
                    'heading' => 'Seus direitos',
                    'body' => 'Você pode pedir confirmação, acesso, correção, portabilidade, anonimização, bloqueio ou exclusão dos seus dados, além de informações sobre com quem eles foram compartilhados. O pedido é feito pelo formulário de contato, com o e-mail usado na conta, e é respondido em até 15 dias.',
                    'link_label' => 'Fazer um pedido sobre meus dados',
                    'link_href' => '/contato',
                ],
                [
                    'heading' => 'Cookies',
                    'body' => 'O site usa apenas o cookie de sessão, que mantém o carrinho e o login funcionando enquanto você navega. Não há cookie de publicidade nem rastreamento de terceiros. Sem ele, o carrinho e a conta não funcionam.',
                ],
                [
                    'heading' => 'Mudanças nesta política',
                    'body' => 'Se o tratamento de dados mudar, esta página é atualizada e a data de revisão é trocada. Mudanças que afetem direitos são avisadas também no site.',
                ],
            ],
        ],
    ];

    $inserirPagina = $pdo->prepare(
        'INSERT INTO pages (slug, eyebrow, title, intro, note, layout)
         VALUES (:slug, :eyebrow, :title, :intro, :note, :layout)'
    );

    $inserirBloco = $pdo->prepare(
        'INSERT INTO page_blocks (page_id, position, heading, body, link_label, link_href)
         VALUES (:page, :position, :heading, :body, :link_label, :link_href)'
    );

    foreach ($paginas as $pagina) {
        $inserirPagina->execute([
            ':slug' => $pagina['slug'],
            ':eyebrow' => $pagina['eyebrow'],
            ':title' => $pagina['title'],
            ':intro' => $pagina['intro'],
            ':note' => $pagina['note'],
            ':layout' => $pagina['layout'],
        ]);

        $paginaId = (int) $pdo->lastInsertId();

        foreach ($pagina['blocks'] as $posicao => $bloco) {
            $inserirBloco->execute([
                ':page' => $paginaId,
                ':position' => $posicao,
                ':heading' => $bloco['heading'],
                ':body' => $bloco['body'],
                ':link_label' => $bloco['link_label'] ?? '',
                ':link_href' => $bloco['link_href'] ?? '',
            ]);
        }
    }
};
