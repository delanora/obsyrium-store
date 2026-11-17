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
