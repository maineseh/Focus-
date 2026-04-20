<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if (isset($_SESSION['usuario_username'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $foto = $_POST['foto_perfil']; 
    $perfil_cognitivo = $_POST['perfil_cognitivo']; 
    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        $erro = "Poxa, esse nome de utilizador já está em uso. Escolhe outro!";
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, foto_perfil = ?, perfil_cognitivo = ? WHERE id = ?");
        if ($stmt->execute([$username, $foto, $perfil_cognitivo, $usuario_id])) {
            $_SESSION['usuario_username'] = $username;
            $_SESSION['toast_msg'] = "Perfil configurado! Bem-vinda ao Focus, @" . $username . "! 🚀";
            header("Location: dashboard.php");
            exit;
        } else {
            $erro = "Erro ao guardar o perfil no banco de dados.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Perfil | Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a2639;
            --primary-light: #2d3a5e;
            --accent: #facc15;
            --bg: #f1f5f9;
            --text: #1e293b;
            --white: #ffffff;
            --border: #e2e8f0;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        body { 
            background-color: var(--primary); 
            min-height: 100vh; 
            display: flex; justify-content: center; align-items: center; padding: 20px;
            background-image: radial-gradient(rgba(255,255,255,0.05) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
        }

        .setup-card { 
            background: var(--white); padding: 50px 45px; border-radius: 45px; width: 100%;
            max-width: 550px; box-shadow: 0 30px 80px rgba(0,0,0,0.4); text-align: center;
            animation: slideUp 0.8s var(--transition); border-bottom: 12px solid var(--accent);
            position: relative;
        }

        .header-setup {
             margin-bottom: 35px;
             }

        .header-setup h2 {
             font-size: 32px; font-weight: 900; color: var(--primary); letter-spacing: -1.5px;
             }

        .header-setup p {
             color: #64748b; font-weight: 600; font-size: 15px;
             }

        .section-title { 
            font-size: 12px; color: var(--primary); margin: 25px 0 15px; 
            font-weight: 800; text-align: left; text-transform: uppercase; 
            letter-spacing: 1.5px; border-left: 5px solid var(--accent); padding-left: 12px;
        }

        .input-group input { 
            width: 100%; padding: 18px; border: 2.5px solid var(--border); 
            border-radius: 20px; font-size: 16px; font-weight: 700;
            text-align: center; outline: none; transition: 0.3s;
            background: #f8fafc; color: var(--primary);
        }

        .input-group input:focus {
             border-color: var(--accent); background: white; box-shadow: 0 0 20px rgba(250, 204, 21, 0.15);
             }

        .avatars-grid {
             display: flex; justify-content: space-between; gap: 20px; margin-bottom: 10px;
             }

        .avatar-label {
             cursor: pointer; flex: 1;
             }

        .avatar-label input {
             display: none;
             } 

        .avatar-img-dinamica { 
            width: 100%; max-width: 85px; aspect-ratio: 1/1; 
            border-radius: 50%; border: 5px solid var(--border); 
            transition: var(--transition); object-fit: cover; 
            filter: grayscale(100%) opacity(0.5); 
        }

        .avatar-label input:checked + .avatar-img-dinamica { 
            border-color: var(--accent); transform: scale(1.1) translateY(-5px); 
            filter: grayscale(0%) opacity(1); 
            box-shadow: 0 10px 25px rgba(250, 204, 21, 0.3); 
        }

        /* GRID SIMÉTRICO PARA OS CHIPS (PERFIL COGNITIVO) */
        .chips-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); /* 3 colunas exatas */
            gap: 12px; 
            margin-bottom: 40px; 
        }

        .chip-label {
             cursor: pointer;
             }

        .chip-label input {
             display: none;
             }

        .chip-text { 
            display: flex; align-items: center; justify-content: center;
            height: 45px; background-color: #f1f5f9; color: #64748b; 
            border-radius: 15px; font-size: 12px; font-weight: 800; 
            transition: 0.3s; border: 2.5px solid transparent;
            text-transform: uppercase;
        }

        .chip-label input:checked + .chip-text { 
            background-color: var(--primary); color: var(--accent); 
            border-color: var(--accent); transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(26, 38, 57, 0.2);
        }

        .btn { 
            width: 100%; padding: 20px; background: var(--primary); 
            border: none; border-radius: 20px; font-weight: 900; 
            font-size: 17px; cursor: pointer; transition: var(--transition); 
            color: var(--accent); text-transform: uppercase; letter-spacing: 1.5px;
            display: flex; align-items: center; justify-content: center; gap: 12px;
        }

        .btn:hover {
             background: var(--primary-light); transform: translateY(-5px); box-shadow: 0 15px 30px rgba(26, 38, 57, 0.3);
             }

        .erro {
             color: #ef4444; background: #fee2e2; padding: 15px; border-radius: 16px; font-size: 14px; margin-bottom: 20px; font-weight: 700; border-left: 6px solid #ef4444; display: flex; align-items: center; gap: 10px;
             }

        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 480px) {
            .setup-card { padding: 35px 20px; }
            .chips-grid { grid-template-columns: repeat(2, 1fr); } /* No celular vira 2 colunas pra caber */
        }

    </style>

</head>

<body>

<div class="setup-card">
    <header class="header-setup">
        <h2>Olá, <?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?>!</h2>
        <p>Vamos calibrar seu perfil no Focus.</p>
    </header>

    <?php if($erro): ?>
        <div class="erro"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="section-title">1. Nome de Utilizador</div>
        <div class="input-group">
            <input type="text" name="username" placeholder="@exemplo_user" required autocomplete="off">
        </div>

        <div class="section-title">2. Escolha seu Avatar</div>
        <div class="avatars-grid">
            <label class="avatar-label">
                <input type="radio" name="foto_perfil" value="img/ex1.png" required>
                <img src="img/ex1.png" class="avatar-img-dinamica" alt="Avatar 1">
            </label>
            <label class="avatar-label">
                <input type="radio" name="foto_perfil" value="img/ex2.png">
                <img src="img/ex2.png" class="avatar-img-dinamica" alt="Avatar 2">
            </label>
            <label class="avatar-label">
                <input type="radio" name="foto_perfil" value="img/ex3.png">
                <img src="img/ex3.png" class="avatar-img-dinamica" alt="Avatar 3">
            </label>
        </div>

        <div class="section-title">3. Estilo de Aprendizagem</div>
        <div class="chips-grid">
            <label class="chip-label">
                <input type="radio" name="perfil_cognitivo" value="TDAH">
                <span class="chip-text">TDAH</span>
            </label>
            <label class="chip-label">
                <input type="radio" name="perfil_cognitivo" value="Ansiedade">
                <span class="chip-text">Ansiedade</span>
            </label>
            <label class="chip-label">
                <input type="radio" name="perfil_cognitivo" value="Dislexia">
                <span class="chip-text">Dislexia</span>
            </label>
            <label class="chip-label">
                <input type="radio" name="perfil_cognitivo" value="TEA">
                <span class="chip-text">TEA</span>
            </label>
            <label class="chip-label">
                <input type="radio" name="perfil_cognitivo" value="Outros">
                <span class="chip-text">Outros</span>
            </label>
            <label class="chip-label">
                <input type="radio" name="perfil_cognitivo" value="Nenhum" checked>
                <span class="chip-text">Padrão</span>
            </label>
        </div>

        <button type="submit" class="btn">
            Sincronizar e Entrar <i class="fa-solid fa-arrow-right"></i>
        </button>
    </form>
</div>

</body>
</html>