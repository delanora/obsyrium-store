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

    // Cor: o próprio campo é o drop down — ao focar, mostra a lista das cores
    // dentro dele. Cada linha preenche o campo e o × da linha apaga aquela cor.
    // Digitar um nome que não está na lista já vale como cor nova: nada de
    // campo separado. Sem este script o campo continua sendo um texto comum.
    cada(document.querySelectorAll('[data-color-field]'), function (campo) {
        var entrada = campo.querySelector('[data-color-input]');
        var painel = campo.querySelector('[data-color-panel]');
        var botao = campo.querySelector('[data-color-toggle]');
        var vazio = campo.querySelector('[data-color-empty]');

        if (!entrada || !painel) {
            return;
        }

        var opcoes = painel.querySelectorAll('[data-color-pick]');

        var abrir = function (aberto) {
            painel.hidden = !aberto;
            entrada.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        };

        var filtrar = function () {
            var termo = entrada.value.trim().toLowerCase();
            var achou = 0;

            cada(opcoes, function (opcao) {
                var nome = opcao.getAttribute('data-color-pick').toLowerCase();
                var mostra = termo === '' || nome.indexOf(termo) !== -1;

                opcao.parentNode.hidden = !mostra;
                achou += mostra ? 1 : 0;
            });

            if (vazio) {
                vazio.hidden = achou > 0;
            }
        };

        entrada.addEventListener('focus', function () {
            filtrar();
            abrir(true);
        });

        entrada.addEventListener('input', function () {
            filtrar();
            abrir(true);
        });

        if (botao) {
            botao.addEventListener('click', function () {
                filtrar();
                abrir(painel.hidden);

                if (!painel.hidden) {
                    entrada.focus();
                }
            });
        }

        cada(opcoes, function (opcao) {
            opcao.addEventListener('click', function () {
                entrada.value = opcao.getAttribute('data-color-pick');

                // Focar abre a lista; fechar vem depois para ela não reabrir.
                entrada.focus();
                abrir(false);
            });
        });

        // O × apaga a cor da linha. Sem JavaScript ele já envia o formulário;
        // aqui só pede confirmação.
        cada(campo.querySelectorAll('[data-color-remove]'), function (remover) {
            remover.addEventListener('click', function (evento) {
                var aviso = 'Apagar a cor "' + remover.value + '"? Ela sai da lista do seletor.';

                if (!window.confirm(aviso)) {
                    evento.preventDefault();
                }
            });
        });

        campo.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter' && evento.target === entrada && !painel.hidden) {
                evento.preventDefault();
            }

            if (evento.key === 'Escape') {
                entrada.focus();
                abrir(false);
            }
        });

        document.addEventListener('click', function (evento) {
            if (!campo.contains(evento.target)) {
                abrir(false);
            }
        });
    });
})();
