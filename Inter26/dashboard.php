<?php
session_start();
require_once 'conexao.php';

// Se não tiver sessão, tenta auto-login. Se falhar, manda pro login.
if (!isset($_SESSION['usuario_id']) && !verificarAutoLogin($pdo)) {
    header("Location: login.php");
    exit;
}

// Se tiver logado mas sem username, força a completar perfil
if (!isset($_SESSION['usuario_username'])) {
    header("Location: setup_perfil.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; margin: 0; }
        nav { background: #1a2639; padding: 15px; color: white; display: flex; justify-content: space-between; }
        nav a { color: #facc15; text-decoration: none; font-weight: bold; }
        .container { padding: 20px; }
    </style>
</head>
<body>
    <nav>
        <span>Bem-vindo(a), @<?php echo $_SESSION['usuario_username']; ?>!</span>
        <a href="logout.php">Sair do Sistema</a>
    </nav>
    <div class="container">
        <h1>Seu Painel de Estudos</h1>
        <p>Login realizado com sucesso. A conexão e a lógica estão perfeitas!</p>
    </div>
</body>
</html>