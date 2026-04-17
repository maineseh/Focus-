<?php
session_start();
require_once 'conexao.php';

// Verificação de segurança original
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// BUSCA OS DADOS EXATOS DO BANCO
// Aqui usamos 'username' conforme o seu código de setup de perfil
$stmt = $pdo->prepare("SELECT foto_perfil, username FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario_dados = $stmt->fetch();

/**
 * LÓGICA DE FOTO CORRIGIDA
 * Como o seu setup_perfil já salva "img/ex1.png" no banco, 
 * basta imprimir o valor puro que vem de 'foto_perfil'.
 */
$foto_perfil = !empty($usuario_dados['foto_perfil']) ? $usuario_dados['foto_perfil'] : 'img/padrao.png'; 
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
        
        /* PERFIL BLOQUEADO: Sem cursor pointer e sem clique */
        .profile-trigger { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            color: white; 
            padding: 5px 10px; 
            border-radius: 30px;
            cursor: default; 
        }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #facc15; background: #fff; object-fit: cover; }
        .profile-trigger span { font-size: 14px; font-weight: 600; }

        /* Dropdown removido permanentemente */
        .dropdown-menu { display: none !important; }

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
        
        .modal-content input:focus {
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
        <i class="fa-solid fa-check-circle" style="color: #facc15; margin-right: 10px;"></i>
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
            <li><a href="agenda.php"><i class="fa-solid fa-calendar-days"></i> Agenda</a></li>
        </ul>

        <div class="nav-right">
            <div class="profile-trigger">
                <span>@<?php echo htmlspecialchars($usuario_dados['username'] ?? 'Usuário'); ?></span>
                <img src="<?php echo $foto_perfil; ?>" alt="Perfil" class="profile-img">
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
            </div>
    </div>

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

    <div id="modalTarefa" class="modal">
        <div class="modal-content">
            <h3>Adicionar Tarefa</h3>
            <p>Disciplina: <strong id="disciplinaNomeModal"></strong></p>
            <input type="text" id="tarefaNome" placeholder="Nome da tarefa" required>
            <div class="modal-buttons">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('modalTarefa')">Cancelar</button>
                <button type="button" class="btn-modal-confirm" onclick="adicionarTarefa()">Adicionar</button>
            </div>
        </div>
    </div>

    <script>
        let currentDisciplinaId = null;
        let currentRating = 1;
        let disciplinas = JSON.parse(localStorage.getItem('disciplinas')) || [];

        function initStarRating() {
            const stars = document.querySelectorAll('#ratingStars .rating-star');
            if (!stars.length) return;
            function update(val) {
                stars.forEach(s => s.className = s.dataset.value <= val ? 'fa-solid fa-star rating-star active' : 'fa-regular fa-star rating-star');
            }
            stars.forEach(s => {
                s.onclick = () => { currentRating = s.dataset.value; update(currentRating); };
                s.onmouseenter = () => update(s.dataset.value);
            });
            document.getElementById('ratingStars').onmouseleave = () => update(currentRating);
            update(currentRating);
        }

        function showToast(msg) {
            const t = document.getElementById('toastMsg');
            document.getElementById('toastMessage').textContent = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        }

        function save() {
            localStorage.setItem('disciplinas', JSON.stringify(disciplinas));
            render();
        }

        function adicionarDisciplina() {
            const n = document.getElementById('disciplinaNome').value.trim();
            if(!n) return;
            disciplinas.push({ id: Date.now(), nome: n, dificuldade: currentRating, tarefas: [], expanded: false });
            save();
            closeModal('modalDisciplina');
            document.getElementById('disciplinaNome').value = '';
            showToast('Disciplina adicionada!');
        }

        function adicionarTarefa() {
            const n = document.getElementById('tarefaNome').value.trim();
            if(!n) return;
            const d = disciplinas.find(i => i.id === currentDisciplinaId);
            if(d) {
                d.tarefas.push({ id: Date.now(), nome: n, concluida: false });
                save();
                closeModal('modalTarefa');
                showToast('Tarefa salva!');
            }
        }

        function toggleTarefa(dId, tId) {
            const d = disciplinas.find(i => i.id === dId);
            if(d) {
                const t = d.tarefas.find(j => j.id === tId);
                if(t) t.concluida = !t.concluida;
                save();
            }
        }

        function deleteD(id) {
            if(confirm('Excluir disciplina?')) {
                disciplinas = disciplinas.filter(i => i.id !== id);
                save();
            }
        }

        function toggleD(id) {
            const d = disciplinas.find(i => i.id === id);
            if(d) d.expanded = !d.expanded;
            save();
        }

        function render() {
            const container = document.getElementById('disciplinasList');
            if(!disciplinas.length) {
                container.innerHTML = `<div class="empty-state"><i class="fa-solid fa-book-open"></i><h3>Nenhuma matéria ainda</h3></div>`;
                return;
            }
            container.innerHTML = disciplinas.map(d => {
                const perc = d.tarefas.length ? Math.round((d.tarefas.filter(t => t.concluida).length / d.tarefas.length) * 100) : 0;
                let stars = '';
                for(let i=1; i<=5; i++) stars += `<i class="fa-solid fa-star star ${i <= d.dificuldade ? 'active' : ''}"></i>`;
                
                return `
                <div class="disciplina-card ${d.expanded ? 'expanded' : ''}">
                    <div class="disciplina-header" onclick="toggleD(${d.id})">
                        <div class="disciplina-info">
                            <div class="disciplina-nome">${d.nome}</div>
                            <div style="margin-bottom:10px">${stars}</div>
                            <div style="display:flex; align-items:center; gap:10px">
                                <div class="progress-bar"><div class="progress-fill" style="width:${perc}%"></div></div>
                                <span class="progress-text">${perc}%</span>
                            </div>
                        </div>
                        <div class="disciplina-actions">
                            <button class="btn-add-tarefa" onclick="event.stopPropagation(); openModal('tarefa', ${d.id}, '${d.nome}')">Add Tarefa</button>
                            <button class="btn-delete" onclick="event.stopPropagation(); deleteD(${d.id})"><i class="fa-solid fa-trash"></i></button>
                            <i class="fa-solid fa-chevron-down dropdown-icon"></i>
                        </div>
                    </div>
                    <div class="tarefas-area">
                        <div class="tarefas-list">
                            ${d.tarefas.map(t => `
                                <div class="tarefa-item ${t.concluida ? 'concluida' : ''}">
                                    <input type="checkbox" ${t.concluida ? 'checked' : ''} onchange="toggleTarefa(${d.id}, ${t.id})">
                                    <span class="tarefa-nome" style="flex:1">${t.nome}</span>
                                </div>`).join('') || '<p style="text-align:center; color:#94a3b8">Sem tarefas.</p>'}
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function openModal(type, id=null, nome='') {
            if(type === 'disciplina') {
                document.getElementById('modalDisciplina').classList.add('show');
                initStarRating();
            } else {
                currentDisciplinaId = id;
                document.getElementById('disciplinaNomeModal').innerText = nome;
                document.getElementById('modalTarefa').classList.add('show');
            }
        }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }

        window.onclick = (e) => { if(e.target.classList.contains('modal')) closeModal(e.target.id); };

        document.addEventListener('DOMContentLoaded', render);
    </script>
</body>
</html>
