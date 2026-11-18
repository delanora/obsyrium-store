/*
 * Comportamento do site.
 *
 * Menu do celular (hamburger, com foco, Escape e clique fora), gaveta do
 * carrinho no computador, sombra do cabeçalho ao rolar, entrada dos blocos
 * marcados com [data-reveal], os filtros recolhíveis do catálogo no celular, as
 * máscaras de telefone e CPF e o CEP que preenche o endereço pela BrasilAPI.
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

        var atualizarListaDaGaveta = function (doc) {
            var listaAtual = gaveta.querySelector('.cart-drawer__list');
            var listaNova = doc.querySelector('.cart-drawer__list');

            if (listaAtual && listaNova) {
                listaAtual.replaceWith(document.importNode(listaNova, true));
            }
        };

        var aplicarResposta = function (doc) {
            atualizarSinais(doc);
            atualizarListaDaGaveta(doc);
            atualizarPaginaDoCarrinho(doc);
        };

        // Quantidade: na página o número é enviado sozinho quando muda; na gaveta,
        // pelos botões + e −. Nenhum dos dois recarrega a página. Se o servidor
        // recusar (limite ou estoque), o envio tradicional mostra o aviso.
        var enviarQuantidade = function (formulario, quantidade) {
            if (typeof window.fetch !== 'function' || typeof window.FormData !== 'function' || typeof window.DOMParser !== 'function') {
                formulario.submit();

                return;
            }

            var dados = new FormData(formulario);
            dados.set('quantity', String(quantidade));

            fetch(formulario.action, {
                method: 'POST',
                body: dados,
                credentials: 'same-origin',
                redirect: 'follow'
            }).then(function (resposta) {
                if (!resposta.ok) {
                    throw new Error('quantidade falhou');
                }

                return resposta.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');

                if (doc.querySelector('.flash--error')) {
                    formulario.submit();

                    return;
                }

                aplicarResposta(doc);
            }).catch(function () {
                formulario.submit();
            });
        };

        document.addEventListener('change', function (evento) {
            var entrada = evento.target;

            if (!entrada || !entrada.matches || !entrada.matches('.cart-quantity__input')) {
                return;
            }

            var formulario = entrada.closest('form');
            var quantidade = parseInt(entrada.value, 10);

            if (!formulario || isNaN(quantidade) || quantidade < 1) {
                return;
            }

            enviarQuantidade(formulario, quantidade);
        });

        document.addEventListener('click', function (evento) {
            var botao = evento.target.closest ? evento.target.closest('[data-quantity-step]') : null;

            if (!botao) {
                return;
            }

            var formulario = botao.closest('form');
            var entrada = formulario ? formulario.querySelector('[data-quantity-input]') : null;

            if (!formulario || !entrada) {
                return;
            }

            var passo = parseInt(botao.getAttribute('data-quantity-step'), 10) || 0;
            var atual = parseInt(entrada.value, 10) || 1;
            var maximo = parseInt(entrada.getAttribute('data-max'), 10) || atual;
            var quantidade = Math.min(Math.max(atual + passo, 1), maximo);

            if (quantidade === atual) {
                return;
            }

            entrada.value = String(quantidade);
            enviarQuantidade(formulario, quantidade);
        });

        gaveta.addEventListener('submit', function (evento) {
            var formulario = evento.target;

            if (!formulario || !formulario.closest || formulario.getAttribute('action') !== '/carrinho/remover') {
                return;
            }

            var item = formulario.closest('.cart-drawer__item');

            if (!item) {
                return;
            }

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
    // CEP: ao completar os oito dígitos, a BrasilAPI devolve rua, bairro,
    // cidade e UF do endereço. Número e complemento continuam manuais, e sem
    // JavaScript o formulário segue funcionando do jeito antigo.
    var campoCep = document.querySelector('[data-cep]');

    if (campoCep && campoCep.form && typeof window.fetch === 'function') {
        var formularioCep = campoCep.form;
        var avisoCep = formularioCep.querySelector('[data-cep-aviso]');
        var buscaCep = 0;
        var ultimoCepConsultado = '';

        var mostrarAvisoCep = function (texto) {
            if (!avisoCep) {
                return;
            }

            avisoCep.textContent = texto;
            avisoCep.hidden = texto === '';
        };

        var preencherCep = function (dados) {
            var campos = {
                street: formularioCep.querySelector('[name="street"]'),
                district: formularioCep.querySelector('[name="district"]'),
                city: formularioCep.querySelector('[name="city"]'),
                state: formularioCep.querySelector('[name="state"]')
            };

            if (campos.street && dados.street) {
                campos.street.value = dados.street;
            }

            if (campos.district && dados.neighborhood) {
                campos.district.value = dados.neighborhood;
            }

            if (campos.city && dados.city) {
                campos.city.value = dados.city;
            }

            if (campos.state && dados.state) {
                campos.state.value = dados.state;
            }
        };

        var consultarCep = function () {
            var digitos = campoCep.value.replace(/\D+/g, '');

            if (digitos.length !== 8) {
                mostrarAvisoCep('');

                return;
            }

            if (digitos === ultimoCepConsultado) {
                return;
            }

            ultimoCepConsultado = digitos;

            var tentativa = buscaCep + 1;
            buscaCep = tentativa;
            campoCep.setAttribute('aria-busy', 'true');
            mostrarAvisoCep('Buscando o endereço do CEP…');

            window.fetch('https://brasilapi.com.br/api/cep/v2/' + digitos, {
                headers: { Accept: 'application/json' }
            }).then(function (resposta) {
                // 404 é a BrasilAPI dizendo que nenhum serviço achou o CEP; os
                // demais códigos (ou a rede caída) são falha de consulta.
                if (resposta.status === 404) {
                    throw new Error('cep-nao-encontrado');
                }

                if (!resposta.ok) {
                    throw new Error('cep-falhou');
                }

                return resposta.json();
            }).then(function (dados) {
                if (tentativa !== buscaCep) {
                    return;
                }

                preencherCep(dados);
                mostrarAvisoCep('Endereço preenchido pelo CEP. Confira o número e o complemento.');
            }).catch(function (erro) {
                if (tentativa !== buscaCep) {
                    return;
                }

                ultimoCepConsultado = '';

                if (erro instanceof Error && erro.message === 'cep-nao-encontrado') {
                    mostrarAvisoCep('CEP não encontrado. Confira os números.');

                    return;
                }

                mostrarAvisoCep('Não foi possível buscar o CEP agora. Preencha manualmente.');
            }).then(function () {
                if (tentativa === buscaCep) {
                    campoCep.removeAttribute('aria-busy');
                }
            });
        };

        // O campo mostra 01310-100, mas só os dígitos valem: o servidor recebe o
        // valor com hífen e descarta tudo que não é número.
        var apenasDigitosCep = function (texto) {
            return texto.replace(/\D+/g, '').slice(0, 8);
        };

        var formatarCep = function (digitos) {
            return digitos.length > 5 ? digitos.slice(0, 5) + '-' + digitos.slice(5) : digitos;
        };

        if (campoCep.value !== '') {
            campoCep.value = formatarCep(apenasDigitosCep(campoCep.value));
        }

        campoCep.addEventListener('input', function () {
            var digitos = apenasDigitosCep(campoCep.value);
            var formatado = formatarCep(digitos);

            if (campoCep.value !== formatado) {
                var cursor = campoCep.selectionStart;

                if (typeof cursor !== 'number') {
                    cursor = campoCep.value.length;
                }

                var digitosAntesDoCursor = apenasDigitosCep(campoCep.value.slice(0, cursor)).length;

                campoCep.value = formatado;

                var alvo = digitosAntesDoCursor > 5 ? digitosAntesDoCursor + 1 : digitosAntesDoCursor;
                campoCep.setSelectionRange(alvo, alvo);
            }

            if (digitos.length === 8) {
                consultarCep();
            }
        });

        // Backspace logo depois do hífen apaga o quinto dígito: o hífen é enfeite
        // e não pode prender o cursor.
        campoCep.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Backspace' || campoCep.selectionStart !== campoCep.selectionEnd) {
                return;
            }

            if (campoCep.selectionStart === 6 && campoCep.value.charAt(5) === '-') {
                evento.preventDefault();
                campoCep.value = formatarCep(apenasDigitosCep(campoCep.value).slice(0, 5));
                campoCep.setSelectionRange(5, 5);
            }
        });

        campoCep.addEventListener('change', consultarCep);
    }
    // Máscaras de número: telefone e CPF ganham os separadores enquanto a
    // pessoa digita, com o cursor mantido depois do último dígito mexido e o
    // backspace pulando parêntese, espaço, ponto e hífen. O campo guarda o valor
    // formatado; quem fica só com os dígitos é o servidor, e sem script os
    // campos continuam sendo texto comum.
    var apenasDigitos = function (texto) {
        return String(texto).replace(/\D+/g, '');
    };

    // Posição do cursor depois de N dígitos, porque os separadores também
    // ocupam lugar no texto.
    var posicaoDoDigito = function (formatado, quantidade) {
        if (quantidade <= 0) {
            return 0;
        }

        var vistos = 0;

        for (var i = 0; i < formatado.length; i += 1) {
            if (/\d/.test(formatado.charAt(i))) {
                vistos += 1;

                if (vistos === quantidade) {
                    return i + 1;
                }
            }
        }

        return formatado.length;
    };

    // Aplica uma máscara a todos os campos que a pedem.
    var mascararCampos = function (seletor, formatar) {
        Array.prototype.forEach.call(document.querySelectorAll(seletor), function (campo) {
            if (campo.value !== '') {
                campo.value = formatar(campo.value);
            }

            campo.addEventListener('input', function () {
                var cursor = campo.selectionStart;

                if (typeof cursor !== 'number') {
                    cursor = campo.value.length;
                }

                var digitosAntesDoCursor = apenasDigitos(campo.value.slice(0, cursor)).length;
                var formatado = formatar(campo.value);

                if (campo.value === formatado) {
                    return;
                }

                campo.value = formatado;

                var alvo = posicaoDoDigito(formatado, digitosAntesDoCursor);
                campo.setSelectionRange(alvo, alvo);
            });

            // Backspace logo depois de um separador apaga o dígito anterior: o
            // enfeite não pode prender o cursor.
            campo.addEventListener('keydown', function (evento) {
                if (evento.key !== 'Backspace' || campo.selectionStart !== campo.selectionEnd) {
                    return;
                }

                var cursor = campo.selectionStart;
                var anterior = campo.value.charAt(cursor - 1);

                if (anterior !== '(' && anterior !== ')' && anterior !== ' ' && anterior !== '-' && anterior !== '.') {
                    return;
                }

                evento.preventDefault();

                var digitos = apenasDigitos(campo.value);
                var quantidade = apenasDigitos(campo.value.slice(0, cursor - 1)).length;

                if (quantidade > 0) {
                    digitos = digitos.slice(0, quantidade - 1) + digitos.slice(quantidade);
                }

                var formatado = formatar(digitos);
                var alvo = posicaoDoDigito(formatado, quantidade - 1);

                campo.value = formatado;
                campo.setSelectionRange(alvo, alvo);
            });
        });
    };

    // Telefone brasileiro: (11) 99999-9999 no celular e (11) 9999-9999 no fixo.
    mascararCampos('[data-phone-mask]', function (texto) {
        var digitos = apenasDigitos(texto);

        // Número colado com o país (+55) perde o 55: o campo é do número local,
        // com DDD.
        if (digitos.length > 11 && digitos.slice(0, 2) === '55') {
            digitos = digitos.slice(2);
        }

        digitos = digitos.slice(0, 11);

        if (digitos.length === 0) {
            return '';
        }

        if (digitos.length <= 2) {
            return '(' + digitos;
        }

        if (digitos.length <= 6) {
            return '(' + digitos.slice(0, 2) + ') ' + digitos.slice(2);
        }

        // Fixo (até 10 dígitos) tem 4 antes do hífen; celular tem 5.
        var corte = digitos.length > 10 ? 7 : 6;

        return '(' + digitos.slice(0, 2) + ') ' + digitos.slice(2, corte) + '-' + digitos.slice(corte);
    });

    // CPF: 000.000.000-00.
    mascararCampos('[data-cpf-mask]', function (texto) {
        var digitos = apenasDigitos(texto).slice(0, 11);

        if (digitos.length <= 3) {
            return digitos;
        }

        if (digitos.length <= 6) {
            return digitos.slice(0, 3) + '.' + digitos.slice(3);
        }

        if (digitos.length <= 9) {
            return digitos.slice(0, 3) + '.' + digitos.slice(3, 6) + '.' + digitos.slice(6);
        }

        return digitos.slice(0, 3) + '.' + digitos.slice(3, 6) + '.' + digitos.slice(6, 9) + '-' + digitos.slice(9);
    });

    // Catálogo: no celular o painel de filtros nasce recolhido. Com filtro
    // ativo ele continua aberto, para dar para ajustar o que está ligado.
    var filtros = document.querySelector('[data-catalog-filters]');

    if (
        filtros &&
        window.matchMedia('(max-width: 47.99rem)').matches &&
        !filtros.hasAttribute('data-active')
    ) {
        filtros.open = false;
    }

    // Categoria do catálogo: o menu de subcategorias nasce escondido e aparece
    // quando a categoria escolhida tem filhas. As opções saem do JSON da árvore
    // que a view imprime, então o servidor não é consultado de novo.
    var categoriaFiltro = document.querySelector('[data-catalog-category]');
    var subcategoriaCampo = document.querySelector('[data-catalog-subfield]');
    var subcategoriaFiltro = subcategoriaCampo ? subcategoriaCampo.querySelector('select') : null;
    var arvoreCategorias = document.querySelector('[data-catalog-tree]');
    var filhasPorCategoria = {};

    if (arvoreCategorias) {
        try {
            filhasPorCategoria = JSON.parse(arvoreCategorias.textContent) || {};
        } catch (erro) {
            filhasPorCategoria = {};
        }
    }

    var montarSubcategorias = function () {
        if (!categoriaFiltro || !subcategoriaCampo || !subcategoriaFiltro) {
            return false;
        }

        var filhas = filhasPorCategoria[categoriaFiltro.value] || [];
        var escolhida = subcategoriaFiltro.value;

        subcategoriaFiltro.textContent = '';

        var todas = document.createElement('option');
        todas.value = '';
        todas.textContent = 'Todas nesta categoria';
        subcategoriaFiltro.appendChild(todas);

        filhas.forEach(function (filha) {
            var opcao = document.createElement('option');
            opcao.value = filha.slug;
            opcao.textContent = filha.name + ' (' + filha.total + ')';
            subcategoriaFiltro.appendChild(opcao);
        });

        // A escolha anterior só fica se ainda existir na categoria nova.
        var mantem = filhas.some(function (filha) {
            return filha.slug === escolhida;
        });

        subcategoriaFiltro.value = mantem ? escolhida : '';
        subcategoriaCampo.hidden = filhas.length === 0;
        subcategoriaFiltro.disabled = filhas.length === 0;

        return filhas.length > 0;
    };

    // Com JavaScript o formulário de filtros se envia sozinho quando um campo
    // muda; o botão "Aplicar filtros" continua lá para quem está sem script.
    var formFiltros = document.querySelector('[data-filter-form]');

    if (formFiltros) {
        formFiltros.addEventListener('change', function (evento) {
            // Categoria com filhas só abre o menu de subcategorias: quem envia é
            // a escolha da subcategoria ou o botão Aplicar filtros.
            if (evento.target === categoriaFiltro && montarSubcategorias()) {
                return;
            }

            if (typeof formFiltros.requestSubmit === 'function') {
                formFiltros.requestSubmit();
            } else {
                formFiltros.submit();
            }
        });
    }

    // Menus recolhidos (a ordenação do catálogo) fecham ao clicar fora ou com
    // Escape. Sem script o próprio <details> abre e fecha pelo resumo.
    var menus = document.querySelectorAll('details[data-menu]');

    if (menus.length > 0) {
        document.addEventListener('click', function (evento) {
            menus.forEach(function (menu) {
                if (menu.open && !menu.contains(evento.target)) {
                    menu.open = false;
                }
            });
        });

        document.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Escape') {
                return;
            }

            menus.forEach(function (menu) {
                menu.open = false;
            });
        });
    }

    // Preço digitado do catálogo: o campo já nasce com ",00" e a pessoa digita
    // só a parte inteira antes da vírgula; os centavos continuam editáveis se
    // ela quiser (149,90). Sem script o campo é texto livre e o servidor aceita
    // "149", "149,90" e "149.90".
    var camposDePreco = document.querySelectorAll('[data-money]');

    Array.prototype.forEach.call(camposDePreco, function (campo) {
        var formatar = function (valor) {
            var partes = String(valor).replace('.', ',').split(',');
            var inteiro = (partes[0] || '').replace(/\D+/g, '').slice(0, 7);
            var centavos = (partes[1] || '').replace(/\D+/g, '').slice(0, 2);

            if (centavos.length === 0) {
                centavos = '00';
            } else if (centavos.length === 1) {
                centavos += '0';
            }

            return inteiro + ',' + centavos;
        };

        var caretAntesDaVirgula = function () {
            var posicao = campo.value.indexOf(',');
            campo.setSelectionRange(posicao, posicao);
        };

        if (campo.value !== '') {
            campo.value = formatar(campo.value);
        }

        campo.addEventListener('focus', function () {
            if (campo.value === '') {
                campo.value = ',00';
            }

            caretAntesDaVirgula();
        });

        // O clique reposiciona o cursor depois do foco; com o campo ainda em
        // ",00" o cursor volta para antes da vírgula, que é por onde se começa
        // a digitar.
        campo.addEventListener('click', function () {
            if (campo.value === ',00') {
                caretAntesDaVirgula();
            }
        });

        campo.addEventListener('input', function () {
            campo.value = formatar(campo.value);
            caretAntesDaVirgula();
        });

        // Backspace logo depois da vírgula apaga o último dígito do valor: a
        // vírgula é enfeite e não pode prender o cursor.
        campo.addEventListener('keydown', function (evento) {
            if (evento.key !== 'Backspace' || campo.selectionStart !== campo.selectionEnd) {
                return;
            }

            if (campo.selectionStart === 1 && campo.value.charAt(0) === ',') {
                evento.preventDefault();
            }
        });

        // Campo só com ",00" não vira filtro: sai da URL em vez de pedir preço
        // zero ao servidor.
        campo.addEventListener('blur', function () {
            if (campo.value === ',00') {
                campo.value = '';
            }
        });
    });

    // Galeria do produto: cada miniatura é um botão que troca a foto grande.
    // A miniatura da foto em exibição fica marcada com aria-pressed, e o foco
    // do teclado cai no próprio botão, então Enter e espaço funcionam sozinhos.
    // No celular, arrastar de lado também troca de foto; o gesto avisa o leitor
    // de tela pelo texto da região viva. A foto grande abre ampliada numa
    // janela, pelo botão (teclado incluído) ou pelo clique na imagem.
    Array.prototype.forEach.call(document.querySelectorAll('.product-gallery'), function (galeria) {
        var principal = galeria.querySelector('[data-gallery-main]');
        var miniaturas = Array.prototype.slice.call(galeria.querySelectorAll('[data-gallery-src]'));
        var status = galeria.querySelector('[data-gallery-status]');
        var atual = 0;

        if (!principal) {
            return;
        }

        var mostrar = function (indice) {
            if (indice < 0 || indice >= miniaturas.length) {
                return false;
            }

            atual = indice;
            var miniatura = miniaturas[indice];
            principal.src = miniatura.getAttribute('data-gallery-src');
            principal.alt = miniatura.getAttribute('data-gallery-alt');

            miniaturas.forEach(function (outra) {
                outra.setAttribute('aria-pressed', outra === miniatura ? 'true' : 'false');
            });

            return true;
        };

        miniaturas.forEach(function (miniatura, indice) {
            miniatura.addEventListener('click', function () {
                mostrar(indice);
            });
        });

        // Arrastar troca de foto: só conta quando o movimento é mais horizontal
        // que vertical, para não roubar a rolagem da página.
        var area = galeria.querySelector('.product-gallery__main');
        var inicio = null;

        if (area && miniaturas.length > 1) {
            area.addEventListener('touchstart', function (evento) {
                var toque = evento.changedTouches[0];
                inicio = { x: toque.clientX, y: toque.clientY };
            }, { passive: true });

            area.addEventListener('touchend', function (evento) {
                if (inicio === null) {
                    return;
                }

                var toque = evento.changedTouches[0];
                var dx = toque.clientX - inicio.x;
                var dy = toque.clientY - inicio.y;
                inicio = null;

                if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy) * 1.2) {
                    return;
                }

                if (mostrar(atual + (dx < 0 ? 1 : -1)) && status) {
                    status.textContent = 'Foto ' + (atual + 1) + ' de ' + miniaturas.length;
                }
            }, { passive: true });
        }

        var botaoZoom = galeria.querySelector('[data-gallery-zoom]');
        var janela = galeria.querySelector('[data-gallery-dialog]');
        var ampliada = galeria.querySelector('[data-gallery-zoom-image]');

        if (!botaoZoom || !janela || !ampliada || typeof janela.showModal !== 'function') {
            return;
        }

        var abrirZoom = function () {
            ampliada.src = principal.src;
            ampliada.alt = principal.alt;
            janela.showModal();
        };

        botaoZoom.addEventListener('click', abrirZoom);
        principal.addEventListener('click', abrirZoom);

        Array.prototype.forEach.call(janela.querySelectorAll('[data-gallery-close]'), function (botao) {
            botao.addEventListener('click', function () {
                janela.close();
            });
        });

        // Esc fecha pelo próprio <dialog>; o tratamento aqui cobre o navegador
        // que não dispara o cancelamento nativo, e o foco volta para a lupa.
        janela.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                evento.preventDefault();
                janela.close();
            }
        });

        // Clique no fundo escuro fecha; a volta do foco é do <dialog>.
        janela.addEventListener('click', function (evento) {
            if (evento.target === janela) {
                janela.close();
            }
        });
    });

    // Guia de medidas do produto: a tabela abre numa janela pelo botão do
    // tamanho e fecha pelo ×, pelo fundo escuro e pelo Esc, com o foco de volta
    // no botão ao terminar.
    Array.prototype.forEach.call(document.querySelectorAll('[data-guide-dialog]'), function (janela) {
        var abridor = document.querySelector('[data-guide-open]');

        if (!abridor || typeof janela.showModal !== 'function') {
            return;
        }

        abridor.addEventListener('click', function () {
            janela.showModal();
        });

        Array.prototype.forEach.call(janela.querySelectorAll('[data-guide-close]'), function (botao) {
            botao.addEventListener('click', function () {
                janela.close();
            });
        });

        janela.addEventListener('keydown', function (evento) {
            if (evento.key === 'Escape') {
                evento.preventDefault();
                janela.close();
            }
        });

        janela.addEventListener('click', function (evento) {
            if (evento.target === janela) {
                janela.close();
            }
        });
    });

    // Porta do checkout: o cadastro mostra só o nome até o cliente começar a
    // preencher; aí os outros campos aparecem com a transição do CSS. Um clique
    // fora do card recolhe de novo enquanto os campos estiverem vazios.
    var gateMore = document.querySelector('[data-gate-more]');
    var gateName = document.getElementById('gate-name');

    if (gateMore && gateName && gateMore.getAttribute('data-open') !== '1') {
        var gateCards = gateMore.closest('.auth-gate__cards');
        var gateCard = gateMore.closest('.auth-gate__card--register');
        var gateFields = gateMore.querySelectorAll('input');

        var recolherCadastro = function (recolhido) {
            gateMore.classList.toggle('is-open', !recolhido);
            gateMore.toggleAttribute('inert', recolhido);

            if (gateCards) {
                gateCards.classList.toggle('is-collapsed', recolhido);
            }

            if (gateCard) {
                gateCard.classList.toggle('is-collapsed', recolhido);
            }
        };

        var temAlgoPreenchido = function () {
            return Array.prototype.some.call(gateFields, function (campo) {
                return campo.value.trim() !== '' || (campo.type === 'checkbox' && campo.checked);
            });
        };

        recolherCadastro(true);

        gateName.addEventListener('focus', function () {
            recolherCadastro(false);
        });

        document.addEventListener('click', function (evento) {
            if (!gateMore.classList.contains('is-open')) {
                return;
            }

            if (gateCard && gateCard.contains(evento.target)) {
                return;
            }

            if (!temAlgoPreenchido()) {
                recolherCadastro(true);
            }
        });
    }

    // Marca para o script inline que o comportamento carregou.
    window.__obsyriumReady = true;
})();
