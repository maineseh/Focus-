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

$username_exibir = !empty($usuario_dados['username']) ? $usuario_dados['username'] : 'Usuário';
$foto_perfil = !empty($usuario_dados['foto_perfil']) ? $usuario_dados['foto_perfil'] : 'img/padrao.png';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Focus OS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    :root {
        --primary: #5b7c99;      
        --primary-light: #7da0bd; 
        --accent: #ffd174;       
        --accent-glow: rgba(212, 173, 96, 0.3); 
        --bg: #f2efea;            
        --card-bg: #ffffff;
        --text: #455a64;          
        --text-soft: #78909c;
        --border: #d1d9e0;
        --nav-bg: #5b7c99;
        --btn-bg: rgba(0,0,0,0.03);
        --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        --nav-height: 85px;
        
       
        --mood-overlay: transparent;
        --mood-intensity: 0;
    }

    /* ADAPTAÇÕES COGNITIVAS INTEGRADAS */
    
    /* [Ansiedade/TEA]: Overlay com transição suave para não assustar */

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

    /* [TDAH]: Destaque lateral fixo nos cards para ancorar a atenção */

    .nav-card {
        border-left: 10px solid var(--accent) !important;
    }

    /* TEMAS ALTERNATIVOS PRESERVADOS */

    body.dark-theme {
    --primary: #d0d3d7;
    --accent: #d6d2c4;

    --bg: #1a2230;
    --card-bg: #242f3d;

    --text: #f7f9fc;
    --text-soft: #cbd5e1;

    --border: #3a4656;

    --nav-bg: #1a2230;

    --btn-bg: rgba(255,255,255,0.06);
}

  body.purple-theme {
    --primary: #8b7cff;
    --accent: #9a8cff;

    --bg: #f7f6fb;
    --card-bg: #ffffff;

    --text: #2c243d;
    --text-soft: #6f6785;

    --border: #e7e1f5;

    --nav-bg: #5e52b8;

    --btn-bg: rgba(139, 124, 255, 0.06);
}


    * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; }

    body {
        background-color: var(--bg);
        min-height: 100vh;
        color: var(--text);
        background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
        background-size: 30px 30px;
        overflow-x: hidden;
        padding-top: calc(var(--nav-height) + 40px);
    }

    .navbar-main {
        background-color: var(--nav-bg); height: var(--nav-height);
        display: flex; justify-content: center; align-items: center;
        position: fixed; top: 0; left: 0; width: 100%; z-index: 1000;
        box-shadow: 0 4px 25px rgba(0,0,0,0.1); border-bottom: 2px solid var(--accent);
    }

    .nav-content { width: 100%; max-width: 1200px; padding: 0 40px; display: flex; justify-content: space-between; align-items: center; }

    .nav-brand {
        display: flex; align-items: center; color: white; text-decoration: none; gap: 12px; position: relative; 
    }

    .nav-brand::after {
        content: '';
        position: absolute;
        top: -2px;
        left: 47px;
        width: 28px;
        height: 28px;
        background: radial-gradient(circle, rgba(212,173,96,0.95) 0%, rgba(212,173,96,0.5) 40%, transparent 70%);
        border-radius: 50%;
        animation: lampPulse 3s infinite ease-in-out;
        pointer-events: none;
    }

    @keyframes lampPulse {
        0% { opacity: 0.3; transform: scale(0.85); }
        50% { opacity: 1; transform: scale(1.3); }
        100% { opacity: 0.3; transform: scale(0.85); }
    }

    .nav-logo {
        width: 80px; height: auto;
        filter: drop-shadow(0 6px 12px rgba(0,0,0,0.1));
    }

    .nav-logo:hover { transform: scale(1.08); } 

.brand-text {
        font-size: 26px; font-weight: 900; color: white;
        letter-spacing: -0.5px; transition: 0.3s;
    }

    .nav-brand:hover .brand-text { color: var(--accent); }

    .nav-links { display: flex; list-style: none; gap: 10px; }

    .nav-links a { 
        color: #f2efea; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 18px; border-radius: 12px; transition: var(--transition); display: flex; align-items: center; gap: 8px; 
    }

    .nav-links a:hover, .nav-links a.active { 
        color: var(--accent); background: rgba(255,255,255,0.1); transform: translateY(-2px);
    }

    .profile-container { position: relative; }

    .profile-trigger { 
        display: flex; align-items: center; gap: 15px; cursor: pointer; padding: 8px 20px; border-radius: 40px; transition: var(--transition); 
    }

    .profile-trigger span { color: white; font-weight: 700; font-size: 16px; }

    .profile-img { 
        width: 48px; height: 48px; border-radius: 50%; border: 3px solid var(--accent); object-fit: cover; 
    }

    .dropdown-menu { 
        position: absolute; top: 75px; right: 0; background: var(--card-bg); width: 220px; border-radius: 18px; box-shadow: 0 15px 40px rgba(0,0,0,0.1); display: none; flex-direction: column; overflow: hidden; border: 1px solid var(--border); transform-origin: top right;
    }

    .dropdown-menu.show { 
        display: flex; animation: growIn 0.3s ease forwards; 
    }

    .dropdown-item { 
        padding: 15px 22px; text-decoration: none; color: var(--text); font-size: 15px; display: flex; align-items: center; gap: 12px; transition: 0.2s; 
    }

    .dropdown-item:hover { background: var(--btn-bg); padding-left: 28px; }

    .container { 
        max-width: 1200px; margin: 0 auto; padding: 0 40px; animation: slideUp 0.8s ease; 
    }

    .hud-header { margin-bottom: 50px; border-left: 10px solid var(--accent); padding-left: 20px; }

    .hud-header h1 { font-size: 42px; font-weight: 800; color: var(--text); letter-spacing: -1.5px; }

    .hud-header p { color: var(--text-soft); font-size: 18px; font-weight: 500; }

    .mood-card {
        background: var(--card-bg); padding: 60px; border-radius: 40px; box-shadow: 0 15px 50px rgba(0,0,0,0.05); margin-bottom: 40px; display: flex; align-items: center; gap: 80px; border: 2px solid var(--border); transition: var(--transition);
    }

    .battery-outer { 
        width: 100px; height: 200px; border: 6px solid var(--text); border-radius: 20px; position: relative; padding: 8px; display: flex; flex-direction: column-reverse; gap: 8px; background: var(--card-bg);
    }

    .battery-outer::after { 
        content: ''; width: 40px; height: 12px; background: var(--text); position: absolute; top: -18px; left: 50%; transform: translateX(-50%); border-radius: 5px 5px 0 0;
    }

    .battery-cell { width: 100%; height: 18%; background: var(--btn-bg); border-radius: 5px; transition: 0.5s; }

    .battery-cell.active.low {
        background: #e57373; box-shadow: 0 0 20px rgba(229, 115, 115, 0.4);
    }

    .battery-cell.active.mid {
        background: var(--accent); box-shadow: 0 0 20px var(--accent-glow);
    }

    .battery-cell.active.high {
        background: #81c784; box-shadow: 0 0 20px rgba(129, 199, 132, 0.4);
    }

    .battery-cell.active.high.pulse {
        animation: batteryPulse 2s infinite; /* Pulsação mais calma */
    }

    @keyframes batteryPulse {
        0% { filter: brightness(1); } 50% { filter: brightness(1.2); } 100% { filter: brightness(1); }
    }

    .mood-content h2 { font-size: 32px; font-weight: 800; margin-bottom: 10px; color: var(--text); }

    input[type="range"] { width: 100%; margin: 35px 0; accent-color: var(--accent); cursor: pointer; }

    .mood-status {
        display: inline-block; padding: 10px 25px; border-radius: 30px; background: var(--text); color: var(--card-bg); font-weight: 800; font-size: 14px; text-transform: uppercase;
    }

    .nav-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; }

    .nav-card {
        background: var(--card-bg); padding: 50px 30px; border-radius: 35px; text-decoration: none; color: var(--text); box-shadow: 0 15px 40px rgba(0,0,0,0.05); border: 2px solid var(--border); border-bottom: 10px solid var(--accent); transition: var(--transition); display: flex; flex-direction: column; align-items: center; text-align: center;
    }

    .nav-card:hover {
        transform: translateY(-15px); border-color: var(--accent); box-shadow: 0 25px 60px var(--accent-glow);
    }

    .nav-card i {
        font-size: 38px; margin-bottom: 25px; color: var(--accent); background: var(--btn-bg); width: 85px; height: 85px; display: flex; align-items: center; justify-content: center; border-radius: 25px; transition: var(--transition);
    }

    .nav-card:hover i { background: var(--accent); color: white; transform: rotate(-10deg); }

    .nav-card h3 { font-size: 22px; font-weight: 800; margin-bottom: 12px; }

    .nav-card p { font-size: 15px; color: var(--text-soft); font-weight: 500; line-height: 1.5; }

    @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

    @keyframes growIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }

    @media (max-width: 900px) {
        .nav-grid { grid-template-columns: 1fr; } 
        .mood-card { flex-direction: column; text-align: center; gap: 40px; } 
        .nav-links { display: none; }
    }

.hud-info-card {

    background: var(--nav-bg);

    padding: 55px;

    border-radius: 50px;

    display: flex;

    align-items: center;

    gap: 45px;

    color: white;

    border: 2px solid var(--border);

    box-shadow: 0 35px 70px rgba(0,0,0,0.2);

    position: relative;

    overflow: hidden;

    margin-top: 60px;

}

.hud-info-text h4 {

    font-size: 30px;

    font-weight: 900;

    margin-bottom: 14px;

    color: var(--text-main);

    letter-spacing: -0.5px;

    display: flex;

    align-items: center;

    gap: 10px;

}

.hud-info-icon {

    width: 100px;
    height: 100px;

    background: var(--accent);

    border-radius: 35px;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 45px;

    color: var(--nav-bg);

    flex-shrink: 0;

    box-shadow: 0 0 35px var(--accent-glow);

    position: relative;

    animation: brainFloat 3.5s ease-in-out infinite;

}

@keyframes brainFloat {

    0% {
        transform: translateY(0px);
        box-shadow: 0 0 20px var(--accent-glow);
    }

    50% {
        transform: translateY(-8px);
        box-shadow: 0 0 45px var(--accent-glow);
    }

    100% {
        transform: translateY(0px);
        box-shadow: 0 0 20px var(--accent-glow);
    }

}

.hud-info-icon i {

    animation: brainPulse 2.8s ease-in-out infinite;

}

@keyframes brainPulse {

    0% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.15);
    }

    100% {
        transform: scale(1);
    }

}

@media (max-width: 700px) {

    .hud-info-card {

        flex-direction: column;

        text-align: center;

    }

}

.focus-reminder {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.35);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    backdrop-filter: blur(6px);
}

.focus-reminder.show {
    display: flex;
}

.focus-box {
    background: var(--card-bg);
    padding: 35px;
    border-radius: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    border-left: 8px solid var(--accent);
    animation: popIn 0.3s ease;
}

.focus-box i {
    font-size: 32px;
    color: var(--accent);
}

.focus-box h4 {
    margin: 0;
    font-size: 18px;
    font-weight: 900;
    color: var(--text);
}

.focus-box p {
    margin: 5px 0 0;
    font-size: 14px;
    color: var(--text-soft);
}

.focus-box button {
    margin-left: auto;
    background: var(--accent);
    border: none;
    padding: 10px 18px;
    border-radius: 12px;
    font-weight: 800;
    cursor: pointer;
}

@keyframes popIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

</style>

</head>
<body>

    <nav class="navbar-main">
        <div class="nav-content">
           <a href="dashboard.php" class="nav-brand">

<img src="img/cat.png" class="nav-logo" alt="Logo Focus">

<span class="brand-text">Focus</span>

</a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> Início</a></li>
                <li><a href="meus_estudos.php"><i class="fa-solid fa-graduation-cap"></i> Estudos</a></li>
                <li><a href="meu_desempenho.php"><i class="fa-solid fa-chart-line"></i> Desempenho</a></li>
                <li><a href="agenda.php"><i class="fa-solid fa-bullseye"></i> Metas</a></li>
            </ul>
            <div class="profile-container">
                <div class="profile-trigger" onclick="toggleDropdown()">
                    <span>@<?php echo htmlspecialchars($username_exibir); ?></span>
                    <img src="<?php echo $foto_perfil; ?>" class="profile-img">
                </div>
                <div class="dropdown-menu" id="userDropdown">
                    <a href="perfil.php" class="dropdown-item"><i class="fa-solid fa-user"></i> Perfil</a>
                    <a href="configuracoes.php" class="dropdown-item"><i class="fa-solid fa-gear"></i> Configurações</a>
                    <a href="logout.php" class="dropdown-item" style="color: #ff4d4d;"><i class="fa-solid fa-power-off"></i> Sair</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <header class="hud-header">
            <h1>Olá, <?php echo htmlspecialchars($username_exibir); ?></h1>
            <p>Selecione uma área para gerenciar suas atividades.</p>
        </header>

        <section class="mood-card">
            <div class="battery-outer">
                <div class="battery-cell" data-v="1"></div>
                <div class="battery-cell" data-v="2"></div>
                <div class="battery-cell" data-v="3"></div>
                <div class="battery-cell" data-v="4"></div>
                <div class="battery-cell" data-v="5"></div>
            </div>
            <div class="mood-content">
                <h2>Estado de Energia</h2>
                <p>Sua bateria mental ajuda a calibrar sua produtividade para os desafios de hoje.</p>
                <input type="range" min="1" max="5" value="3" id="moodIn">
                <span class="mood-status" id="moodStatus">HUMOR: ESTÁVEL / OK 😐</span>
            </div>
        </section>

        <div class="nav-grid">
            <a href="meus_estudos.php" class="nav-card" style="animation-delay: 0.1s;">
                <i class="fa-solid fa-graduation-cap"></i>
                <h3>Meus Estudos</h3>
                <p>Acesse suas disciplinas, adicione atividades e acompanhe seu progresso.</p>
            </a>
            <a href="meu_desempenho.php" class="nav-card" style="animation-delay: 0.2s;">
                <i class="fa-solid fa-chart-line"></i>
                <h3>Desempenho</h3>
                <p>Analise estatísticas detalhadas sobre sua evolução e produtividade.</p>
            </a>
            <a href="agenda.php" class="nav-card" style="animation-delay: 0.3s;">
                <i class="fa-solid fa-calendar-check"></i>
                <h3>Agenda</h3>
                <p>Organize seu tempo, visualize prazos e mantenha tarefas sob controle.</p>
            </a>
        </div>

<div class="hud-info-card">

    <div class="hud-info-icon">
        <i class="fa-solid fa-brain"></i>
    </div>

    <div class="hud-info-text">

        <h4>Central Operacional Focus</h4>

        <p>
            O Focus organiza sua rotina em módulos inteligentes.
            Utilize os estudos para dividir disciplinas, acompanhe o desempenho,
            gerencie metas e monitore seu progresso diário em um ambiente
            otimizado para foco e clareza mental.
        </p>

    </div>


<div id="focusReminder" class="focus-reminder">
    <div class="focus-box">
        <i class="fa-solid fa-brain"></i>
        <div>
            <h4>Você ainda está aí?</h4>
            <p>Que tal retomar seus estudos por alguns minutos?</p>
        </div>
        <button onclick="closeReminder()">Continuar</button>
    </div>
</div>


</div>
    </div>

    <script>

        const slider = document.getElementById('moodIn');
        const cells = document.querySelectorAll('.battery-cell');
        const status = document.getElementById('moodStatus');
        const moodMap = {
            1: { t: "NÍVEL CRÍTICO 😢", c: "low", color: "rgba(255,0,0,0.12)", intensity: "0.2" },
            2: { t: "ENERGIA BAIXA 🥱", c: "low", color: "rgba(255,100,0,0.06)", intensity: "0.1" },
            3: { t: "ESTÁVEL / OK 😐", c: "mid", color: "transparent", intensity: "0" },
            4: { t: "MUITO BEM! 😊", c: "high", color: "rgba(250,204,21,0.06)", intensity: "0.1" },
            5: { t: "EXCELENTE! 🚀", c: "high", color: "rgba(250,204,21,0.12)", intensity: "0.2" }
        };

        function updateMood(v, save = true) {
            const m = moodMap[v];
            cells.forEach(c => {
                const lvl = parseInt(c.dataset.v);
                c.className = 'battery-cell';
                if(lvl <= v) {
                    c.classList.add('active', m.c);
                    if(v == 5) c.classList.add('pulse');
                }
            });
            status.innerText = `HUMOR: ${m.t}`;
            
            // Otimização: Muda variáveis CSS em vez de filtros pesados
            document.documentElement.style.setProperty('--mood-overlay', m.color);
            document.documentElement.style.setProperty('--mood-intensity', m.intensity);

            if(save) localStorage.setItem('focus_user_energy', v);
        }

        slider.oninput = (e) => updateMood(e.target.value);

        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('focus_theme') || 'default';
            if(savedTheme !== 'default') document.body.classList.add(savedTheme + '-theme');
            
            const savedEnergy = localStorage.getItem('focus_user_energy') || 3;
            slider.value = savedEnergy;
            updateMood(savedEnergy, false);
        });

        function toggleDropdown() { document.getElementById('userDropdown').classList.toggle('show'); }
        window.onclick = (e) => { if (!e.target.closest('.profile-container')) document.getElementById('userDropdown').classList.remove('show');

         }


let idleTimer;

function resetIdleTimer() {
    clearTimeout(idleTimer);

    idleTimer = setTimeout(() => {
        mostrarReminder();
    }, 20000); // 20 segundos
}

function mostrarReminder() {
    document.getElementById("focusReminder").classList.add("show");
}

function closeReminder() {
    document.getElementById("focusReminder").classList.remove("show");
    resetIdleTimer();
}

/* Detecta qualquer interação do usuário */
["mousemove", "keydown", "click", "scroll", "touchstart"].forEach(evt => {
    document.addEventListener(evt, resetIdleTimer);
});

/* inicia o sistema */
document.addEventListener("DOMContentLoaded", resetIdleTimer);


    </script>

</body>
</html>