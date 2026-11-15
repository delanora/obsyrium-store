/*
 * Comportamento do site.
 *
 * Menu do celular (hamburger, com foco, Escape e clique fora), sombra do
 * cabeçalho ao rolar e entrada dos blocos marcados com [data-reveal].
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

    // Marca para o script inline que o comportamento carregou.
    window.__obsyriumReady = true;
})();
