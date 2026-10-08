// Recorte + zoom de imagem, no estilo do editor de avatar do Discord —
// usado tanto na foto de perfil quanto no ícone de grupo. Em vez de só
// aceitar o arquivo cru (o que ficava feio, com a imagem inteira
// espremida/esticada num círculo), abre um modal onde a pessoa pode dar
// zoom e arrastar antes de confirmar.
(function () {
    let estado = null; // { img, escala, escalaMin, offsetX, offsetY, arquivoNome, aoConfirmar }

    const TAMANHO_VIEWPORT = 280;   // tamanho do "visor" quadrado no modal
    const TAMANHO_SAIDA = 480;      // resolução final salva

    function garantirModal() {
        if (document.getElementById('cropperModal')) return;
        const div = document.createElement('div');
        div.id = 'cropperModal';
        div.innerHTML = `
            <div class="cropper-backdrop">
                <div class="cropper-card">
                    <span class="cropper-titulo">Ajuste sua foto</span>
                    <div class="cropper-viewport" id="cropperViewport">
                        <canvas id="cropperCanvas" width="${TAMANHO_VIEWPORT}" height="${TAMANHO_VIEWPORT}"></canvas>
                    </div>
                    <div class="cropper-zoom-row">
                        <i class="fa-solid fa-magnifying-glass-minus"></i>
                        <input type="range" id="cropperZoom" min="1" max="3" step="0.01" value="1">
                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                    </div>
                    <div class="cropper-acoes">
                        <button type="button" class="com-btn ghost" id="cropperCancelar">Cancelar</button>
                        <button type="button" class="com-btn" id="cropperConfirmar"><i class="fa-solid fa-check"></i> Confirmar</button>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(div);

        const canvas = document.getElementById('cropperCanvas');
        let arrastando = false;
        let ultimoX = 0;
        let ultimoY = 0;

        function posicaoEvento(e) {
            if (e.touches && e.touches[0]) return { x: e.touches[0].clientX, y: e.touches[0].clientY };
            return { x: e.clientX, y: e.clientY };
        }

        function iniciarArraste(e) {
            arrastando = true;
            const p = posicaoEvento(e);
            ultimoX = p.x;
            ultimoY = p.y;
        }
        function moverArraste(e) {
            if (!arrastando || !estado) return;
            const p = posicaoEvento(e);
            estado.offsetX += p.x - ultimoX;
            estado.offsetY += p.y - ultimoY;
            ultimoX = p.x;
            ultimoY = p.y;
            limitarOffset();
            desenhar();
        }
        function pararArraste() { arrastando = false; }

        canvas.addEventListener('mousedown', iniciarArraste);
        window.addEventListener('mousemove', moverArraste);
        window.addEventListener('mouseup', pararArraste);
        canvas.addEventListener('touchstart', iniciarArraste, { passive: true });
        window.addEventListener('touchmove', moverArraste, { passive: true });
        window.addEventListener('touchend', pararArraste);

        document.getElementById('cropperZoom').addEventListener('input', (e) => {
            if (!estado) return;
            estado.escala = parseFloat(e.target.value);
            limitarOffset();
            desenhar();
        });

        document.getElementById('cropperCancelar').addEventListener('click', fecharModal);
        document.getElementById('cropperConfirmar').addEventListener('click', confirmar);
    }

    function limitarOffset() {
        // Não deixa arrastar a ponto de sobrar espaço vazio dentro do visor.
        const largura = estado.img.width * estado.escala;
        const altura = estado.img.height * estado.escala;
        const maxX = Math.max(0, (largura - TAMANHO_VIEWPORT) / 2);
        const maxY = Math.max(0, (altura - TAMANHO_VIEWPORT) / 2);
        estado.offsetX = Math.min(maxX, Math.max(-maxX, estado.offsetX));
        estado.offsetY = Math.min(maxY, Math.max(-maxY, estado.offsetY));
    }

    function desenhar() {
        const canvas = document.getElementById('cropperCanvas');
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, TAMANHO_VIEWPORT, TAMANHO_VIEWPORT);
        const largura = estado.img.width * estado.escala;
        const altura = estado.img.height * estado.escala;
        const x = TAMANHO_VIEWPORT / 2 - largura / 2 + estado.offsetX;
        const y = TAMANHO_VIEWPORT / 2 - altura / 2 + estado.offsetY;
        ctx.drawImage(estado.img, x, y, largura, altura);

        // Máscara circular por cima, só pra dar a noção de como vai ficar
        // (a imagem salva continua quadrada — o site já arredonda via CSS).
        ctx.save();
        ctx.globalCompositeOperation = 'destination-in';
        ctx.beginPath();
        ctx.arc(TAMANHO_VIEWPORT / 2, TAMANHO_VIEWPORT / 2, TAMANHO_VIEWPORT / 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }

    function fecharModal() {
        const modal = document.getElementById('cropperModal');
        if (modal) modal.style.display = 'none';
        estado = null;
    }

    function confirmar() {
        const saida = document.createElement('canvas');
        saida.width = TAMANHO_SAIDA;
        saida.height = TAMANHO_SAIDA;
        const ctx = saida.getContext('2d');
        const fator = TAMANHO_SAIDA / TAMANHO_VIEWPORT;
        const largura = estado.img.width * estado.escala * fator;
        const altura = estado.img.height * estado.escala * fator;
        const x = TAMANHO_SAIDA / 2 - largura / 2 + estado.offsetX * fator;
        const y = TAMANHO_SAIDA / 2 - altura / 2 + estado.offsetY * fator;
        ctx.drawImage(estado.img, x, y, largura, altura);
        saida.toBlob((blob) => {
            const callback = estado.aoConfirmar;
            const nome = estado.arquivoNome;
            fecharModal();
            if (blob && callback) callback(new File([blob], nome, { type: 'image/png' }));
        }, 'image/png', 0.92);
    }

    // abrirRecorteImagem(arquivo, aoConfirmar) — arquivo é um File (de um
    // <input type="file">), aoConfirmar(fileRecortado) roda quando a
    // pessoa confirma o ajuste.
    window.abrirRecorteImagem = function (arquivo, aoConfirmar) {
        if (!arquivo || !arquivo.type.startsWith('image/')) return;
        garantirModal();
        const modal = document.getElementById('cropperModal');
        modal.style.display = 'flex';

        const img = new Image();
        img.onload = () => {
            const escalaMin = Math.max(TAMANHO_VIEWPORT / img.width, TAMANHO_VIEWPORT / img.height);
            estado = {
                img, escala: escalaMin, escalaMin,
                offsetX: 0, offsetY: 0,
                arquivoNome: arquivo.name || 'foto.png',
                aoConfirmar,
            };
            const zoom = document.getElementById('cropperZoom');
            zoom.min = escalaMin;
            zoom.max = escalaMin * 3;
            zoom.step = (zoom.max - zoom.min) / 100;
            zoom.value = escalaMin;
            desenhar();
        };
        img.src = URL.createObjectURL(arquivo);
    };
})();
