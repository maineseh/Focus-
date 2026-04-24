<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$erro = '';

// Puxar dados atuais (SEM perfil cognitivo)
$stmt = $pdo->prepare("SELECT nome, username, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $username = trim($_POST['username']);
    $foto = $_POST['foto_perfil'];

    // verificar username duplicado
    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
    $stmt_check->execute([$username, $id_usuario]);

    if ($stmt_check->rowCount() > 0) {
        $erro = "Este nome de utilizador já está ocupado.";
    } else {
        $stmt_update = $pdo->prepare("
            UPDATE usuarios 
            SET nome = ?, username = ?, foto_perfil = ?
            WHERE id = ?
        ");

        if ($stmt_update->execute([$nome, $username, $foto, $id_usuario])) {
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_username'] = $username;
            $_SESSION['usuario_foto'] = $foto;

            header("Location: dashboard.php");
            exit;
        } else {
            $erro = "Erro ao atualizar o banco de dados.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil | Focus OS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        
        :root {
            --primary: #5b7c99;
            --accent: #ffd174;
            --accent-glow: rgba(212, 173, 96, 0.4);
            --bg: #f2efea;
            --card-bg: #ffffff;
            --text: #455a64;
            --border: #d1d9e0;
            --input-bg: #f8fafc;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body.dark-theme {
            --primary: #c7ccd3;
            --accent: #d6d2c4;
            --bg: #1a2230;
            --card-bg: #242f3d;
            --text: #f7f9fc;
            --border: #3a4656;
            --input-bg: #202a38;
        }

        body.purple-theme {
            --primary: #8b7cff;
            --accent: #9a8cff;
            --bg: #f7f6fb;
            --card-bg: #ffffff;
            --text: #2c243d;
            --border: #e7e1f5;
            --input-bg: rgba(139, 124, 255, 0.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', sans-serif; }

        body {
            background-color: var(--bg);
            min-height: 100vh;
            color: var(--text);
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .edit-card {
            background: var(--card-bg);
            padding: 50px;
            border-radius: 45px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.1);
            border: 2px solid var(--border);
            border-bottom: 15px solid var(--primary);
        }

        .header-edit {
            text-align: center;
            margin-bottom: 35px;
        }

        .header-edit h1 {
            font-size: 32px;
            font-weight: 900;
        }

        .input-group {
            margin-bottom: 25px;
        }

        .input-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
            opacity: 0.7;
        }

        .input-group input {
            width: 100%;
            padding: 18px;
            border-radius: 20px;
            border: 2px solid var(--border);
            background: var(--input-bg);
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
            outline: none;
            transition: var(--transition);
        }

        .input-group input:focus {
            border-color: var(--accent);
            transform: scale(1.02);
        }

        .section-title {
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 30px 0 15px;
            color: var(--primary);
        }

        .avatars-grid {
            display: flex;
            gap: 25px;
            justify-content: center;
            margin-bottom: 40px;
        }

        .avatar-option input { display: none; }

        .avatar-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 4px solid var(--border);
            cursor: pointer;
            transition: var(--transition);
            object-fit: cover;
        }

        .avatar-option input:checked + .avatar-img {
            border-color: var(--accent);
            transform: scale(1.15);
        }

        .btn-save {
            width: 100%;
            padding: 22px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 22px;
            font-weight: 950;
            font-size: 16px;
            cursor: pointer;
            text-transform: uppercase;
        }

        .btn-back {
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            color: var(--text);
            opacity: 0.6;
        }

        .btn-back:hover {
            opacity: 1;
            color: var(--primary);
        }
    </style>
</head>

<body>

<script>
    const theme = localStorage.getItem('focus_theme') || 'default';
    if(theme !== 'default') document.body.classList.add(theme + '-theme');
</script>

<div class="edit-card">

    <div class="header-edit">
        <h1>Editar Perfil</h1>
    </div>

    <?php if($erro): ?>
        <div style="color:#e57373; background:rgba(229,115,115,0.1); padding:15px; border-radius:15px; font-weight:700; margin-bottom:25px;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="input-group">
            <label>Nome Completo</label>
            <input type="text" name="nome" value="<?php echo htmlspecialchars($user['nome']); ?>" required>
        </div>

        <div class="input-group">
            <label>Nome de Utilizador (@)</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
        </div>

        <div class="section-title">Identidade Visual</div>

        <div class="avatars-grid">
            <?php for($i=1; $i<=3; $i++): $img = "img/ex$i.png"; ?>
            <label class="avatar-option">
                <input type="radio" name="foto_perfil" value="<?php echo $img; ?>"
                <?php echo ($user['foto_perfil'] == $img) ? 'checked' : ''; ?>>
                <img src="<?php echo $img; ?>" class="avatar-img">
            </label>
            <?php endfor; ?>
        </div>

        <button type="submit" class="btn-save">Atualizar Perfil</button>

        <a href="dashboard.php" class="btn-back">
            <i class="fa-solid fa-arrow-left"></i> Voltar
        </a>

    </form>

</div>

</body>
</html>