<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT username, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$id_user]);
$user = $stmt->fetch();

$username_exibir = !empty($user['username']) ? $user['username'] : 'usuário';
$foto = !empty($user['foto_perfil']) ? $user['foto_perfil'] : 'img/padrao.png';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Premium | Focus OS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>

        /* 1. DESIGN PADRAO - OCEAN SAND  */

        :root {
            --primary: #5b7c99;
            --accent: #ffd174;
            --accent-glow: rgba(212, 173, 96, 0.4);
            --bg: #f2efea;
            --card-bg: #ffffff;
            --text-main: #455a64;
            --text-soft: #78909c;
            --border: #d1d9e0;
            --nav-bg: #5b7c99;
            --low: #81c784;    
            --mid: #f1962d;    
            --high: #e57373;   
            --transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

body.dark-theme {
    --primary: #c7ccd3;
    --primary-light: #e2e6ea;

    --accent: #d6d2c4;
    --accent-glow: rgba(214, 210, 196, 0.12);

    --bg: #1a2230;
    --card-bg: #242f3d;

    --text: #f7f9fc;
    --text-main: #f7f9fc;
    --text-soft: #cbd5e1;

    --border: #3a4656;

    --nav-bg: #1a2230;

    --btn-bg: rgba(255,255,255,0.06);

    --active-card-bg: #2c3a4d;
}

body.purple-theme {
    --primary: #8b7cff;
    --accent: #9a8cff;

    --bg: #f7f6fb;
    --card-bg: #ffffff;

    --text: #2c243d;
    --text-main: #2c243d;
    --text-soft: #6f6785;

    --border: #e7e1f5;
    --nav-bg: #5e52b8;

    --btn-bg: rgba(139, 124, 255, 0.04);

    --chart-grid: rgba(94, 82, 184, 0.1);
}

        * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; }

        body {
            background-color: var(--bg);
            min-height: 100vh;
            color: var(--text-main);
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* NAVBAR 85PX */

        .navbar-main {
            background-color: var(--nav-bg);
            height: 85px;
            display: flex; justify-content: center; align-items: center;
            position: sticky; top: 0; z-index: 9000;
            box-shadow: 0 4px 30px rgba(0,0,0,0.1);
            border-bottom: 2px solid var(--accent);
        }

        .nav-content {
            width: 100%; max-width: 1300px; padding: 0 40px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .nav-brand { display: flex; align-items: center; color: white; text-decoration: none; gap: 15px; }

        .logo-wrap { position: relative; display: flex; align-items: center; }

        .nav-logo {
            width: 80px; height: auto; display: block; 
            transition: transform 0.3s ease;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.1));
        }

        .nav-logo:hover {
            transform: scale(1.08);
        }

        .logo-light {
            position: absolute; top: 8px; left: 50px; width: 16px; height: 16px;
            background: rgba(250, 204, 21, 1); border-radius: 50%;
            filter: blur(10px);
            box-shadow: 0 0 18px rgba(250, 204, 21, 1), 0 0 30px rgba(250, 204, 21, 0.8);
            animation: lampGlow 2.5s infinite ease-in-out;
        }

        @keyframes lampGlow {
            0%, 100% { opacity: 0.6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.3); }
        }

        .brand-text { font-size: 24px; font-weight: 800; color: white; transition: 0.3s; }

        .nav-brand:hover .brand-text { color: var(--accent); }

        .nav-links { display: flex; list-style: none; gap: 10px; }

        .nav-links a {
            color: #f2efea; text-decoration: none; font-size: 14px; font-weight: 700;
            padding: 12px 18px; border-radius: 12px; transition: var(--transition);
            display: flex; align-items: center; gap: 8px;
        }

        .nav-links a:hover, .nav-links a.active { color: var(--accent); background: rgba(255,255,255,0.08); transform: translateY(-2px); }

        .profile-static { display: flex; align-items: center; gap: 15px; }

        .profile-static span { color: white; font-weight: 700; font-size: 15px; }

        .profile-img { width: 46px; height: 46px; border-radius: 50%; border: 3px solid var(--accent); object-fit: cover; }

        /* CONTAINER & GRID */

        .container {
            max-width: 1250px; margin: 60px auto; padding: 0 40px; animation: slideUp 0.8s ease;
        }

        .header-agenda { margin-bottom: 45px; border-left: 15px solid var(--accent); padding-left: 25px; }

        .header-agenda h1 { font-size: 44px; font-weight: 900; letter-spacing: -2px; color: var(--text-main); }

        .header-agenda p { color: var(--text-soft); }

        .week-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; margin-bottom: 50px; }

        .day-box {
            background: var(--card-bg); padding: 35px 10px; border-radius: 35px;
            text-align: center; box-shadow: 0 8px 20px rgba(0,0,0,0.03);
            border: 2px solid var(--border); border-bottom: 8px solid var(--border);
            transition: var(--transition); cursor: pointer; position: relative; overflow: hidden;
            color: var(--text-main);
        }

        .day-box:hover { transform: translateY(-12px); border-color: var(--accent); }

        .day-box.active-day { border-color: var(--accent); border-bottom-color: var(--accent) !important; box-shadow: 0 15px 40px var(--accent-glow); }

        .day-box.low-effort { border-bottom-color: var(--high); } 

        .day-box.mid-effort { border-bottom-color: var(--mid); }

        .day-box.high-effort { border-bottom-color: var(--low); } 

        .day-name { font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--text-soft); letter-spacing: 1.5px; }

        .day-num { font-size: 34px; font-weight: 900; display: block; margin: 10px 0; }

        /*  PLANNER CARD */

        .planner-card {
            background: var(--card-bg); padding: 50px; border-radius: 50px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.05); border: 2px solid var(--border);
            border-left: 20px solid var(--accent);
            position: relative; overflow: hidden;
            color: var(--text-main);
        }

        .suggestion-header {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px dashed var(--border);
        }

        .suggestion-header h2 { font-weight: 900; font-size: 28px; color: var(--text-main); }

        .suggestion-header p { color: var(--text-soft); }

        .mood-tag {
            background: var(--nav-bg); color: var(--accent); padding: 12px 25px; border-radius: 20px; font-weight: 900; text-transform: uppercase; font-size: 13px;
        }

        /* CARD DA META */

        .meta-box {
            background: var(--bg);
            padding: 45px; border-radius: 40px; border: 2px solid var(--border);
            animation: metaFloat 1.2s cubic-bezier(0.23, 1, 0.32, 1) backwards; 
            transition: all 0.4s ease;
            display: flex; align-items: center; justify-content: space-between; gap: 30px;
            position: relative; z-index: 1;
        }

        @keyframes metaFloat {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .meta-content-inner { flex: 1; }

        /* Tag com pulso Neural */

        .meta-tag-internal {
            display: inline-block; padding: 10px 22px; border-radius: 15px; font-size: 11px; font-weight: 900; color: white; text-transform: uppercase; margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            animation: tagPulse 2s infinite ease-in-out;
        }

        @keyframes tagPulse {
            0%, 100% { transform: scale(1); filter: brightness(1); }
            50% { transform: scale(1.05); filter: brightness(1.1); }
        }

        .meta-title { font-size: 26px; font-weight: 900; color: var(--text-main); display: block; margin-bottom: 10px; }

        .meta-desc { font-size: 17px; color: var(--text-soft); font-weight: 600; line-height: 1.6; }

        
        .btn-complete-compact {
            width: 75px; height: 75px; border-radius: 25px; border: none;
            background: var(--card-bg); font-size: 30px;
            cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .btn-complete-compact:hover {
            transform: scale(1.08);
            box-shadow: 0 12px 25px rgba(0,0,0,0.1);
        }

        .meta-box.done { 
            opacity: 0.6; 
            border-left: 12px solid var(--low); 
            background: rgba(16, 185, 129, 0.1); 
        }

        .meta-box.done .meta-title { text-decoration: line-through; }

        .meta-box.done .btn-complete-compact {
            background: var(--low);
            color: white !important;
            pointer-events: none;
        }

        /* TOAST */

        .toast-gold {
            position: fixed; bottom: 40px; right: -500px; background: var(--nav-bg); color: white; padding: 25px 45px; border-radius: 25px; border-left: 10px solid var(--accent); box-shadow: 0 20px 40px rgba(0,0,0,0.3); transition: 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55); z-index: 10000; font-weight: 800;
        }

        .toast-gold.show { right: 40px; }

        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1000px) { .week-grid { grid-template-columns: repeat(4, 1fr); } .nav-links { display: none; } .meta-box { flex-direction: column; text-align: center; } }


.hud-goals-card {

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

/* TEXTO */

.hud-goals-card .hud-info-text h4 {

    font-size: 30px;
    font-weight: 900;

    margin-bottom: 14px;

    color: #ffffff;

    letter-spacing: -0.5px;

    display: flex;
    align-items: center;
    gap: 10px;

}

.hud-goals-card .hud-info-text p {

    font-size: 15px;
    line-height: 1.6;

    color: rgba(255,255,255,0.85);

}

/* ÍCONE */

.hud-goals-card .hud-info-icon {

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

    animation: brainFloat 3.5s ease-in-out infinite;

}

/* ANIMAÇÃO (reutiliza a mesma do HUD) */

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

.hud-goals-card .hud-info-icon i {
    animation: brainPulse 2.8s ease-in-out infinite;
}

@keyframes brainPulse {

    0% { transform: scale(1); }
    50% { transform: scale(1.15); }
    100% { transform: scale(1); }

}

@media (max-width: 700px) {

    .hud-goals-card {
        flex-direction: column;
        text-align: center;
    }

}

    </style>
</head>
<div id="toastGold" class="toast-gold">
        <i class="fa-solid fa-bolt" style="color:var(--accent); margin-right: 15px;"></i>
        <span id="toastMessage">Protocolo Sincronizado</span>
    </div>

    <nav class="navbar-main">
        <div class="nav-content">
            <a href="dashboard.php" class="nav-brand">
                <div class="logo-wrap">
                    <img src="img/cat.png" class="nav-logo" alt="Logo">
                    <span class="logo-light"></span>
                </div>
                <h2 class="brand-text">Focus</h2>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Início</a></li>
                <li><a href="meus_estudos.php"><i class="fa-solid fa-graduation-cap"></i> Estudos</a></li>
                <li><a href="meu_desempenho.php"><i class="fa-solid fa-chart-line"></i> Desempenho</a></li>
                <li><a href="agenda.php" class="active"><i class="fa-solid fa-bullseye"></i> Metas</a></li>
            </ul>
            <div class="profile-static">
                <span>@<?php echo htmlspecialchars($username_exibir); ?></span>
                <img src="<?php echo $foto; ?>" class="profile-img">
            </div>
        </div>
    </nav>

    <div class="container">
        <header class="header-agenda">
            <h1>Carga Semanal</h1>
            <p>Meta Universal recalibrada para sua saúde mental.</p>
        </header>

        <div class="week-grid" id="weekGrid"></div>

        <div class="planner-card">
            <div class="suggestion-header">
                <div>
                    <h2>Objetivo do Protocolo</h2>
                    <p>Metas fixas geradas pelo sistema com base no seu humor.</p>
                </div>
                <div id="moodBadge" class="mood-tag">ANALISANDO...</div>
            </div>

            <div id="metaContainer"></div>
        </div>

     <div class="hud-goals-card">

    <div class="hud-info-icon">
        <i class="fa-solid fa-bullseye"></i>
    </div>

    <div class="hud-info-text">

        <h4>Central de Metas</h4>

        <p>
            O sistema de metas do Focus adapta automaticamente seus objetivos com base no nível de energia cognitiva detectado no seu perfil.
        </p>

    </div>

</div>

</div>

    </div>

    <script>
        
        const BibliotecaMetas = {
            1: [ 
                { t: "Foco de Manutenção", d: "A meta é organizar seus tópicos de estudo e limpar sua mesa de trabalho. Evite novos conteúdos hoje.", e: 1 },
                { t: "Rastreio de Dúvidas", d: "Liste 5 termos técnicos que você tem dificuldade e apenas pesquise o significado de um deles.", e: 1 },
                { t: "Input Suave", d: "Assista a um vídeo curto (máx 5min) sobre produtividade suave ou bem-estar.", e: 1 }
            ],
            2: [ 
                { t: "Início de Fluxo", d: "Assista a uma videoaula de 15 minutos e anote os 3 pontos mais importantes do conteúdo.", e: 2 },
                { t: "Resumo em Flashcard", d: "Crie 5 flashcards sobre o último módulo que você estudou e revise-os uma vez.", e: 2 },
                { t: "Micro-Prática", d: "Resolva 3 exercícios de nível fácil da sua disciplina principal para manter o engajamento.", e: 2 }
            ],
            3: [ 
                { t: "Ciclo Pomodoro", d: "Realize uma sessão de 45 minutos de estudo focado (Deep Work) sem nenhuma distração.", e: 3 },
                { t: "Mapa Mental Base", d: "Crie um mapa mental conectando o tema central da sua matéria a pelo menos 5 ramificações.", e: 3 },
                { t: "Bateria de Fixação", d: "Resolva 10 questões de nível médio e analise detalhadamente o porquê de cada acerto ou erro.", e: 3 }
            ],
            4: [ 
                { t: "Técnica Feynman", d: "Grave um áudio de 5 minutos explicando a matéria atual como se desse aula para um iniciante.", e: 4 },
                { t: "Simulado de Pressão", d: "Resolva 20 questões cronometrando exatamente 2 minutos para cada uma das perguntas.", e: 4 },
                { t: "Desafio Prático", d: "Crie um problema prático da vida real que envolva a teoria que você está estudando no momento.", e: 4 }
            ],
            5: [ 
                { t: "Sprint de Produção", d: "Avançar dois módulos inteiros e criar um guia de estudos rápido para quem vai começar.", e: 5 },
                { t: "Projeto de Domínio", d: "Desenvolva uma aplicação real (código, plano, texto ou cálculo) do conhecimento da semana.", e: 5 },
                { t: "Maratona Analítica", d: "Resolva 40 questões variadas e identifique qual o seu principal 'ponto cego' no desempenho.", e: 5 }
            ]
        };

        let currentMeta = null;
        let energia = parseInt(localStorage.getItem('focus_user_energy') || 3);

        document.addEventListener('DOMContentLoaded', () => {
            const theme = localStorage.getItem('focus_theme') || 'default';
            if(theme !== 'default') document.body.classList.add(theme + '-theme');
            gerarMeta();
            renderWeek();
        });

        function gerarMeta() {
            const moodNames = ["Crítica", "Baixa", "Estável", "Alta", "Máxima"];
            document.getElementById('moodBadge').innerText = `Energia: ${moodNames[energia - 1]}`;

            const opcoes = BibliotecaMetas[energia];
            const selecionada = opcoes[Math.floor(Math.random() * opcoes.length)];

            currentMeta = {
                id: Date.now(),
                titulo: selecionada.t,
                desc: selecionada.d,
                esforco: selecionada.e,
                concluida: false
            };
            renderMeta();
        }

        function renderMeta() {
            const container = document.getElementById('metaContainer');
            let color;
            if(energia === 1) color = 'var(--high)';      
            else if(energia === 5) color = 'var(--low)'; 
            else color = 'var(--mid)';                   

            container.innerHTML = `
                <div class="meta-box ${currentMeta.concluida ? 'done' : ''}" id="metaElement">
                    <div class="meta-content-inner">
                        <span class="meta-tag-internal" style="background:${color}">PRONTIDÃO NÍVEL ${currentMeta.esforco}</span>
                        <span class="meta-title">${currentMeta.titulo}</span>
                        <p class="meta-desc">${currentMeta.desc}</p>
                    </div>
                    <button class="btn-complete-compact" onclick="finalizarMeta()" title="Finalizar Objetivo" style="color:${color}">
                        <i class="fa-solid ${currentMeta.concluida ? 'fa-circle-check' : 'fa-check'}"></i>
                    </button>
                </div>
            `;
        }

        function renderWeek() {
            const grid = document.getElementById('weekGrid');
            grid.innerHTML = '';
            const now = new Date();
            const todayIdx = now.getDay();
            const daysShort = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

            daysShort.forEach((day, i) => {
                const isToday = (i === todayIdx);
                const d = new Date();
                d.setDate(now.getDate() - (todayIdx - i));
                
                grid.innerHTML += `
                    <div class="day-box ${isToday ? 'active-day' : ''} ${isToday ? getEffortClass() : ''}">
                        <span class="day-name">${day}</span>
                        <span class="day-num">${d.getDate()}</span>
                        ${isToday ? '<i class="fa-solid fa-location-dot" style="margin-top:10px; color:var(--accent)"></i>' : ''}
                    </div>
                `;
            });
        }

        function getEffortClass() {
            if(energia === 1) return "low-effort";  
            if(energia === 5) return "high-effort"; 
            return "mid-effort";
        }

        function finalizarMeta() {
            if(!currentMeta.concluida) {
                currentMeta.concluida = true;
                renderMeta();
                showToast("Protocolo de Meta Concluído com Sucesso! 🚀");
            }
        }

        function showToast(msg) {
            const t = document.getElementById('toastGold');
            document.getElementById('toastMessage').innerText = msg;
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 4000);
        }

    </script>
</body>
</html>