<?php
require_once 'src/assets/config.php';


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: dashboard");
    exit();
}

$logs_query = "
    SELECT a.*, u.username, u.email 
    FROM activity_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 200
";
$logs_stmt = $conn->query($logs_query);
$logs = $logs_stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="header">
    <div>
        <h1>Registro de Actividades</h1>
        <p>Historial detallado de las acciones realizadas en el panel de administración.</p>
    </div>
    <a href="?clear=old" style="background:rgba(239,68,68,0.1); color:var(--danger); border:1px solid rgba(239,68,68,0.2); text-decoration:none; display:inline-block; padding:0.8rem 1.5rem; border-radius:10px; font-weight:800; transition:0.3s;" onclick="alert('Esta función estará disponible próximamente para limpiar logs antiguos.'); return false;"><i class="fa-solid fa-broom"></i> Limpiar Antiguos</a>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Últimos 200 Registros</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Fecha y Hora</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Tipo de Acción</th>
                <th>Descripción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($logs as $l): ?>
            <tr>
                <td style="color:#94a3b8; font-size:0.9rem; font-weight:bold;"><?php echo date("d M Y, H:i", strtotime($l['created_at'])); ?></td>
                <td>
                    <div style="display:flex; align-items:center; gap:0.8rem;">
                        <div class="avatar" style="width:30px; height:30px; font-size:0.9rem;"><?php echo strtoupper(substr($l['username'] ?? '?', 0, 1)); ?></div>
                        <span style="font-weight:bold; color:#fff;"><?php echo htmlspecialchars($l['username'] ?? 'Usuario Eliminado'); ?></span>
                    </div>
                </td>
                <td>
                    <span class="role-badge" style="background:rgba(255,255,255,0.05); border:none;"><?php echo strtoupper(htmlspecialchars($l['role'])); ?></span>
                </td>
                <td>
                    <span style="color:var(--primary); font-weight:800; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px;"><?php echo htmlspecialchars($l['action_type']); ?></span>
                </td>
                <td style="color:#e2e8f0;">
                    <?php echo htmlspecialchars($l['description']); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(count($logs) == 0): ?>
            <tr>
                <td colspan="5">
                    <div class="empty-state">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <h3>No hay registros de actividad</h3>
                        <p>El historial de acciones se mostrará aquí cuando los usuarios realicen cambios.</p>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ?>
