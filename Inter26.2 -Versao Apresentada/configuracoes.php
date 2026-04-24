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
    <title>Core Settings | Focus OS Gold</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #5b7c99;       
            --primary-light: #7da0bd; 
            --accent: #d4ad60;       
            --accent-glow: rgba(212, 173, 96, 0.4);
            --bg: #f2efea;           
            --white: #ffffff;
            --card-bg: #ffffff;
            --text-main: #455a64;    
            --text-soft: #78909c;
            --border: #d1d9e0;
            --input-bg: #f8fafc;
            --active-card-bg: #ffffff;
            --nav-bg: #5b7c99;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

    body.dark-theme {
    --primary: #c7ccd3;
    --primary-light: #e2e6ea;

    --accent: #d6d2c4;
    --accent-glow: rgba(214, 210, 196, 0.12);

    --bg: #1a2230;
    --card-bg: #242f3d;
    --active-card-bg: #2c3a4d;

    --text-main: #f7f9fc;
    --text-soft: #cbd5e1;

    --border: #3a4656;

    --input-bg: #273244;

    --nav-bg: #1a2230;

    --btn-bg: rgba(255,255,255,0.06);
}

      body.purple-theme {
    --primary: #8b7cff;
    --primary-light: #b4a8ff;

    --accent: #9a8cff;
    --accent-glow: rgba(154, 140, 255, 0.18);

    --bg: #0e0d16;
    --card-bg: #1a1730;
    --active-card-bg: #221c3f;

    --text-main: #f7f5ff;
    --text-soft: #ddd6ff;

    --border: #322a52;

    --input-bg: rgba(255,255,255,0.05);

    --nav-bg: #0e0d16;

    --btn-bg: rgba(139, 124, 255, 0.08);
}

        /* ACESSIBILIDADE - REDUÇÃO DE MOVIMENTO */

        body.reduce-motion * {
            animation: none !important;
            transition: none !important;
        }

        /* ACESSIBILIDADE - ALTO CONTRASTE */

        body.high-contrast {
            --bg: #000;
            --card-bg: #000;
            --text-main: #fff;
            --border: #fff;
            --accent: #ffff00;
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
            border-radius: 50px; box-shadow: 0 40px 100px rgba(0,0,0,0.15);
            padding: 60px; border: 2px solid var(--border);
            animation: slideUp 0.8s var(--transition); position: relative; overflow: hidden;
        }

        .settings-hub::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 10px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .hub-header { text-align: center; margin-bottom: 50px; }

        .hub-header h1 { font-size: 44px; font-weight: 900; letter-spacing: -2px; color: var(--text-main); }

        .hub-header p { color: var(--text-soft); font-weight: 600; margin-top: 10px; }

        .config-section { margin-bottom: 50px; }

        .section-title {
            font-size: 14px; font-weight: 800; color: var(--accent);
            text-transform: uppercase; letter-spacing: 2px; margin-bottom: 25px;
            display: flex; align-items: center; gap: 15px;
        }

        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .theme-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }

        .theme-card {
            cursor: pointer; padding: 25px 15px; border-radius: 25px; border: 2px solid var(--border);
            text-align: center; background: var(--input-bg); transition: var(--transition);
        }

        .theme-card:hover { transform: translateY(-8px); border-color: var(--accent); }

        .theme-card.active {
            border-color: var(--accent); background: var(--active-card-bg);
            box-shadow: 0 10px 30px var(--accent-glow);
        }

        .color-preview { width: 100%; height: 45px; border-radius: 12px; margin-bottom: 15px; display: flex; overflow: hidden; border: 1px solid rgba(0,0,0,0.1); }

/* OCEAN SAND */       
.cp-orig { 
    background: #5b7c99; 
    flex: 1; 
}

/* NIGHT GREY */
.cp-dark { 
    background: #1a2230; 
    flex: 1; 
}

.cp-dark-light {
    background: #ffffff;
    flex: 1;
}

/* LAVANDA */
.cp-purp { 
    background: #8b7cff; 
    flex: 1; 
}

.cp-purp-light {
    background: #ffffff;
    flex: 1;
}

/* ACCENT */
.cp-acc { 
    background: #d4ad60; 
    width: 35%; 
}
        .setting-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 22px 28px; background: var(--input-bg); border-radius: 22px;
            margin-bottom: 12px; border: 1px solid transparent; transition: 0.3s;
        }

        .setting-item:hover { border-color: var(--accent); transform: translateX(10px); }

        .item-info b { display: block; font-size: 17px; color: var(--text-main); }

        .item-info span { font-size: 13px; color: var(--text-soft); }

        /* TOGGLE SWITCH CUSTOM */
        .switch { position: relative; display: inline-block; width: 60px; height: 32px; }

        .switch input { opacity: 0; width: 0; height: 0; }

        .slider { position: absolute; cursor: pointer; inset: 0; background-color: #cbd5e1; transition: .4s; border-radius: 30px; }

        .slider:before { position: absolute; content: ""; height: 24px; width: 24px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }

        input:checked + .slider { background-color: var(--accent); }

        input:checked + .slider:before { transform: translateX(28px); }

        .btn-save-exit {
            width: 100%; padding: 25px; border-radius: 22px; background: var(--nav-bg);
            color: #fff; border: 2px solid var(--accent); font-size: 18px; font-weight: 900;
            text-transform: uppercase; cursor: pointer; margin-top: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1); letter-spacing: 1px;
        }

        .btn-save-exit:hover { background: var(--accent); color: #000; transform: scale(1.02); }

        .toast-engine {
            position: fixed; bottom: 40px; right: -600px;
            background: #1e293b; color: #ffffff;
            padding: 25px 45px; border-radius: 25px; border-left: 10px solid var(--accent);
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


.censored {
    position: relative;
    overflow: hidden;
}

.censored * {
    pointer-events: none;
    filter: blur(3px);
    user-select: none;
}

.censored::after {
    content: "NOVAS CONFIGURAÇÕES DE \A ACESSIBILIDADE EM BREVE \A :3";

    white-space: pre;

    position: absolute;
    inset: 0;

    display: flex;
    align-items: center;
    justify-content: center;

    text-align: center;

    font-size: 22px;
    font-weight: 900;
    letter-spacing: 2px;

    color: #ffffff;

    background: rgba(0,0,0,0.65);

    z-index: 10;

    border-radius: 30px;

    padding: 20px;
}


    </style>
</head>

<body>

    <div id="toast" class="toast-engine">
        <i class="fa-solid fa-microchip fa-spin" style="color: var(--accent); font-size: 28px;"></i>
        <span>Sincronizando Core...</span>
    </div>

    <div class="settings-hub">
        <header class="hub-header">
            <h1>Configurações</h1>
            <p>Gerenciamento de núcleo e interface Focus.</p>
        </header>

        <div class="config-section">
            <div class="section-title">Aparência e Design</div>
            <div class="theme-grid">

    <!-- 🌊 OCEAN -->
    <div class="theme-card" id="t-default" onclick="setTheme('default')">
        <div class="color-preview">
            <div class="cp-orig"></div>
            <div class="cp-acc"></div>
        </div>
        <b>Ocean Sand</b>
    </div>

    <!-- 🌑 NIGHT GREY -->
    <div class="theme-card" id="t-dark" onclick="setTheme('dark')">
        <div class="color-preview">
            <div class="cp-dark"></div>
            <div class="cp-dark-light"></div>
        </div>
        <b>Night Grey</b>
    </div>

    <!-- 🟣 LAVANDA -->
    <div class="theme-card" id="t-purple" onclick="setTheme('purple')">
        <div class="color-preview">
            <div class="cp-purp"></div>
            <div class="cp-purp-light"></div>
        </div>
        <b>Lavanda</b>
    </div>

</div>
        </div>

       <div class="config-section censored">
    <div class="section-title">Acessibilidade</div>
            
            <div class="setting-item">
                <div class="item-info">
                    <b>Alto Contraste</b>
                    <span>Maximizar legibilidade para TEA/Baixa visão.</span>
                </div>
                <label class="switch"><input type="checkbox" id="c-contrast" onchange="toggleContrast()"><span class="slider"></span></label>
            </div>

            <div class="setting-item">
                <div class="item-info">
                    <b>Reduzir Movimento</b>
                    <span>Desativar animações (Ideal para TDAH/Ansiedade).</span>
                </div>
                <label class="switch"><input type="checkbox" id="c-motion" onchange="toggleMotion()"><span class="slider"></span></label>
            </div>

            <div class="setting-item">
                <div class="item-info">
                    <b>Fontes Amigáveis</b>
                    <span>Utilizar tipografia otimizada para Dislexia.</span>
                </div>
                <label class="switch"><input type="checkbox" id="c-font"><span class="slider"></span></label>
            </div>
        </div>

        <div class="config-section">
            <div class="section-title">Protocolos do HUD</div>
            <div class="setting-item">
                <div class="item-info">
                    <b>Alertas de Metas</b>
                    <span>Notificações de humor e prazos.</span>
                </div>
                <label class="switch"><input type="checkbox" id="c-notif" checked><span class="slider"></span></label>
            </div>
        </div>

        <div class="config-section">
            <div class="section-title">Manutenção</div>
            <div class="setting-item" onclick="clearCache()" style="cursor:pointer;">
                <div class="item-info">
                    <b style="color: #ef4444;">Resetar Protocolos</b>
                    <span>Limpar preferências e cache local.</span>
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

        function toggleContrast() {
            const isChecked = document.getElementById('c-contrast').checked;
            if(isChecked) document.body.classList.add('high-contrast');
            else document.body.classList.remove('high-contrast');
        }

        function toggleMotion() {
            const isChecked = document.getElementById('c-motion').checked;
            if(isChecked) document.body.classList.add('reduce-motion');
            else document.body.classList.remove('reduce-motion');
        }

        function clearCache() {
            if(confirm("Deseja resetar todos os protocolos visuais?")) {
                localStorage.clear();
                location.reload();
            }
        }

        function finalizeConfigs() {
            const settings = {
                notif: document.getElementById('c-notif').checked,
                motion: document.getElementById('c-motion').checked,
                contrast: document.getElementById('c-contrast').checked,
                dyslexia: document.getElementById('c-font').checked
            };

            localStorage.setItem('focus_global_settings', JSON.stringify(settings));
            document.getElementById('toast').classList.add('show');

            setTimeout(() => { window.location.href = "dashboard.php"; }, 2000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Carregar Tema
            setTheme(localStorage.getItem('focus_theme') || 'default');

            // Carregar Configurações
            const saved = JSON.parse(localStorage.getItem('focus_global_settings'));
            if(saved) {
                document.getElementById('c-notif').checked = saved.notif;
                document.getElementById('c-motion').checked = saved.motion || false;
                document.getElementById('c-contrast').checked = saved.contrast || false;
                document.getElementById('c-font').checked = saved.dyslexia || false;
                
                if(saved.motion) document.body.classList.add('reduce-motion');
                if(saved.contrast) document.body.classList.add('high-contrast');
            }
        });
        
    </script>
</body>
</html>