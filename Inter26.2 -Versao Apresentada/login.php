<?php
session_start();
require_once 'conexao.php';

if (function_exists('verificarAutoLogin') && verificarAutoLogin($pdo)) {
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
<title>Login | Focus OS</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>

:root {
    --primary: #5b7c99;       
    --primary-light: #7da0bd; 
    --accent: #ffd174;        
    --bg: #f2efea;            
    --text: #455a64;          
    --white: #ffffff;
    --border: #d1d9e0;
    --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
}

* { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }

body { 
    background-color: var(--bg); 
    min-height: 100vh; 
    display: flex; justify-content: center; align-items: center; padding: 20px;
    background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
    background-size: 30px 30px;
    color: var(--text);
}

.login-card { 
    background: var(--white); 
    padding: 50px 40px; 
    border-radius: 40px; 
    width: 100%; max-width: 420px; 
    box-shadow: 0 25px 70px rgba(69, 90, 100, 0.1); 
    text-align: center;
    animation: slideUp 0.8s var(--transition);
    border-bottom: 12px solid var(--accent);
    position: relative;
}

.header-login { margin-bottom: 35px; }

.header-login .logo-icon { 
    width: 110px; height: auto; margin-bottom: 18px; 
    display: block; margin-left: auto; margin-right: auto;
    filter: drop-shadow(0 6px 12px rgba(0,0,0,0.1));
    transition: 0.3s;
}

.header-login .logo-icon:hover { transform: scale(1.08) rotate(2deg); }

.header-login h2 { font-size: 34px; font-weight: 900; color: var(--primary); letter-spacing: -1.5px; }

.header-login p { color: #78909c; font-weight: 600; font-size: 15px; }

.input-group { position: relative; margin-bottom: 20px; text-align: left; }

.input-group label { 
    display: block; font-size: 12px; color: var(--primary); 
    font-weight: 800; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px;
}

.input-group i.icon-field { 
    position: absolute; left: 18px; top: 40px; color: #b0bec5; font-size: 18px; transition: 0.3s;
}

.input-group input { 
    width: 100%; padding: 16px 16px 16px 50px; 
    border: 2px solid var(--border); border-radius: 16px; 
    font-size: 15px; font-weight: 600; background: #fbfbfc; 
    outline: none; transition: var(--transition); color: var(--text);
}

.input-group input:focus { 
    border-color: var(--primary); background: white; 
    box-shadow: 0 0 15px rgba(91, 124, 153, 0.1);
}
.input-group input:focus + i.icon-field { color: var(--primary); transform: scale(1.1); }

.eye-btn { 
    position: absolute; right: 18px; top: 40px; 
    background: none; border: none; color: #b0bec5; cursor: pointer; font-size: 18px;
    transition: 0.3s;
}
.eye-btn:hover { color: var(--primary); }

.options { 
    display: flex; justify-content: space-between; align-items: center; 
    font-size: 13px; margin-bottom: 30px; color: #78909c; font-weight: 600;
}
.options label { cursor: pointer; display: flex; align-items: center; gap: 8px; }

.options input { accent-color: var(--primary); }

.link-esqueceu { color: var(--primary); text-decoration: none; font-weight: 700; transition: 0.3s; }

.link-esqueceu:hover { color: var(--accent); text-decoration: underline; }

/* BOTÕES INTERATIVOS */

.btn { 
    width: 100%; padding: 18px; 
    background: var(--primary); color: white; 
    border: none; border-radius: 16px; 
    font-weight: 900; font-size: 17px; 
    cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px;
    transition: var(--transition);
    box-shadow: 0 8px 20px rgba(91, 124, 153, 0.2);
}

.btn:hover { 
    transform: translateY(-4px); 
    background: var(--primary-light);
    box-shadow: 0 12px 25px rgba(91, 124, 153, 0.3);
}

.btn:active { transform: translateY(-1px); scale: 0.98; }

.divider { 
    margin: 30px 0 20px; font-size: 12px; color: #b0bec5; 
    font-weight: 800; text-transform: uppercase; letter-spacing: 2px;
}

.btn-outline { 
    background: transparent; border: 2.5px solid var(--border); 
    color: #78909c; font-weight: 800; display: flex;
    align-items: center; justify-content: center; gap: 10px;
    padding: 14px; border-radius: 16px; font-size: 14px; cursor: pointer; width: 100%;
    transition: 0.3s;
}

.btn-outline:hover { 
    background: white; border-color: var(--primary); color: var(--primary);
    transform: scale(1.02);
}

.erro { 
    color: #d32f2f; background: #ffebee; padding: 14px;
    border-radius: 14px; font-size: 14px; margin-bottom: 25px;
    font-weight: 700; border-left: 5px solid #d32f2f;
    animation: shake 0.4s ease;
}

@keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

</style>
</head>
<body>

<div class="login-card">
    <header class="header-login">
        <img src="img/cat.png" class="logo-icon" alt="Logo Focus">
        <h2>Focus</h2>
        <p>Acesse seu painel operacional</p>
    </header>

    <?php if($erro): ?>
        <div class="erro">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label>Identificação</label>
            <i class="fa-regular fa-user icon-field"></i>
            <input type="text" name="login" placeholder="E-mail ou Usuário" required autocomplete="off">
        </div>

        <div class="input-group">
            <label>Chave de Acesso</label>
            <i class="fa-solid fa-lock icon-field"></i>
            <input type="password" name="senha" id="senha" placeholder="Sua senha" required>
            <button type="button" class="eye-btn" onclick="toggleSenha('senha','eye-icon')">
                <i class="fa-regular fa-eye-slash" id="eye-icon"></i>
            </button>
        </div>

        <div class="options">
            <label><input type="checkbox" name="lembrar"> Manter conectado</label>
            <a href="esqueceu_senha.php" class="link-esqueceu">Esqueceu a senha?</a>
        </div>

        <button type="submit" class="btn">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Autenticar
        </button>
    </form>

    <div class="divider">Novo por aqui?</div>

    <a href="cadastro.php" style="text-decoration:none;">
        <button class="btn-outline" type="button">
            <i class="fa-solid fa-user-plus"></i> Criar Nova Conta
        </button>
    </a>
</div>

<script>

function toggleSenha(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("fa-eye-slash", "fa-eye");
    } else {
        input.type = "password";
        icon.classList.replace("fa-eye", "fa-eye-slash");
    }
}

</script>

</body>
</html>