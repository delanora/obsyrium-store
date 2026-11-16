/*
 * Painel: mostra o seletor de subcategoria só quando a categoria está marcada.
 *
 * Sem este script o seletor fica visível o tempo todo e o servidor ignora
 * subcategoria de categoria não marcada — o formulário continua funcionando.
 */
(function () {
    'use strict';

    var alternadores = document.querySelectorAll('[data-category-toggle]');

    if (!alternadores.length) {
        return;
    }

    var atualizar = function (caixa) {
        var bloco = caixa.closest('[data-category]');
        var sub = bloco ? bloco.querySelector('[data-category-sub]') : null;

        if (!sub) {
            return;
        }

        sub.hidden = !caixa.checked;

        if (!caixa.checked) {
            var seletor = sub.querySelector('select');

            if (seletor) {
                seletor.value = '';
            }
        }
    };

    Array.prototype.forEach.call(alternadores, function (caixa) {
        caixa.addEventListener('change', function () {
            atualizar(caixa);
        });

        atualizar(caixa);
    });
})();
