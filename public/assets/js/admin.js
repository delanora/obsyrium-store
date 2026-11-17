/*
 * Painel: os seletores de subcategoria só aparecem quando a categoria está
 * marcada — no produto, por categoria; no cadastro de categoria, pela marca
 * "Subcategoria".
 *
 * Sem este script os seletores ficam visíveis o tempo todo e o servidor ignora
 * o que não foi marcado — o formulário continua funcionando.
 */
(function () {
    'use strict';

    var cada = function (lista, acao) {
        Array.prototype.forEach.call(lista, acao);
    };

    // Produto: mostra a subcategoria de cada categoria marcada.
    cada(document.querySelectorAll('[data-category-toggle]'), function (caixa) {
        var bloco = caixa.closest('[data-category]');
        var sub = bloco ? bloco.querySelector('[data-category-sub]') : null;

        if (!sub) {
            return;
        }

        var atualizar = function () {
            sub.hidden = !caixa.checked;

            if (!caixa.checked) {
                var seletor = sub.querySelector('select');

                if (seletor) {
                    seletor.value = '';
                }
            }
        };

        caixa.addEventListener('change', atualizar);
        atualizar();
    });

    // Categoria: a marca de subcategoria abre o "Subcategoria de".
    cada(document.querySelectorAll('[data-subcategory-toggle]'), function (marca) {
        var campo = document.querySelector('[data-subcategory-field]');

        if (!campo) {
            return;
        }

        var atualizar = function () {
            campo.hidden = !marca.checked;
        };

        marca.addEventListener('change', atualizar);
        atualizar();
    });

    // Cor do produto: o que foi digitado e ainda não estava na lista passa a ser
    // sugerido nela, sem sair do campo e sem recarregar a página.
    cada(document.querySelectorAll('[data-color-input]'), function (campo) {
        var lista = document.getElementById(campo.getAttribute('list') || '');

        if (!lista) {
            return;
        }

        campo.addEventListener('change', function () {
            var valor = campo.value.trim();
            var existe = false;

            if (valor === '') {
                return;
            }

            cada(lista.options, function (opcao) {
                if (opcao.value.toLowerCase() === valor.toLowerCase()) {
                    existe = true;
                }
            });

            if (!existe) {
                var nova = document.createElement('option');
                nova.value = valor;
                lista.appendChild(nova);
            }
        });
    });
})();
