/*
 * Comportamento do site.
 *
 * Menu do celular (hamburger, com foco, Escape e clique fora), gaveta do
 * carrinho no computador, sombra do cabeçalho ao rolar e entrada dos blocos
 * marcados com [data-reveal].
 * Sem este arquivo o site continua inteiro: o menu fica em duas linhas e
 * nada é escondido, porque os estados iniciais dependem de html.js.
 */

(function () {
    'use strict';

    var header = document.querySelector('.site-header');
    var toggle = document.querySelector('.nav-toggle');

    if (header && toggle) {
        var setOpen = function (open) {
            header.dataset.menu = open ? 'open' : 'closed';
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
        };

        toggle.addEventListener('click', function () {
            setOpen(header.dataset.menu !== 'open');
        });

        // Fecha ao escolher um item, inclusive nas âncoras da página.
        header.addEventListener('click', function (evento) {
            if (evento.target.closest('.site-nav__link')) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && header.dataset.menu === 'open') {
                setOpen(false);
                toggle.focus();
            }
        });

        document.addEventListener('click', function (evento) {
            if (header.dataset.menu === 'open' && !header.contains(evento.target)) {
                setOpen(false);
            }
        });

        setOpen(false);
    }

    // Cabeçalho com sombra depois de sair do topo.
    if (header) {
        var rolou = false;

        var aoRolar = function () {
            var atual = window.scrollY > 8;

            if (atual !== rolou) {
                rolou = atual;
                header.classList.toggle('is-scrolled', atual);
            }
        };

        window.addEventListener('scroll', aoRolar, { passive: true });
        aoRolar();
    }

    // Entrada dos blocos quando aparecem na tela.
    var blocos = document.querySelectorAll('[data-reveal], [data-reveal-stagger]');

    if (blocos.length) {
        if ('IntersectionObserver' in window) {
            var observador = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (entrada) {
                    if (entrada.isIntersecting) {
                        entrada.target.classList.add('is-visible');
                        observador.unobserve(entrada.target);
                    }
                });
            }, { rootMargin: '0px 0px -6% 0px', threshold: 0.08 });

            blocos.forEach(function (bloco) {
                observador.observe(bloco);
            });
        } else {
            blocos.forEach(function (bloco) {
                bloco.classList.add('is-visible');
            });
        }
    }

    // Gaveta do carrinho: no computador o ícone do cabeçalho abre a lateral com
    // os itens; no celular o mesmo link segue para /carrinho. Sem JavaScript a
    // gaveta nunca aparece e o link funciona como sempre.
    var gaveta = document.querySelector('[data-cart-drawer]');
    var abridor = document.querySelector('[data-cart-open]');

    if (gaveta && abridor) {
        var telaGrande = window.matchMedia('(min-width: 64rem)');
        var focoAnterior = null;
        var fechamentoAutomatico = 0;
        var aberturaEm = 0;
        var painelDaGaveta = gaveta.querySelector('.cart-drawer__panel');

        var cancelarFechamentoAutomatico = function () {
            if (fechamentoAutomatico) {
                window.clearTimeout(fechamentoAutomatico);
                fechamentoAutomatico = 0;
            }
        };

        var abrirGaveta = function (comFoco) {
            focoAnterior = document.activeElement;
            aberturaEm = Date.now();
            gaveta.hidden = false;
            document.body.classList.add('cart-drawer-open');
            abridor.setAttribute('aria-expanded', 'true');

            var fechar = gaveta.querySelector('.cart-drawer__close');

            if (fechar && comFoco !== false) {
                fechar.focus();
            }
        };

        var fecharGaveta = function () {
            cancelarFechamentoAutomatico();
            gaveta.hidden = true;
            gaveta.classList.remove('is-collapsing');
            document.body.classList.remove('cart-drawer-open');
            abridor.setAttribute('aria-expanded', 'false');

            // preventScroll: o ícone do carrinho fica no topo do documento e
            // focá-lo rolava a página para lá, então o site "subia" no fim do
            // fechamento. O foco volta sem mexer na rolagem.
            if (focoAnterior && focoAnterior !== document.body && document.contains(focoAnterior)) {
                focoAnterior.focus({preventScroll: true});
            }
        };

        // Fecha encolhendo na direção do ícone do carrinho e pisca o ícone no
        // fim, para ficar claro que é ali que a gaveta abre de novo.
        var fecharComAnimacao = function () {
            if (gaveta.hidden || gaveta.classList.contains('is-collapsing')) {
                return;
            }

            var concluido = false;

            var concluir = function () {
                if (concluido) {
                    return;
                }

                concluido = true;
                fecharGaveta();

                abridor.classList.add('cart-pulse');

                window.setTimeout(function () {
                    abridor.classList.remove('cart-pulse');
                }, 700);
            };

            gaveta.classList.add('is-collapsing');

            if (painelDaGaveta) {
                painelDaGaveta.addEventListener('animationend', function (evento) {
                    if (evento.animationName === 'cart-drawer-collapse') {
                        concluir();
                    }
                });
            }

            // Rede de segurança: se a animação não rodar (movimento reduzido ou
            // aba em segundo plano), fecha do mesmo jeito.
            window.setTimeout(concluir, 420);
        };

        var agendarFechamento = function () {
            cancelarFechamentoAutomatico();
            fechamentoAutomatico = window.setTimeout(fecharComAnimacao, 3000);
        };

        // Item recém-adicionado: no computador a gaveta abre sozinha e o aviso
        // passa para dentro dela. O atalho "Ver carrinho" sai, porque a lista
        // já está à vista. Depois de 3 segundos ela se recolhe sozinha.
        var avisoAdicionado = document.querySelector('[data-toast]');

        // Na página do carrinho o aviso fica só no cabeçalho: a lista já está à
        // vista, então abrir a gaveta por cima dela seria redundante.
        var paginaDoCarrinho = window.location.pathname.replace(/\/+$/, '') === '/carrinho';

        if (avisoAdicionado && telaGrande.matches) {
            var atalhoDoAviso = avisoAdicionado.querySelector('.cart-toast__link');

            if (atalhoDoAviso) {
                atalhoDoAviso.remove();
            }

            if (!paginaDoCarrinho) {
                var destinoDoAviso = gaveta.querySelector('.cart-drawer__list, .cart-drawer__empty');

                if (painelDaGaveta && destinoDoAviso) {
                    painelDaGaveta.insertBefore(avisoAdicionado, destinoDoAviso);
                }

                abrirGaveta(false);
                agendarFechamento();
            }
        }

        // Remover pelo painel não recarrega a página: o próprio item vira o
        // aviso dourado, some depois de meio segundo e os de baixo sobem. Os
        // números do cabeçalho e do rodapé vêm da resposta do servidor, então
        // continuam certos sem refresh.
        var atualizarSinais = function (doc) {
            var contadorAtual = document.querySelector('.icon-button__count');
            var contadorNovo = doc.querySelector('.icon-button__count');

            if (contadorAtual && contadorNovo) {
                contadorAtual.textContent = contadorNovo.textContent;
            } else if (contadorAtual && !contadorNovo) {
                contadorAtual.remove();
            }

            var abridorNovo = doc.querySelector('[data-cart-open]');
            var rotuloNovo = abridorNovo ? abridorNovo.getAttribute('aria-label') : null;

            if (rotuloNovo) {
                abridor.setAttribute('aria-label', rotuloNovo);
            }

            // O rodapé inteiro (total e quantidade de itens) vem da resposta.
            var rodapeAtual = gaveta.querySelector('.cart-drawer__footer');
            var rodapeNovo = doc.querySelector('.cart-drawer__footer');

            if (rodapeAtual && rodapeNovo) {
                rodapeAtual.replaceWith(document.importNode(rodapeNovo, true));
            }
        };

        var mostrarCarrinhoVazio = function (doc) {
            var vazio = doc.querySelector('.cart-drawer__empty');
            var lista = gaveta.querySelector('.cart-drawer__list');
            var rodape = gaveta.querySelector('.cart-drawer__footer');

            if (!vazio || !lista) {
                return;
            }

            if (rodape) {
                rodape.remove();
            }

            lista.replaceWith(document.importNode(vazio, true));
        };

        var sumirComItem = function (item, doc) {
            item.style.height = item.offsetHeight + 'px';

            // Força o navegador a registrar a altura antes de animá-la a zero.
            void item.offsetHeight;

            item.classList.add('is-removendo');
            item.style.height = '0px';

            window.setTimeout(function () {
                item.remove();
                mostrarCarrinhoVazio(doc);
            }, 260);
        };

        // Na /carrinho a lista da própria página também precisa acompanhar, então
        // ela é trocada pelo que veio na resposta — sem recarregar nada.
        var atualizarPaginaDoCarrinho = function (doc) {
            var paginaAtual = document.querySelector('.cart-page');
            var paginaNova = doc.querySelector('.cart-page');

            if (paginaAtual && paginaNova) {
                paginaAtual.replaceWith(document.importNode(paginaNova, true));
            }
        };

        gaveta.addEventListener('submit', function (evento) {
            var formulario = evento.target;

            if (!formulario || !formulario.closest) {
                return;
            }

            var item = formulario.closest('.cart-drawer__item');

            if (!item || item.classList.contains('is-removendo') || item.classList.contains('cart-drawer__item--aviso')) {
                return;
            }

            if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function' || typeof window.DOMParser !== 'function') {
                return;
            }

            evento.preventDefault();

            var botao = formulario.querySelector('button');

            if (botao) {
                botao.disabled = true;
            }

            fetch(formulario.action, {
                method: 'POST',
                body: new FormData(formulario),
                credentials: 'same-origin',
                redirect: 'follow'
            }).then(function (resposta) {
                if (!resposta.ok) {
                    throw new Error('remocao falhou');
                }

                return resposta.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');

                item.classList.add('cart-drawer__item--aviso');
                item.innerHTML = '<p class="cart-drawer__item-aviso">Item removido do carrinho :/</p>';

                atualizarSinais(doc);
                atualizarPaginaDoCarrinho(doc);

                window.setTimeout(function () {
                    sumirComItem(item, doc);
                }, 500);
            }).catch(function () {
                // Sem rede ou resposta ruim: manda o formulário do jeito tradicional.
                formulario.submit();
            });
        });

        abridor.addEventListener('click', function (evento) {
            if (!telaGrande.matches) {
                return;
            }

            evento.preventDefault();
            cancelarFechamentoAutomatico();

            if (gaveta.hidden) {
                abrirGaveta();
            } else {
                fecharComAnimacao();
            }
        });

        gaveta.addEventListener('click', function (evento) {
            if (evento.target.closest('[data-cart-close]')) {
                fecharComAnimacao();
            }
        });

        // Se o cliente começa a mexer na gaveta, o fechamento automático espera.
        // A escuta é só no painel: o container cobre a tela inteira e o
        // navegador dispara mouseenter nele mesmo com o cursor parado, o que
        // cancelava o fechamento sem o cliente ter feito nada.
        if (painelDaGaveta) {
            ['pointerdown', 'keydown', 'focusin'].forEach(function (nome) {
                painelDaGaveta.addEventListener(nome, cancelarFechamentoAutomatico);
            });
        }

        // Rolar a página com a gaveta aberta é permitido, e é esse gesto que
        // recolhe a gaveta na direção do ícone: o cliente voltou a navegar. A
        // rolagem dentro do painel não mexe na página (overscroll-behavior no
        // cart.css) e por isso não fecha nada.
        window.addEventListener('scroll', function () {
            if (gaveta.hidden || gaveta.classList.contains('is-collapsing')) {
                return;
            }

            // A posição que o navegador restaura ao carregar a página não é
            // intenção do cliente.
            if (Date.now() - aberturaEm < 300) {
                return;
            }

            fecharComAnimacao();
        }, {passive: true});

        document.addEventListener('keydown', function (evento) {
            if (!gaveta.hidden && evento.key === 'Escape') {
                fecharComAnimacao();
            }
        });
    }
    // Aviso de item adicionado ao carrinho: fecha sozinho depois de alguns
    // segundos e também pelo botão, sem depender de recarregar a página.
    var avisoDoCarrinho = document.querySelector('[data-toast]');

    if (avisoDoCarrinho) {
        var fecharAviso = function () {
            avisoDoCarrinho.remove();
        };

        var botaoAviso = avisoDoCarrinho.querySelector('[data-toast-close]');

        if (botaoAviso) {
            botaoAviso.addEventListener('click', fecharAviso);
        }

        window.setTimeout(fecharAviso, 6000);
    }
    // Marca para o script inline que o comportamento carregou.
    window.__obsyriumReady = true;
})();
