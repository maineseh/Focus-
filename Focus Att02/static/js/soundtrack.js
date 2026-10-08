// Trilha sonora de fundo do Focus.
// As 5 trilhas são geradas ao vivo com a Web Audio API (osciladores + ruído
// filtrado), por isso elas são infinitas de verdade — não são um arquivo de
// áudio em loop, é som sendo sintetizado continuamente enquanto a página
// estiver aberta.
(function () {
    var ctx = null;
    var nodes = [];              // nós ativos, pra poder desligar tudo de uma vez
    var tocando = false;
    var trilhaAtual = 0;

    var TRILHAS = [
        { id: 0, nome: "Chuva Suave",     icone: "fa-cloud-rain" },
        { id: 1, nome: "Ondas do Mar",    icone: "fa-water" },
        { id: 2, nome: "Floresta",        icone: "fa-tree" },
        { id: 3, nome: "Lo-fi Ambiente",  icone: "fa-music" },
        { id: 4, nome: "Ruído Rosa Zen",  icone: "fa-spa" },
    ];

    function lerConfig() {
        return {
            ligado: localStorage.getItem('focus_som_ligado') === '1',
            trilha: Math.max(0, Math.min(4, parseInt(localStorage.getItem('focus_som_trilha') || '0', 10)))
        };
    }

    function salvarConfig(cfg) {
        localStorage.setItem('focus_som_ligado', cfg.ligado ? '1' : '0');
        localStorage.setItem('focus_som_trilha', String(cfg.trilha));
    }

    function getCtx() {
        if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
        return ctx;
    }

    // Buffer de ruído branco de alguns segundos, reaproveitado (em loop) como
    // matéria-prima das trilhas de chuva/mar/floresta/zen.
    function criarBufferRuido(c) {
        var duracao = 4;
        var buffer = c.createBuffer(1, c.sampleRate * duracao, c.sampleRate);
        var dados = buffer.getChannelData(0);
        for (var i = 0; i < dados.length; i++) dados[i] = Math.random() * 2 - 1;
        return buffer;
    }

    function pararTudo() {
        nodes.forEach(function (n) {
            try { n.stop ? n.stop() : null; } catch (e) {}
            try { n.disconnect(); } catch (e) {}
        });
        nodes = [];
        tocando = false;
        limparDestravador();
    }

    // Listener de "destravamento" pendente (ver iniciarComContexto). Fica
    // guardado numa variável só pra não empilhar vários listeners iguais
    // se o usuário trocar de trilha antes do primeiro clique acontecer.
    var destravarPendente = null;

    function limparDestravador() {
        if (destravarPendente) {
            document.removeEventListener('click', destravarPendente);
            document.removeEventListener('keydown', destravarPendente);
            document.removeEventListener('touchstart', destravarPendente);
            destravarPendente = null;
        }
    }

    // --- As 5 trilhas ---

    function trilhaChuva(c, master) {
        var src = c.createBufferSource();
        src.buffer = criarBufferRuido(c);
        src.loop = true;
        var filtro = c.createBiquadFilter();
        filtro.type = 'lowpass';
        filtro.frequency.value = 1200;
        var lfo = c.createOscillator();
        lfo.frequency.value = 0.08;
        var lfoGain = c.createGain();
        lfoGain.gain.value = 150;
        lfo.connect(lfoGain);
        lfoGain.connect(filtro.frequency);
        lfo.start();
        src.connect(filtro).connect(master);
        src.start();
        nodes.push(src, filtro, lfo, lfoGain);
    }

    function trilhaMar(c, master) {
        var src = c.createBufferSource();
        src.buffer = criarBufferRuido(c);
        src.loop = true;
        var filtro = c.createBiquadFilter();
        filtro.type = 'lowpass';
        filtro.frequency.value = 500;
        var ondaGain = c.createGain();
        ondaGain.gain.value = 0.5;
        var lfo = c.createOscillator();
        lfo.frequency.value = 0.12; // ritmo lento de "vai e vem" da onda
        var lfoGain = c.createGain();
        lfoGain.gain.value = 0.4;
        lfo.connect(lfoGain);
        lfoGain.connect(ondaGain.gain);
        lfo.start();
        src.connect(filtro).connect(ondaGain).connect(master);
        src.start();
        nodes.push(src, filtro, ondaGain, lfo, lfoGain);
    }

    function trilhaFloresta(c, master) {
        var src = c.createBufferSource();
        src.buffer = criarBufferRuido(c);
        src.loop = true;
        var filtro = c.createBiquadFilter();
        filtro.type = 'bandpass';
        filtro.frequency.value = 700;
        filtro.Q.value = 0.6;
        var fundoGain = c.createGain();
        fundoGain.gain.value = 0.25;
        src.connect(filtro).connect(fundoGain).connect(master);
        src.start();
        nodes.push(src, filtro, fundoGain);

        // "passarinhos": bipes agudos aleatórios, reagendados infinitamente
        var ativo = { on: true };
        nodes.push({ disconnect: function () { ativo.on = false; } });
        function passaro() {
            if (!ativo.on) return;
            var osc = c.createOscillator();
            var g = c.createGain();
            osc.type = 'sine';
            osc.frequency.value = 1800 + Math.random() * 1200;
            g.gain.value = 0;
            osc.connect(g).connect(master);
            var t = c.currentTime;
            g.gain.linearRampToValueAtTime(0.06, t + 0.05);
            g.gain.linearRampToValueAtTime(0, t + 0.25);
            osc.start(t);
            osc.stop(t + 0.3);
            setTimeout(passaro, 1500 + Math.random() * 4000);
        }
        setTimeout(passaro, 1000);
    }

    function trilhaLofi(c, master) {
        // Pad suave com acordes que vão trocando devagar, infinito por natureza.
        var acordes = [
            [220.00, 261.63, 329.63],   // Am
            [174.61, 220.00, 261.63],   // F
            [196.00, 246.94, 293.66],   // G
            [164.81, 207.65, 246.94],   // Em
        ];
        var passo = 0;
        var ativo = { on: true };
        var ganhosAtuais = [];

        function tocarAcorde() {
            if (!ativo.on) return;
            ganhosAtuais.forEach(function (g) {
                try { g.gain.linearRampToValueAtTime(0, c.currentTime + 1.2); } catch (e) {}
            });
            ganhosAtuais = [];

            var notas = acordes[passo % acordes.length];
            notas.forEach(function (freq) {
                var osc = c.createOscillator();
                osc.type = 'triangle';
                osc.frequency.value = freq;
                var g = c.createGain();
                g.gain.value = 0;
                osc.connect(g).connect(master);
                osc.start();
                g.gain.linearRampToValueAtTime(0.045, c.currentTime + 1.5);
                nodes.push(osc, g);
                ganhosAtuais.push(g);
            });
            passo++;
            setTimeout(tocarAcorde, 6000);
        }
        nodes.push({ disconnect: function () { ativo.on = false; } });
        tocarAcorde();
    }

    function trilhaZen(c, master) {
        var src = c.createBufferSource();
        src.buffer = criarBufferRuido(c);
        src.loop = true;
        // Filtro suave pra aproximar de ruído rosa (mais grave, mais "cheio")
        var f1 = c.createBiquadFilter(); f1.type = 'lowpass'; f1.frequency.value = 900;
        var f2 = c.createBiquadFilter(); f2.type = 'highpass'; f2.frequency.value = 80;
        var drone = c.createOscillator();
        drone.type = 'sine';
        drone.frequency.value = 110;
        var droneGain = c.createGain();
        droneGain.gain.value = 0.04;
        drone.connect(droneGain).connect(master);
        drone.start();
        src.connect(f1).connect(f2).connect(master);
        src.start();
        nodes.push(src, f1, f2, drone, droneGain);
    }

    var GERADORES = [trilhaChuva, trilhaMar, trilhaFloresta, trilhaLofi, trilhaZen];

    // Ponto único de partida do áudio.
    //
    // O bug real: ao aplicar a trilha (ex.: entrando numa página nova, onde
    // o AudioContext sempre nasce "suspended"), o código só registrava um
    // reforço de destravamento por clique/tecla dentro do .catch() de
    // resume(). Só que em navegadores como Chrome e Safari, resume() chamado
    // FORA de um gesto do usuário não rejeita — ele simplesmente fica
    // pendurado em "suspended" (a promise não resolve nem rejeita de
    // verdade), então aquele .catch() quase nunca era chamado e o som ficava
    // mudo até uma ação qualquer em OUTRA parte do código acionar um
    // resume() por acaso (daí a impressão de que "sincronizava sozinho"
    // depois). Agora o destravador por clique/tecla/toque é sempre
    // registrado, não só quando a promise falha.
    function iniciarComContexto(indice) {
        try {
            var c = getCtx();
            limparDestravador();

            var realmenteIniciar = function () {
                pararTudo();
                var master = c.createGain();
                master.gain.value = 0.5;
                master.connect(c.destination);
                nodes.push(master);
                (GERADORES[indice] || GERADORES[0])(c, master);
                tocando = true;
            };

            if (c.state === 'running') {
                realmenteIniciar();
                return;
            }

            destravarPendente = function () {
                limparDestravador();
                c.resume().then(function () {
                    if (!tocando) realmenteIniciar();
                }).catch(function () {});
            };
            document.addEventListener('click', destravarPendente);
            document.addEventListener('keydown', destravarPendente);
            document.addEventListener('touchstart', destravarPendente);

            // Tenta mesmo assim: em algumas situações (origem já com
            // engajamento de mídia, ou o próprio clique que ligou o som)
            // resume() resolve na hora, sem precisar de outro gesto.
            c.resume().then(function () {
                if (c.state === 'running' && !tocando) {
                    limparDestravador();
                    realmenteIniciar();
                }
            }).catch(function () {});
        } catch (e) { /* Web Audio indisponível — ignora silenciosamente */ }
    }

    function aplicarEstado() {
        var cfg = lerConfig();
        trilhaAtual = cfg.trilha;
        if (cfg.ligado) {
            iniciarComContexto(trilhaAtual);
        } else {
            pararTudo();
        }
    }

    window.FocusSoundtrack = {
        TRILHAS: TRILHAS,
        getConfig: lerConfig,
        setEnabled: function (ligado) {
            var cfg = lerConfig();
            cfg.ligado = !!ligado;
            salvarConfig(cfg);
            aplicarEstado();
        },
        setTrilha: function (indice) {
            var cfg = lerConfig();
            cfg.trilha = Math.max(0, Math.min(4, indice));
            cfg.ligado = true; // escolher uma trilha já liga o som, pra bater com o que toca de verdade
            salvarConfig(cfg);
            trilhaAtual = cfg.trilha; // corrige o bug: antes só era atualizado ao recarregar a página
            iniciarComContexto(trilhaAtual);
        },
        isTocando: function () { return tocando; }
    };

    if (document.body) {
        aplicarEstado();
    } else {
        document.addEventListener('DOMContentLoaded', aplicarEstado);
    }
})();
