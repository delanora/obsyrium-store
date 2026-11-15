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
        var painelDaGaveta = gaveta.querySelector('.cart-drawer__panel');

        var cancelarFechamentoAutomatico = function () {
            if (fechamentoAutomatico) {
                window.clearTimeout(fechamentoAutomatico);
                fechamentoAutomatico = 0;
            }
        };

        var abrirGaveta = function (comFoco) {
            focoAnterior = document.activeElement;
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

            if (focoAnterior && typeof focoAnterior.focus === 'function') {
                focoAnterior.focus();
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

        if (avisoAdicionado && telaGrande.matches) {
            var atalhoDoAviso = avisoAdicionado.querySelector('.cart-toast__link');

            if (atalhoDoAviso) {
                atalhoDoAviso.remove();
            }

            var destinoDoAviso = gaveta.querySelector('.cart-drawer__list, .cart-drawer__empty');

            if (painelDaGaveta && destinoDoAviso) {
                painelDaGaveta.insertBefore(avisoAdicionado, destinoDoAviso);
            }

            abrirGaveta(false);
            agendarFechamento();
        }

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

        document.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && !gaveta.hidden) {
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
