<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'admin', 'manager', 'moderator'])) {
    header("Location: login");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id']) && in_array($_SESSION['role'], ['owner', 'manager'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    $app_name = "Desconocido";
    $stmt = $conn->prepare("SELECT minecraft_name FROM applications WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $app_name = $row['minecraft_name'];
    }

    if ($action == 'approve') {
        $upd = $conn->prepare("UPDATE applications SET status='approved' WHERE id=?");
        $upd->execute([$id]);
        log_action("APPROVE_APP", "Aprobó la postulación de $app_name desde Dashboard");
    } elseif ($action == 'reject') {
        $upd = $conn->prepare("UPDATE applications SET status='rejected' WHERE id=?");
        $upd->execute([$id]);
        log_action("REJECT_APP", "Rechazó la postulación de $app_name desde Dashboard");
    }
    header("Location: dashboard");
    exit();
}

$total_apps   = $conn->query("SELECT COUNT(*) FROM applications")->fetchColumn();
$pending_apps  = $conn->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetchColumn();
$approved_apps = $conn->query("SELECT COUNT(*) FROM applications WHERE status='approved'")->fetchColumn();
$apps_stmt     = $conn->query("SELECT * FROM applications ORDER BY created_at DESC LIMIT 6");
$apps          = $apps_stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="header">
    <div>
        <h1>Vista General</h1>
        <p>Resumen y métricas de todas las postulaciones recibidas.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <i class="fa-solid fa-users stat-icon c-primary"></i>
        <h3>Total Recibidas</h3>
        <div class="number c-primary"><?php echo $total_apps; ?></div>
    </div>
    <div class="stat-card">
        <i class="fa-solid fa-clock stat-icon c-warning"></i>
        <h3>En Revisión</h3>
        <div class="number c-warning"><?php echo $pending_apps; ?></div>
    </div>
    <div class="stat-card">
        <i class="fa-solid fa-check-circle stat-icon c-success"></i>
        <h3>Aprobadas</h3>
        <div class="number c-success"><?php echo $approved_apps; ?></div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Últimas Solicitudes</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Candidato</th>
                <th>Rol Solicitado</th>
                <th>Discord</th>
                <th>Edad</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($apps as $app): ?>
            <tr>
                <td>
                    <div class="user-cell">
                        <div class="avatar"><?php echo strtoupper(substr($app['minecraft_name'], 0, 1)); ?></div>
                        <span class="nick"><?php echo htmlspecialchars($app['minecraft_name']); ?></span>
                    </div>
                </td>
                <td><span class="role-badge"><?php echo strtoupper($app['job_type']); ?></span></td>
                <td style="color: #94a3b8;"><i class="fa-brands fa-discord"></i> <?php echo htmlspecialchars($app['discord_tag']); ?></td>
                <td><?php echo $app['age']; ?> años</td>
                <td><span class="status <?php echo $app['status']; ?>"><?php 
                    if($app['status'] == 'pending') echo 'Pendiente';
                    elseif($app['status'] == 'approved') echo 'Aprobado';
                    else echo 'Rechazado';
                ?></span></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(count($apps) == 0): ?>
            <tr>
                <td colspan="5">
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <h3>No hay postulaciones</h3>
                        <p>No se encontraron registros recientes.</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
