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
       
        :root {
            --primary: #5b7c99;       /* Azul Oceano */
            --primary-light: #7da0bd; 
            --accent: #ffd174;        /* Gold Sand */
            --accent-glow: rgba(212, 173, 96, 0.4);
            --bg: #f2efea;            /* Creme Areia */
            --text: #455a64;          /* Deep Slate */
            --white: #ffffff;
            --border: #d1d9e0;
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
            background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 35px 35px;
        }

        .recovery-card { 
            background: var(--bg); 
            padding: 55px 45px; 
            border-radius: 45px; 
            width: 100%;
            max-width: 440px; 
            box-shadow: 0 30px 80px rgba(0,0,0,0.3); 
            text-align: center;
            animation: slideUp 0.8s var(--transition);
            border: 2px solid var(--border);
            border-bottom: 12px solid var(--accent);
            position: relative;
        }

        .header-rec {
             margin-bottom: 35px;
        }

        .header-rec i {
             font-size: 45px; color: var(--primary); margin-bottom: 15px; display: block;
             filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
        }

        .header-rec h2 {
             font-size: 32px; font-weight: 900; color: var(--primary); letter-spacing: -1.5px;
        }

        .header-rec p {
             color: var(--text); font-weight: 600; font-size: 15px; margin-top: 8px; opacity: 0.8;
        }

        .input-group {
             position: relative; margin-bottom: 25px; text-align: left;
        }
             
        .input-group input { 
            width: 100%; 
            padding: 20px; 
            border: 2px solid var(--border); 
            border-radius: 20px; 
            font-size: 16px; 
            font-weight: 600;
            background: var(--white); 
            outline: none; 
            transition: var(--transition);
            color: var(--text);
            text-align: center;
        }

        .input-group input:focus {
             border-color: var(--accent); transform: scale(1.02); box-shadow: 0 10px 25px var(--accent-glow);
        }

        .btn { 
            width: 100%; 
            padding: 20px; 
            background: var(--primary); 
            color: var(--white); 
            border: none; 
            border-radius: 20px; 
            font-weight: 900; 
            font-size: 16px; 
            cursor: pointer; 
            transition: var(--transition); 
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 8px 20px rgba(91, 124, 153, 0.3);
        }

        .btn:hover {
             transform: translateY(-5px); background: var(--primary-light); box-shadow: 0 12px 25px rgba(0,0,0,0.2);
        }

        .success-box { 
            background: var(--white); 
            border-radius: 25px; 
            margin-bottom: 25px; 
            padding: 35px 25px;
            text-align: center;
            border: 2px solid var(--accent);
            animation: fadeIn 0.6s ease;
        }

        .success-box p {
             color: var(--text); font-size: 15px; line-height: 1.6; margin-bottom: 25px; font-weight: 600;
        }
        
        .btn-confirm { 
            display: block; 
            background: #81c784; /* Verde suave da paleta */
            color: white; 
            padding: 18px; 
            border-radius: 18px; 
            text-decoration: none; 
            font-weight: 900;
            font-size: 14px;
            text-transform: uppercase;
            transition: 0.3s;
            box-shadow: 0 6px 15px rgba(129, 199, 132, 0.3);
        }

        .btn-confirm:hover {
             background: #66bb6a; transform: scale(1.05);
        }

        .error-box { 
            color: #e57373; background: rgba(229, 115, 115, 0.1); 
            padding: 18px; border-radius: 18px; font-size: 14px; 
            margin-bottom: 25px; font-weight: 700; border-left: 6px solid #e57373;
        }

        .btn-voltar { 
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 25px; color: var(--text); text-decoration: none; 
            font-weight: 800; font-size: 13px; 
            transition: 0.3s; text-transform: uppercase;
            cursor: pointer; border: none; background: none;
            width: 100%; opacity: 0.6;
        }

        .btn-voltar:hover {
             color: var(--primary); transform: translateX(-5px); opacity: 1;
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
            <i class="fa-solid fa-envelope-circle-check" style="color: #81c784;"></i>
            <h2>Link Gerado</h2>
            <p>Protocolo de recuperação ativo</p>
        </div>

        <div class="success-box">
            <p>Identificamos sua conta. Para garantir a segurança, utilize o acesso temporário abaixo:</p>
            <a href="<?php echo $link_simulado; ?>" class="btn-confirm">Redefinir Senha Agora</a>
        </div>
        
        <a href="login.php" class="btn-voltar">
            <i class="fa-solid fa-arrow-left"></i> Voltar ao Login
        </a>

    <?php else: ?>
        <header class="header-rec">
            <i class="fa-solid fa-shield-halved"></i>
            <h2>Segurança</h2>
            <p>Recupere seu acesso ao Focus OS</p>
        </header>

        <?php if($erro): ?>
            <div class="error-box">
                <i class="fa-solid fa-circle-info"></i> <?php echo $erro; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <input type="email" name="email" placeholder="E-mail de cadastro" required>
            </div>
            <button type="submit" class="btn">Autenticar e Enviar</button>
        </form>
        
        <a href="login.php" class="btn-voltar">
            <i class="fa-solid fa-xmark"></i> Cancelar Operação
        </a>
    <?php endif; ?>
</div>

</body>
</html>