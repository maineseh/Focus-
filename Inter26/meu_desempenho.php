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

$username_limpo = !empty($usuario_dados['username']) ? $usuario_dados['username'] : 'usuário';
$foto_perfil = !empty($usuario_dados['foto_perfil']) ? $usuario_dados['foto_perfil'] : 'img/padrao.png';

/**
 * LÓGICA DE ALTA ALEATORIEDADE (DADOS EXTREMAMENTE VARIADOS)
 */
$diasSemana = ["Seg", "Ter", "Qua", "Qui", "Sex", "Sáb", "Dom"];
$humorStatus = [];
$horasEstudo = [];

foreach ($diasSemana as $dia) {
    // Agora a variação é de 0.0h a 10.0h com decimais, para o gráfico nunca ficar igual
    $h = rand(0, 1000) / 100; 
    $horasEstudo[] = $h;
    
    // Humor oscilando totalmente entre 1 e 5
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
    <title>Desempenho Inteligente - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #1a2639;
            --accent: #facc15;
            --bg: #f8fafc;
            --white: #ffffff;
        }

        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; }
        body { background-color: var(--bg); min-height: 100vh; color: #1e293b; overflow-x: hidden; }

        .navbar-main { 
            background-color: var(--primary); 
            height: 70px; padding: 0 5%; 
            display: flex; justify-content: space-between; align-items: center; 
            position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(0,0,0,0.15); 
        }
        .nav-brand { display: flex; align-items: center; color: white; text-decoration: none; gap: 10px; }
        .nav-brand i { font-size: 26px; color: var(--accent); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--accent); object-fit: cover; }

        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        
        .btn-back { text-decoration: none; color: #64748b; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 25px; transition: 0.3s; }
        .btn-back:hover { color: var(--primary); transform: translateX(-3px); }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { 
            background: var(--white); padding: 30px; border-radius: 28px; text-align: center; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.03); transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(0,0,0,0.02);
        }
        .metric-card:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
        .metric-card i { font-size: 32px; color: var(--primary); margin-bottom: 15px; display: block; }
        .metric-card h3 { font-size: 28px; font-weight: 800; color: var(--primary); }
        .metric-card p { color: #64748b; font-weight: 600; font-size: 13px; text-transform: uppercase; }

        .chart-box { 
            background: var(--white); padding: 35px; border-radius: 32px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.04); margin-bottom: 40px;
        }
        
        .chart-wrapper { position: relative; height: 380px; width: 100%; }

        .insight-ai {
            padding: 25px; background: linear-gradient(135deg, var(--primary) 0%, #2d3a5e 100%);
            border-radius: 24px; color: white; display: flex; align-items: center; gap: 20px;
            box-shadow: 0 10px 30px rgba(26, 38, 57, 0.2); margin-top: 20px;
        }
        .insight-icon {
            width: 55px; height: 55px; background: var(--accent); border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: var(--primary); flex-shrink: 0;
        }
    </style>
</head>
<body>

    <nav class="navbar-main">
        <a href="dashboard.php" class="nav-brand"><i class="fa-solid fa-bolt"></i><h2>Focus</h2></a>
        <div style="display: flex; align-items: center; gap: 12px; color: white;">
            <span style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($username_limpo); ?></span>
            <img src="<?php echo $foto_perfil; ?>" class="profile-img">
        </div>
    </nav>

    <div class="container">
        <a href="dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left-long"></i> VOLTAR</a>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <i class="fa-solid fa-clock"></i>
                <h3><?php echo number_format($totalHoras, 1); ?>h</h3>
                <p>Estudo Total</p>
            </div>
            <div class="metric-card">
                <i class="fa-solid fa-face-smile" style="color: var(--accent);"></i>
                <h3><?php echo $mediaHumor; ?></h3>
                <p>Estabilidade</p>
            </div>
            <div class="metric-card">
                <i class="fa-solid fa-fire" style="color: #f97316;"></i>
                <h3><?php echo rand(60, 99); ?>%</h3>
                <p>Foco Diário</p>
            </div>
        </div>

        <div class="chart-box">
            <h2 style="font-weight: 800; color: var(--primary); margin-bottom: 25px;">Tendências de Performance</h2>
            <div class="chart-wrapper">
                <canvas id="mainChart"></canvas>
            </div>

            <div class="insight-ai">
                <div class="insight-icon"><i class="fa-solid fa-brain"></i></div>
                <div>
                    <h4 style="color: var(--accent); font-size: 14px; letter-spacing: 1px; text-transform: uppercase;">Focus AI</h4>
                    <p id="dynamicText" style="font-size: 16px; line-height: 1.4;">Analisando seus dados...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
        
        new Chart(ctx, {
            data: {
                labels: <?php echo json_encode($diasSemana); ?>,
                datasets: [
                    {
                        type: 'line',
                        label: 'Humor',
                        data: <?php echo json_encode($humorStatus); ?>,
                        borderColor: '#facc15',
                        backgroundColor: 'rgba(250, 204, 21, 0.1)',
                        borderWidth: 5,
                        fill: true,
                        tension: 0.45,
                        pointRadius: 6,
                        yAxisID: 'yH'
                    },
                    {
                        type: 'bar',
                        label: 'Estudo',
                        data: <?php echo json_encode($horasEstudo); ?>,
                        backgroundColor: '#1a2639',
                        borderRadius: 12,
                        yAxisID: 'yE'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 2500, easing: 'easeOutQuart' },
                scales: {
                    yE: { 
                        beginAtZero: true, 
                        max: 12, // Teto alto para não vazar as barras
                        position: 'left', 
                        grid: { borderDash: [5, 5] } 
                    },
                    yH: { 
                        beginAtZero: true, 
                        max: 7, // Teto alto para a linha amarela não furar o topo
                        position: 'right', 
                        grid: { display: false } 
                    }
                },
                plugins: {
                    legend: { labels: { usePointStyle: true, font: { weight: '600' } } }
                }
            }
        });

        const total = <?php echo $totalHoras; ?>;
        const mood = <?php echo $mediaHumor; ?>;
        const user = "<?php echo htmlspecialchars($username_limpo); ?>";
        const text = document.getElementById('dynamicText');

        if(mood < 3 && total > 20) {
            text.innerText = user + "! Detectamos alta carga com humor baixo. Reduza o ritmo hoje.";
        } else if(mood > 4 && total < 15) {
            text.innerText = user + "! Seu humor está excelente. Ótimo momento para matérias difíceis!";
        } else {
            text.innerText = user + "! Sua semana segue com um bom equilíbrio emocional e foco.";
        }
    </script>
</body>
</html>