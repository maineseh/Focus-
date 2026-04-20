<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$erro = '';

// 1. Puxar dados atuais
$stmt = $pdo->prepare("SELECT nome, username, foto_perfil, perfil_cognitivo FROM usuarios WHERE id = ?");
$stmt->execute([$id_usuario]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome']);
    $username = trim($_POST['username']);
    $foto = $_POST['foto_perfil'];
    $perfil_cognitivo = $_POST['perfil_cognitivo'];

    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
    $stmt_check->execute([$username, $id_usuario]);

    if ($stmt_check->rowCount() > 0) {
        $erro = "Este nome de utilizador já está ocupado.";
    } else {
        $stmt_update = $pdo->prepare("UPDATE usuarios SET nome = ?, username = ?, foto_perfil = ?, perfil_cognitivo = ? WHERE id = ?");
        
        if ($stmt_update->execute([$nome, $username, $foto, $perfil_cognitivo, $id_usuario])) {
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['usuario_username'] = $username;
            $_SESSION['usuario_foto'] = $foto; 
            $_SESSION['toast_msg'] = "Perfil atualizado com sucesso! ✨";
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
    <title>Editar Perfil - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a2639;
            --accent: #facc15;
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #e2e8f0;
            --input-bg: #f8fafc;
            --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        }

        body.dark-theme {
            --primary: #facc15; --accent: #facc15; --bg: #020617; --card-bg: #0f172a;
            --text: #f1f5f9; --border: #1e293b; --input-bg: #1e293b;
        }

        body.purple-theme {
            --primary: #a855f7; --accent: #a855f7; --bg: #0f0720; --card-bg: #120626;
            --text: #f5f3ff; --border: #4c1d95; --input-bg: rgba(255,255,255,0.05);
        }

        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0;
     }
        
        body { 
            background-color: var(--bg); min-height: 100vh; color: var(--text);
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 30px 30px; display: flex; justify-content: center; align-items: center; padding: 40px 20px; transition: background 0.3s ease;
        }

        .edit-card { 
            background: var(--card-bg); padding: 50px; border-radius: 45px; width: 100%; max-width: 600px;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.1); animation: slideUp 0.8s var(--transition);
            border: 2px solid var(--border); border-bottom: 15px solid var(--primary);
        }

        .header-edit { margin-bottom: 35px; text-align: center; }
        .header-edit h1 { font-size: 32px; font-weight: 900; color: var(--text); letter-spacing: -1px; }

        .input-group {
             margin-bottom: 25px;
             }

        .input-group label {
             display: block; margin-bottom: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8;
             }

        .input-group input { 
            width: 100%; padding: 18px; border: 2px solid var(--border); border-radius: 18px; 
            font-size: 16px; font-weight: 600; color: var(--text); background: var(--input-bg); outline: none; transition: 0.3s;
        }

        .input-group input:focus {
             border-color: var(--accent); box-shadow: 0 0 20px rgba(250, 204, 21, 0.15);
             }

        .section-title {
             font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin: 30px 0 15px; opacity: 0.8; text-align: left;
             }

        /* GRID DE CHIPS */

        .chips-grid {
             display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 35px;
             }

        .chip-label {
             cursor: pointer;
             }

        .chip-label input {
             display: none;
             }

        .chip-text { 
            display: flex; align-items: center; justify-content: center; height: 45px; 
            background-color: var(--input-bg); color: var(--text); border-radius: 15px; 
            font-size: 11px; font-weight: 800; transition: 0.3s; border: 2px solid var(--border); text-transform: uppercase;
        }

        .chip-label input:checked + .chip-text {
             background-color: var(--primary); color: var(--bg); border-color: var(--accent); transform: scale(1.05);
             }

        /* AVATARES */

        .avatars-grid {
             display: flex; gap: 20px; margin-bottom: 40px; justify-content: center;
             }

        .avatar-option input {
             display: none;
             }

        .avatar-img {
             width: 85px; height: 85px; border-radius: 50%; border: 5px solid var(--border); cursor: pointer; transition: var(--transition); object-fit: cover;
             }

        .avatar-option input:checked + .avatar-img {
             border-color: var(--accent); transform: scale(1.1) translateY(-5px); box-shadow: 0 10px 25px rgba(250, 204, 21, 0.3);
             }

        .btn-group {
             display: flex; flex-direction: column; gap: 15px;
             }

        .btn-save {
             width: 100%; padding: 20px; background: var(--primary); color: #fff; border: none; border-radius: 20px; font-weight: 950; font-size: 17px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px;
             }

        body:not(.dark-theme):not(.purple-theme) .btn-save { color: var(--accent);
     }

        .btn-back {
             text-align: center; text-decoration: none; color: #64748b; font-weight: 700; font-size: 14px;
     }

        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); }
     }
     
    </style>

</head>

<body>

    <script>
        const theme = localStorage.getItem('focus_theme') || 'default';
        if(theme !== 'default') document.body.classList.add(theme + '-theme');
    </script>

    <div class="edit-card">
        <header class="header-edit">
            <h1>Editar Perfil</h1>
        </header>

        <?php if($erro): ?>
            <div style="color:#ef4444; background:rgba(239,68,68,0.1); padding:15px; border-radius:15px; font-weight:700; margin-bottom:25px; border-left:6px solid #ef4444; font-size:14px;">
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

            <div class="section-title">Estilo de Aprendizagem</div>
            <div class="chips-grid">
                <?php 
                $opcoes = ['TDAH', 'Ansiedade', 'Dislexia', 'TEA', 'Outros', 'Nenhum'];
                foreach($opcoes as $opt): 
                    $checked = ($user['perfil_cognitivo'] == $opt) ? 'checked' : '';
                ?>
                <label class="chip-label">
                    <input type="radio" name="perfil_cognitivo" value="<?php echo $opt; ?>" <?php echo $checked; ?>>
                    <span class="chip-text"><?php echo ($opt == 'Nenhum' ? 'Padrão' : $opt); ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="section-title">Sua Identidade (Avatar)</div>
            <div class="avatars-grid">
                <label class="avatar-option">
                    <input type="radio" name="foto_perfil" value="img/ex1.png" <?php echo $user['foto_perfil'] == 'img/ex1.png' ? 'checked' : ''; ?>>
                    <img src="img/ex1.png" class="avatar-img" alt="Avatar 1">
                </label>
                <label class="avatar-option">
                    <input type="radio" name="foto_perfil" value="img/ex2.png" <?php echo $user['foto_perfil'] == 'img/ex2.png' ? 'checked' : ''; ?>>
                    <img src="img/ex2.png" class="avatar-img" alt="Avatar 2">
                </label>
                <label class="avatar-option">
                    <input type="radio" name="foto_perfil" value="img/ex3.png" <?php echo $user['foto_perfil'] == 'img/ex3.png' ? 'checked' : ''; ?>>
                    <img src="img/ex3.png" class="avatar-img" alt="Avatar 3">
                </label>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-save">Salvar Alterações</button>
                <a href="dashboard.php" class="btn-back">
                    <i class="fa-solid fa-chevron-left"></i> Voltar ao Painel
                </a>
            </div>
        </form>
    </div>
</body>
</html>