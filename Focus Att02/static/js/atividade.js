// Heartbeat de atividade no site — usado pelo Controle Parental pra saber
// quanto tempo o filho ficou de fato usando o Focus. Envia um "ping" a cada
// 60s, só enquanto a aba está visível (em primeiro plano), pra não contar
// tempo com o site aberto em segundo plano sem uso real.
(function () {
    const INTERVALO_MS = 60 * 1000;
    let intervalo = null;

    function enviarPing() {
        if (document.visibilityState !== "visible") return;
        fetch("/api/atividade/ping", { method: "POST", keepalive: true }).catch(() => {});
    }

    function iniciar() {
        if (intervalo) return;
        enviarPing();
        intervalo = setInterval(enviarPing, INTERVALO_MS);
    }

    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") enviarPing();
    });

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", iniciar);
    } else {
        iniciar();
    }
})();
