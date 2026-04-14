<?php
session_start();
require_once 'conexao.php';

// Se já tiver cookie válido, loga direto
if (verificarAutoLogin($pdo)) {
    header("Location: dashboard.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login_informado = trim($_POST['login']);
    $senha = $_POST['senha'];
    $lembrar = isset($_POST['lembrar']) ? true : false;

    $stmt = $pdo->prepare("SELECT id, nome, username, senha FROM usuarios WHERE email = :login OR username = :login");
    $stmt->execute(['login' => $login_informado]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];

        if ($lembrar) {
            $token = bin2hex(random_bytes(32)); 
            $stmt = $pdo->prepare("UPDATE usuarios SET remember_token = ? WHERE id = ?");
            $stmt->execute([$token, $usuario['id']]);
            setcookie('lembrar_token', $token, time() + (86400 * 30), "/"); 
        }

        if (empty($usuario['username'])) {
            header("Location: setup_perfil.php");
        } else {
            $_SESSION['usuario_username'] = $usuario['username'];
$_SESSION['toast_msg'] = "Que bom ter você de volta, " . explode(' ', $usuario['nome'])[0] . "! 🎉";
            header("Location: dashboard.php");
        }
        exit;
    } else {
        $erro = "Credenciais inválidas. Tente novamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #1a2639; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; }
        .logo-icon { font-size: 40px; color: #1a2639; margin-bottom: 10px; }
        h2 { margin: 0 0 5px 0; color: #1a2639; }
        p.subtitle { color: #666; font-size: 14px; margin-bottom: 25px; }
        .input-group { position: relative; margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; font-size: 12px; color: #666; font-weight: bold; margin-bottom: 5px; }
        .input-group i.fa-user, .input-group i.fa-envelope, .input-group i.fa-lock { position: absolute; left: 15px; top: 38px; color: #999; }
        .input-group input { width: 100%; padding: 12px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: #f9f9f9; outline: none; }
        .input-group input:focus { border-color: #facc15; background: white; }
        .eye-btn { position: absolute; right: 15px; top: 38px; background: none; border: none; color: #999; cursor: pointer; }
        .options { display: flex; justify-content: space-between; align-items: center; font-size: 13px; margin-bottom: 20px; color: #666; }
        .btn { width: 100%; padding: 12px; background: #facc15; border: none; border-radius: 8px; font-weight: bold; color: #1a2639; font-size: 16px; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #eab308; }
        .btn-outline { background: transparent; border: 2px solid #facc15; margin-top: 10px; }
        .btn-outline:hover { background: #fef08a; }
        .divider { margin: 20px 0; font-size: 12px; color: #999; position: relative; }
        .erro { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; }

        /* Efeito animado para o link Esqueceu a Senha */
.link-esqueceu {
    color: #1a2639;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative; /* Necessário para a linha animada funcionar */
}

.link-esqueceu:hover {
    color: #eab308; /* Fica amarelo no hover */
}

/* Criando a linhazinha invisível embaixo */
.link-esqueceu::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 2px;
    bottom: -2px;
    left: 0;
    background-color: #eab308;
    visibility: hidden;
    transform: scaleX(0); /* Começa com tamanho zero */
    transition: all 0.3s ease-in-out;
}

/* Faz a linha crescer quando passa o mouse */
.link-esqueceu:hover::after {
    visibility: visible;
    transform: scaleX(1); /* Cresce até 100% */
}

    </style>
</head>
<body>

<div class="card">
    <i class="fa-solid fa-anchor logo-icon"></i>
    <h2>Focus</h2>
    <p class="subtitle">Acesso seguro à sua plataforma</p>

    <?php if($erro) echo "<div class='erro'><i class='fa-solid fa-triangle-exclamation'></i> $erro</div>"; ?>

    <form method="POST">
        <div class="input-group">
            <label><i class="fa-solid fa-envelope" style="position:static; color:#facc15;"></i> E-mail / Usuário</label>
            <i class="fa-regular fa-user"></i>
            <input type="text" name="login" required autocomplete="off">
        </div>

        <div class="input-group">
            <label><i class="fa-solid fa-lock" style="position:static; color:#facc15;"></i> Senha</label>
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="senha" id="senha" required>
            <button type="button" class="eye-btn" onclick="toggleSenha('senha', 'eye-icon')"><i class="fa-regular fa-eye-slash" id="eye-icon"></i></button>
        </div>

        <div class="options">
            <label><input type="checkbox" name="lembrar"> Lembrar de mim</label>
            <a href="esqueceu_senha.php" class="link-esqueceu">Esqueceu a senha?</a>
        </div>

        <button type="submit" class="btn"><i class="fa-solid fa-arrow-right-to-bracket"></i> Entrar</button>
    </form>

    <div class="divider">Novo por aqui?</div>
    <a href="cadastro.php"><button class="btn btn-outline"><i class="fa-solid fa-user-plus"></i> Criar conta</button></a>
</div>

<script>
    function toggleSenha(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        }
    }
</script>
</body>
</html>
