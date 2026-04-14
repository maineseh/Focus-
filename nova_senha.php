<?php
session_start();
require_once 'conexao.php';

$erro = '';
$token_valido = false;
$usuario_id = null;

// 1. Verifica se a pessoa chegou aqui clicando no link (com o Token na URL)
if (isset($_GET['token'])) {
    $token_url = $_GET['token'];

    // Busca no banco se esse token existe e se não expirou
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE codigo_recuperacao = ? AND codigo_expiracao > NOW()");
    $stmt->execute([$token_url]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        $token_valido = true;
        $usuario_id = $usuario['id']; // Guarda de quem é essa senha
    } else {
        $erro = "Link de recuperação inválido ou expirado. Por favor, solicite um novo.";
    }
} else {
    // Se tentou acessar a página direto sem link, expulsa pro login
    header("Location: login.php");
    exit;
}

// 2. Quando o usuário preenche a nova senha e clica em Salvar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $token_valido) {
    $senha = $_POST['senha'];
    $confirma_senha = $_POST['confirma_senha'];

    if ($senha !== $confirma_senha) {
        $erro = "As senhas não coincidem!";
    } else {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // Atualiza a senha e DELETA o token (para o link não funcionar de novo)
        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ?, codigo_recuperacao = NULL, codigo_expiracao = NULL WHERE id = ?");
        
        if ($stmt->execute([$senha_hash, $usuario_id])) {
            // Sucesso! Manda pro login
            header("Location: login.php?msg=senha_atualizada");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Nova Senha - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #1a2639; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; text-align: center; }
        h2 { color: #1a2639; margin-bottom: 5px; }
        p.subtitle { color: #666; font-size: 14px; margin-bottom: 25px; }
        .input-group { margin-bottom: 15px; }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; outline: none; }
        .btn { width: 100%; padding: 12px; background: #facc15; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        .erro { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="card">
    <?php if(!$token_valido): ?>
        <i class="fa-solid fa-triangle-exclamation" style="font-size: 40px; color: #dc2626; margin-bottom:15px;"></i>
        <h2>Erro no Link</h2>
        <p class="subtitle"><?php echo $erro; ?></p>
        <a href="esqueceu_senha.php"><button class="btn" style="background:#1a2639; color:white;">Solicitar novo link</button></a>
    
    <?php else: ?>
        <h2>Criar Nova Senha</h2>
        <p class="subtitle">Digite sua nova senha de acesso.</p>
        
        <?php if($erro) echo "<div class='erro'>$erro</div>"; ?>

        <form method="POST" action="nova_senha.php?token=<?php echo htmlspecialchars($token_url); ?>">
            <div class="input-group">
                <input type="password" name="senha" placeholder="Nova Senha" required>
            </div>
            <div class="input-group">
                <input type="password" name="confirma_senha" placeholder="Confirme a Nova Senha" required>
            </div>
            <button type="submit" class="btn">Salvar Nova Senha</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>