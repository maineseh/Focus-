<?php
session_start();

// 1. Esvazia todas as variáveis da sessão atual
$_SESSION = array();

// 2. Apaga o cookie padrão de sessão do PHP do navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Destrói a sessão no servidor
session_destroy();

// 4. MATA o nosso cookie "Lembrar de mim" forçando o caminho raiz ('/')
setcookie('lembrar_token', '', time() - 3600, '/');
unset($_COOKIE['lembrar_token']); // Remove da memória do script atual

// Redireciona pro login limpo
header("Location: login.php");
exit;
?>