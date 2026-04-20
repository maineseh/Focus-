<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT foto_perfil, username FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario_dados = $stmt->fetch();

$username_exibir = !empty($usuario_dados['username']) ? $usuario_dados['username'] : 'usuário';
$foto_perfil = !empty($usuario_dados['foto_perfil']) ? $usuario_dados['foto_perfil'] : 'img/padrao.png';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Estudos | Focus OS Gold</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ================================================================
           ENGINE DE TEMAS DINÂMICOS
           ================================================================
        */
        :root {
            --primary: #1a2639;
            --primary-light: #2d3a5e;
            --accent: #facc15;
            --accent-glow: rgba(250, 204, 21, 0.4);
            --bg: #f1f5f9;
            --white: #ffffff;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-soft: #64748b;
            --border: #e2e8f0;
            --nav-bg: #1a2639;
            --btn-bg: rgba(0,0,0,0.03);
            --transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --radius-hud: 35px;
            --mood-overlay: transparent;
            --mood-intensity: 0;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: var(--mood-overlay);
            opacity: var(--mood-intensity);
            pointer-events: none;
            z-index: 9999;
            transition: opacity 0.8s ease, background 0.8s ease;
        }

        body.dark-theme {
            --primary: #facc15;
            --accent: #facc15;
            --bg: #020617;
            --card-bg: #0f172a;
            --text-main: #f1f5f9;
            --text-soft: #94a3b8;
            --border: #334155;
            --nav-bg: #020617;
            --btn-bg: rgba(255,255,255,0.05);
            --accent-glow: rgba(250, 204, 21, 0.2);
        }

        body.purple-theme {
            --primary: #a855f7;
            --primary-dark: #2e1065;
            --accent: #a855f7;
            --accent-glow: rgba(168, 85, 247, 0.6);
            --bg: #0f0720;
            --card-bg: #120626;
            --text-main: #f5f3ff;
            --text-soft: #c084fc;
            --border: #4c1d95;
            --nav-bg: #120626;
            --btn-bg: rgba(255,255,255,0.1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        body { 
            background-color: var(--bg); 
            min-height: 100vh; 
            color: var(--text-main);
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 35px 35px;
            line-height: 1.6;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* ================================================================
           NAVBAR 
           ================================================================
        */

        .navbar-main {
            background-color: var(--nav-bg);
            height: 85px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 9000;
            box-shadow: 0 4px 30px rgba(0,0,0,0.3);
            border-bottom: 2px solid var(--accent);
        }

        .nav-content {
            width: 100%; max-width: 1300px; padding: 0 40px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .nav-brand {
    display: flex;
    align-items: center;
    text-decoration: none;
    gap: 12px;

    position: relative;
}

/* Logo do gatinho */
.nav-logo {
    width: 80px;
    height: auto;

    transition: transform 0.3s ease;
}

/* efeito de aumento ao passar o mouse */
.nav-brand:hover .nav-logo {
    transform: scale(1.08);
}

/* Texto Focus */
.brand-text {
    font-size: 26px;
    font-weight: 900;
    color: white;

    letter-spacing: -0.5px;

    transition: 0.3s;
}

.nav-brand:hover .brand-text {
    color: var(--accent);
}

/* Lâmpada animada */
.nav-brand::after {
    content: '';
    position: absolute;

    top: -2px;
    left: 47px;

    width: 26px;
    height: 26px;

    background: radial-gradient(
        circle,
        rgba(250, 204, 21, 0.95) 0%,
        rgba(250, 204, 21, 0.4) 40%,
        rgba(250, 204, 21, 0) 70%
    );

    border-radius: 50%;

    animation: lampPulse 2.5s infinite ease-in-out;

    pointer-events: none;
}

/* Animação */
@keyframes lampPulse {

    0% {
        opacity: 0.4;
        transform: scale(0.9);
    }

    50% {
        opacity: 1;
        transform: scale(1.2);
    }

    100% {
        opacity: 0.4;
        transform: scale(0.9);
    }

}
        .nav-brand i { 
            font-size: 32px; color: var(--accent); 
        }

        .nav-brand h2 {
             font-size: 26px; font-weight: 800; color: white; letter-spacing: -1px;
             }

        .nav-links {
             display: flex; list-style: none; gap: 8px;
             }

        .nav-links a {
            color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 700;
            padding: 10px 18px; border-radius: 12px; transition: var(--transition);
            display: flex; align-items: center; gap: 8px;
        }

        .nav-links a:hover, .nav-links a.active {
             color: var(--accent); background: rgba(255,255,255,0.08); transform: translateY(-2px);
             }

        .profile-static {
             display: flex; align-items: center; gap: 15px;
             }

        .profile-static span {
             color: white; font-weight: 700; font-size: 15px;
             }

        .profile-img {
             width: 46px; height: 46px; border-radius: 50%; border: 3px solid var(--accent); object-fit: cover;
             }

        /* ================================================================
           HUD CONTENT & INTERATIVIDADE
           ================================================================
        */

        .container {
             max-width: 1200px; margin: 60px auto; padding: 0 20px; animation: slideUp 0.8s ease;
             }

        .header-section {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 50px; border-left: 12px solid var(--accent); padding-left: 25px;
        }

        .header-section h1 {
             font-size: 40px; font-weight: 900; color: var(--text-main); letter-spacing: -2px;
             }

        .btn-add {
            background: var(--nav-bg); color: var(--accent); border: 2px solid var(--accent);
            padding: 18px 35px; border-radius: 20px; cursor: pointer;
            font-size: 16px; font-weight: 900; display: flex; align-items: center; gap: 12px;
            transition: var(--transition); box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-transform: uppercase;
        }

        .btn-add:hover {
             transform: scale(1.1) rotate(1deg); box-shadow: 0 15px 45px var(--accent-glow); background: var(--accent); color: var(--nav-bg);
             }

        /* LISTA DE DISCIPLINAS */

        .disciplinas-list {
             display: flex; flex-direction: column; gap: 35px; margin-bottom: 60px;
             }

        .disciplina-card {
            background: var(--card-bg); border-radius: var(--radius-hud);
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            transition: var(--transition);
            border: 2px solid var(--border); border-left: 18px solid var(--nav-bg);
            overflow: hidden;
        }

        .disciplina-card:hover {
             transform: translateY(-5px) translateX(10px); border-color: var(--accent); box-shadow: 0 15px 40px rgba(0,0,0,0.1);
             }

        .disciplina-card.expanded {
             border-left-color: var(--accent);
             }

        .disciplina-header {
             padding: 45px 55px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;
             }

        .disciplina-nome {
             font-size: 32px; font-weight: 900; color: var(--text-main); margin-bottom: 12px;
             }

        .disciplina-card:hover .disciplina-nome { color: var(--accent);
     }
        .star {
             color: var(--border); font-size: 18px; margin-right: 5px; transition: 0.3s;
             }

        .star.active {
             color: var(--accent); text-shadow: 0 0 15px var(--accent-glow);
             }

        /* PROGRESS ENGINE */

        .progress-bar {
             flex: 1; height: 16px; background: var(--btn-bg); border-radius: 20px; overflow: hidden; border: 1px solid var(--border);
             }

        .progress-fill {
             height: 100%; background: linear-gradient(90deg, var(--accent), #f59e0b); transition: width 1.5s cubic-bezier(0.22, 1, 0.36, 1);
             }

        .progress-text {
             font-weight: 950; font-size: 20px; color: var(--text-main); min-width: 60px;
             }

        /* ACTIONS */

        .disciplina-actions {
             display: flex; align-items: center; gap: 15px;
             }

        .btn-add-tarefa {
            padding: 15px 30px; border-radius: 15px; font-weight: 900; border: none;
            cursor: pointer; transition: 0.3s; text-transform: uppercase; font-size: 12px;
            background: var(--accent); color: var(--nav-bg);
        }

        .btn-add-tarefa:hover {
             transform: scale(1.15); box-shadow: 0 5px 20px var(--accent-glow);
             }

        .btn-delete {
             background: rgba(239, 68, 68, 0.1); color: #ef4444; width: 60px; height: 60px; border: none; border-radius: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s;
             }

        .btn-delete:hover {
             background: #ef4444; color: white; transform: rotate(10deg);
             }

        /* TAREFAS AREA */

        .tarefas-area {
             max-height: 0; overflow: hidden; transition: max-height 0.8s cubic-bezier(0.4, 0, 0.2, 1); background: var(--btn-bg);
             }

        .expanded .tarefas-area {
             max-height: 2500px; border-top: 2px solid var(--border);
             }

        .tarefas-list {
             padding: 45px 65px; display: flex; flex-direction: column; gap: 18px;
             }

        .tarefa-item {
            background: var(--card-bg); padding: 25px 40px; border-radius: 28px;
            display: flex; align-items: center; gap: 30px;
            border: 1px solid var(--border); transition: 0.4s;
            animation: taskPop 0.5s ease backwards;
        }

        @keyframes taskPop {
             from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); }
             }

        .tarefa-item:hover {
             transform: translateX(15px); border-color: var(--accent);
             }

        .tarefa-checkbox {
             width: 32px; height: 32px; accent-color: var(--accent); cursor: pointer; transition: 0.3s;
             }

        .tarefa-checkbox:active {
             transform: scale(1.4);
             }

        .tarefa-nome {
             flex: 1; font-weight: 800; font-size: 19px; color: var(--text-main);
             }

        .concluida {
             border-left: 12px solid #10b981; background: rgba(16, 185, 129, 0.05);
             }

        .concluida .tarefa-nome {
             text-decoration: line-through; opacity: 0.5;
             }

        /* PROTOCOLO CARD */

        .protocol-card {
            background: var(--nav-bg); padding: 55px; border-radius: 50px;
            display: flex; align-items: center; gap: 45px; color: white;
            border: 2px solid var(--border); box-shadow: 0 35px 70px rgba(0,0,0,0.4);
            position: relative; overflow: hidden; margin-top: 50px;
        }

        .protocol-icon {
             width: 100px; height: 100px; background: var(--accent); border-radius: 35px; display: flex; align-items: center; justify-content: center; font-size: 45px; color: var(--nav-bg); flex-shrink: 0; box-shadow: 0 0 35px var(--accent-glow); animation: pulse-icon 2.5s infinite;
             }

        @keyframes pulse-icon {
             0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); }
             }

        .protocol-text h4 {
             color: var(--accent); font-size: 16px; text-transform: uppercase; letter-spacing: 4px; font-weight: 950; margin-bottom: 12px;
             }

        .protocol-text p {
             font-size: 19px; line-height: 1.8; color: #cbd5e1;
             }

        /* MODAIS */

        .modal {
             display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(15px); z-index: 99999; justify-content: center; align-items: center;
             }

        .modal.show {
             display: flex;
             }

        .modal-content {
             background: var(--card-bg); padding: 70px; border-radius: 60px; width: 95%; max-width: 600px; border: 2px solid var(--border); border-bottom: 25px solid var(--nav-bg); animation: modalIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
             }

        @keyframes modalIn {
             from { opacity: 0; transform: translateY(-100px); } to { opacity: 1; transform: translateY(0); }
             }

        .modal-content h3 {
             font-size: 42px; font-weight: 950; margin-bottom: 45px; text-align: center;
             }

        .modal-content input {
             width: 100%; padding: 28px; border: 3px solid var(--border); border-radius: 22px; background: var(--bg); color: var(--text-main); font-size: 20px; font-weight: 700; margin-bottom: 40px; outline: none; transition: 0.3s;
             }

        .modal-content input:focus {
             border-color: var(--accent);
             }

        .rating-stars {
             display: flex; gap: 12px; justify-content: center; margin-bottom: 35px;
             }

        .rating-star {
             font-size: 50px; cursor: pointer; color: var(--border); transition: 0.3s;
             }

        .rating-star.active {
             color: var(--accent); transform: scale(1.3); filter: drop-shadow(0 0 10px var(--accent-glow));
             }

        .btn-modal-confirm {
             background: var(--accent); color: var(--nav-bg); padding: 22px 45px; border-radius: 20px; font-weight: 950; border: none; cursor: pointer; text-transform: uppercase; font-size: 18px; transition: 0.3s;
             }

        .btn-modal-confirm:hover {
             transform: scale(1.05);
             }

        .btn-modal-cancel {
             background: var(--btn-bg); color: var(--text-soft); padding: 22px 45px; border-radius: 20px; font-weight: 950; border: none; cursor: pointer; text-transform: uppercase; margin-right: 15px;
             }

        /* TOAST */

        .toast {
             position: fixed; bottom: 50px; right: -700px; background: var(--nav-bg); color: white; padding: 30px 60px; border-radius: 30px; border-left: 12px solid var(--accent); box-shadow: 0 30px 60px rgba(0,0,0,0.5); z-index: 100000; transition: right 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55); display: flex; align-items: center; gap: 25px; font-weight: 900;
             }

        .toast.show {
             right: 50px;
             }

        @keyframes slideUp {
             from { opacity: 0; transform: translateY(60px); } to { opacity: 1; transform: translateY(0); }
             }
             
        @media (max-width: 1100px) {
             .nav-links, .profile-static span { display: none; } .header-section { flex-direction: column; text-align: center; gap: 35px; } .disciplina-header { flex-direction: column; text-align: center; gap: 35px; } .protocol-card { flex-direction: column; text-align: center; }
             }

    </style>

</head>

<body>

    <div id="toastMsg" class="toast">
        <i class="fa-solid fa-microchip fa-spin" style="color: var(--accent); font-size: 28px;"></i>
        <span id="toastMessage"></span>
    </div>

    <nav class="navbar-main">
        <div class="nav-content">
            <a href="dashboard.php" class="nav-brand">

    <img src="img/cat.png" class="nav-logo">

    <span class="brand-text">Focus</span>

</a>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Início</a></li>
                <li><a href="meus_estudos.php" class="active"><i class="fa-solid fa-book-bookmark"></i> Estudos</a></li>
                <li><a href="meu_desempenho.php"><i class="fa-solid fa-chart-line"></i> Desempenho</a></li>
                <li><a href="agenda.php"><i class="fa-solid fa-calendar-days"></i> Agenda</a></li>
            </ul>
            <div class="profile-static">
                <span>@<?php echo htmlspecialchars($username_exibir); ?></span>
                <img src="<?php echo $foto_perfil; ?>" class="profile-img">
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="header-section">
            <h1>Central de Módulos</h1>
            <button class="btn-add" onclick="openModal('disciplina')">
                <i class="fa-solid fa-plus-circle"></i> Adicionar Disciplina
            </button>
        </div>

        <div class="disciplinas-list" id="disciplinasList"></div>

        <div class="protocol-card">
            <div class="protocol-icon"><i class="fa-solid fa-shield-halved"></i></div>
            <div class="protocol-text">
                <h4>Disciplina de Operação Focus</h4>
                <p>Ambiente operacional para decomposição de disciplinas em atividades granulares. Monitore a sincronia dinâmica para otimizar seu ciclo de aprendizagem e produtividade neural.</p>
            </div>
        </div>
    </div>

    <div id="modalDisciplina" class="modal">
        <div class="modal-content">
            <h3>Nova Disciplina</h3>
            <input type="text" id="disciplinaNome" placeholder="Identificação da Matéria">
            <div class="rating-stars" id="ratingStars">
                <i class="fa-regular fa-star rating-star" data-value="1"></i>
                <i class="fa-regular fa-star rating-star" data-value="2"></i>
                <i class="fa-regular fa-star rating-star" data-value="3"></i>
                <i class="fa-regular fa-star rating-star" data-value="4"></i>
                <i class="fa-regular fa-star rating-star" data-value="5"></i>
            </div>
            <div style="text-align:right;">
                <button class="btn-modal-cancel" onclick="closeModal('modalDisciplina')">Abortar</button>
                <button class="btn-modal-confirm" onclick="adicionarDisciplina()">Implementar</button>
            </div>
        </div>
    </div>

    <div id="modalTarefa" class="modal">
        <div class="modal-content">
            <h3>Vincular Atividade</h3>
            <p>Destino: <strong id="disciplinaNomeModal" style="color:var(--accent)"></strong></p>
            <input type="text" id="tarefaNome" placeholder="Descrição da Tarefa">
            <div style="text-align:right;">
                <button class="btn-modal-cancel" onclick="closeModal('modalTarefa')">Cancelar</button>
                <button class="btn-modal-confirm" onclick="adicionarTarefa()">Registrar</button>
            </div>
        </div>
    </div>

    <script>
       
        let currentDisciplinaId = null;
        let currentRating = 3;
        let disciplinas;

try {
    disciplinas = JSON.parse(localStorage.getItem('disciplinas')) || [];
} catch (e) {
    disciplinas = [];
    localStorage.removeItem('disciplinas');
}

        document.addEventListener('DOMContentLoaded', () => {
            const currentTheme = localStorage.getItem('focus_theme') || 'default';
            if(currentTheme !== 'default') document.body.classList.add(currentTheme + '-theme');
            
            // MOTOR DE HUMOR OTIMIZADO (RECEPTOR)

            const energy = localStorage.getItem('focus_user_energy') || 3;
            const config = {
                1: { col: "rgba(255,0,0,0.08)", int: "0.15" },
                2: { col: "rgba(255,100,0,0.04)", int: "0.08" },
                4: { col: "rgba(250,204,21,0.04)", int: "0.08" },
                5: { col: "rgba(250,204,21,0.08)", int: "0.15" }
            };
            if(config[energy]) {
                document.documentElement.style.setProperty('--mood-overlay', config[energy].col);
                document.documentElement.style.setProperty('--mood-intensity', config[energy].int);
            }
            render();
        });

        function initStarRating() {
            const stars = document.querySelectorAll('#ratingStars .rating-star');
            function update(val) {
                stars.forEach(s => s.className = s.dataset.value <= val ? 'fa-solid fa-star rating-star active' : 'fa-regular fa-star rating-star');
            }
            stars.forEach(s => {
                s.onclick = () => { currentRating = parseInt(s.dataset.value); update(currentRating); };
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

    requestAnimationFrame(() => {

        localStorage.setItem(
            'disciplinas',
            JSON.stringify(disciplinas)
        );

        render();

    });

}

        function adicionarDisciplina() {
            const n = document.getElementById('disciplinaNome').value.trim();
            if(!n) return;
            disciplinas.push({ id: Date.now(), nome: n, dificuldade: currentRating, tarefas: [], expanded: false });
            save();
            closeModal('modalDisciplina');
            document.getElementById('disciplinaNome').value = '';
            showToast('Módulo Implementado!');
        }

        function adicionarTarefa() {
            const n = document.getElementById('tarefaNome').value.trim();
            if(!n) return;
            const d = disciplinas.find(i => i.id === currentDisciplinaId);
            if(d) {
                d.tarefas.push({ id: Date.now(), nome: n, concluida: false });
                save();
                closeModal('modalTarefa');
                document.getElementById('tarefaNome').value = '';
                showToast('Atividade Registrada!');
            }
        }

        function toggleTarefa(dId, tId) {
            const d = disciplinas.find(i => i.id === dId);
            if(d) {
                const t = d.tarefas.find(j => j.id === tId);
                if(t) {
                    t.concluida = !t.concluida;
                    save();
                    if(t.concluida) showToast('Tarefa Finalizada! 🚀');
                }
            }
        }

        function deleteD(id) {
            if(confirm('Excluir este módulo operacional permanentemente?')) {
                disciplinas = disciplinas.filter(i => i.id !== id);
                save();
                showToast('Módulo Removido.');
            }
        }

        function toggleD(id) {
            const d = disciplinas.find(i => i.id === id);
            if(d) {
                d.expanded = !d.expanded;
                save();
            }
        }

        function render() {
            const container = document.getElementById('disciplinasList');
            if(!disciplinas.length) {
                container.innerHTML = `<div style="text-align:center; padding:100px; border:3px dashed var(--border); border-radius:50px; opacity:0.5;"><i class="fa-solid fa-book-bookmark" style="font-size:70px;"></i><h3 style="margin-top:20px; font-weight:900;">AGUARDANDO PROTOCOLO...</h3></div>`;
                return;
            }
            container.innerHTML = disciplinas.map(d => {
                const completed = d.tarefas.filter(t => t.concluida).length;
                const perc = d.tarefas.length ? Math.round((completed / d.tarefas.length) * 100) : 0;
                let stars = '';
                for(let i=1; i<=5; i++) stars += `<i class="fa-solid fa-star star ${i <= d.dificuldade ? 'active' : ''}"></i>`;
                
                return `
                <div class="disciplina-card ${d.expanded ? 'expanded' : ''}">
                    <div class="disciplina-header" onclick="toggleD(${d.id})">
                        <div class="disciplina-info">
                            <div class="disciplina-nome">${d.nome}</div>
                            <div style="margin-bottom:15px">${stars}</div>
                            <div style="display:flex; align-items:center; gap:20px">
                                <div class="progress-bar"><div class="progress-fill" style="width:${perc}%"></div></div>
                                <span class="progress-text">${perc}%</span>
                            </div>
                        </div>
                        <div class="disciplina-actions">
                            <button class="btn-add-tarefa" onclick="event.stopPropagation(); openModal('tarefa', ${d.id}, '${d.nome}')">
                                <i class="fa-solid fa-plus"></i> ADD TAREFA
                            </button>
                            <button class="btn-delete" onclick="event.stopPropagation(); deleteD(${d.id})"><i class="fa-solid fa-trash-can"></i></button>
                            <i class="fa-solid fa-chevron-down" style="font-size:24px; margin-left:15px; transition:0.4s; ${d.expanded ? 'transform:rotate(180deg)' : ''}"></i>
                        </div>
                    </div>
                    <div class="tarefas-area">
                        <div class="tarefas-list">
                            ${d.tarefas.map(t => `
                                <div class="tarefa-item ${t.concluida ? 'concluida' : ''}">
                                    <input type="checkbox" class="tarefa-checkbox" ${t.concluida ? 'checked' : ''} onchange="toggleTarefa(${d.id}, ${t.id})">
                                    <span class="tarefa-nome">${t.nome}</span>
                                </div>`).join('') || '<p style="text-align:center; opacity:0.6; font-weight:800;">Nenhuma atividade vinculada.</p>'}
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
    </script>
</body>
</html>