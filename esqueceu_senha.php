<?php
session_start();
require_once 'conexao.php';

$erro = '';
$link_simulado = ''; // Variável para o nosso modo de desenvolvimento

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        // Gera o token super seguro de 64 caracteres
        $token = bin2hex(random_bytes(32));
        // Validade de 30 minutos
        $expiracao = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        // Salva o token no banco
        $stmt = $pdo->prepare("UPDATE usuarios SET codigo_recuperacao = ?, codigo_expiracao = ? WHERE email = ?");
        $stmt->execute([$token, $expiracao, $email]);

        // Monta o Link Mágico
        $link_simulado = "http://localhost/sistema_estudos/nova_senha.php?token=" . $token;

    } else {
        // Mensagem genérica por segurança (não diz se o e-mail existe)
        $erro = "Se este e-mail estiver cadastrado, o link seria enviado (Simulação).";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Esqueceu a Senha - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #1a2639; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        h2 { color: #1a2639; margin-bottom: 5px; }
        p.subtitle { color: #666; font-size: 14px; margin-bottom: 25px; }
        .input-group { margin-bottom: 20px; }
        .input-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; outline: none; }
        .btn { width: 100%; padding: 12px; background: #facc15; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; color: #1a2639; font-size: 16px;}
        .erro { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 14px;}
        
        /* Estilo para a caixa de simulação */
        .caixa-dev { background-color: #f0fdf4; border: 2px dashed #22c55e; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: left;}
        .caixa-dev h3 { margin: 0 0 10px 0; color: #166534; font-size: 16px;}
        .caixa-dev p { color: #15803d; font-size: 14px; margin-bottom: 15px;}
        .btn-dev { display: block; text-align: center; background: #22c55e; color: white; padding: 10px; border-radius: 6px; text-decoration: none; font-weight: bold;}
        .btn-dev:hover { background: #16a34a; }
        
        a.voltar { color: #1a2639; text-decoration: none; font-size: 14px; font-weight: bold; display: inline-block; margin-top: 15px;}
    </style>
</head>
<body>
<div class="card">
    <?php if($erro) echo "<div class='erro'>$erro</div>"; ?>

    <?php if($link_simulado): ?>
        <div class="caixa-dev">
            <h3><i class="fa-solid fa-code"></i> Modo Desenvolvimento</h3>
            <p>O envio de e-mail foi simulado com sucesso. Clique no botão abaixo para acessar o link mágico que chegaria na sua caixa de entrada:</p>
            <a href="<?php echo $link_simulado; ?>" class="btn-dev">Acessar Link de Recuperação</a>
        </div>
        <a href="login.php" class="voltar"><i class="fa-solid fa-arrow-left"></i> Voltar ao Login</a>
    
    <?php else: ?>
        <h2>Recuperar Senha</h2>
        <p class="subtitle">Digite seu e-mail para receber o link de acesso</p>
        <form method="POST">
            <div class="input-group">
                <input type="email" name="email" placeholder="Seu e-mail cadastrado" required>
            </div>
            <button type="submit" class="btn">Gerar Link de Acesso</button>
        </form>
        <a href="login.php" class="voltar"><i class="fa-solid fa-arrow-left"></i> Voltar ao Login</a>
    <?php endif; ?>
</div>
</body>
</html>