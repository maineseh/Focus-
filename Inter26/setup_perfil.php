<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Se já tem username, não deve acessar essa tela
if (isset($_SESSION['usuario_username'])) {
    header("Location: dashboard.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $foto = $_POST['foto_perfil'];
    $usuario_id = $_SESSION['usuario_id'];

    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        $erro = "Poxa, esse nome de usuário já está em uso. Escolha outro!";
    } else {
        $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, foto_perfil = ? WHERE id = ?");
        if ($stmt->execute([$username, $foto, $usuario_id])) {
            $_SESSION['usuario_username'] = $username;
            header("Location: dashboard.php");
            exit;
        } else {
            $erro = "Erro ao salvar perfil.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Completar Perfil - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #1a2639; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; width: 100%; max-width: 450px; text-align: center; }
        h2 { color: #1a2639; margin-bottom: 5px; }
        p { color: #666; margin-bottom: 20px; font-size: 14px; }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; text-align: center; margin-bottom: 20px;}
        .avatars { display: flex; justify-content: space-around; margin-bottom: 30px; }
        .avatar-label { cursor: pointer; text-align: center; }
        .avatar-label input { display: none; }
        .avatar-img { width: 70px; height: 70px; border-radius: 50%; border: 3px solid transparent; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 30px; color: #999; transition: 0.3s; }
        .avatar-label input:checked + .avatar-img { border-color: #facc15; color: #facc15; background: #fffbeb; }
        .btn { width: 100%; padding: 12px; background: #facc15; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; }
        .erro { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="card">
    <h2>Quase lá, <?php echo explode(' ', $_SESSION['usuario_nome'])[0]; ?>!</h2>
    <p>Crie seu nome de usuário único e escolha um avatar provisório.</p>

    <?php if($erro) echo "<div class='erro'>$erro</div>"; ?>

    <form method="POST">
        <div class="input-group">
            <input type="text" name="username" placeholder="@seu_usuario" required>
        </div>

        <p><strong>Escolha seu Avatar:</strong></p>
        <div class="avatars">
            <label class="avatar-label">
                <input type="radio" name="foto_perfil" value="gamer.png" required>
                <div class="avatar-img"><i class="fa-solid fa-gamepad"></i></div>
            </label>
            <label class="avatar-label">
                <input type="radio" name="foto_perfil" value="code.png">
                <div class="avatar-img"><i class="fa-solid fa-code"></i></div>
            </label>
            <label class="avatar-label">
                <input type="radio" name="foto_perfil" value="book.png">
                <div class="avatar-img"><i class="fa-solid fa-book-open"></i></div>
            </label>
        </div>

        <button type="submit" class="btn">Finalizar e Entrar <i class="fa-solid fa-check"></i></button>
    </form>
</div>

</body>
</html>