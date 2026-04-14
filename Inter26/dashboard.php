<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id']) && !verificarAutoLogin($pdo)) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['usuario_username'])) {
    header("Location: setup_perfil.php");
    exit;
}

// Busca a foto de perfil
$stmt = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario_dados = $stmt->fetch();

// Se tiver foto no banco, usa ela. Se não tiver, usa uma imagem local padrão.
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
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; }
        body { background-color: #f1f5f9; min-height: 100vh; }

        /* BARRA DE NAVEGAÇÃO SUPERIOR (FIXA) */
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

        /* PERFIL E DROPDOWN NO TOPO */
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

        /* CONTEÚDO */
        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .welcome-header { margin-bottom: 30px; }
        .welcome-header h1 { color: #1e293b; font-size: 32px; }
        .welcome-header p { color: #64748b; margin-top: 5px; }

        /* GRID DE CARDS */
        .cards-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }
        .card-box { 
            background: white; padding: 35px; border-radius: 20px; text-decoration: none; 
            color: inherit; display: block; border-bottom: 6px solid #1a2639; transition: 0.3s;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .card-box:hover { transform: translateY(-8px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .card-box.accent { border-bottom-color: #facc15; }
        .card-box i { font-size: 45px; margin-bottom: 20px; display: block; }
        .card-box h3 { color: #1e293b; font-size: 22px; margin-bottom: 12px; }
        .card-box p { color: #64748b; line-height: 1.6; }

        /* TOAST ANIMATION */
        .toast {
            position: fixed; bottom: 30px; right: -400px; background: #1a2639; color: white;
            padding: 18px 30px; border-radius: 12px; border-left: 6px solid #facc15;
            box-shadow: 0 15px 30px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 15px;
            z-index: 2000; font-weight: 600; transition: right 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .toast.show { right: 30px; }
        .toast i { color: #facc15; font-size: 22px; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

    <?php if(isset($_SESSION['toast_msg'])): ?>
        <div id="welcomeToast" class="toast">
            <i class="fa-solid fa-sparkles"></i>
            <span><?php echo $_SESSION['toast_msg']; ?></span>
        </div>
        <?php unset($_SESSION['toast_msg']); ?>
    <?php endif; ?>

    <nav class="navbar-main">
        <a href="dashboard.php" class="nav-brand">
            <i class="fa-solid fa-anchor"></i>
            <h2>Focus</h2>
        </a>

        <ul class="nav-links">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> Início</a></li>
            <li><a href="meus_estudos.php"><i class="fa-solid fa-graduation-cap"></i> Meus Estudos</a></li>
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
        <header class="welcome-header">
            <h1>Bem-vinda ao seu painel, <?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?>!</h1>
            <p>Selecione uma área para gerenciar suas tarefas acadêmicas.</p>
        </header>

        <div class="cards-grid">
            <a href="meus_estudos.php" class="card-box accent">
                <i class="fa-solid fa-brain" style="color: #facc15;"></i>
                <h3>Meus Estudos</h3>
                <p>Acesse suas disciplinas e filtre o que estudar baseado no seu humor e energia atual.</p>
            </a>

            <a href="#" class="card-box">
                <i class="fa-solid fa-chart-line" style="color: #1a2639;"></i>
                <h3>Desempenho</h3>
                <p>Acompanhe suas notas, níveis de dificuldade completados e estatísticas de foco.</p>
            </a>

            <a href="#" class="card-box">
                <i class="fa-solid fa-compass" style="color: #1a2639;"></i>
                <h3>Explorar</h3>
                <p>Descubra novos métodos de estudo e organize seus materiais extras em um só lugar.</p>
            </a>
        </div>
    </div>

    <script>
        function toggleMenu() {
            document.getElementById("dropdownMenu").classList.toggle("show");
        }

        // Fecha o dropdown ao clicar fora
        window.onclick = function(event) {
            if (!event.target.closest('.profile-trigger')) {
                var dropdown = document.getElementById("dropdownMenu");
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        }

        // Mostra o Toast e esconde após 6 segundos
        window.onload = function() {
            var toast = document.getElementById("welcomeToast");
            if (toast) {
                setTimeout(() => { toast.classList.add("show"); }, 500);
                setTimeout(() => { toast.classList.remove("show"); }, 6000);
            }
        };
    </script>
</body>
</html>
