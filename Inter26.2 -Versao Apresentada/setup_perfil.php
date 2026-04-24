<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$erro = '';

$usuario_id = $_SESSION['usuario_id'];

// Dados atuais
$stmt = $pdo->prepare("SELECT nome, username, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $username = trim($_POST['username']);
    $foto = $_POST['foto_perfil'];

    // verifica username duplicado
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
    $stmt->execute([$username, $usuario_id]);

    if ($stmt->rowCount() > 0) {
        $erro = "Esse nome de utilizador já está em uso.";
    } else {

        $stmt = $pdo->prepare("UPDATE usuarios SET username = ?, foto_perfil = ? WHERE id = ?");

        if ($stmt->execute([$username, $foto, $usuario_id])) {

            $_SESSION['usuario_username'] = $username;

            header("Location: dashboard.php");
            exit;

        } else {
            $erro = "Erro ao atualizar perfil.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Completar Perfil | Focus OS</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    /* OCEAN SAND - NOVO PADRÃO HUD */
    --primary: #5b7c99;
    --primary-light: #7da0bd;
    --accent: #ffd174;
    --accent-glow: rgba(212, 173, 96, 0.3);

    --bg: #f2efea;
    --card-bg: #ffffff;

    --text: #455a64;
    --text-soft: #78909c;

    --border: #d1d9e0;
    --input-bg: #f8fafc;

    --transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', sans-serif;
}

body{
    background: var(--bg);
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:40px 20px;
    background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
    background-size:30px 30px;
}

.card{
    width:100%;
    max-width:520px;
    background:var(--card-bg);
    border-radius:40px;
    padding:50px;
    border:2px solid var(--border);
    border-bottom:12px solid var(--primary);
    box-shadow:0 25px 70px rgba(0,0,0,0.08);
    animation:fadeUp .8s ease;
}

@keyframes fadeUp{
    from{opacity:0; transform:translateY(30px);}
    to{opacity:1; transform:translateY(0);}
}

.header{
    text-align:center;
    margin-bottom:35px;
}

.header h1{
    font-size:30px;
    font-weight:900;
    color:var(--text);
}

.header p{
    color:var(--text-soft);
    font-weight:600;
    margin-top:5px;
}

.section{
    margin-top:25px;
    margin-bottom:10px;
    font-size:11px;
    font-weight:900;
    letter-spacing:1.5px;
    text-transform:uppercase;
    color:var(--primary);
}

.input{
    width:100%;
    padding:18px;
    border-radius:18px;
    border:2px solid var(--border);
    background:var(--input-bg);
    font-size:15px;
    font-weight:600;
    color:var(--text);
    outline:none;
    transition:var(--transition);
}

.input:focus{
    border-color:var(--accent);
    box-shadow:0 0 15px var(--accent-glow);
}

.avatars{
    display:flex;
    justify-content:space-between;
    gap:15px;
    margin-top:15px;
}

.avatar{
    flex:1;
    cursor:pointer;
}

.avatar input{
    display:none;
}

.avatar img{
    width:80px;
    height:80px;
    border-radius:50%;
    border:4px solid var(--border);
    object-fit:cover;
    transition:var(--transition);
    filter:grayscale(40%);
}

.avatar input:checked + img{
    border-color:var(--accent);
    transform:scale(1.1);
    filter:grayscale(0%);
    box-shadow:0 10px 25px var(--accent-glow);
}

.btn{
    width:100%;
    margin-top:30px;
    padding:18px;
    border:none;
    border-radius:20px;
    background:var(--primary);
    color:white;
    font-weight:900;
    font-size:15px;
    cursor:pointer;
    transition:var(--transition);
}

.btn:hover{
    transform:translateY(-4px);
    background:var(--primary-light);
    box-shadow:0 15px 30px rgba(0,0,0,0.15);
}

.error{
    background:#ffe5e5;
    color:#c53030;
    padding:12px;
    border-radius:15px;
    margin-bottom:20px;
    font-weight:700;
    border-left:5px solid #c53030;
}
</style>
</head>

<body>

<div class="card">

    <div class="header">
        <h1>Completar Perfil</h1>
        <p>Configuração inicial do seu ambiente Focus</p>
    </div>

    <?php if($erro): ?>
        <div class="error"><?php echo $erro; ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="section">Nome de utilizador</div>
        <input class="input" type="text" name="username"
        value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <div class="section">Escolha seu avatar</div>

        <div class="avatars">

            <label class="avatar">
                <input type="radio" name="foto_perfil" value="img/ex1.png" required>
                <img src="img/ex1.png">
            </label>

            <label class="avatar">
                <input type="radio" name="foto_perfil" value="img/ex2.png">
                <img src="img/ex2.png">
            </label>

            <label class="avatar">
                <input type="radio" name="foto_perfil" value="img/ex3.png">
                <img src="img/ex3.png">
            </label>

        </div>

        <button class="btn" type="submit">
            Iniciar Experiência Focus
        </button>

    </form>

</div>

</body>
</html>