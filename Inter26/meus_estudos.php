<?php
session_start();

if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['usuario_username'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['usuario_username'])) {
    header("Location: setup_perfil.php");
    exit;
}

// Busca a foto de perfil (se existir no banco, senão usa padrão)
$foto_perfil = 'img/padrao.png';
if (file_exists('img/perfil_' . $_SESSION['usuario_id'] . '.jpg')) {
    $foto_perfil = 'img/perfil_' . $_SESSION['usuario_id'] . '.jpg';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Estudos - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; }
        body { background-color: #f1f5f9; min-height: 100vh; }

        /* BARRA DE NAVEGAÇÃO SUPERIOR */
        .navbar-main {
            background-color: #1a2639;
            height: 70px;
            padding: 0 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .nav-brand { display: flex; align-items: center; color: white; text-decoration: none; gap: 10px; }
        .nav-brand i { font-size: 28px; color: #facc15; }
        .nav-brand h2 { font-size: 22px; letter-spacing: 1px; }

        .nav-links { display: flex; list-style: none; gap: 30px; margin-left: 50px; flex: 1; }
        .nav-links a { color: #cbd5e1; text-decoration: none; font-size: 15px; font-weight: 500; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .nav-links a:hover, .nav-links a.active { color: #facc15; }

        .nav-right { display: flex; align-items: center; gap: 20px; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; color: white; padding: 5px 10px; border-radius: 30px; transition: 0.3s; position: relative;}
        .profile-trigger:hover { background: rgba(255,255,255,0.1); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #facc15; background: #fff; object-fit: cover; }
        .profile-trigger span { font-size: 14px; font-weight: 600; }

        .dropdown-menu {
            position: absolute; top: 60px; right: 0; background: white; 
            min-width: 200px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            display: none; flex-direction: column; overflow: hidden; animation: slideDown 0.3s ease;
        }
        .dropdown-menu.show { display: flex; }
        .dropdown-menu a { padding: 12px 20px; color: #1e293b; text-decoration: none; font-size: 14px; transition: 0.2s; border-bottom: 1px solid #f1f5f9; }
        .dropdown-menu a:hover { background: #f8fafc; padding-left: 25px; color: #1a2639; }
        .dropdown-menu a.logout { color: #dc2626; border-bottom: none; }

        /* CONTEÚDO PRINCIPAL */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        
        /* CABEÇALHO */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-section h1 {
            color: #1e293b;
            font-size: 32px;
        }
        
        /* BOTÃO ADICIONAR DISCIPLINA */
        .btn-add {
            background: linear-gradient(135deg, #1a2639 0%, #2d3a5e 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        /* DISCIPLINAS */
        .disciplinas-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .disciplina-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            transition: 0.3s;
        }
        
        .disciplina-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid #facc15;
            transition: 0.3s;
        }
        
        .disciplina-header:hover {
            background: #f1f5f9;
        }
        
        .disciplina-info {
            flex: 1;
        }
        
        .disciplina-nome {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .disciplina-dificuldade {
            display: flex;
            gap: 4px;
            margin-bottom: 10px;
        }
        
        .star {
            color: #cbd5e1;
            font-size: 18px;
        }
        
        .star.active {
            color: #facc15;
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #facc15, #f59e0b);
            border-radius: 10px;
            transition: width 0.3s ease;
            width: 0%;
        }
        
        .progress-text {
            font-size: 14px;
            font-weight: 600;
            color: #1a2639;
            min-width: 45px;
        }
        
        .disciplina-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-add-tarefa {
            background: #facc15;
            color: #1a2639;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
        }
        
        .btn-add-tarefa:hover {
            background: #eab308;
            transform: scale(1.05);
        }
        
        .btn-delete {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: 0.3s;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            transform: scale(1.05);
        }
        
        .dropdown-icon {
            font-size: 24px;
            color: #64748b;
            transition: 0.3s;
        }
        
        .disciplina-header .dropdown-icon {
            transform: rotate(0deg);
        }
        
        .disciplina-card.expanded .disciplina-header .dropdown-icon {
            transform: rotate(180deg);
        }
        
        /* TAREFAS */
        .tarefas-area {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            background: #f8fafc;
        }
        
        .disciplina-card.expanded .tarefas-area {
            max-height: 1000px;
        }
        
        .tarefas-list {
            padding: 20px 25px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .tarefa-item {
            background: white;
            padding: 12px 18px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: 0.2s;
            border: 1px solid #e2e8f0;
        }
        
        .tarefa-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }
        
        .tarefa-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #facc15;
        }
        
        .tarefa-nome {
            flex: 1;
            font-size: 16px;
            color: #1e293b;
        }
        
        .tarefa-item.concluida .tarefa-nome {
            text-decoration: line-through;
            color: #94a3b8;
        }
        
        .delete-tarefa {
            color: #ef4444;
            cursor: pointer;
            font-size: 18px;
            transition: 0.2s;
        }
        
        .delete-tarefa:hover {
            color: #dc2626;
            transform: scale(1.1);
        }
        
        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 450px;
            animation: slideUp 0.3s ease;
        }
        
        .modal-content h3 {
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .modal-content input, .modal-content select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            margin-bottom: 20px;
            transition: 0.3s;
        }
        
        .modal-content input:focus, .modal-content select:focus {
            outline: none;
            border-color: #facc15;
        }
        
        .rating-stars {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            justify-content: center;
        }
        
        .rating-star {
            font-size: 35px;
            cursor: pointer;
            color: #cbd5e1;
            transition: 0.2s;
        }
        
        .rating-star:hover, .rating-star.active {
            color: #facc15;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .btn-modal-confirm {
            background: #1a2639;
            color: white;
        }
        
        .btn-modal-confirm:hover {
            background: #2d3a5e;
        }
        
        .btn-modal-cancel {
            background: #e2e8f0;
            color: #64748b;
        }
        
        .btn-modal-cancel:hover {
            background: #cbd5e1;
        }
        
        /* TOAST */
        .toast {
            position: fixed; bottom: 30px; right: -400px; background: #1a2639; color: white;
            padding: 18px 30px; border-radius: 12px; border-left: 6px solid #facc15;
            box-shadow: 0 15px 30px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 15px;
            z-index: 2000; font-weight: 600; transition: right 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .toast.show { right: 30px; }
        .toast i { color: #facc15; font-size: 22px; }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #cbd5e1;
        }
    </style>
</head>
<body>

    <div id="toastMsg" class="toast">
        <i class="fa-solid fa-check-circle"></i>
        <span id="toastMessage"></span>
    </div>

    <nav class="navbar-main">
        <a href="dashboard.php" class="nav-brand">
            <i class="fa-solid fa-anchor"></i>
            <h2>Focus</h2>
        </a>

        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Início</a></li>
            <li><a href="meus_estudos.php" class="active"><i class="fa-solid fa-graduation-cap"></i> Meus Estudos</a></li>
            <li><a href="#"><i class="fa-solid fa-calendar-days"></i> Agenda</a></li>
        </ul>

        <div class="nav-right">
            <div class="profile-trigger" onclick="toggleMenu()">
                <span>@<?php echo $_SESSION['usuario_username']; ?></span>
                <img src="<?php echo $foto_perfil; ?>" alt="Perfil" class="profile-img">
                <div id="dropdownMenu" class="dropdown-menu">
                    <a href="#"><i class="fa-solid fa-user-gear"></i> Perfil e Conta</a>
                    <a href="#"><i class="fa-solid fa-sliders"></i> Preferências</a>
                    <a href="logout.php" class="logout"><i class="fa-solid fa-power-off"></i> Sair do Sistema</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="header-section">
            <h1><i class="fa-solid fa-graduation-cap"></i> Meus Estudos</h1>
            <button class="btn-add" onclick="openModal('disciplina')">
                <i class="fa-solid fa-plus"></i> Adicionar Disciplina
            </button>
        </div>

        <div class="disciplinas-list" id="disciplinasList">
            <!-- As disciplinas serão carregadas via JavaScript -->
        </div>
    </div>

    <!-- MODAL PARA ADICIONAR DISCIPLINA -->
    <div id="modalDisciplina" class="modal">
        <div class="modal-content">
            <h3>Adicionar Disciplina</h3>
            <input type="text" id="disciplinaNome" placeholder="Nome da disciplina" required>
            <div class="rating-stars" id="ratingStars">
                <i class="fa-regular fa-star rating-star" data-value="1"></i>
                <i class="fa-regular fa-star rating-star" data-value="2"></i>
                <i class="fa-regular fa-star rating-star" data-value="3"></i>
                <i class="fa-regular fa-star rating-star" data-value="4"></i>
                <i class="fa-regular fa-star rating-star" data-value="5"></i>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('modalDisciplina')">Cancelar</button>
                <button type="button" class="btn-modal-confirm" onclick="adicionarDisciplina()">Adicionar</button>
            </div>
        </div>
    </div>

    <!-- MODAL PARA ADICIONAR TAREFA -->
    <div id="modalTarefa" class="modal">
        <div class="modal-content">
            <h3>Adicionar Tarefa</h3>
            <p style="color: #64748b; margin-bottom: 10px;">Disciplina: <strong id="disciplinaNomeModal"></strong></p>
            <input type="text" id="tarefaNome" placeholder="Nome da tarefa" required>
            <div class="modal-buttons">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('modalTarefa')">Cancelar</button>
                <button type="button" class="btn-modal-confirm" onclick="adicionarTarefa()">Adicionar</button>
            </div>
        </div>
    </div>

    <script>
        // Variável global para armazenar o ID da disciplina atual
        let currentDisciplinaId = null;
        let currentRating = 1;
        
        // Carregar dados do localStorage
        let disciplinas = JSON.parse(localStorage.getItem('disciplinas')) || [];
        
        // Sistema de estrelas para dificuldade
        function initStarRating() {
            const stars = document.querySelectorAll('#ratingStars .rating-star');
            const ratingStars = document.getElementById('ratingStars');
            
            if (!stars.length) return;
            
            function updateStars(value) {
                stars.forEach(star => {
                    const starValue = parseInt(star.dataset.value);
                    if (starValue <= value) {
                        star.className = 'fa-solid fa-star rating-star active';
                    } else {
                        star.className = 'fa-regular fa-star rating-star';
                    }
                });
            }
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    currentRating = parseInt(this.dataset.value);
                    updateStars(currentRating);
                });
                
                star.addEventListener('mouseenter', function() {
                    const value = parseInt(this.dataset.value);
                    stars.forEach(s => {
                        const starValue = parseInt(s.dataset.value);
                        if (starValue <= value) {
                            s.className = 'fa-solid fa-star rating-star';
                        } else {
                            s.className = 'fa-regular fa-star rating-star';
                        }
                    });
                });
            });
            
            if (ratingStars) {
                ratingStars.addEventListener('mouseleave', function() {
                    updateStars(currentRating);
                });
            }
            
            updateStars(currentRating);
        }
        
        // Função para mostrar toast
        function showToast(message) {
            const toast = document.getElementById('toastMsg');
            const toastMessage = document.getElementById('toastMessage');
            toastMessage.textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        // Função para salvar disciplinas no localStorage
        function saveDisciplinas() {
            localStorage.setItem('disciplinas', JSON.stringify(disciplinas));
            renderDisciplinas();
        }
        
        // Função para adicionar disciplina
        function adicionarDisciplina() {
            const nome = document.getElementById('disciplinaNome').value.trim();
            if (!nome) {
                showToast('Por favor, insira o nome da disciplina!');
                return;
            }
            
            const novaDisciplina = {
                id: Date.now(),
                nome: nome,
                dificuldade: currentRating,
                tarefas: [],
                expanded: false
            };
            
            disciplinas.push(novaDisciplina);
            saveDisciplinas();
            closeModal('modalDisciplina');
            document.getElementById('disciplinaNome').value = '';
            currentRating = 1;
            initStarRating();
            showToast('Disciplina adicionada com sucesso!');
        }
        
        // Função para adicionar tarefa
        function adicionarTarefa() {
            const nome = document.getElementById('tarefaNome').value.trim();
            if (!nome) {
                showToast('Por favor, insira o nome da tarefa!');
                return;
            }
            
            const disciplina = disciplinas.find(d => d.id === currentDisciplinaId);
            if (disciplina) {
                const novaTarefa = {
                    id: Date.now(),
                    nome: nome,
                    concluida: false
                };
                disciplina.tarefas.push(novaTarefa);
                saveDisciplinas();
                closeModal('modalTarefa');
                document.getElementById('tarefaNome').value = '';
                showToast('Tarefa adicionada com sucesso!');
            }
        }
        
        // Função para toggle da tarefa (marcar/desmarcar como concluída)
        function toggleTarefa(disciplinaId, tarefaId, checkbox) {
            const disciplina = disciplinas.find(d => d.id === disciplinaId);
            if (disciplina) {
                const tarefa = disciplina.tarefas.find(t => t.id === tarefaId);
                if (tarefa) {
                    tarefa.concluida = checkbox.checked;
                    saveDisciplinas();
                }
            }
        }
        
        // Função para deletar tarefa
        function deleteTarefa(disciplinaId, tarefaId, event) {
            event.stopPropagation();
            const disciplina = disciplinas.find(d => d.id === disciplinaId);
            if (disciplina) {
                disciplina.tarefas = disciplina.tarefas.filter(t => t.id !== tarefaId);
                saveDisciplinas();
                showToast('Tarefa removida!');
            }
        }
        
        // Função para deletar disciplina
        function deleteDisciplina(disciplinaId, event) {
            event.stopPropagation();
            if (confirm('Tem certeza que deseja excluir esta disciplina e todas as suas tarefas?')) {
                disciplinas = disciplinas.filter(d => d.id !== disciplinaId);
                saveDisciplinas();
                showToast('Disciplina removida!');
            }
        }
        
        // Função para toggle expandir/recolher disciplina
        function toggleDisciplina(disciplinaId) {
            const disciplina = disciplinas.find(d => d.id === disciplinaId);
            if (disciplina) {
                disciplina.expanded = !disciplina.expanded;
                saveDisciplinas();
            }
        }
        
        // Função para calcular progresso da disciplina
        function calcularProgresso(tarefas) {
            if (tarefas.length === 0) return 0;
            const concluidas = tarefas.filter(t => t.concluida).length;
            return Math.round((concluidas / tarefas.length) * 100);
        }
        
        // Função para renderizar as disciplinas
        function renderDisciplinas() {
            const container = document.getElementById('disciplinasList');
            
            if (disciplinas.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-book-open"></i>
                        <h3>Nenhuma disciplina cadastrada</h3>
                        <p>Clique no botão acima para adicionar sua primeira disciplina!</p>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = disciplinas.map(disciplina => {
                const progresso = calcularProgresso(disciplina.tarefas);
                const expandedClass = disciplina.expanded ? 'expanded' : '';
                
                return `
                    <div class="disciplina-card ${expandedClass}" data-id="${disciplina.id}">
                        <div class="disciplina-header" onclick="toggleDisciplina(${disciplina.id})">
                            <div class="disciplina-info">
                                <div class="disciplina-nome">${escapeHtml(disciplina.nome)}</div>
                                <div class="disciplina-dificuldade">
                                    ${gerarEstrelas(disciplina.dificuldade)}
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: ${progresso}%"></div>
                                    </div>
                                    <span class="progress-text">${progresso}%</span>
                                </div>
                            </div>
                            <div class="disciplina-actions">
                                <button class="btn-add-tarefa" onclick="event.stopPropagation(); openModal('tarefa', ${disciplina.id}, '${escapeHtml(disciplina.nome)}')">
                                    <i class="fa-solid fa-tasks"></i> Adicionar Tarefa
                                </button>
                                <button class="btn-delete" onclick="deleteDisciplina(${disciplina.id}, event)">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                            </div>
                        </div>
                        <div class="tarefas-area">
                            <div class="tarefas-list">
                                ${renderizarTarefas(disciplina.tarefas, disciplina.id)}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        // Função para renderizar as tarefas
        function renderizarTarefas(tarefas, disciplinaId) {
            if (tarefas.length === 0) {
                return `
                    <div style="text-align: center; padding: 20px; color: #94a3b8;">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <p>Nenhuma tarefa ainda. Adicione sua primeira tarefa!</p>
                    </div>
                `;
            }
            
            return tarefas.map(tarefa => `
                <div class="tarefa-item ${tarefa.concluida ? 'concluida' : ''}">
                    <input type="checkbox" class="tarefa-checkbox" 
                        ${tarefa.concluida ? 'checked' : ''} 
                        onchange="toggleTarefa(${disciplinaId}, ${tarefa.id}, this)">
                    <span class="tarefa-nome">${escapeHtml(tarefa.nome)}</span>
                    <i class="fa-solid fa-trash delete-tarefa" onclick="deleteTarefa(${disciplinaId}, ${tarefa.id}, event)"></i>
                </div>
            `).join('');
        }
        
        // Função para gerar estrelas
        function gerarEstrelas(dificuldade) {
            let estrelas = '';
            for (let i = 1; i <= 5; i++) {
                estrelas += `<i class="fa-solid fa-star star ${i <= dificuldade ? 'active' : ''}"></i>`;
            }
            return estrelas;
        }
        
        // Função para escapar HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Funções de modal
        function openModal(type, disciplinaId = null, disciplinaNome = '') {
            if (type === 'disciplina') {
                document.getElementById('modalDisciplina').classList.add('show');
                document.getElementById('disciplinaNome').value = '';
                currentRating = 1;
                initStarRating();
            } else if (type === 'tarefa' && disciplinaId) {
                currentDisciplinaId = disciplinaId;
                document.getElementById('disciplinaNomeModal').innerHTML = disciplinaNome;
                document.getElementById('tarefaNome').value = '';
                document.getElementById('modalTarefa').classList.add('show');
            }
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function toggleMenu() {
            document.getElementById("dropdownMenu").classList.toggle("show");
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
            if (!event.target.closest('.profile-trigger')) {
                var dropdown = document.getElementById("dropdownMenu");
                if (dropdown && dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        }
        
        // Inicializar a página
        document.addEventListener('DOMContentLoaded', () => {
            renderDisciplinas();
            initStarRating();
        });
    </script>
</body>
</html>
