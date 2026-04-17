<?php
session_start();
require_once 'conexao.php';

if (isset($_SESSION['usuario_id']) || verificarAutoLogin($pdo)) {
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
                
                // ==========================================
                // O PULO DO GATO: Limpa a poluição de testes anteriores!
                // Isso garante que não tem nenhum 'username' velho na memória.
                session_unset(); 
                // ==========================================

                // Agora sim, criamos a sessão novinha em folha para este usuário
                $_SESSION['usuario_id'] = $usuario_id;
                $_SESSION['usuario_nome'] = $nome;

                if ($lembrar) {
                    $token = bin2hex(random_bytes(32)); 
                    $stmt = $pdo->prepare("UPDATE usuarios SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $usuario_id]);
                    setcookie('lembrar_token', $token, time() + (86400 * 30), "/"); 
                }
                
                // Prepara a mensagem bonita do Toast!
                $_SESSION['toast_msg'] = "Conta criada com sucesso! Vamos escolher seu Avatar? 🚀";
                
                // Redireciona para completar o perfil (agora vai dar certo!)
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
    <title>Cadastro - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS mantido igualzinho ao seu */
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #1a2639; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px;}
        .card { background: white; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); text-align: center; }
        h2 { margin: 0 0 5px 0; color: #1a2639; }
        p.subtitle { color: #666; font-size: 14px; margin-bottom: 25px; }
        .input-group { position: relative; margin-bottom: 15px; text-align: left; }
        .input-group i.icon-left { position: absolute; left: 15px; top: 14px; color: #999; }
        .input-group input { width: 100%; padding: 12px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: #f9f9f9; outline: none; }
        .input-group input:focus { border-color: #facc15; background: white; }
        .eye-btn { position: absolute; right: 15px; top: 14px; background: none; border: none; color: #999; cursor: pointer; }
        .options { display: flex; justify-content: flex-start; align-items: center; font-size: 13px; margin-bottom: 20px; color: #666; }
        .btn { width: 100%; padding: 12px; background: #facc15; border: none; border-radius: 8px; font-weight: bold; color: #1a2639; font-size: 16px; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #eab308; }
        .btn-outline { background: transparent; border: 2px solid #ddd; margin-top: 15px; color: #666;}
        .btn-outline:hover { background: #f9f9f9; }
        .erro { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; }
    </style>
</head>
<body>

<div class="card">
    <h2>Criar Conta</h2>
    <p class="subtitle">Junte-se à nossa plataforma</p>

    <?php if($erro) echo "<div class='erro'><i class='fa-solid fa-circle-exclamation'></i> $erro</div>"; ?>

    <form method="POST">
        <div class="input-group">
            <i class="fa-regular fa-id-badge icon-left"></i>
            <input type="text" name="nome" placeholder="Nome completo" required>
        </div>

        <div class="input-group">
            <i class="fa-regular fa-envelope icon-left"></i>
            <input type="email" name="email" placeholder="E-mail" required>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock icon-left"></i>
            <input type="password" name="senha" id="senha_cad" placeholder="Crie uma senha" required>
            <button type="button" class="eye-btn" onclick="toggleSenha('senha_cad', 'eye-cad')"><i class="fa-regular fa-eye-slash" id="eye-cad"></i></button>
        </div>

        <div class="input-group">
            <i class="fa-solid fa-check-double icon-left"></i>
            <input type="password" name="confirma_senha" id="confirma_cad" placeholder="Confirme a senha" required>
        </div>

        <div class="options">
            <label><input type="checkbox" name="lembrar" checked> Lembrar de mim neste dispositivo</label>
        </div>

        <button type="submit" class="btn">Cadastrar</button>
    </form>

    <a href="login.php"><button class="btn btn-outline"><i class="fa-solid fa-arrow-left"></i> Voltar ao Login</button></a>
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
