/*
 * Painel: as categorias do produto ficam num menu de marcas (como o de cor),
 * com as escolhidas em etiquetas lado a lado e a subcategoria de cada uma
 * logo abaixo. No cadastro de categoria, a marca "Subcategoria" abre o
 * "Subcategoria de".
 *
 * Sem este script o menu abre pelo próprio <details> e as marcas continuam
 * funcionando; só as etiquetas e o resumo ficam sem atualizar. O servidor
 * ignora subcategoria de categoria não marcada.
 */
(function () {
    'use strict';

    var cada = function (lista, acao) {
        Array.prototype.forEach.call(lista, acao);
    };

    // Produto: menu de categorias com mais de uma escolha. As marcadas viram
    // etiquetas lado a lado, a subcategoria de cada uma aparece abaixo e o
    // "×" da etiqueta desmarca.
    cada(document.querySelectorAll('[data-categories-field]'), function (campo) {
        var menu = campo.querySelector('[data-categories-menu]');
        var resumo = campo.querySelector('[data-categories-summary]');
        var etiquetas = campo.querySelector('[data-categories-chips]');
        var caixas = campo.querySelectorAll('[data-category-toggle]');

        if (!menu || !etiquetas) {
            return;
        }

        var atualizar = function () {
            etiquetas.innerHTML = '';

            var marcadas = 0;

            cada(caixas, function (caixa) {
                var sub = campo.querySelector('[data-category-sub-for="' + caixa.value + '"]');

                if (sub) {
                    sub.hidden = !caixa.checked;

                    if (!caixa.checked) {
                        var seletor = sub.querySelector('select');

                        if (seletor) {
                            seletor.value = '';
                        }
                    }
                }

                if (!caixa.checked) {
                    return;
                }

                marcadas += 1;

                var nome = caixa.getAttribute('data-category-name');
                var etiqueta = document.createElement('li');
                var texto = document.createElement('span');
                var remover = document.createElement('button');

                etiqueta.className = 'field__chip';
                texto.textContent = nome;
                remover.type = 'button';
                remover.className = 'field__chip__remove';
                remover.setAttribute('data-category-remove', caixa.value);
                remover.setAttribute('aria-label', 'Remover a categoria ' + nome);
                remover.textContent = '×';

                etiqueta.appendChild(texto);
                etiqueta.appendChild(remover);
                etiquetas.appendChild(etiqueta);
            });

            if (resumo) {
                resumo.textContent = marcadas === 0
                    ? 'Escolha as categorias'
                    : (marcadas === 1 ? '1 categoria escolhida' : marcadas + ' categorias escolhidas');
            }
        };

        cada(caixas, function (caixa) {
            caixa.addEventListener('change', atualizar);
        });

        etiquetas.addEventListener('click', function (evento) {
            var botao = evento.target.closest('[data-category-remove]');

            if (!botao) {
                return;
            }

            var caixa = campo.querySelector('[data-category-toggle][value="' + botao.getAttribute('data-category-remove') + '"]');

            if (caixa) {
                caixa.checked = false;
                caixa.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // O × de cada linha apaga a categoria: o formulário vai para a rota de
        // exclusão. Sem JavaScript ele já envia; aqui só pede confirmação.
        cada(campo.querySelectorAll('[data-category-delete]'), function (apagar) {
            apagar.addEventListener('click', function (evento) {
                var aviso = 'Apagar a categoria "' + apagar.getAttribute('data-category-name') + '"? Ela sai da lista.';

                if (!window.confirm(aviso)) {
                    evento.preventDefault();
                }
            });
        });

        document.addEventListener('click', function (evento) {
            if (menu.open && !campo.contains(evento.target)) {
                menu.open = false;
            }
        });

        campo.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape' && menu.open) {
                menu.open = false;
                menu.querySelector('summary').focus();
            }
        });

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
