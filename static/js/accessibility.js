// Aplica as preferências de acessibilidade em qualquer página do Focus,
// lendo o que foi salvo em Configurações.
(function () {
    // Mapa de cores do overlay de humor, por nível de energia (1 a 5),
    // usado em todo o site. É a mesma escala usada no slider do Dashboard.
    var MOOD_MAP = {
        1: { color: "rgba(255,0,0,0.12)",    intensity: "0.2" },
        2: { color: "rgba(255,100,0,0.06)",  intensity: "0.1" },
        3: { color: "transparent",           intensity: "0" },
        4: { color: "rgba(250,204,21,0.06)", intensity: "0.1" },
        5: { color: "rgba(250,204,21,0.12)", intensity: "0.2" }
    };

    function lerConfigSalva() {
        var raw = localStorage.getItem('focus_accessibility');
        var config = {
            contrast: false,
            textSize: 'normal',
            reduceMotion: false,
            dyslexicFont: false,
            moodOverlay: true
        };
        if (raw) {
            try { config = Object.assign(config, JSON.parse(raw)); } catch (e) {}
        }
        return config;
    }

    function aplicarMoodOverlay(config) {
        if (config.moodOverlay === false) {
            document.documentElement.style.setProperty('--mood-overlay', 'transparent');
            document.documentElement.style.setProperty('--mood-intensity', '0');
            return;
        }
        var energia = parseInt(localStorage.getItem('focus_user_energy'), 10) || 3;
        var m = MOOD_MAP[energia] || MOOD_MAP[3];
        document.documentElement.style.setProperty('--mood-overlay', m.color);
        document.documentElement.style.setProperty('--mood-intensity', m.intensity);
    }

    function aplicarAcessibilidade() {
        var config = lerConfigSalva();

        document.body.classList.toggle('high-contrast', !!config.contrast);
        document.body.classList.toggle('reduce-motion', !!config.reduceMotion);
        document.body.classList.toggle('dyslexic-font', !!config.dyslexicFont);

        document.body.classList.remove('text-medio', 'text-grande');
        if (config.textSize === 'medio') document.body.classList.add('text-medio');
        if (config.textSize === 'grande') document.body.classList.add('text-grande');

        aplicarMoodOverlay(config);
    }

    if (document.body) {
        aplicarAcessibilidade();
    } else {
        document.addEventListener('DOMContentLoaded', aplicarAcessibilidade);
    }

    // Exposto para outras páginas (ex.: o slider ao vivo do Dashboard) poderem
    // checar/aplicar o overlay sem duplicar o mapa de cores.
    window.FocusAccessibility = {
        isMoodOverlayEnabled: function () {
            return lerConfigSalva().moodOverlay !== false;
        },
        applyMoodOverlay: aplicarMoodOverlay
    };
})();
