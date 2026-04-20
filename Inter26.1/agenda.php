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
$username_exibir = !empty($user['username']) ? $user['username'] : 'usuário';
$foto = !empty($user['foto_perfil']) ? $user['foto_perfil'] : 'img/padrao.png';

?>

<!DOCTYPE html>
<html lang="pt-br">
    
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Premium | Focus OS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>

        :root {
            --primary: #1a2639;
            --accent: #facc15;
            --accent-glow: rgba(250, 204, 21, 0.4);
            --bg: #f1f5f9;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-soft: #64748b;
            --border: #e2e8f0;
            --nav-bg: #1a2639;
            --low: #10b981;
            --mid: #facc15;
            --high: #ef4444;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        body.dark-theme {
            --primary: #facc15;
            --accent: #facc15;
            --bg: #020617;
            --card-bg: #0f172a;
            --text-main: #f1f5f9;
            --text-soft: #94a3b8;
            --border: #1e293b;
            --nav-bg: #020617;
        }

        body.purple-theme {
            --primary: #a855f7;
            --accent: #a855f7;
            --accent-glow: rgba(168, 85, 247, 0.6);
            --bg: #0f0720;
            --card-bg: #120626;
            --text-main: #f5f3ff;
            --text-soft: #c084fc;
            --border: #4c1d95;
            --nav-bg: #120626;
        }

        * { box-sizing: border-box; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 0; transition: background 0.3s, color 0.3s, border-color 0.3s;
     }

        body {
            background-color: var(--bg);
            min-height: 100vh;
            color: var(--text-main);
            background-image: radial-gradient(var(--border) 0.8px, transparent 0.8px);
            background-size: 30px 30px;
        }

        .navbar-main {
            background-color: var(--nav-bg);
            height: 85px;
            display: flex; justify-content: center; align-items: center;
            position: sticky; top: 0; z-index: 9000;
            box-shadow: 0 4px 30px rgba(0,0,0,0.3);
            border-bottom: 2px solid var(--accent);
        }

        .nav-content {
            width: 100%; max-width: 1300px; padding: 0 40px;
            display: flex; justify-content: space-between; align-items: center;
        }

        .nav-brand {
             display: flex; align-items: center; color: white; text-decoration: none; gap: 15px;
     }

/* CONTAINER DA LOGO */

.logo-wrap {
    position: relative;
    display: flex;
    align-items: center;
}

/* GATO */

.nav-logo {
    width: 80px;
    height: auto;
    display: block;
    transition: transform 0.3s ease;
    filter: drop-shadow(0 6px 12px rgba(0,0,0,0.28));
}

.nav-logo:hover {
    transform: scale(1.08);
}

.logo-light {
    position: absolute;

    top: 8px;
    left: 50px;

    width: 16px;
    height: 16px;

    background: rgba(250, 204, 21, 1);

    border-radius: 50%;

    filter: blur(10px);

    box-shadow:

        0 0 18px rgba(250, 204, 21, 1),
        0 0 38px rgba(250, 204, 21, 0.95),
        0 0 65px rgba(250, 204, 21, 0.9);

    animation: lampGlow 2.5s infinite ease-in-out;
}

@keyframes lampGlow {

    0% {
        opacity: 0.6;
        transform: scale(1);
    }

    50% {
        opacity: 1;
        transform: scale(1.3);
    }

    100% {
        opacity: 0.6;
        transform: scale(1);
    }
}

.brand-text {
    font-size: 24px;
    font-weight: 800;
    color: white;
    transition: 0.3s;
}

.nav-brand:hover .brand-text {
    color: var(--accent);
}

        .nav-brand i {
             font-size: 32px; color: var(--accent);
             }

        .nav-brand h2 {
             font-size: 24px; font-weight: 800; color: white;
             }

        .nav-links {
             display: flex; list-style: none; gap: 10px;
             }

        .nav-links a {
            color: #cbd5e1; text-decoration: none; font-size: 14px; font-weight: 700;
            padding: 12px 18px; border-radius: 12px; transition: var(--transition);
            display: flex; align-items: center; gap: 8px;
        }

        .nav-links a:hover, .nav-links a.active {
             color: var(--accent); background: rgba(255,255,255,0.08); transform: translateY(-2px);
             }

        .profile-static {
             display: flex; align-items: center; gap: 15px;
             }

        .profile-static span {
             color: white; font-weight: 700; font-size: 15px;
             }

        .profile-img {
             width: 46px; height: 46px; border-radius: 50%; border: 3px solid var(--accent); object-fit: cover;
             }

        .container {
             max-width: 1250px; margin: 60px auto; padding: 0 40px; animation: slideUp 0.8s ease;
             }

        .header-agenda {
             margin-bottom: 45px; border-left: 15px solid var(--accent); padding-left: 25px;
             }

        .header-agenda h1 {
             font-size: 44px; font-weight: 900; letter-spacing: -2px;
             }

        .week-grid {
             display: grid; grid-template-columns: repeat(7, 1fr); gap: 15px; margin-bottom: 50px;
             }

        .day-box {
            background: var(--card-bg); padding: 35px 10px; border-radius: 35px;
            text-align: center; box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border: 2px solid var(--border); border-bottom: 8px solid var(--border);
            transition: var(--transition); cursor: pointer; position: relative; overflow: hidden;
        }

        .day-box:hover {
             transform: translateY(-12px); border-color: var(--accent);
             }

        .day-box.active-day {
             border-color: var(--accent); border-bottom-color: var(--accent) !important; box-shadow: 0 15px 40px var(--accent-glow);
             }

        .day-box.low-effort {
             border-bottom-color: var(--low);
             }

        .day-box.mid-effort {
             border-bottom-color: var(--mid);
             }

        .day-box.high-effort {
             border-bottom-color: var(--high);
             }

        .day-name {
             font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--text-soft); letter-spacing: 1.5px;
             }

        .day-num {
             font-size: 34px; font-weight: 900; color: var(--text-main); display: block; margin: 10px 0;
             }

        .planner-card {
            background: var(--card-bg); padding: 50px; border-radius: 50px;
            box-shadow: 0 25px 70px rgba(0,0,0,0.1); border: 2px solid var(--border);
            border-left: 18px solid var(--accent);
        }

        .input-group {
             display: flex; gap: 20px; margin-bottom: 45px; flex-wrap: wrap;
             }

        .input-group input {
            flex: 2; min-width: 250px; padding: 22px; border: 3px solid var(--border); border-radius: 20px;
            font-weight: 600; background: var(--bg); color: var(--text-main); outline: none; transition: 0.3s;
        }

        .input-group input:focus {
             border-color: var(--accent); box-shadow: 0 0 20px var(--accent-glow);
             }

        .input-group select {
            flex: 1; min-width: 150px; padding: 10px 20px; border: 3px solid var(--border); border-radius: 20px;
            background: var(--bg); color: var(--text-main); font-weight: 700; cursor: pointer;
        }

        .btn-add-plan {
            flex: 1; min-width: 200px;
            background: var(--nav-bg); color: var(--accent);
            border: 2px solid var(--accent); padding: 15px 30px;
            border-radius: 20px; font-size: 15px; font-weight: 900;
            cursor: pointer; transition: var(--transition);
            display: flex; align-items: center; justify-content: center; gap: 12px;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .btn-add-plan:hover {
            transform: scale(1.05);
            background: var(--accent);
            color: var(--nav-bg);
            box-shadow: 0 10px 30px var(--accent-glow);
        }

        .plan-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 28px 35px; background: var(--bg); border-radius: 30px;
            margin-bottom: 20px; border: 1px solid var(--border); transition: var(--transition);
            animation: taskIn 0.5s ease backwards;
        }

        @keyframes taskIn {
             from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); }
             }

        .plan-item:hover {
             transform: translateX(15px); border-color: var(--accent); background: var(--card-bg);
             }

        .tag-status {
            padding: 12px 22px; border-radius: 15px; font-size: 11px; font-weight: 950;
            color: white; text-transform: uppercase; display: flex; align-items: center; gap: 10px;
        }

        .plan-item span.title {
             font-weight: 800; color: var(--text-main); font-size: 19px; margin-left: 20px; flex: 1;
             }

        /* ESTILO META */

        .plan-item.done {
             opacity: 0.5; border-left: 8px solid var(--low);
             }

        .plan-item.done span.title {
             text-decoration: line-through;
             }

        .actions-btn {
             display: flex; gap: 10px;
             }

        .btn-check {
            background: rgba(16, 185, 129, 0.1); border: none; color: var(--low);
            width: 50px; height: 50px; border-radius: 15px; cursor: pointer;
            font-size: 20px; transition: 0.3s; display: flex; align-items: center; justify-content: center;
        }

        .btn-check:hover {
             background: var(--low); color: white; transform: scale(1.1);
             }

        .btn-del {
            background: rgba(239, 68, 68, 0.1); border: none; color: var(--high);
            width: 50px; height: 50px; border-radius: 15px; cursor: pointer;
            font-size: 20px; transition: 0.3s; display: flex; align-items: center; justify-content: center;
        }

        .btn-del:hover {
             background: var(--high); color: white; transform: rotate(90deg);
             }

        @keyframes slideUp {
             from { opacity: 0; transform: translateY(40px); } to { opacity: 1; transform: translateY(0); } 
            }

        @media (max-width: 1000px) 
        {

            .week-grid { grid-template-columns: repeat(4, 1fr); }
            .nav-links, .profile-static span { display: none; }
            .input-group { flex-direction: column; }
            .btn-add-plan, .input-group input, .input-group select { width: 100%; }

        }

    </style>

</head>

<body>

    <nav class="navbar-main">

        <div class="nav-content">

           <a href="dashboard.php" class="nav-brand">

<div class="logo-wrap">

    <img src="img/cat.png" class="nav-logo" alt="Logo Focus">

    <span class="logo-light"></span>

</div>

<h2 class="brand-text">Focus</h2>

</a>

            <ul class="nav-links">

                <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> Início</a></li>
                <li><a href="meus_estudos.php"><i class="fa-solid fa-book-bookmark"></i> Estudos</a></li>
                <li><a href="meu_desempenho.php"><i class="fa-solid fa-chart-line"></i> Desempenho</a></li>
                <li><a href="agenda.php" class="active"><i class="fa-solid fa-calendar-days"></i> Agenda</a></li>

            </ul>

            <div class="profile-static">

                <span>@<?php echo htmlspecialchars($username_exibir); ?></span>
                <img src="<?php echo $foto; ?>" class="profile-img">

            </div>

        </div>

    </nav>

    <div class="container">

        <header class="header-agenda">

            <h1>Carga Semanal</h1>
            <p>Sincronização neural de metas prioritárias.</p>

        </header>

        <div class="week-grid" id="weekGrid"></div>

        <div class="planner-card">

            <div class="input-group">

                <input type="text" id="planDesc" placeholder="Defina sua próxima meta operacional...">

                <select id="planEffort">

                    <option value="1">Meta Leve (☕)</option>
                    <option value="3">Meta Média (⚡)</option>
                    <option value="5">Meta Crítica (🔥)</option>

                </select>

                <button class="btn-add-plan" onclick="addPlan()">
                    <i class="fa-solid fa-circle-check"></i> Agendar Meta

                </button>

            </div>

            <div id="planList"></div>

        </div>

    </div>

    <script>

        let plans = JSON.parse(localStorage.getItem('focus_plans')) || [];
        const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        document.addEventListener('DOMContentLoaded', () => {

            const theme = localStorage.getItem('focus_theme') || 'default';
            if(theme !== 'default') document.body.classList.add(theme + '-theme');
            renderPlans();

        });

        function renderWeek() {

            const grid = document.getElementById('weekGrid');
            grid.innerHTML = '';
            const now = new Date();
            const todayIdx = now.getDay();
            const startOfWeek = new Date(now);

            startOfWeek.setDate(now.getDate() - todayIdx);

            days.forEach((day, i) => {

                const dayPlans = plans.filter(p => p.dayIndex === i);
                const total = dayPlans.reduce((acc, c) => acc + parseInt(c.effort), 0);
                const d = new Date(startOfWeek);
                d.setDate(startOfWeek.getDate() + i);
                const dateNum = d.getDate();
                let effortClass = "";

                if(total > 0 && total <= 2) effortClass = "low-effort";
                else if(total > 2 && total <= 5) effortClass = "mid-effort";
                else if(total > 5) effortClass = "high-effort";

                const isActive = (dateNum === now.getDate() && now.getMonth() === d.getMonth());
                grid.innerHTML += `

                    <div class="day-box ${isActive ? 'active-day' : ''} ${effortClass}">

                        <span class="day-name">${day}</span>

                        <span class="day-num">${dateNum}</span>

                    </div>

                `;

            });

        }

        function renderPlans() {

            const list = document.getElementById('planList');
            const todayIdx = new Date().getDay();
            const todayPlans = plans.filter(p => p.dayIndex === todayIdx);

            list.innerHTML = `<h3 style="margin-bottom:30px; font-weight: 900; color:var(--text-main);">Metas de hoje:</h3>`;

            todayPlans.forEach(p => {

                const config = {

                    1: { c: 'var(--low)', l: 'Leve', i: 'fa-mug-hot' },
                    3: { c: 'var(--mid)', l: 'Médio', i: 'fa-bolt' },
                    5: { c: 'var(--high)', l: 'Crítica', i: 'fa-fire' }

                }[p.effort];

                list.innerHTML += `

                    <div class="plan-item ${p.done ? 'done' : ''}">

                        <div style="display: flex; align-items: center; flex: 1;">

                            <div class="tag-status" style="background: ${config.c}">

                                <i class="fa-solid ${config.i}"></i> ${config.l}

                            </div>

                            <span class="title">${p.desc}</span>

                        </div>

                        <div class="actions-btn">

                            <button class="btn-check" onclick="togglePlan(${p.id})"><i class="fa-solid fa-check"></i></button>

                            <button class="btn-del" onclick="deletePlan(${p.id})"><i class="fa-solid fa-trash-can"></i></button>

                        </div>

                    </div>

                `;

            });

            if(todayPlans.length === 0) list.innerHTML += `<p style="color:var(--text-soft); font-weight: 500; text-align:center; padding: 20px;">Nenhuma meta definida para hoje.</p>`;
            localStorage.setItem('focus_plans', JSON.stringify(plans));
            renderWeek();

        }

        function addPlan() {

            const desc = document.getElementById('planDesc').value.trim();
            const effort = document.getElementById('planEffort').value;

            if(!desc) return;
            plans.push({ id: Date.now(), desc, effort, dayIndex: new Date().getDay(), done: false });
            document.getElementById('planDesc').value = '';
            renderPlans();

        }

        function togglePlan(id) {

            const plan = plans.find(p => p.id === id);

            if(plan) {
                plan.done = !plan.done;
                renderPlans();

            }

        }

        function deletePlan(id) {

            if(confirm("Deseja remover esta meta?")) {
                plans = plans.filter(p => p.id !== id);
                renderPlans();

            }

        }

    </script>

</body>

</html>