<?php
$host = 'localhost';
$dbname = 'sistema_estudos';
$usuario = 'root';
$senha = ''; // Coloque a senha do seu banco se houver

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $usuario, $senha);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Função global para verificar o cookie de "Lembrar-se de mim"
function verificarAutoLogin($pdo) {
    if (!isset($_SESSION['usuario_id']) && isset($_COOKIE['lembrar_token'])) {
        $token = $_COOKIE['lembrar_token'];
        $stmt = $pdo->prepare("SELECT id, nome, username FROM usuarios WHERE remember_token = ?");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            if (!empty($usuario['username'])) {
                $_SESSION['usuario_username'] = $usuario['username'];
            }
            return true;
        }
    }
    return false;
}
?>