<?php
session_start();
require_once 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id_user = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT username, foto_perfil FROM usuarios WHERE id = ?");
$stmt->execute([$id_user]);
$user = $stmt->fetch();

$foto = !empty($user['foto_perfil']) ? $user['foto_perfil'] : 'img/padrao.png';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Visual - Focus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a2639;
            --accent: #facc15;
            --bg: #f1f5f9;
            --low: #10b981;
            --mid: #facc15;
            --high: #ef4444;
        }

        * { box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 0; }
        body { background-color: var(--bg); color: #1e293b; }

        .navbar-main {
            background-color: var(--primary);
            height: 70px; padding: 0 5%;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 1000;
        }
        .nav-brand { display: flex; align-items: center; color: white; text-decoration: none; gap: 10px; }
        .nav-brand i { color: var(--accent); font-size: 24px; }
        .nav-links { display: flex; list-style: none; gap: 30px; }
        .nav-links a { color: #cbd5e1; text-decoration: none; font-weight: 600; font-size: 14px; }
        .nav-links a.active { color: var(--accent); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--accent); object-fit: cover; }

        .container { max-width: 900px; margin: 40px auto; padding: 0 20px; }

        .header-agenda { margin-bottom: 30px; border-left: 8px solid var(--primary); padding-left: 15px; }
        .header-agenda h1 { font-size: 32px; font-weight: 800; color: var(--primary); }

        /* QUADRO SEMANAL */
        .week-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; margin-bottom: 40px; }
        .day-box {
            background: white; padding: 15px; border-radius: 20px; text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03); border-bottom: 5px solid #e2e8f0;
        }
        .day-box.active-day { border-bottom-color: var(--accent); transform: scale(1.05); }
        .day-name { font-size: 12px; font-weight: 800; text-transform: uppercase; color: #94a3b8; }
        .day-num { font-size: 20px; font-weight: 900; color: var(--primary); display: block; margin: 5px 0; }
        .heat-dot { width: 12px; height: 12px; border-radius: 50%; margin: 5px auto; background: #e2e8f0; }

        /* INPUT AREA */
        .planner-card { background: white; padding: 30px; border-radius: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .input-group { display: flex; gap: 10px; margin-bottom: 25px; }
        .input-group input { flex: 1; padding: 15px; border: 2px solid #f1f5f9; border-radius: 12px; font-weight: 600; outline: none; }
        .input-group select { padding: 10px; border: 2px solid #f1f5f9; border-radius: 12px; font-weight: 600; }

        .plan-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px; background: #f8fafc; border-radius: 15px; margin-bottom: 10px; border: 1px solid #e2e8f0;
        }

        .effort-tag { padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 800; color: white; margin-right: 10px; }
        
        .btn-del { background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px; transition: 0.2s; }
        .btn-del:hover { transform: scale(1.2); }
    </style>
</head>
<body>

    <nav class="navbar-main">
        <a href="dashboard.php" class="nav-brand"><i class="fa-solid fa-anchor"></i><h2>Focus</h2></a>
        <ul class="nav-links">
            <li><a href="dashboard.php">Início</a></li>
            <li><a href="meus_estudos.php">Meus Estudos</a></li>
            <li><a href="agenda.php" class="active">Agenda</a></li>
        </ul>
        <div style="display: flex; align-items: center; gap: 12px; color: white;">
            <span style="font-weight: 600;">@<?php echo htmlspecialchars($user['username']); ?></span>
            <img src="<?php echo $foto; ?>" class="profile-img">
        </div>
    </nav>

    <div class="container">
        <header class="header-agenda">
            <h1>Carga Semanal</h1>
            <p>Planeamento de esforço e metas.</p>
        </header>

        <div class="week-grid" id="weekGrid"></div>

        <div class="planner-card">
            <div class="input-group">
                <input type="text" id="planDesc" placeholder="O que tens para fazer?">
                <select id="planEffort">
                    <option value="1">Leve (☕)</option>
                    <option value="3">Médio (⚡)</option>
                    <option value="5">Intenso (🔥)</option>
                </select>
                <button onclick="addPlan()" style="background: var(--primary); color:white; border:none; padding:0 20px; border-radius:12px; cursor:pointer;">
                    <i class="fa-solid fa-plus"></i>
                </button>
            </div>

            <div id="planList"></div>
        </div>
    </div>

    <script>
        let plans = JSON.parse(localStorage.getItem('focus_plans')) || [];
        const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        function renderWeek() {
            const grid = document.getElementById('weekGrid');
            grid.innerHTML = '';
            const today = new Date().getDay();

            days.forEach((day, index) => {
                const dayPlans = plans.filter(p => p.dayIndex === index);
                const totalEffort = dayPlans.reduce((acc, curr) => acc + parseInt(curr.effort), 0);
                
                let heatColor = '#e2e8f0';
                if(totalEffort > 0 && totalEffort <= 2) heatColor = 'var(--low)';
                else if(totalEffort > 2 && totalEffort <= 5) heatColor = 'var(--mid)';
                else if(totalEffort > 5) heatColor = 'var(--high)';

                grid.innerHTML += `
                    <div class="day-box ${index === today ? 'active-day' : ''}">
                        <span class="day-name">${day}</span>
                        <span class="day-num">${new Date().getDate() - today + index}</span>
                        <div class="heat-dot" style="background: ${heatColor}"></div>
                    </div>
                `;
            });
        }

        function renderPlans() {
            const list = document.getElementById('planList');
            const today = new Date().getDay();
            const todayPlans = plans.filter(p => p.dayIndex === today);
            
            list.innerHTML = `<h3 style="margin-bottom:15px; color:var(--primary)">Metas de Hoje:</h3>`;
            
            if(todayPlans.length === 0) {
                list.innerHTML += `<p style="color:#94a3b8; font-size:14px;">Nada planeado.</p>`;
            }

            todayPlans.forEach((p, index) => {
                const color = p.effort == 1 ? 'var(--low)' : p.effort == 3 ? 'var(--mid)' : 'var(--high)';
                // Usamos o timestamp (id) para encontrar a meta certa ao apagar
                list.innerHTML += `
                    <div class="plan-item">
                        <div>
                            <span class="effort-tag" style="background:${color}">${p.effort == 1 ? 'LEVE' : p.effort == 3 ? 'MÉDIO' : 'INTENSO'}</span>
                            <span>${p.desc}</span>
                        </div>
                        <button class="btn-del" onclick="deletePlan(${p.id})">
                            <i class="fa-solid fa-circle-xmark"></i>
                        </button>
                    </div>
                `;
            });
            
            localStorage.setItem('focus_plans', JSON.stringify(plans));
            renderWeek();
        }

        function addPlan() {
            const desc = document.getElementById('planDesc').value;
            const effort = document.getElementById('planEffort').value;
            const today = new Date().getDay();

            if(!desc) return;

            plans.push({ 
                id: Date.now(), 
                desc: desc, 
                effort: effort, 
                dayIndex: today 
            });

            document.getElementById('planDesc').value = '';
            renderPlans();
        }

        function deletePlan(id) {
            plans = plans.filter(p => p.id !== id);
            renderPlans();
        }

        document.addEventListener('DOMContentLoaded', renderPlans);
    </script>
</body>
</html>