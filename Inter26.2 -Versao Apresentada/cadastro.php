<?php
session_start();
require_once 'conexao.php';

if (isset($_SESSION['usuario_id']) || (function_exists('verificarAutoLogin') && verificarAutoLogin($pdo))) {
    header("Location: dashboard.php");
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirma_senha = $_POST['confirma_senha'];
    $lembrar = isset($_POST['lembrar']) ? true : false;

    if ($senha !== $confirma_senha) {
        $erro = "As senhas não coincidem!";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $erro = "Este e-mail já está cadastrado.";
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$nome, $email, $senha_hash])) {
                $usuario_id = $pdo->lastInsertId();
                session_unset(); 
                $_SESSION['usuario_id'] = $usuario_id;
                $_SESSION['usuario_nome'] = $nome;

                if ($lembrar) {
                    $token = bin2hex(random_bytes(32)); 
                    $stmt = $pdo->prepare("UPDATE usuarios SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $usuario_id]);
                    setcookie('lembrar_token', $token, time() + (86400 * 30), "/"); 
                }
                
                $_SESSION['toast_msg'] = "Conta criada com sucesso! Vamos escolher seu Avatar? 🚀";
                header("Location: setup_perfil.php");
                exit;
            } else {
                $erro = "Erro ao cadastrar. Tente novamente.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta | Focus OS</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
            color: var(--text);
        }

        .register-card { 
            background: var(--white); 
            padding: 50px 40px; 
            border-radius: 40px; 
            width: 100%;
            max-width: 450px; 
            box-shadow: 0 25px 70px rgba(0,0,0,0.1); 
            text-align: center;
            animation: slideUp 0.8s var(--transition);
            border-bottom: 12px solid var(--accent);
            position: relative;
        }

        .header-reg { margin-bottom: 35px; }

        .header-reg .logo-icon { 
            width: 125px;
            height: auto;
            margin-bottom: 18px; 
            display: block;
            margin-left: auto;
            margin-right: auto;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.1));
            transition: 0.3s;
        }

        .header-reg .logo-icon:hover { transform: scale(1.05); }

        .header-reg h2 {
            font-size: 32px; font-weight: 900; color: var(--primary); letter-spacing: -1.5px;
        }

        .header-reg p {
            color: #78909c; font-weight: 600; font-size: 15px; margin-top: 5px;
        }

        .input-group { position: relative; margin-bottom: 18px; text-align: left; }

        .input-group i.icon-left { 
            position: absolute; left: 18px; top: 18px; color: #b0bec5; 
            font-size: 18px; transition: 0.3s;
        }

        .input-group input { 
            width: 100%; 
            padding: 16px 16px 16px 50px; 
            border: 2px solid var(--border); 
            border-radius: 16px; 
            font-size: 15px; 
            font-weight: 600;
            background: #fbfbfc; 
            outline: none; 
            transition: var(--transition);
            color: var(--text);
        }

        .input-group input:focus { 
            border-color: var(--primary); 
            background: white; 
            box-shadow: 0 0 20px rgba(91, 124, 153, 0.1); 
        }
        
        .input-group input:focus + i.icon-left { color: var(--primary); }

        .eye-btn { 
            position: absolute; right: 18px; top: 18px; 
            background: none; border: none; color: #b0bec5; 
            cursor: pointer; font-size: 18px;
        }

        .options { 
            display: flex; align-items: center; 
            font-size: 13px; margin: 20px 0 30px; 
            color: #78909c; font-weight: 600;
        }

        .options input { margin-right: 8px; accent-color: var(--primary); cursor: pointer; }

        .btn { 
            width: 100%; 
            padding: 18px; 
            background: var(--primary); 
            color: white; 
            border: none; 
            border-radius: 16px; 
            font-weight: 900; 
            font-size: 17px; 
            cursor: pointer; 
            transition: var(--transition); 
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 10px 20px rgba(91, 124, 153, 0.2);
        }
        
        .btn:hover { 
            transform: translateY(-5px); 
            background: var(--primary-light);
            box-shadow: 0 15px 30px rgba(91, 124, 153, 0.3);
        }

        .btn-outline { 
            background: transparent; 
            border: 2.5px solid var(--border); 
            margin-top: 20px; 
            color: #78909c;
            font-weight: 800;
            text-decoration: none;
            display: flex;
            align-items: center; justify-content: center; gap: 10px;
            padding: 14px; border-radius: 16px; font-size: 14px; transition: 0.3s;
        }
        
        .btn-outline:hover { 
            background: #f8fafc; 
            border-color: var(--primary);
            color: var(--primary);
        }

        .erro { 
            color: #d32f2f; background: #ffebee; 
            padding: 14px; border-radius: 14px; font-size: 14px; 
            margin-bottom: 25px; font-weight: 700;
            border-left: 5px solid #d32f2f;
            display: flex; align-items: center; gap: 10px;
        }

        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }

    </style>
</head>
<body>

<div class="register-card">
    <header class="header-reg">
        <img src="img/cat.png" class="logo-icon" alt="Logo Focus">
        <h2>Criar Conta</h2>
        <p>Inicie seu protocolo Focus OS</p>
    </header>

    <?php if($erro): ?>
        <div class="erro">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo $erro; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <i class="fa-regular fa-user icon-left"></i>
            <input type="text" name="nome" placeholder="Nome Completo" value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>" required>
        </div>

        <div class="input-group">
            <i class="fa-regular fa-envelope icon-left"></i>
            <input type="email" name="email" placeholder="E-mail Operacional" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" name="senha" id="senha_cad" placeholder="Crie uma senha forte" required>
            <button type="button" class="eye-btn" onclick="toggleSenha('senha_cad', 'eye-cad')">
                <i class="fa-regular fa-eye-slash" id="eye-cad"></i>
            </button>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" name="confirma_senha" id="confirma_cad" placeholder="Confirme sua senha" required>
            <i class="fa-solid fa-shield-check" style="position: absolute; right: 18px; top: 18px; color: #b0bec5; font-size: 18px; opacity: 0.5;"></i>
        </div>

        <div class="options">
            <label style="cursor: pointer;">
                <input type="checkbox" name="lembrar" checked> Lembrar dispositivo
            </label>
        </div>

        <button type="submit" class="btn">Finalizar Cadastro</button>
    </form>

    <a href="login.php" class="btn-outline">
        <i class="fa-solid fa-arrow-left"></i> Voltar ao Acesso
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