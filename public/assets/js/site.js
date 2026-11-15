/*
 * Menu do celular.
 *
 * O layout funciona sem este arquivo: sem JavaScript o menu aparece em duas
 * linhas, no formato anterior. Aqui só recolhemos esse menu em um botão
 * (hamburger) e cuidamos de foco, Escape e clique fora.
 */

(function () {
    'use strict';

    var header = document.querySelector('.site-header');
    var toggle = document.querySelector('.nav-toggle');

    if (!header || !toggle) {
        return;
    }

    function setOpen(open) {
        header.dataset.menu = open ? 'open' : 'closed';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
    }

    toggle.addEventListener('click', function () {
        setOpen(header.dataset.menu !== 'open');
    });

    // Fecha ao escolher um item, inclusive nas âncoras da própria página.
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
})();
