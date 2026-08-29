<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'admin', 'manager', 'moderator'])) {
    header("Location: login");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
if ($current_page == 'index') $current_page = 'dashboard';

$page_titles = [
    'dashboard'     => 'Vista General',
    'news'          => 'Gestión de Noticias',
    'create_news'   => 'Crear Noticia',
    'edit_news'     => 'Editar Noticia',
    'staff'         => 'Postulaciones Staff',
    'media'         => 'Postulaciones Media',
    'form_settings' => 'Editor de Formularios',
    'reports'       => 'Sistema de Reportes',
    'users'         => 'Gestión de Usuarios',
    'activity_logs' => 'Logs de Actividad'
];
$title_suffix = $page_titles[$current_page] ?? ucfirst($current_page);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title_suffix); ?> | Veltryx Admin</title>
    <link rel="icon" type="image/png" href="src/img/Veltryx.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }
    </script>
    <style>
        :root {
            --bg-main: #02040a;
            --bg-card: rgba(13,20,33,0.7);
            --primary: #3b82f6;
            --accent: #10b981;
            --danger: #ef4444;
            --border: rgba(255,255,255,0.06);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg-main); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-main); }
        ::-webkit-scrollbar-thumb { background: rgba(59,130,246,0.3); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

        .sidebar { width: 280px; background: rgba(8,12,20,0.95); backdrop-filter: blur(10px); border-right: 1px solid var(--border); padding: 2rem 1.5rem; display: flex; flex-direction: column; z-index: 10; overflow-y: auto; height: 100%; }
        .logo-wrap { display: flex; flex-direction: column; align-items: center; margin-bottom: 3rem; gap: 0.5rem; text-align: center; }
        .logo-wrap img { height: 75px; filter: drop-shadow(0 0 10px rgba(59,130,246,0.3)); }
        .logo-title { color: #fff; font-weight: 900; letter-spacing: 1px; font-size: 1.1rem; text-transform: uppercase; }
        .logo-subtitle { color: var(--primary); font-size: 0.8rem; font-weight: 800; letter-spacing: 2px; }
        
        .nav-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px; color: #475569; font-weight: 800; margin-bottom: 1rem; padding-left: 1rem; }
        
        .nav-link { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.2rem; color: var(--text-muted); text-decoration: none; border-radius: 12px; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease; }
        .nav-link i { font-size: 1.2rem; width: 20px; text-align: center; }
        .nav-link:hover { background: rgba(255,255,255,0.03); color: #fff; }
        .nav-link.active { background: linear-gradient(90deg, rgba(59,130,246,0.15), transparent); color: var(--primary); border-left: 3px solid var(--primary); }
        
        .user-panel { margin-top: auto; background: var(--bg-card); padding: 1rem; border-radius: 14px; border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .user-info { display: flex; flex-direction: column; }
        .user-info span { font-weight: 800; font-size: 0.95rem; }
        .user-info small { color: var(--text-muted); font-size: 0.8rem; }
        .logout-btn { color: var(--danger); background: rgba(239,68,68,0.1); width: 35px; height: 35px; display: flex; justify-content: center; align-items: center; border-radius: 8px; text-decoration: none; transition: 0.3s; }
        .logout-btn:hover { background: var(--danger); color: #fff; }

        .main-content { flex: 1; position: relative; overflow-y: auto; padding: 3rem; background: radial-gradient(circle at top right, rgba(59,130,246,0.05), transparent 40%); }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem; }
        .header h1 { font-size: 2.2rem; font-weight: 800; color: #fff; }
        .header p { color: var(--text-muted); font-size: 1.1rem; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
        .stat-card { background: var(--bg-card); backdrop-filter: blur(10px); border: 1px solid var(--border); padding: 2rem; border-radius: 20px; position: relative; overflow: hidden; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.1); }
        .stat-icon { position: absolute; top: 1.5rem; right: 1.5rem; font-size: 2rem; opacity: 0.2; }
        .stat-card h3 { color: var(--text-muted); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 1rem; }
        .stat-card .number { font-size: 3rem; font-weight: 900; line-height: 1; }
        .c-primary { color: var(--primary); } .c-warning { color: #f59e0b; } .c-success { color: var(--accent); }

        .table-container { background: var(--bg-card); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: 20px; overflow: hidden; }
        .table-header { padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .table-header h2 { font-size: 1.2rem; font-weight: 800; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 1.2rem 2rem; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; background: rgba(0,0,0,0.2); }
        td { padding: 1.2rem 2rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.01); }
        
        .user-cell { display: flex; align-items: center; gap: 1rem; }
        .avatar { width: 40px; height: 40px; border-radius: 10px; background: rgba(59,130,246,0.1); display: flex; justify-content: center; align-items: center; color: var(--primary); font-weight: bold; font-size: 1.2rem; }
        .nick { font-weight: 800; font-size: 1.05rem; }
        
        .role-badge { background: rgba(255,255,255,0.05); padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 0.8rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.1); color: #cbd5e1; }
        
        .status { padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; display: inline-flex; align-items: center; gap: 0.5rem; }
        .status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .status.pending { background: rgba(245,158,11,0.1); color: #f59e0b; }
        .status.pending::before { background: #f59e0b; }
        .status.approved { background: rgba(16,185,129,0.1); color: var(--accent); }
        .status.approved::before { background: var(--accent); }
        .status.rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        .status.rejected::before { background: var(--danger); }
        
        .actions { display: flex; gap: 0.5rem; }
        .btn-action { width: 35px; height: 35px; border-radius: 8px; display: flex; justify-content: center; align-items: center; text-decoration: none; color: #fff; transition: all 0.2s ease; border: none; cursor: pointer; }
        .btn-action.approve { background: rgba(16,185,129,0.2); color: var(--accent); }
        .btn-action.approve:hover { background: var(--accent); color: #fff; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16,185,129,0.3); }
        .btn-action.reject { background: rgba(239,68,68,0.2); color: var(--danger); }
        .btn-action.reject:hover { background: var(--danger); color: #fff; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(239,68,68,0.3); }
        .btn-action.view:hover { background: var(--primary) !important; color: #fff !important; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59,130,246,0.3); }

        .empty-state { padding: 4rem; text-align: center; color: var(--text-muted); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(13,20,33,0.9);
            border: 1px solid var(--border);
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            z-index: 99;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            z-index: 98;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            body {
                position: relative;
            }
            .mobile-toggle {
                display: flex;
            }
            .sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: -280px;
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                z-index: 100;
                box-shadow: 10px 0 30px rgba(0,0,0,0.5);
            }
            .sidebar.active {
                left: 0;
            }
            .sidebar-overlay.active {
                display: block;
                opacity: 1;
                pointer-events: auto;
            }
            .main-content {
                padding: 5rem 1.5rem 2rem;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar">
        <div class="logo-wrap">
            <img src="src/img/Veltryx.webp" alt="Veltryx Admin">
            <div class="logo-title">Veltryx Network</div>
            <div class="logo-subtitle">ADMIN PANEL</div>
        </div>
        
        <div class="nav-label">General</div>
        <a href="dashboard" class="nav-link <?php echo $current_page=='dashboard'?'active':''; ?>"><i class="fa-solid fa-chart-pie"></i> Vista General</a>
        
        <?php if (in_array($_SESSION['role'], ['owner', 'admin'])): ?>
        <div class="nav-label" style="margin-top: 1.5rem;">Contenido</div>
        <a href="news" class="nav-link <?php echo $current_page=='news'?'active':''; ?>"><i class="fa-solid fa-newspaper"></i> Noticias</a>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['owner', 'manager'])): ?>
        <div class="nav-label" style="margin-top: 1.5rem;">Postulaciones</div>
        <a href="staff" class="nav-link <?php echo $current_page=='staff'?'active':''; ?>"><i class="fa-solid fa-shield-halved"></i> Postulaciones Staff</a>
        <a href="media" class="nav-link <?php echo $current_page=='media'?'active':''; ?>"><i class="fa-solid fa-video"></i> Postulaciones Media</a>
        <a href="form_settings" class="nav-link <?php echo $current_page=='form_settings'?'active':''; ?>"><i class="fa-solid fa-list-check"></i> Editor de Formularios</a>
        <?php endif; ?>

        <?php if (in_array($_SESSION['role'], ['owner', 'admin', 'moderator'])): ?>
        <div class="nav-label" style="margin-top: 1.5rem;">Moderación</div>
        <a href="reports" class="nav-link <?php echo $current_page=='reports'?'active':''; ?>"><i class="fa-solid fa-flag"></i> Reportes</a>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'owner'): ?>
        <div class="nav-label" style="margin-top: 1.5rem;">Administración</div>
        <a href="users" class="nav-link <?php echo $current_page=='users'?'active':''; ?>"><i class="fa-solid fa-users"></i> Usuarios</a>
        <a href="activity_logs" class="nav-link <?php echo $current_page=='activity_logs'?'active':''; ?>"><i class="fa-solid fa-clipboard-list"></i> Logs de Actividad</a>
        <?php endif; ?>
        
        <div class="user-panel" style="margin-top: 2rem;">
            <div class="user-info">
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <small><?php echo ucfirst(htmlspecialchars($_SESSION['role'])); ?></small>
            </div>
            <a href="logout" class="logout-btn" title="Cerrar Sesión"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </aside>

    <main class="main-content">
