<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['owner', 'manager'])) {
    header("Location: dashboard");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
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
        log_action("APPROVE_APP", "Aprobó la postulación de $app_name (Media)");
    } elseif ($action == 'reject') {
        $upd = $conn->prepare("UPDATE applications SET status='rejected' WHERE id=?");
        $upd->execute([$id]);
        log_action("REJECT_APP", "Rechazó la postulación de $app_name (Media)");
    } elseif ($action == 'delete' && $_SESSION['role'] === 'owner') {
        $del = $conn->prepare("DELETE FROM applications WHERE id=?");
        $del->execute([$id]);
        log_action("DELETE_APP", "Eliminó la postulación de $app_name (Media)");
    }
    header("Location: media");
    exit();
}

$apps_stmt = $conn->query("SELECT * FROM applications WHERE job_type IN ('youtuber', 'tiktoker', 'streamer') ORDER BY created_at DESC");
$apps = $apps_stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="header">
    <div>
        <h1>Postulaciones Media</h1>
        <p>Gestiona las solicitudes de creadores de contenido para la network.</p>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Listado Completo</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Candidato</th>
                <th>Rol Solicitado</th>
                <th>Discord</th>
                <th>Edad</th>
                <th>Estado</th>
                <th style="text-align: right;">Acción</th>
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
                <td style="text-align: right;">
                    <div class="actions" style="justify-content: flex-end; align-items: center; gap: 1rem;">
                        <?php if($app['status'] == 'pending'): ?>
                        <a href="?action=approve&id=<?php echo $app['id']; ?>" class="btn-action approve" title="Aprobar"><i class="fa-solid fa-check"></i></a>
                        <a href="?action=reject&id=<?php echo $app['id']; ?>" class="btn-action reject" title="Rechazar"><i class="fa-solid fa-xmark"></i></a>
                        <?php else: ?>
                        <span style="color:#475569; font-size: 0.8rem; font-weight: 800; text-transform: uppercase;">Procesada</span>
                        <?php endif; ?>
                        <a href="app_details?id=<?php echo $app['id']; ?>&back=media" class="btn-action view" style="background: rgba(59,130,246,0.15); color: var(--primary);" title="Ver Detalles"><i class="fa-solid fa-eye"></i></a>
                        <?php if($_SESSION['role'] === 'owner'): ?>
                        <a href="?action=delete&id=<?php echo $app['id']; ?>" class="btn-action reject" style="margin-left: 0.5rem;" title="Eliminar" onclick="return confirm('¿Seguro que deseas eliminar esta postulación?');"><i class="fa-solid fa-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(count($apps) == 0): ?>
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <h3>No hay postulaciones</h3>
                        <p>No se encontraron registros en esta categoría.</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
