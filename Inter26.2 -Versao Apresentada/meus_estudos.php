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

        :root {
            --primary: #5b7c99;      
            --primary-light: #7da0bd; 
            --accent: #ffd174;        
            --accent-glow: rgba(212, 173, 96, 0.3);
            --bg: #f2efea;           
            --white: #ffffff;
            --card-bg: #ffffff;
            --text-main: #455a64;      
            --text-soft: #78909c;
            --border: #d1d9e0;
            --nav-bg: #5b7c99;
            --btn-bg: rgba(0,0,0,0.03);
            --transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --radius-hud: 35px;
            --mood-overlay: transparent;
            --mood-intensity: 0;
        }

        .disciplina-card { border-left: 18px solid var(--primary) !important; }
        .disciplina-card.expanded { border-left-color: var(--accent) !important; }

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
         --primary: #c7ccd3; 
         --primary-light: #e2e6ea; 
         --accent: #d6d2c4; 
         --accent-glow: rgba(214, 210, 196, 0.12); 
         --bg: #1a2230; 
         --card-bg: #242f3d; 
         --text-main: #f7f9fc; 
         --text-soft: #cbd5e1; 
         --border: #3a4656; 
         --nav-bg: #1a2230; 
         --btn-bg: rgba(255,255,255,0.06); 
         --active-card-bg: #2c3a4d; }

body.purple-theme {

    --primary: #8b7cff;
    --primary-light: #b6a9ff;
    --accent: #9a8cff;
    --accent-glow: rgba(154, 140, 255, 0.08);
    --bg: #f7f6fb;
    --card-bg: #ffffff;
    --text-main: #2c243d;
    --text-soft: #6f6785;
    --border: #e7e1f5;

    --nav-bg: #5e52b8;

    --btn-bg: rgba(139, 124, 255, 0.04);

    --active-card-bg: #f1effa;
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


        .navbar-main {
            background-color: var(--nav-bg);
            height: 85px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 9000;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
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

        .nav-logo {
            width: 80px;
            height: auto;
            transition: transform 0.3s ease;
        }

        .nav-brand:hover .nav-logo {
            transform: scale(1.08);
        }

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

        .nav-brand::after {
            content: '';
            position: absolute;
            top: -2px;
            left: 47px;
            width: 26px;
            height: 26px;
            background: radial-gradient(
                circle,
                rgba(212, 173, 96, 0.95) 0%,
                rgba(212, 173, 96, 0.4) 40%,
                rgba(212, 173, 96, 0) 70%
            );
            border-radius: 50%;
            animation: lampPulse 2.5s infinite ease-in-out;
            pointer-events: none;
        }

        @keyframes lampPulse {
            0% { opacity: 0.4; transform: scale(0.9); }
            50% { opacity: 1; transform: scale(1.2); }
            100% { opacity: 0.4; transform: scale(0.9); }
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
            color: #f2efea; text-decoration: none; font-size: 14px; font-weight: 700;
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
            background: var(--nav-bg); color: white; border: 2px solid var(--accent);
            padding: 18px 35px; border-radius: 20px; cursor: pointer;
            font-size: 16px; font-weight: 900; display: flex; align-items: center; gap: 12px;
            transition: var(--transition); box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            text-transform: uppercase;
        }

        .btn-add:hover {
            transform: scale(1.1) rotate(1deg); box-shadow: 0 15px 45px var(--accent-glow); background: var(--accent); color: var(--nav-bg);
        }

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

        .disciplina-card:hover .disciplina-nome { color: var(--accent); }

    
.difficulty-level {

    display: flex;

    gap: 6px;

    margin-bottom: 15px;

}


.difficulty-label {

    font-size: 14px;

    font-weight: 800;

    color: var(--text-soft);

    text-align: center;

    margin-bottom: 12px;

    text-transform: uppercase;

    letter-spacing: 1px;

}


.difficulty-level {

    display: flex;

    justify-content: center;

    gap: 10px;

    margin-bottom: 10px;

}

.diff-bar {

    width: 40px;

    height: 12px;

    border-radius: 10px;

    background: var(--border);

    transition: 0.25s;

    cursor: pointer;

    transform: scale(1);

}


.diff-bar:hover {

    transform: scale(1.15);

}


.diff-bar.active:nth-child(1) {
    background: #22c55e;
}

.diff-bar.active:nth-child(2) {
    background: #84cc16;
}

.diff-bar.active:nth-child(3) {
    background: #facc15;
}

.diff-bar.active:nth-child(4) {
    background: #f97316;
}

.diff-bar.active:nth-child(5) {
    background: #ef4444;
}


.difficulty-text {

    text-align: center;

    font-size: 13px;

    font-weight: 900;

    margin-top: 5px;

    color: var(--accent);

}


.diff-bar.active:nth-child(1) {
    background: #22c55e; 
}

.diff-bar.active:nth-child(2) {
    background: #84cc16;
}

.diff-bar.active:nth-child(3) {
    background: #facc15; 
}

.diff-bar.active:nth-child(4) {
    background: #f97316; 
}

.diff-bar.active:nth-child(5) {
    background: #ef4444; 
}

        .progress-bar {
            flex: 1; height: 16px; background: var(--btn-bg); border-radius: 20px; overflow: hidden; border: 1px solid var(--border);
        }

        .progress-fill {
            height: 100%; background: linear-gradient(90deg, var(--accent), #f59e0b); transition: width 1.5s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .progress-text {
            font-weight: 950; font-size: 20px; color: var(--text-main); min-width: 60px;
        }

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

    .protocol-card {
    background: linear-gradient(135deg, var(--nav-bg), #2f4456);
    padding: 55px;
    border-radius: 50px;

    display: flex;
    align-items: center;
    gap: 45px;

    color: white;

    border: 2px solid var(--border);
    box-shadow: 0 35px 70px rgba(0,0,0,0.25);

    position: relative;
    overflow: hidden;
    margin-top: 50px;

    transition: 0.4s ease;
}

.protocol-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 45px 90px rgba(0,0,0,0.35);
}

.protocol-icon {
    width: 100px;
    height: 100px;

    background: var(--accent);
    border-radius: 35px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 45px;
    color: white;

    flex-shrink: 0;

    box-shadow: 0 0 35px rgba(91, 124, 153, 0.35);
    animation: pulse-icon 2.5s infinite;
}

@keyframes pulse-icon {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.08); }
}

.protocol-text h4 {
    color: var(--accent);
    font-size: 18px;
    text-transform: uppercase;
    letter-spacing: 3px;
    font-weight: 900;
    margin-bottom: 10px;
}

.protocol-text p {
    font-size: 20px;
    line-height: 1.8;
    color: var(--text); 
    opacity: 0.92; 
}


.protocol-title {
    font-size: 34px;
    font-weight: 900;
    color: var(--text);
    margin-bottom: 15px;
    letter-spacing: -0.5px;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.75);
    backdrop-filter: blur(12px);
    z-index: 99999;
    justify-content: center;
    align-items: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: var(--card-bg);
    padding: 60px;
    border-radius: 40px;
    width: 95%;
    max-width: 600px;
    border: 1px solid var(--border);
    box-shadow: 0 25px 60px rgba(0,0,0,0.2);
    animation: modalIn 0.4s ease;
    color: var(--text);
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: translateY(-60px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-content h3 {
    font-size: 34px;
    font-weight: 900;
    margin-bottom: 35px;
    text-align: center;
    color: var(--text);
}

.modal-content input {
    width: 100%;
    padding: 22px;
    border: 1px solid var(--border);
    border-radius: 16px;
    background: var(--bg);
    color: var(--text);
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 30px;
    outline: none;
    transition: 0.3s;
}

.modal-content input:focus {
    border-color: var(--accent);
}

.rating-stars {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 25px;
}

.rating-star {
    font-size: 42px;
    cursor: pointer;
    color: var(--text-soft);
    transition: 0.3s;
}

.rating-star.active {
    color: var(--accent);
    transform: scale(1.2);
    filter: drop-shadow(0 0 8px var(--accent-glow));
}

        .btn-modal-confirm {
    background: var(--accent);
    color: var(--nav-bg);

    padding: 18px 40px;   /* tamanho melhor */
    border-radius: 16px;

    font-weight: 900;
    border: none;

    cursor: pointer;

    text-transform: uppercase;
    font-size: 15px;

    transition: 0.3s;

    margin-left: 10px; /* espaço entre botões */
}

.btn-modal-confirm:hover {
    transform: scale(1.05);
}

.btn-modal-cancel {

    background: var(--btn-bg);
    color: var(--text-soft);

    padding: 18px 40px;  /* mesmo tamanho */
    border-radius: 16px;

    font-weight: 900;
    border: none;

    cursor: pointer;

    text-transform: uppercase;
    font-size: 15px;

    transition: 0.3s;

}

.modal-buttons {

    display: flex;

    justify-content: center; /* centraliza */

    align-items: center;

    gap: 15px; /* espaço entre botões */

    margin-top: 35px; /* desce eles */

}

        .toast {
            position: fixed; bottom: 50px; right: -700px; background: var(--nav-bg); color: white; padding: 30px 60px; border-radius: 30px; border-left: 12px solid var(--accent); box-shadow: 0 30px 60px rgba(0,0,0,0.3); z-index: 100000; transition: right 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55); display: flex; align-items: center; gap: 25px; font-weight: 900;
        }

        .toast.show {
            right: 50px;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(60px); } to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 1100px) {
            .nav-links, .profile-static span { display: none; } 
            .header-section { flex-direction: column; text-align: center; gap: 35px; } 
            .disciplina-header { flex-direction: column; text-align: center; gap: 35px; } 
            .protocol-card { flex-direction: column; text-align: center; }
        }

       /* Notificação Lateral Adaptativa */
.notificacao-humor {
    position: fixed !important;
    top: 100px;
    right: -450px; /* Começa escondida */
    width: 350px;
    background: var(--card-bg) !important;
    padding: 25px;
    border-radius: 30px;
    border-left: 10px solid var(--accent);
    box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    z-index: 999999 !important; /* Valor altíssimo para ficar na frente de tudo */
    transition: right 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.notificacao-humor.show {
    right: 30px !important; /* Posição de entrada */
}

.notificacao-humor .timer-badge {
    background: var(--nav-bg);
    color: var(--accent);
    padding: 6px 14px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 900;
    align-self: flex-start;
    letter-spacing: 1px;
}

/* MICRO TAREFAS - MESMO PADRÃO VISUAL */

.microtask-section {
    margin-top: 40px;
}

.microtask-card {

    background: var(--card-bg);

    border-radius: var(--radius-hud);

    border: 2px solid var(--border);

    padding: 40px;

    transition: var(--transition);

}

.microtask-header {

    display: flex;

    align-items: center;

    gap: 25px;

    margin-bottom: 30px;

}

.microtask-icon {

    width: 70px;

    height: 70px;

    border-radius: 20px;

    background: var(--accent);

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 30px;

    color: var(--nav-bg);

}

.microtask-info h3 {

    font-size: 26px;

    font-weight: 900;

}

.microtask-info p {

    font-size: 16px;

    color: var(--text-soft);

}

.microtask-list {

    display: flex;

    flex-direction: column;

    gap: 15px;

}

.microtask-item {

    background: var(--btn-bg);

    padding: 18px 25px;

    border-radius: 20px;

    border-left: 10px solid var(--accent);

    font-weight: 700;

    transition: 0.3s;

}

.microtask-item:hover {

    transform: translateX(10px);

    border-left-color: var(--primary);

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
    <li><a href="meus_estudos.php" class="active"><i class="fa-solid fa-graduation-cap"></i> Estudos</a></li>
    <li><a href="meu_desempenho.php"><i class="fa-solid fa-chart-line"></i> Desempenho</a></li>
    <li><a href="agenda.php"><i class="fa-solid fa-bullseye"></i> Metas</a></li>
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

<!-- ======================================================
    SESSÃO DE MICRO TAREFAS DINÂMICAS
====================================================== -->

<div class="microtask-section">

    <div class="header-section" style="margin-top:60px;">
        <h1>Microtarefas Adaptativas</h1>
    </div>

    <div class="microtask-card" id="microtaskBox">

        <div class="microtask-header">

            <div class="microtask-icon">
                <i class="fa-solid fa-brain"></i>
            </div>

            <div class="microtask-info">

                <h3 id="microtaskTitle">
                    Sincronizando protocolo...
                </h3>

                <p id="microtaskDesc">
                    Aguarde a geração das microtarefas baseadas no seu estado.
                </p>

            </div>

        </div>

        <div class="microtask-list" id="microtaskList">

            <!-- tarefas vão aparecer aqui -->

        </div>

    </div>

</div>

        <div class="protocol-card">
    <div class="protocol-icon">
        <i class="fa-solid fa-shield-halved"></i>
    </div>

    <div class="protocol-text">
        <h4>Meus Estudos</h4>
        <p>
             Espaço dedicado à organização de disciplinas, tarefas e microtarefas de estudo.  
            O sistema estrutura atividades em blocos leves e progressivos, facilitando a execução sem sobrecarga.
            O objetivo é apoiar o aprendizado contínuo de forma equilibrada, mantendo consistência sem pressão excessiva.
        </p>
    </div>
</div>
    </div>

    <div id="modalDisciplina" class="modal">
        <div class="modal-content">
            <h3>Nova Disciplina</h3>
            <input type="text" id="disciplinaNome" placeholder="Identificação da Matéria">
           
            <div class="difficulty-label">
    Escolha o nível de dificuldade da disciplina
</div>

            <div class="difficulty-level" id="ratingStars">

    <div class="diff-bar" data-value="1"></div>

    <div class="diff-bar" data-value="2"></div>

    <div class="diff-bar" data-value="3"></div>

    <div class="diff-bar" data-value="4"></div>

    <div class="diff-bar" data-value="5"></div>

</div>
           <div class="modal-buttons">
    
    <button 
        class="btn-modal-cancel" 
        onclick="closeModal('modalDisciplina')"
    >
        Cancelar
    </button>

    <button 
        class="btn-modal-confirm" 
        onclick="adicionarDisciplina()"
    >
        Criar Disciplina
    </button>

</div>
        </div>
    </div>

    <div id="modalTarefa" class="modal">
        <div class="modal-content">
            <h3>Vincular Atividade</h3>
            <p>Destino: <strong id="disciplinaNomeModal" style="color:var(--accent)"></strong></p>
            <input type="text" id="tarefaNome" placeholder="Descrição da Tarefa">
            <div class="modal-buttons">

    <button 
        class="btn-modal-cancel" 
        onclick="closeModal('modalTarefa')"
    >
        Cancelar
    </button>

    <button 
        class="btn-modal-confirm" 
        onclick="adicionarTarefa()"
    >
        Adicionar Tarefa
    </button>

</div>
        </div>
    </div>

    <script>

        function agendarNotificacaoHumor() {
    // 1. Pega a energia salva. Se não existir, assume 3.
    const energiaRaw = localStorage.getItem('focus_user_energy');
    const energia = parseInt(energiaRaw) || 3;
    
    // 2. TEMPO DE TESTE (Mude depois para 300000 e 900000)
    // Se energia for 1 ou 2 (baixa): aparece em 3 segundos
    // Se energia for 3, 4 ou 5 (alta): aparece em 10 segundos
    const tempoEspera = (energia <= 2) ? 3000 : 10000; 

    const configs = {
        1: { m: "Sua bateria está no crítico. Que tal parar 5 minutos e beber água?", t: "ALERTA" },
        2: { m: "Energia baixa detectada. Vamos focar apenas no que for simples?", t: "DICA" },
        3: { m: "Ritmo estável. Momento ideal para avançar nos módulos.", t: "INFO" },
        4: { m: "Energia alta! Seu foco está excelente hoje.", t: "FOCO" },
        5: { m: "Estado de Flow! Você está rendendo o máximo possível.", t: "FLOW" }
    };

    const config = configs[energia];

    console.log("Notificação agendada para: " + tempoEspera + "ms | Energia: " + energia);

    setTimeout(() => {
        const div = document.createElement('div');
        div.className = 'notificacao-humor';
        div.innerHTML = `
            <span class="timer-badge">${config.t} OPERACIONAL</span>
            <div style="display:flex; gap:18px; align-items:center;">
                <div style="background:var(--btn-bg); width:50px; height:50px; border-radius:15px; display:flex; align-items:center; justify-content:center;">
                    <i class="fa-solid fa-brain" style="color:var(--accent); font-size:24px;"></i>
                </div>
                <p style="font-size:15px; font-weight:700; color:var(--text-main); margin:0; line-height:1.4;">${config.m}</p>
            </div>
            <button onclick="fecharNotificacao(this)" style="background:none; border:none; color:var(--text-soft); font-size:11px; font-weight:900; cursor:pointer; text-align:right; margin-top:5px; text-transform:uppercase;">Dispensar Protocolo</button>
        `;
        document.body.appendChild(div);
        
        // Ativa a animação de deslizar
        setTimeout(() => div.classList.add('show'), 100);
    }, tempoEspera);
}

// Função para fechar
function fecharNotificacao(btn) {
    const nav = btn.parentElement;
    nav.classList.remove('show');
    setTimeout(() => nav.remove(), 800);
}

// Chame no DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    agendarNotificacaoHumor();
});

function renderHumorSuggestion() {
    const config = regrasHumor[energia];
    
    // 3. Atualiza o HTML da notificação (vamos criar esse HTML abaixo)
    const boxSugestao = document.getElementById('sugestaoHumorBox');
    if(boxSugestao) {
        boxSugestao.style.display = 'flex';
        boxSugestao.style.borderLeft = `8px solid ${config.cor}`;
        boxSugestao.innerHTML = `
            <i class="fa-solid ${config.icon}" style="color: ${config.cor}; font-size: 24px;"></i>
            <div style="flex: 1;">
                <p style="margin: 0; font-size: 13px; font-weight: 800; text-transform: uppercase; color: ${config.cor};">Sugestão Operacional de Hoje</p>
                <p style="margin: 5px 0 0; font-size: 15px; font-weight: 600;">${config.msg}</p>
            </div>
            <div style="text-align: right; min-width: 80px;">
                <span style="display: block; font-size: 11px; font-weight: 800; opacity: 0.6;">TEMPO</span>
                <span style="font-size: 18px; font-weight: 900; color: ${config.cor};">${config.tempo}</span>
            </div>
        `;
    }
}

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

    const bars =
        document.querySelectorAll(
            '#ratingStars .diff-bar'
        );

    const difficultyNames = {

        1: "Muito Fácil",
        2: "Fácil",
        3: "Médio",
        4: "Difícil",
        5: "Muito Difícil"

    };

    const text =
        document.getElementById(
            'difficultyText'
        );

    function update(val) {

        bars.forEach(bar => {

            const value =
                parseInt(bar.dataset.value);

            if(value <= val) {

                bar.classList.add('active');

            } else {

                bar.classList.remove('active');

            }

        });

        if(text) {

            text.textContent =
                difficultyNames[val];

        }

    }

    bars.forEach(bar => {

        bar.onclick = () => {

            currentRating =
                parseInt(bar.dataset.value);

            update(currentRating);

        };

        bar.onmouseenter = () => {

            update(
                parseInt(bar.dataset.value)
            );

        };

    });

    document
        .getElementById('ratingStars')
        .onmouseleave =
            () => update(currentRating);

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
                let dificuldadeHTML = '<div class="difficulty-level">';

for(let i = 1; i <= 5; i++) {

    dificuldadeHTML += `
        <div class="diff-bar 
            ${i <= d.dificuldade ? 'active' : ''}">
        </div>
    `;

}

dificuldadeHTML += '</div>';
                
                return `
                <div class="disciplina-card ${d.expanded ? 'expanded' : ''}">
                    <div class="disciplina-header" onclick="toggleD(${d.id})">
                        <div class="disciplina-info">
                            <div class="disciplina-nome">${d.nome}</div>
                            <div style="margin-bottom:15px">${dificuldadeHTML}</div>
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

/* MICRO TAREFAS DINÂMICAS */

function gerarMicrotarefas() {

    const energiaRaw =
        localStorage.getItem('focus_user_energy');

    const energia =
        parseInt(energiaRaw) || 3;

    const microtarefas = {

        1: [
            "Respirar de forma lenta por 60 segundos",
            "Hidratar-se com um copo de água",
            "Organizar um item do ambiente de estudo"
        ],

        2: [
            "Ler um parágrafo do material",
            "Revisar uma anotação anterior",
            "Abrir e localizar o conteúdo principal"
        ],

        3: [
            "Resolver uma questão objetiva",
            "Revisar um conceito importante",
            "Ler duas páginas do conteúdo"
        ],

        4: [
            "Resolver duas questões com atenção total",
            "Elaborar um resumo estruturado",
            "Revisar o conteúdo por alguns minutos com foco  ativo"
        ],

        5: [
            "Resolver questões de maior complexidade",
            "Executar uma sequência de exercícios",
            "Realizar uma avaliação prática do conteúdo"
        ]

    };

    const titles = {

        1: "Modo Recuperação Ativa",
        2: "Modo Aquecimento Cognitivo",
        3: "Modo Ritmo Estável",
        4: "Modo Alta Performance",
        5: "Modo Flow Cognitivo"

    };

    const descs = {

        1: "Microações leves para restaurar energia mental.",
        2: "Atividades rápidas para ativar o foco.",
        3: "Manutenção do ritmo produtivo.",
        4: "Execução direcionada e eficiente.",
        5: "Aproveitamento máximo do estado mental."

    };

    const lista =
        microtarefas[energia];

    document.getElementById(
        "microtaskTitle"
    ).textContent =
        titles[energia];

    document.getElementById(
        "microtaskDesc"
    ).textContent =
        descs[energia];

    const container =
        document.getElementById(
            "microtaskList"
        );

    container.innerHTML = "";

    lista.forEach(tarefa => {

        const div =
            document.createElement("div");

        div.className =
            "microtask-item";

        div.textContent =
            tarefa;

        container.appendChild(div);

    });

}

setTimeout(() => {

    gerarMicrotarefas();

}, 30000);

setInterval(() => {

    gerarMicrotarefas();

}, 300000);

document.addEventListener(
    "visibilitychange",
    () => {

        if (!document.hidden) {

            gerarMicrotarefas();

        }

    }
);

    </script>

</body>
</html>