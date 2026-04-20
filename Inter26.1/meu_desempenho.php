<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT foto_perfil, username FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$u = $stmt->fetch();

$username_limpo = !empty($u['username']) ? $u['username'] : 'usuário';
$foto_perfil = !empty($u['foto_perfil']) ? $u['foto_perfil'] : 'img/padrao.png';

/**
 * LÓGICA DE DADOS
 */
$diasSemana = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];
$humorStatus = [];
$horasEstudo = [];
foreach ($diasSemana as $dia) {
    $horasEstudo[] = rand(100, 900) / 100; 
    $humorStatus[] = rand(1, 5);
}
$totalHoras = array_sum($horasEstudo);
$mediaHumor = round(array_sum($humorStatus) / 7, 1);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Desempenho | Focus OS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ================================================================
           DESIGN SYSTEM - ENGINE DE TEMAS DINÂMICOS
           ================================================================
        */
        :root {
            --primary: #1a2639;
            --accent: #facc15;
            --accent-glow: rgba(250, 204, 21, 0.4);
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-soft: #64748b;
            --border: #e2e8f0;
            --nav-bg: #1a2639;
            --btn-bg: rgba(0,0,0,0.03);
            --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        body.dark-theme {
            --primary: #facc15; 
            --accent: #facc15;
            --bg: #020617;
            --card-bg: #0f172a;
            --text: #f1f5f9;
            --text-soft: #94a3b8;
            --border: #1e293b;
            --nav-bg: #020617;
            --btn-bg: rgba(255,255,255,0.05);
        }

        body.purple-theme {
            --primary: #a855f7; 
            --primary-dark: #2e1065;
            --accent: #a855f7;
            --accent-glow: rgba(168, 85, 247, 0.6);
            --bg: #0f0720;
            --card-bg: #120626;
            --text: #f5f3ff;
            --text-soft: #c084fc;
            --border: #4c1d95;
            --nav-bg: #120626;
            --btn-bg: rgba(255,255,255,0.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; transition: background 0.3s ease, border-color 0.3s ease; }
        
        body { 
            background-color: var(--bg); 
            min-height: 100vh; 
            color: var(--text);
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
        }

        /* ================================================================
           NAVBAR PADRÃO 85PX (RESTAURADA)
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
            z-index: 1000;
            box-shadow: 0 4px 25px rgba(0,0,0,0.3);
            border-bottom: 2px solid var(--accent);
        }

        .nav-content {
            width: 100%; max-width: 1200px; padding: 0 40px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .nav-brand { display: flex; align-items: center; color: white; text-decoration: none; gap: 12px; }
        .nav-brand i { font-size: 32px; color: var(--accent); }
     
.nav-logo:hover {
    transform: scale(1.08);
}

/* CONTAINER DA LOGO */

.logo-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

/* GATO */

.nav-logo {
    width: 80px;
    height: auto;

    display: block;

    transition: transform 0.3s ease;

    /* sombra base */
    filter: drop-shadow(0 6px 12px rgba(0,0,0,0.28));
}

/* HOVER DO GATO */

.nav-logo:hover {
    transform: scale(1.08);
}

/* LUZ DA LÂMPADA (AGORA MAIS FORTE) */

.logo-light {
    position: absolute;

    /* posição correta da lâmpada */
    top: 6px;
    left: 50px;

    width: 18px;
    height: 18px;

    background: rgba(250, 204, 21, 1);

    border-radius: 50%;

    /* brilho mais intenso */
    filter: blur(10px);

    animation: lampGlow 2.5s infinite ease-in-out;
}

/* ANIMAÇÃO DA LUZ (FORTE COMO NAS OUTRAS PÁGINAS) */

@keyframes lampGlow {

    0% {
        opacity: 0.6;
        transform: scale(1);
    }

    50% {
        opacity: 1;
        transform: scale(1.35);
    }

    100% {
        opacity: 0.6;
        transform: scale(1);
    }

}

/* TEXTO FOCUS (HOVER RESTAURADO) */

.brand-text {
    font-size: 24px;
    font-weight: 800;
    color: white;

    transition: 0.3s;
}

/* HOVER DO TEXTO */

.nav-brand:hover .brand-text {
    color: var(--accent);
}

/* TEXTO FOCUS */

.brand-text {
    font-size: 24px;
    font-weight: 800;
    color: white;

    transition: 0.3s;
}

.nav-brand:hover .brand-text {
    color: var(--accent);

}
        .nav-links {
             display: flex; list-style: none; gap: 10px;
             }

        .nav-links a { 
            color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 700; 
            padding: 12px 18px; border-radius: 12px; transition: var(--transition);
            display: flex; align-items: center; gap: 8px;
        }

        .nav-links a:hover, .nav-links a.active {
             color: var(--accent); background: rgba(255,255,255,0.08);
             }

        /* PERFIL ESTÁTICO PADRÃO */

        .profile-static {
            display: flex; align-items: center; gap: 15px;
            padding: 8px 20px; border-radius: 40px;
        }

        .profile-static span {
             color: white; font-weight: 700; font-size: 16px;
             }

        .profile-img {
             width: 48px; height: 48px; border-radius: 50%; border: 3px solid var(--accent); object-fit: cover;
             }

        /* ================================================================
           CONTAINER E CARDS CALIBRADOS
           ================================================================
        */
        .container { 
            max-width: 1200px; margin: 50px auto; padding: 0 40px; 
            animation: slideUp 0.8s ease;
        }

        .header-title {
             margin-bottom: 40px; border-left: 10px solid var(--accent); padding-left: 20px;
             }

        .header-title h1 {
             font-size: 38px; font-weight: 900; color: var(--text); letter-spacing: -1.5px;
             }

        .header-title p {
             color: var(--text-soft); font-size: 16px; font-weight: 600;
             }

        /* MÉTRICAS */

        .metrics-grid {
             display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px;
             }

        .metric-card { 
            background: var(--card-bg); 
            padding: 35px 25px; 
            border-radius: 35px; 
            text-align: center; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            border: 1px solid var(--border); 
            border-bottom: 10px solid var(--accent);
            transition: var(--transition);
        }
        .metric-card:hover {
             transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.1);
             }

        .metric-card i {
             font-size: 32px; color: var(--accent); margin-bottom: 15px; display: block;
             }

        .metric-card h3 {
             font-size: 30px; font-weight: 900;
             }

        .metric-card p {
             font-size: 13px; font-weight: 800; color: var(--text-soft); text-transform: uppercase;
             }

        /* GRÁFICO */

        .chart-box { 
            background: var(--card-bg); padding: 40px; border-radius: 40px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.05); border: 1px solid var(--border);
            margin-bottom: 40px;
        }

        .chart-wrapper {
             height: 400px; width: 100%; position: relative;
             }

        /* INSIGHT AI */
        .insight-ai {
            background: var(--nav-bg); padding: 40px; border-radius: 40px;
            display: flex; align-items: center; gap: 30px; color: white;
            position: relative; overflow: hidden; border: 2px solid var(--border);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); transition: 0.5s;
        }

        .insight-ai::before {
             content: ''; position: absolute; top: -50%; left: -100%; width: 100%; height: 200%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent); transform: rotate(20deg); animation: shine 5s infinite;
             }

        @keyframes shine {
             0% { left: -100%; } 100% { left: 200%; }
             }

        .insight-icon {
             width: 80px; height: 80px; background: var(--accent); border-radius: 25px; display: flex; align-items: center; justify-content: center; font-size: 35px; color: var(--nav-bg); flex-shrink: 0; box-shadow: 0 0 20px var(--accent-glow); animation: brainPulse 2s infinite;
             }

        @keyframes brainPulse {
             0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); }
             }

        .ai-message {
             font-size: 19px; line-height: 1.5; font-weight: 500; min-height: 50px; color: #f1f5f9;
             }

        .ai-cursor {
             border-right: 3px solid var(--accent); animation: blink 0.7s infinite;
             }
             
        @keyframes blink {
             0%, 100% { opacity: 1; } 50% { opacity: 0; }
             }

        @keyframes slideUp {
             from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); }
             }

        @media (max-width: 950px) {
            .metrics-grid { grid-template-columns: 1fr; }
            .nav-links, .profile-static span { display: none; }
            .container { padding: 0 20px; }
            .insight-ai { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

    <nav class="navbar-main">
        <div class="nav-content">
           <a href="dashboard.php" class="nav-brand">

<div class="logo-wrap">

    <img src="img/cat.png" class="nav-logo" alt="Logo Focus">

    <span class="logo-light"></span>

</div>

<h2 class="brand-text">Focus</h2>

</a>
            <ul class="nav-links">
                <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Início</a></li>
                <li><a href="meus_estudos.php"><i class="fa-solid fa-graduation-cap"></i> Estudos</a></li>
                <li><a href="meu_desempenho.php" class="active"><i class="fa-solid fa-chart-line"></i> Desempenho</a></li>
                <li><a href="agenda.php"><i class="fa-solid fa-calendar-days"></i> Agenda</a></li>
            </ul>
            <div class="profile-static">
                <span>@<?php echo htmlspecialchars($username_limpo); ?></span>
                <img src="<?php echo $foto_perfil; ?>" class="profile-img">
            </div>
        </div>
    </nav>

    <div class="container">
        <header class="header-title">
            <h1>Mapeamento Analítico</h1>
            <p>Sincronização de performance e dados operacionais.</p>
        </header>

        <div class="metrics-grid">
            <div class="metric-card">
                <i class="fa-solid fa-clock"></i>
                <h3><?php echo number_format($totalHoras, 1); ?>h</h3>
                <p>Estudo Total</p>
            </div>
            <div class="metric-card">
                <i class="fa-solid fa-brain"></i>
                <h3><?php echo $mediaHumor; ?></h3>
                <p>Nível Operacional</p>
            </div>
            <div class="metric-card">
                <i class="fa-solid fa-bolt"></i>
                <h3><?php echo rand(70, 98); ?>%</h3>
                <p>Sincronia OS</p>
            </div>
        </div>

        <div class="chart-box">
            <div class="chart-wrapper">
                <canvas id="perfChart"></canvas>
            </div>
        </div>

        <div class="insight-ai">
            <div class="insight-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
            <div class="insight-text">
                <div class="ai-message" id="aiMsg"></div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const theme = localStorage.getItem('focus_theme') || 'default';
            if(theme !== 'default') document.body.classList.add(theme + '-theme');
            initChart(theme);
            startAI();
        });

        function initChart(theme) {
            const ctx = document.getElementById('perfChart').getContext('2d');
            const isDark = theme === 'dark' || theme === 'purple';
            new Chart(ctx, {
                data: {
                    labels: <?php echo json_encode($diasSemana); ?>,
                    datasets: [{
                        type: 'line', label: 'Humor', data: <?php echo json_encode($humorStatus); ?>,
                        borderColor: '#facc15', borderWidth: 5, tension: 0.4, yAxisID: 'yH'
                    }, {
                        type: 'bar', label: 'Horas', data: <?php echo json_encode($horasEstudo); ?>,
                        backgroundColor: isDark ? 'rgba(168, 85, 247, 0.6)' : '#1a2639', borderRadius: 10, yAxisID: 'yE'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        yE: { beginAtZero: true, grid: { color: isDark ? 'rgba(255,255,255,0.05)' : '#e2e8f0' }, ticks: { color: isDark ? '#94a3b8' : '#1e293b' } },
                        yH: { display: false, max: 6 }
                    },
                    plugins: { legend: { labels: { color: isDark ? '#94a3b8' : '#1e293b' } } }
                }
            });
        }

        function startAI() {
            const user = "<?php echo htmlspecialchars($username_limpo); ?>";
            const target = document.getElementById('aiMsg');
            let msg = "Mapeamento neural concluído, " + user + ". Sua performance operacional segue o padrão Focus OS de alta carga.";
            let i = 0;
            function type() { if (i < msg.length) { target.innerHTML = msg.substring(0, i + 1) + '<span class="ai-cursor"></span>'; i++; setTimeout(type, 35); } }
            setTimeout(type, 1000);
        }
    </script>
</body>

<script>
    document.addEventListener('DOMContentLoaded', () => {
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
    });
</script>
</html>