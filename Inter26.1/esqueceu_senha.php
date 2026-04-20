<?php
session_start();
require_once 'conexao.php';

$erro = '';
$link_simulado = ''; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario) {
        $token = bin2hex(random_bytes(32));
        $expiracao = date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $stmt = $pdo->prepare("UPDATE usuarios SET codigo_recuperacao = ?, codigo_expiracao = ? WHERE email = ?");
        $stmt->execute([$token, $expiracao, $email]);

        // Link de redirecionamento interno
        $link_simulado = "nova_senha.php?token=" . $token;

    } else {
        $erro = "Se este e-mail estiver cadastrado, as instruções serão enviadas em instantes.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha | Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================================================================
           DESIGN SYSTEM FOCUS
           ================================================================
        */
        :root {
            --primary: #1a2639;
            --primary-light: #2d3a5e;
            --accent: #facc15;
            --bg: #f1f5f9;
            --text: #1e293b;
            --white: #ffffff;
            --border: #e2e8f0;
            --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        body { 
            background-color: var(--primary); 
            min-height: 100vh; 
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background-image: radial-gradient(rgba(255,255,255,0.05) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
        }

        .recovery-card { 
            background: var(--white); 
            padding: 50px 40px; 
            border-radius: 40px; 
            width: 100%;
            max-width: 420px; 
            box-shadow: 0 25px 70px rgba(0,0,0,0.4); 
            text-align: center;
            animation: slideUp 0.8s var(--transition);
            border-bottom: 12px solid var(--accent);
            position: relative;
        }

        .header-rec {
             margin-bottom: 35px;
             }

        .header-rec i {
             font-size: 40px; color: var(--primary); margin-bottom: 15px; display: block;
             }

        .header-rec h2 {
             font-size: 30px; font-weight: 900; color: var(--primary); letter-spacing: -1px;
             }

        .header-rec p {
             color: #64748b; font-weight: 600; font-size: 15px; margin-top: 5px;
             }

        .input-group {
             position: relative; margin-bottom: 25px; text-align: left;
             }
             
        .input-group input { 
            width: 100%; 
            padding: 18px; 
            border: 2px solid var(--border); 
            border-radius: 16px; 
            font-size: 15px; 
            font-weight: 600;
            background: #f8fafc; 
            outline: none; 
            transition: var(--transition);
            color: var(--primary);
            text-align: center;
        }
        .input-group input:focus {
             border-color: var(--accent); background: white; box-shadow: 0 0 20px rgba(250, 204, 21, 0.15);
             }

        .btn { 
            width: 100%; 
            padding: 18px; 
            background: var(--primary); 
            color: var(--accent); 
            border: none; 
            border-radius: 16px; 
            font-weight: 950; 
            font-size: 16px; 
            cursor: pointer; 
            transition: var(--transition); 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn:hover {
             transform: translateY(-5px); background: var(--primary-light); box-shadow: 0 10px 25px rgba(0,0,0,0.2);
             }

        .success-box { 
            background: #f0fdf4; 
            border-radius: 20px; 
            margin-bottom: 25px; 
            padding: 30px 20px;
            text-align: center;
            border: 2px solid #bbf7d0;
            animation: fadeIn 0.6s ease;
        }
        .success-box p {
             color: #166534; font-size: 15px; line-height: 1.6; margin-bottom: 20px; font-weight: 600;
             }
        
        .btn-confirm { 
            display: block; 
            background: #22c55e; 
            color: white; 
            padding: 16px; 
            border-radius: 14px; 
            text-decoration: none; 
            font-weight: 900;
            font-size: 14px;
            text-transform: uppercase;
            transition: 0.3s;
        }
        .btn-confirm:hover {
             background: #16a34a; transform: scale(1.02);
             }

        .error-box { 
            color: #ef4444; background: rgba(239, 68, 68, 0.05); 
            padding: 15px; border-radius: 15px; font-size: 14px; 
            margin-bottom: 20px; font-weight: 700; border-left: 5px solid #ef4444;
        }

        .btn-voltar { 
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 25px; color: #94a3b8; text-decoration: none; 
            font-weight: 800; font-size: 13px; 
            transition: 0.3s; text-transform: uppercase;
            cursor: pointer; border: none; background: none;
            width: 100%;
        }
        .btn-voltar:hover {
             color: var(--primary); transform: translateX(-5px);
             }

        @keyframes slideUp {
             from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); }
             }

        @keyframes fadeIn {
             from { opacity: 0; } to { opacity: 1; }
             }

    </style>

</head>

<body>

<div class="recovery-card">
    <?php if($link_simulado): ?>
        <div class="header-rec">
            <i class="fa-solid fa-circle-check" style="color: #22c55e;"></i>
            <h2>Solicitação Enviada</h2>
            <p>Siga as instruções abaixo</p>
        </div>

        <div class="success-box">
            <p>O link para redefinição de senha foi gerado com sucesso para a sua conta.</p>
            <a href="<?php echo $link_simulado; ?>" class="btn-confirm">Alterar Senha Agora</a>
        </div>
        
        <a href="login.php" class="btn-voltar">
            <i class="fa-solid fa-arrow-left"></i> Voltar ao Login
        </a>

    <?php else: ?>
        <header class="header-rec">
            <i class="fa-solid fa-unlock-keyhole"></i>
            <h2>Esqueceu a senha?</h2>
            <p>Recupere seu acesso ao Focus</p>
        </header>

        <?php if($erro): ?>
            <div class="error-box">
                <i class="fa-solid fa-circle-info"></i> <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <input type="email" name="email" placeholder="Seu e-mail cadastrado" required>
            </div>
            <button type="submit" class="btn">Enviar Instruções</button>
        </form>
        
        <a href="login.php" class="btn-voltar">
            <i class="fa-solid fa-arrow-left"></i> Cancelar e Voltar
        </a>
    <?php endif; ?>
</div>

</body>
</html></html>