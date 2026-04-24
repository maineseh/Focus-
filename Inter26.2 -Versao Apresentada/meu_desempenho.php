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

/* ==========================
   DADOS REALISTAS DO SISTEMA
========================== */

$diasSemana = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];

$horasEstudo = [];
$humorStatus = [];

/* estado mental inicial equilibrado */
$energia = rand(55, 75) / 10;
$fadiga = rand(15, 30) / 10;
$estresse = rand(10, 25) / 10;
$concentracao = rand(60, 80) / 10;

for ($i = 0; $i < 7; $i++) {

    $ciclo = sin(($i / 6) * M_PI * 1.1);
    $ruido = rand(-12, 12) / 10;

    /* eventos mais imprevisíveis */
    $evento = 0;
    $roll = rand(1, 100);

    if ($roll <= 6) $evento = rand(8, 15) / 10;     // pico bom
    elseif ($roll >= 92) $evento = rand(-15, -8) / 10; // queda forte
    elseif ($roll >= 70 && $roll <= 75) $evento = rand(-5, 5) / 10;

    /* HORAS (LIMITADAS E REALISTAS) */
    $horas = (
        $energia * 0.45 +
        $ciclo * 0.9 +
        $evento -
        $fadiga * 0.35 +
        $concentracao * 0.08
    );

    $horas += $ruido;

    /* 🔥 limite real humano (FORTE) */
    $horas = max(0.8, min(4.0, $horas));

    /* evolução leve e realista */
    if ($horas < 2) {
        $fadiga += 0.25;
        $estresse += 0.2;
        $concentracao -= 0.1;
    } elseif ($horas > 3.2) {
        $fadiga -= 0.15;
        $concentracao += 0.1;
    } else {
        $fadiga += 0.05;
    }

    $fadiga = max(1, min(4.5, $fadiga));
    $estresse = max(0.5, min(4, $estresse));
    $concentracao = max(5, min(9, $concentracao));

    /* ==========================
       HUMOR MUITO MAIS ALEATÓRIO
    ========================== */

    $humor = rand(1, 5);

    // caos leve controlado
    $humor += rand(-1, 1);

    if ($horas > 3.2) $humor += rand(0, 1);
    if ($horas < 1.5) $humor -= rand(0, 2);

    if ($fadiga > 3) $humor -= rand(0, 1);
    if ($estresse > 3) $humor -= rand(0, 1);

    if ($concentracao > 7.5) $humor += rand(0, 1);

    $humor = max(1, min(5, $humor));

    $horasEstudo[] = round($horas, 1);
    $humorStatus[] = $humor;
}

/* métricas finais */
$totalHoras = array_sum($horasEstudo);
$mediaHumor = round(array_sum($humorStatus) / 7, 1);

/* nível de desempenho */
if ($mediaHumor >= 4 && $totalHoras >= 14) {
    $nivel = "alto";
} elseif ($mediaHumor >= 2.8) {
    $nivel = "médio";
} else {
    $nivel = "baixo";
}

/* sincronia limitada (MAX 80) */
$sincronia = 55
    + ($mediaHumor * 3)
    + ($totalHoras * 2)
    - ($fadiga * 2)
    - ($estresse * 1.5);

$sincronia = max(35, min(80, round($sincronia)));
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
            --chart-grid: #e2e8f0;
        }

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

    --chart-grid: rgba(255,255,255,0.05);
}

body.purple-theme {
    --primary: #a78bfa;
    --primary-light: #c4b5fd;
    --accent: #9a8cff;
    --accent-glow: rgba(154, 140, 255, 0.18);

    --bg: #f7f6fb;
    --card-bg: #ffffff;

    --text: #2c243d;
    --text-soft: #6f6785;

    --border: #e7e1f5;

    --nav-bg: #5e52b8;

    --btn-bg: rgba(139, 124, 255, 0.06);

    --chart-grid: rgba(94, 82, 184, 0.10);
}

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; transition: background 0.3s ease, border-color 0.3s ease, color 0.3s ease; }
        
        body { 
            background-color: var(--bg); 
            min-height: 100vh; 
            color: var(--text);
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
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

        .navbar-main {
            background-color: var(--nav-bg);
            height: 85px;
            display: flex; justify-content: center; align-items: center;
            position: sticky; top: 0; z-index: 1000;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            border-bottom: 2px solid var(--accent);
        }

        .nav-content {
            width: 100%; max-width: 1200px; padding: 0 40px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .nav-brand { display: flex; align-items: center; color: white; text-decoration: none; gap: 12px; }

        .logo-wrap { position: relative; display: flex; align-items: center; }

        .nav-logo { width: 80px; height: auto; transition: transform 0.3s ease; filter: drop-shadow(0 6px 12px rgba(0,0,0,0.1)); }

        .nav-logo:hover { transform: scale(1.08); }

        .logo-light { position: absolute; top: 6px; left: 50px; width: 18px; height: 18px; background: rgba(250, 204, 21, 1); border-radius: 50%; filter: blur(10px); animation: lampGlow 3s infinite ease-in-out; }

        @keyframes lampGlow { 0%, 100% { opacity: 0.5; transform: scale(1); } 50% { opacity: 1; transform: scale(1.3); } }

        .brand-text { font-size: 24px; font-weight: 800; color: white; transition: 0.3s; }

        .nav-links { display: flex; list-style: none; gap: 10px; }

        .nav-links a { color: #f2efea; text-decoration: none; font-size: 14px; font-weight: 700; padding: 12px 18px; border-radius: 12px; transition: var(--transition); display: flex; align-items: center; gap: 8px; }

        .nav-links a:hover, .nav-links a.active { color: var(--accent); background: rgba(255,255,255,0.08); }

        .profile-static { display: flex; align-items: center; gap: 15px; padding: 8px 20px; border-radius: 40px; }
        .profile-static span { color: white; font-weight: 700; font-size: 16px; }

        .profile-img { width: 48px; height: 48px; border-radius: 50%; border: 3px solid var(--accent); object-fit: cover; }

        .container { max-width: 1200px; margin: 50px auto; padding: 0 40px; animation: slideUp 0.8s ease; }

        .header-title { margin-bottom: 40px; border-left: 10px solid var(--accent); padding-left: 20px; }

        .header-title h1 { font-size: 38px; font-weight: 900; color: var(--text); letter-spacing: -1.5px; }

        .header-title p { color: var(--text-soft); font-size: 16px; font-weight: 600; }

        .metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px; }

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

        .metric-card:hover { transform: translateY(-10px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }

        .metric-card i { font-size: 32px; color: var(--accent); margin-bottom: 15px; display: block; }

        .metric-card h3 { font-size: 30px; font-weight: 900; color: var(--text); }

        .metric-card p { font-size: 13px; font-weight: 800; color: var(--text-soft); text-transform: uppercase; }

        .chart-box { background: var(--card-bg); padding: 40px; border-radius: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.05); border: 1px solid var(--border); margin-bottom: 40px; }

        .chart-wrapper { height: 400px; width: 100%; position: relative; }

        .insight-ai {
            background: var(--nav-bg); padding: 40px; border-radius: 40px;
            display: flex; align-items: center; gap: 30px; color: white;
            position: relative; overflow: hidden; border: 2px solid var(--accent);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1); transition: 0.5s;
        }

        .insight-ai::before { content: ''; position: absolute; top: -50%; left: -100%; width: 100%; height: 200%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent); transform: rotate(20deg); animation: shine 6s infinite; }

        @keyframes shine { 0% { left: -100%; } 100% { left: 200%; } }

        .insight-icon { width: 80px; height: 80px; background: var(--accent); border-radius: 25px; display: flex; align-items: center; justify-content: center; font-size: 35px; color: white; flex-shrink: 0; box-shadow: 0 0 20px var(--accent-glow); animation: brainPulse 2.5s infinite; }

        @keyframes brainPulse { 0%, 100% { transform: scale(1); filter: brightness(1); } 50% { transform: scale(1.05); filter: brightness(1.2); } }

        .ai-message { font-size: 19px; line-height: 1.5; font-weight: 500; min-height: 50px; color: #f2efea; }

        .ai-cursor { border-right: 3px solid var(--accent); animation: blink 0.7s infinite; }

        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 950px) {
            .metrics-grid { grid-template-columns: 1fr; }
            .nav-links, .profile-static span { display: none; }
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
                <li><a href="agenda.php"><i class="fa-solid fa-bullseye"></i> Metas</a></li>
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
                <p>Humor</p>
            </div>
            <div class="metric-card">
                <i class="fa-solid fa-bolt"></i>
                <h3><?php echo rand(70, 98); ?>%</h3>
                <p>Sincronia</p>
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
            
            // Cores baseadas no tema para o gráfico
            const textColor = isDark ? '#94a3b8' : '#455a64';
            const gridColor = isDark ? 'rgba(255,255,255,0.05)' : '#e2e8f0';
            const barColor = theme === 'purple' ? 'rgba(168, 85, 247, 0.7)' : (theme === 'dark' ? '#d4ad60' : '#5b7c99');

            new Chart(ctx, {
                data: {
                    labels: <?php echo json_encode($diasSemana); ?>,
                    datasets: [{
                        type: 'line', 
                        label: 'Humor', 
                        data: <?php echo json_encode($humorStatus); ?>,
                        borderColor: '#facc15', 
                        backgroundColor: '#facc15',
                        borderWidth: 4, 
                        tension: 0.4, 
                        yAxisID: 'yH',
                        pointRadius: 4
                    }, {
                        type: 'bar', 
                        label: 'Horas', 
                        data: <?php echo json_encode($horasEstudo); ?>,
                        backgroundColor: barColor, 
                        borderRadius: 8, 
                        yAxisID: 'yE'
                    }]
                },
                options: {
                    responsive: true, 
                    maintainAspectRatio: false,
                    scales: {
                        yE: { 
                            beginAtZero: true, 
                            grid: { color: gridColor }, 
                            ticks: { color: textColor, font: { weight: '600' } } 
                        },
                        yH: { display: false, max: 6 },
                        x: {
                            grid: { display: false },
                            ticks: { color: textColor, font: { weight: '600' } }
                        }
                    },
                    plugins: { 
                        legend: { 
                            labels: { 
                                color: textColor,
                                font: { size: 14, weight: '700' }
                            } 
                        } 
                    }
                }
            });
        }

       function startAI() {

    const data = <?php echo json_encode([
        "nivel" => $nivel,
        "user" => $username_limpo,
        "media" => $mediaHumor,
        "horas" => $totalHoras
    ]); ?>;

    const target = document.getElementById('aiMsg');

    let msg = "";

    // mensagens dinâmicas por nível

    if (data.nivel === "alto") {

        msg = `Mapeamento neural concluído, ${data.user}. Sistema indica alta performance contínua. Eficiência cognitiva acima do padrão esperado. Sincronização otimizada.`;

    } else if (data.nivel === "médio") {

        msg = `Mapeamento neural concluído, ${data.user}. Performance estável detectada. Há margem real para expansão de produtividade e foco.`;

    } else {

        msg = `Mapeamento neural concluído, ${data.user}. Baixa consistência operacional identificada. Recomenda-se reajuste de rotina e gestão de energia mental.`;

    }

    // efeito de digitação

    let i = 0;

    function type() {
        if (i < msg.length) {
            target.innerHTML = msg.substring(0, i + 1) + '<span class="ai-cursor"></span>';
            i++;
            setTimeout(type, 28);
        }
    }

    setTimeout(type, 900);
}
    </script>

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

</body>
</html>