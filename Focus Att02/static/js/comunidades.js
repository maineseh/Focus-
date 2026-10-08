// Comunidades — chat privado (DM) e chat de grupo.
// Usa polling simples (sem websocket) pra buscar mensagens novas, no mesmo
// espírito do heartbeat de atividade.js já usado no resto do site.
(function () {
    const ehGrupo = typeof COM_GRUPO_ID !== "undefined";
    const alvoId = ehGrupo ? COM_GRUPO_ID : COM_AMIGO_ID;

    function urlEnviar() {
        return ehGrupo ? `/comunidades/grupo/${alvoId}/mensagem` : `/comunidades/dm/${alvoId}/mensagem`;
    }
    function urlPoll() {
        return ehGrupo ? `/comunidades/grupo/${alvoId}/mensagens` : `/comunidades/dm/${alvoId}/mensagens`;
    }
    function urlFixar(id) {
        return ehGrupo ? `/comunidades/grupo/${alvoId}/fixar/${id}` : `/comunidades/dm/${alvoId}/fixar/${id}`;
    }
    function urlExcluirMensagem(id) {
        return `/comunidades/grupo/${alvoId}/mensagem/${id}/excluir`;
    }

    const caixaMensagens = document.getElementById("comMensagens");
    const form = document.getElementById("comForm");
    const campoTexto = document.getElementById("comTexto");
    const campoImagem = document.getElementById("comImagem");
    const caixaFixadas = document.getElementById("comFixadas");

    function escapeHtml(texto) {
        const div = document.createElement("div");
        div.textContent = texto;
        return div.innerHTML;
    }

    function linkificar(texto) {
        // Mesmo padrão do REGEX_LINK do servidor (moderar_mensagem/_linkificar_html
        // no app.py) — antes só pegava "http://", "https://" e "www.", então um
        // link tipo "meusite.com" (sem prefixo) passava na moderação do servidor
        // mas chegava como texto puro aqui, sem virar clicável.
        return escapeHtml(texto).replace(/(https?:\/\/[^\s]+|www\.[^\s]+|\b[a-z0-9-]+\.(?:com|net|org|br|io|gg|app|me)\b)/gi, (url) => {
            const href = url.toLowerCase().startsWith("http") ? url : `https://${url}`;
            return `<a href="${href}" target="_blank" rel="noopener noreferrer">${url}</a>`;
        });
    }

    function criarBolha(m) {
        const minha = m.autor_id === COM_MEU_ID;
        const div = document.createElement("div");
        div.className = `com-msg ${minha ? "minha" : "outra"} ${m.fixada ? "fixada" : ""}`;
        div.dataset.id = m.id;

        let conteudoHtml = "";
        if (ehGrupo && !minha) {
            conteudoHtml += `<span class="autor">@${escapeHtml(m.autor_username)}</span>`;
        }
        if (m.tipo === "imagem") {
            conteudoHtml += `<img class="com-msg-imagem" src="/static/${m.conteudo}">`;
        } else {
            conteudoHtml += linkificar(m.conteudo);
        }
        conteudoHtml += `<span class="hora">${escapeHtml(m.criado_em)}</span>`;

        const podeFixar = (ehGrupo ? window.COM_EH_ADMIN : true) && typeof m.id === "number";
        if (podeFixar) {
            conteudoHtml += `<button class="fixar-btn" title="Fixar"><i class="fa-solid fa-thumbtack"></i></button>`;
        }
        div.innerHTML = conteudoHtml;

        const btnFixar = div.querySelector(".fixar-btn");
        if (btnFixar) btnFixar.addEventListener("click", () => comFixar(m.id));

        return div;
    }

    function rolarParaFinal() {
        caixaMensagens.scrollTop = caixaMensagens.scrollHeight;
    }

    function adicionarMensagem(m) {
        caixaMensagens.appendChild(criarBolha(m));
        rolarParaFinal();
        atualizarFixadas();
    }

    function atualizarFixadas() {
        if (!caixaFixadas) return;
        const fixadas = Array.from(caixaMensagens.querySelectorAll(".com-msg.fixada"));
        if (fixadas.length === 0) {
            caixaFixadas.style.display = "none";
            return;
        }
        caixaFixadas.style.display = "block";
        caixaFixadas.innerHTML = "<strong><i class=\"fa-solid fa-thumbtack\"></i> Fixadas:</strong> " +
            fixadas.map(f => (f.textContent || "").slice(0, 60)).join(" · ");
    }

    window.comFixar = function (id) {
        fetch(urlFixar(id), { method: "POST" })
            .then(r => r.json())
            .then(() => {
                const bolha = caixaMensagens.querySelector(`[data-id="${id}"]`);
                if (bolha) bolha.classList.toggle("fixada");
                atualizarFixadas();
            })
            .catch(() => {});
    };

    window.comExcluirMensagem = function (id) {
        if (!confirm("Excluir essa mensagem pra todo mundo do grupo?")) return;
        fetch(urlExcluirMensagem(id), { method: "POST" })
            .then(r => r.json())
            .then(() => {
                const bolha = caixaMensagens.querySelector(`[data-id="${id}"]`);
                if (bolha) bolha.remove();
                atualizarFixadas();
            })
            .catch(() => {});
    };

    async function enviarTexto(texto) {
        // Envio otimista: mostra a mensagem na hora, sem esperar o servidor
        // responder, pra não parecer que o chat está lento. Se o servidor
        // recusar (filtro de segurança, por ex.), a bolha "temporária" é
        // removida e o motivo aparece no lugar dela.
        const idTemp = `tmp-${Date.now()}`;
        const bolhaTemp = criarBolha({
            id: idTemp,
            autor_id: COM_MEU_ID,
            autor_username: window.COM_MEU_USERNAME || "",
            tipo: "texto",
            conteudo: texto,
            fixada: false,
            criado_em: "enviando...",
        });
        bolhaTemp.classList.add("enviando");
        caixaMensagens.appendChild(bolhaTemp);
        rolarParaFinal();

        const corpo = new URLSearchParams();
        corpo.set("texto", texto);
        try {
            const resp = await fetch(urlEnviar(), { method: "POST", body: corpo });
            const dados = await resp.json();
            bolhaTemp.remove();
            if (!dados.ok) {
                alert(dados.motivo || "Não foi possível enviar essa mensagem.");
                return;
            }
            if (!caixaMensagens.querySelector(`[data-id="${dados.mensagem.id}"]`)) {
                adicionarMensagem(dados.mensagem);
            }
            comUltimoId = Math.max(comUltimoId, dados.mensagem.id);
        } catch (e) {
            bolhaTemp.remove();
            alert("Não foi possível enviar. Verifique sua conexão e tente de novo.");
        }
    }

    async function enviarImagem(arquivo) {
        const dadosForm = new FormData();
        dadosForm.set("imagem", arquivo);
        const resp = await fetch(urlEnviar(), { method: "POST", body: dadosForm });
        const dados = await resp.json();
        if (!dados.ok) {
            alert(dados.motivo || "Não foi possível enviar essa imagem.");
            return;
        }
        adicionarMensagem(dados.mensagem);
        comUltimoId = dados.mensagem.id;
    }

    if (form) {
        // Antes disso dependia só do evento "submit" (form.addEventListener
        // + hx-boost="false"). Como o usuário ainda via a página recarregar
        // ao enviar mesmo com essa proteção, tirei o botão de "submit" (que
        // dispara o comportamento nativo do form) e passei a tratar clique
        // no botão e Enter no campo de texto diretamente — assim nenhum
        // "submit" nativo chega a acontecer, então não tem como o htmx (ou
        // qualquer outra coisa) interceptar e recarregar a página.
        function tentarEnviar() {
            const texto = campoTexto.value.trim();
            if (!texto) return;
            campoTexto.value = "";
            enviarTexto(texto);
        }

        form.addEventListener("submit", (e) => {
            e.preventDefault();
            e.stopPropagation();
            tentarEnviar();
        });

        const btnEnviar = form.querySelector('button[type="submit"], .com-btn-enviar');
        if (btnEnviar) {
            btnEnviar.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                tentarEnviar();
            });
        }

        if (campoTexto) {
            campoTexto.addEventListener("keydown", (e) => {
                if (e.key === "Enter") {
                    e.preventDefault();
                    e.stopPropagation();
                    tentarEnviar();
                }
            });
        }
    }

    if (campoImagem) {
        campoImagem.addEventListener("change", () => {
            if (campoImagem.files && campoImagem.files[0]) {
                enviarImagem(campoImagem.files[0]);
                campoImagem.value = "";
            }
        });
    }

    function poll() {
        fetch(`${urlPoll()}?apos=${comUltimoId}`)
            .then(r => r.json())
            .then(dados => {
                (dados.mensagens || []).forEach(m => {
                    if (!caixaMensagens.querySelector(`[data-id="${m.id}"]`)) {
                        adicionarMensagem(m);
                    }
                    comUltimoId = Math.max(comUltimoId, m.id);
                });
            })
            .catch(() => {});
    }

    rolarParaFinal();
    atualizarFixadas();

    // Com hx-boost, esse arquivo é reexecutado do zero a cada troca de
    // "página" (o body é trocado via AJAX, nunca recarrega de verdade).
    // Sem isso, cada visita a uma DM/grupo criava um setInterval novo sem
    // nunca cancelar os anteriores — depois de algumas trocas de página
    // ficavam vários polls simultâneos rodando (e tentando atualizar uma
    // caixa de mensagens que já nem está mais na tela).
    if (window.__comPollInterval) clearInterval(window.__comPollInterval);
    window.__comPollInterval = setInterval(poll, 2000);

    if (window.__comVisibilityListener) {
        document.removeEventListener("visibilitychange", window.__comVisibilityListener);
    }
    window.__comVisibilityListener = () => {
        if (document.visibilityState === "visible") poll();
    };
    document.addEventListener("visibilitychange", window.__comVisibilityListener);
})();
