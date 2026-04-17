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

$username_exibir = !empty($usuario_dados['username']) ? $usuario_dados['username'] : 'usuario';
$foto_perfil = !empty($usuario_dados['foto_perfil']) ? $usuario_dados['foto_perfil'] : 'img/padrao.png';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a2639;
            --accent: #facc15;
            --bg: #f1f5f9;
            --danger: #ff4d4d;
            --success: #10b981;
        }

        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; }
        body { background-color: var(--bg); min-height: 100vh; color: #1e293b; overflow-x: hidden; }

        /* NAVBAR */
        .navbar-main {
            background-color: var(--primary);
            height: 70px; padding: 0 5%;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 1000; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .nav-brand { display: flex; align-items: center; color: white; text-decoration: none; gap: 10px; }
        .nav-brand i { font-size: 28px; color: var(--accent); }
        .nav-links { display: flex; list-style: none; gap: 30px; flex: 1; margin-left: 50px; }
        .nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 600; transition: 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--accent); object-fit: cover; }

        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }

        /* BATERIA DE HUMOR */
        .mood-card {
            background: white; padding: 40px; border-radius: 35px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.04); margin-bottom: 40px;
            display: flex; align-items: center; justify-content: center; gap: 60px;
            flex-wrap: wrap;
        }

        .battery-body {
            width: 110px; height: 210px;
            border: 6px solid var(--primary);
            border-radius: 20px;
            position: relative;
            padding: 8px;
            display: flex;
            flex-direction: column-reverse; 
            gap: 8px;
            background: #f8fafc;
        }
        .battery-body::after {
            content: ''; width: 45px; height: 12px; background: var(--primary);
            position: absolute; top: -17px; left: 50%; transform: translateX(-50%);
            border-radius: 6px 6px 0 0;
        }

        .battery-segment {
            width: 100%; height: 18%;
            background: #e2e8f0;
            border-radius: 6px;
            transition: 0.3s all ease-in-out;
        }

        .active-seg.low { background: var(--danger); box-shadow: 0 0 10px rgba(255, 77, 77, 0.3); }
        .active-seg.mid { background: var(--accent); box-shadow: 0 0 10px rgba(250, 204, 21, 0.3); }
        .active-seg.high { background: var(--success); box-shadow: 0 0 10px rgba(16, 185, 129, 0.3); }

        .mood-info { flex: 1; min-width: 280px; }
        .mood-info h2 { font-size: 28px; font-weight: 800; margin-bottom: 10px; }
        
        input[type="range"] {
            width: 100%; margin: 25px 0;
            accent-color: var(--primary);
            cursor: pointer;
        }

        /* CARDS ORIGINAIS */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }
        .card-box { 
            background: white; padding: 35px; border-radius: 24px; text-decoration: none; 
            color: inherit; display: block; border-bottom: 6px solid var(--primary); transition: 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .card-box:hover { transform: translateY(-8px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
        .card-box.accent { border-bottom-color: var(--accent); }
        .card-box i { font-size: 35px; margin-bottom: 15px; display: block; color: var(--primary); }
    </style>
</head>
<body>

    <nav class="navbar-main">
        <a href="dashboard.php" class="nav-brand"><i class="fa-solid fa-anchor"></i><h2>Focus</h2></a>
        <ul class="nav-links">
            <li><a href="dashboard.php" class="active">Início</a></li>
            <li><a href="meus_estudos.php">Meus Estudos</a></li>
            <li><a href="agenda.php">Agenda</a></li>
        </ul>
        <div class="nav-right" style="display: flex; align-items: center; gap: 12px; color: white;">
            <span style="font-weight: 600;"><?php echo htmlspecialchars($username_exibir); ?></span>
            <img src="<?php echo $foto_perfil; ?>" class="profile-img">
        </div>
    </nav>

    <div class="container">
        <section class="mood-card">
            <div class="battery-body" id="battery">
                <div class="battery-segment" data-level="1"></div>
                <div class="battery-segment" data-level="2"></div>
                <div class="battery-segment" data-level="3"></div>
                <div class="battery-segment" data-level="4"></div>
                <div class="battery-segment" data-level="5"></div>
            </div>

            <div class="mood-info">
                <h2>Bem-vinda(o), <?php echo htmlspecialchars($username_exibir); ?>!</h2>
                <p>Como está seu <b>humor</b> para os desafios de hoje?</p>
                <input type="range" min="1" max="5" value="3" id="moodSlider">
                <h3 id="moodLabel" style="color: var(--primary); font-weight: 800; text-transform: uppercase; font-size: 15px;">Humor: Estável</h3>
            </div>
        </section>

        <div class="cards-grid">
            <a href="meus_estudos.php" class="card-box accent">
                <i class="fa-solid fa-graduation-cap"></i>
                <h3>Meus Estudos</h3>
                <p>Acesse seu cronograma e organize suas matérias.</p>
            </a>
            <a href="meu_desempenho.php" class="card-box">
                <i class="fa-solid fa-chart-line"></i>
                <h3>Desempenho</h3>
                <p>Acompanhe sua evolução e humor semanal.</p>
            </a>
            <a href="agenda.php" class="card-box" style="border-bottom-color: #3b82f6;">
                <i class="fa-solid fa-calendar-day"></i>
                <h3>Agenda</h3>
                <p>Visualize sua jornada diária e tarefas pendentes.</p>
            </a>
        </div>
    </div>

    <script>
        const slider = document.getElementById('moodSlider');
        const segments = document.querySelectorAll('.battery-segment');
        const label = document.getElementById('moodLabel');

        const config = {
            1: { text: "Bem Baixo 😢", color: "low" },
            2: { text: "Desanimada(o) 🥱", color: "low" },
            3: { text: "Estável / Ok 😐", color: "mid" },
            4: { text: "Muito Bem! 😊", color: "high" },
            5: { text: "Excelente! 🚀", color: "high" }
        };

        function updateMood(val) {
            segments.forEach(seg => {
                const lvl = seg.getAttribute('data-level');
                seg.classList.remove('active-seg', 'low', 'mid', 'high');
                
                if (lvl <= val) {
                    seg.classList.add('active-seg', config[val].color);
                }
            });
            label.innerText = `Humor: ${config[val].text}`;
        }

        slider.addEventListener('input', (e) => updateMood(e.target.value));
        updateMood(3);
    </script>
</body>
</html>
