<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {

    header("Location: login.php");

    exit;
}

$id_usuario = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT nome, username, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$id_usuario]);
$u = $stmt->fetch();
$username = !empty($u['username']) ? $u['username'] : 'usuário';
$foto_perfil = !empty($u['foto_perfil']) ? $u['foto_perfil'] : 'img/padrao.png';
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Core Settings | Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>

        /* 
           DESIGN DO SISTEMA
        */

        :root {
            --primary: #1a2639;
            --primary-light: #2d3a5e;
            --accent: #facc15;
            --accent-glow: rgba(250, 204, 21, 0.4);
            --bg: #f1f5f9;
            --white: #ffffff;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-soft: #64748b;
            --border: #e2e8f0;
            --input-bg: #f8fafc;
            --active-card-bg: #ffffff;
            --nav-bg: #1a2639;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body.dark-theme {

            --primary: #facc15;
            --accent: #facc15;
            --bg: #020617;
            --card-bg: #0f172a;
            --text-main: #f1f5f9;
            --text-soft: #94a3b8;
            --border: #334155;
            --input-bg: #1e293b;
            --active-card-bg: #1e293b;
            --nav-bg: #020617;
        }

        body.purple-theme {
            --primary: #a855f7;
            --accent: #a855f7;
            --accent-glow: rgba(168, 85, 247, 0.5);
            --bg: #0f0720;
            --card-bg: #120626;
            --text-main: #f5f3ff;
            --text-soft: #c084fc;
            --border: #4c1d95;
            --input-bg: rgba(255,255,255,0.05);
            --active-card-bg: #2e1065;
            --nav-bg: #120626;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease; }

        body {
            background-color: var(--bg);
            min-height: 100vh;
            color: var(--text-main);
            background-image: radial-gradient(var(--border) 1px, transparent 1px);
            background-size: 30px 30px;
            display: flex; justify-content: center; align-items: center; padding: 60px 20px;
        }

        .settings-hub {
            background: var(--card-bg); width: 100%; max-width: 900px;
            border-radius: 50px; box-shadow: 0 40px 100px rgba(0,0,0,0.3);
            padding: 60px; border: 2px solid var(--border);
            animation: slideUp 0.8s var(--transition); position: relative; overflow: hidden;
        }

        .settings-hub::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 10px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .hub-header {
             text-align: center; margin-bottom: 50px;
             }

        .hub-header h1 {
             font-size: 44px; font-weight: 900; letter-spacing: -2px; color: var(--text-main);
             }

        .hub-header p {
             color: var(--text-soft); font-weight: 600; margin-top: 10px;
             }

        .config-section {
             margin-bottom: 50px;
             }

        .section-title {
            font-size: 14px; font-weight: 800; color: var(--accent);
            text-transform: uppercase; letter-spacing: 2px; margin-bottom: 25px;
            display: flex; align-items: center; gap: 15px;
        }

        .section-title::after {
             content: ''; flex: 1; height: 1px; background: var(--border);
        }

        .theme-grid {
             display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;
        }

        .theme-card {
            cursor: pointer; padding: 25px 15px; border-radius: 25px; border: 2px solid var(--border);
            text-align: center; background: var(--input-bg); transition: var(--transition);
        }

        .theme-card:hover {
             transform: translateY(-8px); border-color: var(--accent);
             }

        .theme-card.active {
            border-color: var(--accent); background: var(--active-card-bg);
            box-shadow: 0 10px 30px var(--accent-glow);
        }

        .color-preview {
             width: 100%; height: 45px; border-radius: 12px; margin-bottom: 15px; display: flex; overflow: hidden; border: 1px solid rgba(0,0,0,0.1);
             }

        .cp-orig {
             background: #1a2639; flex: 1;
             }

        .cp-dark {
             background: #020617; flex: 1;
             }

        .cp-purp {
             background: #0f0720; flex: 1;
             }

        .cp-acc {
             background: #facc15; width: 35%;
             }

        .setting-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 22px 28px; background: var(--input-bg); border-radius: 22px;
            margin-bottom: 12px; border: 1px solid transparent; transition: 0.3s;

        }

        .setting-item:hover {
             border-color: var(--accent); transform: translateX(10px);
             }

        .item-info b {
             display: block; font-size: 17px; color: var(--text-main);
             }

        .item-info span {
             font-size: 13px; color: var(--text-soft);
             }

        .switch {
             position: relative; display: inline-block; width: 60px; height: 32px;
             }

        .switch input {
             opacity: 0; width: 0; height: 0;
             }

        .slider {
             position: absolute; cursor: pointer; inset: 0; background-color: #334155; transition: .4s; border-radius: 30px;
             }

        .slider:before {
             position: absolute; content: ""; height: 24px; width: 24px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%;
             }

        input:checked + .slider {
             background-color: var(--accent);
             }

        input:checked + .slider:before {
             transform: translateX(28px);
             }

        .btn-save-exit {
            width: 100%; padding: 25px; border-radius: 22px; background: var(--nav-bg);
            color: #fff; border: 2px solid var(--accent); font-size: 18px; font-weight: 900;
            text-transform: uppercase; cursor: pointer; margin-top: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2); letter-spacing: 1px;
        }

        .btn-save-exit:hover { background: var(--accent); color: #000; transform: scale(1.02); }

        /* TOAST COM CORES FIXAS PARA NÃO DESAPARECER */

        .toast-engine {
            position: fixed; bottom: 40px; right: -600px;
            background: #1a2639; color: #ffffff; /* Fundo escuro fixo */
            padding: 25px 45px; border-radius: 25px; border-left: 10px solid #facc15;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5); z-index: 99999;
            display: flex; align-items: center; gap: 20px;
            transition: right 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            font-weight: 800;
        }

        .toast-engine.show { right: 40px; }

        @keyframes slideUp { from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 800px) {

            .settings-hub { padding: 30px; }

            .theme-grid { grid-template-columns: 1fr; }
        }

    </style>

</head>

<body>



    <div id="toast" class="toast-engine">

        <i class="fa-solid fa-microchip fa-spin" style="color: #facc15; font-size: 28px;"></i>

        <span>Sincronizando...</span>

    </div>



    <div class="settings-hub">

        <header class="hub-header">

            <h1>Configurações</h1>

            <p>Gerenciamento de núcleo e interface Focus OS.</p>

        </header>



        <div class="config-section">

            <div class="section-title">Aparência e Design</div>

            <div class="theme-grid">

                <div class="theme-card" id="t-default" onclick="setTheme('default')">

                    <div class="color-preview"><div class="cp-orig"></div><div class="cp-acc"></div></div>

                    <b>Original Navy</b>

                </div>

                <div class="theme-card" id="t-dark" onclick="setTheme('dark')">

                    <div class="color-preview"><div class="cp-dark"></div><div class="cp-acc"></div></div>

                    <b>Dark Matrix</b>

                </div>

                <div class="theme-card" id="t-purple" onclick="setTheme('purple')">

                    <div class="color-preview"><div class="cp-purp"></div><div style="background:#a855f7; width:35%;"></div></div>

                    <b>Purple Night</b>

                </div>

            </div>



            <div class="setting-item">

                <div class="item-info">

                    <b>Acessibilidade Visual</b>

                    <span>Otimizar contraste para leitura densa.</span>

                </div>

                <label class="switch"><input type="checkbox" id="c-font"><span class="slider"></span></label>

            </div>

        </div>



        <div class="config-section">

            <div class="section-title">Protocolos do HUD</div>

            <div class="setting-item">

                <div class="item-info">

                    <b>Alertas de Metas</b>

                    <span>Notificações de prazos no HUD.</span>

                </div>

                <label class="switch"><input type="checkbox" id="c-notif" checked><span class="slider"></span></label>

            </div>

            <div class="setting-item">

                <div class="item-info">

                    <b>Sons do Sistema</b>

                    <span>Feedback auditivo ao sincronizar.</span>

                </div>

                <label class="switch"><input type="checkbox" id="c-sounds"><span class="slider"></span></label>

            </div>

        </div>



        <div class="config-section">

            <div class="section-title">Manutenção</div>

            <div class="setting-item" onclick="clearCache()" style="cursor:pointer;">

                <div class="item-info">

                    <b style="color: #ef4444;">Resetar Interface</b>

                    <span>Limpar cache de temas locais.</span>

                </div>

                <i class="fa-solid fa-trash-can" style="color: #ef4444; font-size: 20px;"></i>

            </div>

        </div>



        <button class="btn-save-exit" onclick="finalizeConfigs()">Sincronizar e Voltar</button>

    </div>



    <script>

        function setTheme(theme) {

            document.body.classList.remove('dark-theme', 'purple-theme');

            document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));



            if(theme === 'dark') {

                document.body.classList.add('dark-theme');

                document.getElementById('t-dark').classList.add('active');

            } else if(theme === 'purple') {

                document.body.classList.add('purple-theme');

                document.getElementById('t-purple').classList.add('active');

            } else {

                document.getElementById('t-default').classList.add('active');

            }

            localStorage.setItem('focus_theme', theme);

        }



        function clearCache() {

            if(confirm("Resetar visual para o padrão original?")) {

                localStorage.clear();

                location.reload();

            }

        }



        function finalizeConfigs() {

            const settings = {

                notif: document.getElementById('c-notif').checked,

                sounds: document.getElementById('c-sounds').checked

            };

            localStorage.setItem('focus_global_settings', JSON.stringify(settings));

            document.getElementById('toast').classList.add('show');

            setTimeout(() => { window.location.href = "dashboard.php"; }, 2000);

        }



        document.addEventListener('DOMContentLoaded', () => {

            setTheme(localStorage.getItem('focus_theme') || 'default');

            const saved = JSON.parse(localStorage.getItem('focus_global_settings'));

            if(saved) {

                document.getElementById('c-notif').checked = saved.notif;

                document.getElementById('c-sounds').checked = saved.sounds;

            }

        });

    </script>

</body>

</html>