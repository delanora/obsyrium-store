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

    // Fotos: o bloco de envio aceita a imagem arrastada do computador. O arquivo
    // solto entra no mesmo <input type="file">, então o envio continua sendo o do
    // formulário — sem um segundo caminho no servidor e sem JavaScript obrigatório.
    cada(document.querySelectorAll('.admin-upload'), function (bloco) {
        var campo = bloco.querySelector('input[type="file"]');

        if (!campo || typeof DataTransfer !== 'function') {
            return;
        }

        var marcar = function (ligado) {
            bloco.classList.toggle('is-dragover', ligado);
        };

        bloco.addEventListener('dragover', function (evento) {
            evento.preventDefault();
            marcar(true);
        });

        bloco.addEventListener('dragleave', function (evento) {
            if (!bloco.contains(evento.relatedTarget)) {
                marcar(false);
            }
        });

        bloco.addEventListener('drop', function (evento) {
            evento.preventDefault();
            marcar(false);

            var arquivos = Array.prototype.filter.call(evento.dataTransfer.files, function (arquivo) {
                return arquivo.type.indexOf('image/') === 0;
            });

            if (arquivos.length === 0) {
                return;
            }

            if (!campo.multiple) {
                arquivos = arquivos.slice(0, 1);
            }

            var sacola = new DataTransfer();

            cada(arquivos, function (arquivo) {
                sacola.items.add(arquivo);
            });

            campo.files = sacola.files;
            campo.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // Cor: "+ adicionar nova cor" abre o campo da cor nova e o esconde de novo
    // quando uma cor da lista é escolhida. Sem este script o campo fica sempre
    // visível e o servidor usa o que estiver preenchido.
    cada(document.querySelectorAll('[data-color-field]'), function (campo) {
        var seletor = campo.querySelector('[data-color-select]');
        var bloco = campo.querySelector('[data-color-new]');
        var entrada = campo.querySelector('[data-color-new-input]');

        if (!seletor || !bloco || !entrada) {
            return;
        }

        // O × apaga a cor escolhida. Sem JavaScript ele continua enviando o
        // formulário; aqui só pede confirmação e some quando não há cor.
        var apagar = campo.querySelector('[data-color-delete]');

        var atualizar = function () {
            bloco.hidden = seletor.value !== campo.getAttribute('data-color-field');

            if (apagar) {
                apagar.disabled = seletor.value === ''
                    || seletor.value === campo.getAttribute('data-color-field');
            }
        };

        seletor.addEventListener('change', function () {
            atualizar();

            if (!bloco.hidden) {
                entrada.focus();
            }
        });

        if (apagar) {
            apagar.addEventListener('click', function (evento) {
                var aviso = 'Apagar a cor "' + seletor.value + '"? Ela sai da lista do seletor.';

                if (!window.confirm(aviso)) {
                    evento.preventDefault();
                }
            });
        }

        atualizar();
    });
})();
