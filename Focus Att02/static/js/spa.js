// Cola do "modo SPA" do Focus.
// Com hx-boost="true" no <body>, o htmx troca a página inteira (o <body>
// todo) via AJAX ao navegar, em vez de recarregar o documento. O CSS de
// cada página viaja junto (o <link> dela fica dentro do próprio <body>,
// logo no topo), e o menu já chega com o item certo marcado como "active"
// direto do servidor — não precisamos sincronizar nada disso na mão.
// O que sobra pra esse arquivo cuidar: fechar o dropdown do perfil e voltar
// o scroll pro topo a cada troca de "página".
// Os scripts que precisam sobreviver à troca (trilha sonora, acessibilidade)
// ficam no <head>, que nunca é tocado pelo swap — por isso não reiniciam.
(function () {
    // Liga a View Transitions API nativa do navegador nos swaps do htmx.
    // A CSS de transição (::view-transition-old/new) já existe em
    // accessibility.css desde que a meta tag foi adicionada, mas sem isso
    // aqui ela nunca era usada de verdade: hx-boost navega via AJAX, não é
    // uma navegação real de página, então a meta tag sozinha não fazia nada.
    // Com globalViewTransitions ligado, o htmx chama document.startViewTransition()
    // em cada troca — em navegadores sem suporte (ex: Firefox mais antigo),
    // ele cai de volta pra troca instantânea normal, sem quebrar nada.
    if (window.htmx) {
        htmx.config.globalViewTransitions = true;
    }

    document.body.addEventListener('htmx:afterSettle', function () {
        window.scrollTo(0, 0);
        var dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('show');
        focusAtualizarOndeParei();
    });

    // Voltar/avançar no histórico do navegador restaura o <body> do cache do
    // htmx sem rodar nada da página — então refazemos o registro e o menu aqui.
    document.body.addEventListener('htmx:historyRestore', focusAtualizarOndeParei);
    window.addEventListener('pageshow', focusAtualizarOndeParei);

    // "Estudos" e "Comunidades" no menu de cima sempre levavam pro início
    // (lista de módulos / lista de amigos), mesmo quando a pessoa estava
    // no meio de algo — um cronômetro rodando numa disciplina, ou uma
    // conversa — e só tinha saído dali rapidinho pra outra aba. Agora,
    // sempre que a pessoa visita uma disciplina/módulo (Estudos) ou uma
    // DM/grupo (Comunidades), essa página guarda a própria URL; aqui a
    // gente lê essa "última parada" e aponta o link do menu pra ela em vez
    // do início — só nesses dois itens do menu, escopado com
    // "nav.navbar-main" pra não mexer em nenhum outro link da página
    // (como a setinha de "voltar" de dentro de uma disciplina, que
    // continua subindo um nível como sempre).
    //
    // IMPORTANTE: quem grava a "última parada" NÃO é mais um <script> inline
    // dentro de cada página lendo window.location. Com hx-boost, o htmx roda
    // os scripts da página nova ANTES de atualizar a URL do navegador — então
    // window.location ainda era o da página ANTERIOR e o "onde parei" ficava
    // sempre uma página atrasado (ou preso no início). Agora cada página
    // renderiza um marcador <i id="focus-onde-parei" data-secao data-url>
    // com a URL correta vinda do servidor, e a função abaixo lê ele.
    // data-url vazio (páginas de lista) significa "zerar": voltar pra lista
    // de módulos/amigos e clicar no menu tem que ficar na lista.
    var FOCUS_CHAVES_ONDE_PAREI = ['focus_ultimo_estudos', 'focus_ultimo_comunidades'];

    // Cada seção: página de LISTA (início da seção), padrão das páginas de
    // DETALHE (as que viram "onde parei") e o link do menu que ela controla.
    // A decisão agora vem da URL REAL do navegador (location), não mais só
    // do marcador <i id="focus-onde-parei"> do HTML: o marcador pode chegar
    // com atributo velho durante o swap do htmx (mesmo id => o htmx mantém
    // os atributos antigos até o "settle"), e era isso que fazia o menu
    // voltar pro último grupo/disciplina mesmo depois de a pessoa ter ido
    // pra lista. O marcador continua no HTML como reforço/documentação.
    var FOCUS_SECOES = [
        { chave: 'focus_ultimo_estudos', lista: '/meus_estudos',
          detalhe: /^\/meus_estudos\/(modulo|disciplina)\/\d+\/?$/ },
        { chave: 'focus_ultimo_comunidades', lista: '/comunidades',
          detalhe: /^\/comunidades\/(dm|grupo)\/\d+\/?$/ }
    ];

    function focusNormalizarCaminho(p) {
        p = (p || '/').replace(/\/+$/, '');
        return p || '/';
    }

    // Olha onde a pessoa está AGORA: página de detalhe => guarda; página de
    // lista => esquece; qualquer outra (dashboard, perfil, metas...) => não
    // mexe, a última ação dentro da seção continua valendo.
    function focusRegistrarOndeParei() {
        var caminho = window.location.pathname;
        try {
            FOCUS_SECOES.forEach(function (sec) {
                if (sec.detalhe.test(caminho)) {
                    localStorage.setItem(sec.chave, caminho + window.location.search);
                } else if (focusNormalizarCaminho(caminho) === sec.lista) {
                    localStorage.removeItem(sec.chave);
                }
            });
        } catch (e) { /* localStorage indisponível: o menu só volta ao início */ }
    }

    // Aponta (ou DESAPONTA) os links do menu. Antes só apontava quando havia
    // algo guardado; se o <body> viesse de um snapshot do histórico do htmx
    // com o href já trocado pelo grupo antigo, ele ficava preso lá. Agora o
    // href é sempre recalculado a partir do original.
    function focusApontarNavPraOndeParou() {
        var nav = document.querySelector('nav.navbar-main');
        if (!nav) return;
        FOCUS_SECOES.forEach(function (sec) {
            var link = nav.querySelector('a[data-focus-href-original="' + sec.lista + '"]') ||
                       nav.querySelector('a[href="' + sec.lista + '"]');
            if (!link) return;
            link.setAttribute('data-focus-href-original', sec.lista);
            var ultimo = null;
            try { ultimo = localStorage.getItem(sec.chave); } catch (e) { /* ignora */ }
            link.setAttribute('href', ultimo || sec.lista);
        });
    }

    function focusAtualizarOndeParei() {
        focusRegistrarOndeParei();
        focusApontarNavPraOndeParou();
    }

    // Clicou num link que vai pra LISTA da seção (ex: a setinha de "voltar"
    // de dentro de um grupo/disciplina): esquece a parada na hora, sem
    // esperar a página nova chegar — assim nem uma resposta lenta nem um
    // swap atrasado deixam o menu apontando pro grupo antigo.
    document.addEventListener('click', function (evt) {
        var a = evt.target && evt.target.closest ? evt.target.closest('a[href]') : null;
        if (!a || a.origin !== window.location.origin) return;
        var caminho = focusNormalizarCaminho(a.pathname);
        try {
            FOCUS_SECOES.forEach(function (sec) {
                if (caminho === sec.lista) localStorage.removeItem(sec.chave);
            });
        } catch (e) { /* ignora */ }
    }, true);

    document.body.addEventListener('htmx:pushedIntoHistory', focusAtualizarOndeParei);
    focusAtualizarOndeParei();

    // (Aviso pra próxima vez que eu mexer aqui: já tentei evitar o
    // recarregamento completo nessa navegação usando htmx.ajax() com
    // select:'body'/swap:'outerHTML' — isso QUEBROU a navegação (tela
    // ficou toda branca), porque a resposta do servidor é uma página HTML
    // inteira, e o htmx não consegue "selecionar" a tag <body> de dentro
    // de um documento completo do mesmo jeito que faz com fragmentos —
    // NÃO REPETIR essa abordagem. O jeito seguro de evitar o "flash" de
    // tema é aplicar a classe do tema o quanto antes no <body>, não tentar
    // reinventar a navegação do htmx na mão.
    window.focusNavegarSemPiscar = function (url) {
        window.location.href = url;
    };

    // Se uma navegação boosted falhar (ex: sessão expirada), recarrega de
    // verdade em vez de deixar a página travada num estado inconsistente.
    // Se a página que falhou era a "última parada" guardada (ex: disciplina
    // ou grupo excluído), esquece ela — senão o menu ficaria apontando pra
    // uma página que não abre mais.
    document.body.addEventListener('htmx:responseError', function (evt) {
        try {
            var info = evt.detail && evt.detail.pathInfo;
            var caminho = ((info && info.requestPath) || '').split('?')[0];
            if (caminho) {
                FOCUS_CHAVES_ONDE_PAREI.forEach(function (chave) {
                    var guardado = (localStorage.getItem(chave) || '').split('?')[0];
                    if (guardado && guardado === caminho) localStorage.removeItem(chave);
                });
            }
        } catch (e) { /* ignora */ }
        window.location.reload();
    });
})();
