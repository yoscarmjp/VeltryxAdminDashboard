<?php
require_once 'src/assets/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'owner') {
    header("Location: dashboard");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_user'])) {
        csrf_verify();
        $username = clean_input($_POST['username']);
        $email = clean_input($_POST['email']);
        $password = $_POST['password'];
        $role = clean_input($_POST['role']);
        
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = "Faltan caracteres. Todos los campos son obligatorios.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato del correo es inválido.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $email, $hashed, $role]);
                log_action("CREATE_USER", "Creó el usuario $username con rol $role");
                $success = "Usuario creado exitosamente.";
            } catch (PDOException $e) {
                $error = "Error al crear el usuario. El correo o usuario ya existe.";
            }
        }
    }
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    if (empty($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'])) {
        http_response_code(403);
        die('Solicitud inválida. Token CSRF incorrecto.');
    }
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($id === (int)$_SESSION['user_id']) {
        $error = "No puedes realizar esta acción sobre tu propio usuario.";
    } else {
        if ($action == 'delete') {
            $stmt_del = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt_del->execute([$id]);
            log_action("DELETE_USER", "Eliminó el usuario con ID $id");
            $success = "Usuario eliminado correctamente.";
        }
    }
    header("Location: users?msg=" . urlencode($success ?: $error) . "&type=" . ($success ? 'success' : 'error'));
    exit();
}

if (isset($_GET['msg'])) {
    if (isset($_GET['type']) && $_GET['type'] == 'error') {
        $error = htmlspecialchars(clean_input($_GET['msg']));
    } else {
        $success = htmlspecialchars(clean_input($_GET['msg']));
    }
}

$users_stmt = $conn->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$users_list = $users_stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="header">
    <div>
        <h1>Gestión de Usuarios</h1>
        <p>Administra los accesos y roles del panel.</p>
    </div>
    <button onclick="document.getElementById('createUserModal').style.display='flex'" style="background:var(--primary); color:#fff; border:none; display:inline-block; padding:0.8rem 1.5rem; border-radius:10px; font-weight:800; cursor:pointer; transition:0.3s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'"><i class="fa-solid fa-plus"></i> Nuevo Usuario</button>
</div>

<?php if($error): ?>
<div style="background: rgba(239,68,68,0.1); color: #ef4444; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid rgba(239,68,68,0.2);">
    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo $error; ?>
</div>
<?php endif; ?>

<?php if($success): ?>
<div style="background: rgba(16,185,129,0.1); color: var(--accent); padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border: 1px solid rgba(16,185,129,0.2);">
    <i class="fa-solid fa-check-circle"></i> <?php echo $success; ?>
</div>
<?php endif; ?>

<div class="table-container">
    <div class="table-header">
        <h2>Usuarios Registrados</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Fecha de Registro</th>
                <th style="text-align: right;">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users_list as $u): ?>
            <tr>
                <td style="font-weight:bold;">
                    <div class="user-cell">
                        <div class="avatar" style="width:35px; height:35px; font-size:1rem;"><?php echo strtoupper(substr($u['username'], 0, 1)); ?></div>
                        <?php echo htmlspecialchars($u['username']); ?>
                    </div>
                </td>
                <td style="color:#94a3b8;"><?php echo htmlspecialchars($u['email']); ?></td>
                <td>
                    <span class="role-badge" style="<?php 
                        if($u['role'] == 'owner') echo 'color: #f59e0b; border-color: rgba(245,158,11,0.3); background: rgba(245,158,11,0.1);';
                        elseif($u['role'] == 'admin') echo 'color: #3b82f6; border-color: rgba(59,130,246,0.3); background: rgba(59,130,246,0.1);';
                    ?>"><?php echo strtoupper($u['role']); ?></span>
                </td>
                <td style="color:#94a3b8;"><?php echo date("d M, Y", strtotime($u['created_at'])); ?></td>
                <td style="text-align: right;">
                    <?php if($u['id'] != $_SESSION['user_id']): ?>
                    <a href="?action=delete&id=<?php echo $u['id']; ?>&csrf_token=<?php echo csrf_token(); ?>" class="btn-action reject" style="margin-left:auto;" title="Eliminar Usuario" onclick="return confirm('¿Seguro que deseas eliminar a este usuario?');"><i class="fa-solid fa-trash"></i></a>
                    <?php else: ?>
                    <span style="color:#64748b; font-size:0.8rem; text-transform:uppercase; font-weight:bold;">Tú</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
 
<div id="createUserModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(5px);">
    <div style="background:var(--bg-card); border:1px solid var(--border); padding:2.5rem; border-radius:20px; width:100%; max-width:450px; position:relative; box-shadow: 0 25px 50px rgba(0,0,0,0.5);">
        <button onclick="document.getElementById('createUserModal').style.display='none'" style="position:absolute; top:1.5rem; right:1.5rem; background:transparent; border:none; color:var(--text-muted); cursor:pointer; font-size:1.2rem;"><i class="fa-solid fa-xmark"></i></button>
        <h2 style="margin-bottom: 1.5rem;">Nuevo Usuario</h2>
        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="create_user" value="1">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem; font-weight:bold;">Nombre de Usuario</label>
                <input type="text" name="username" required style="width:100%; padding:0.8rem; border-radius:10px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem; font-weight:bold;">Correo Electrónico</label>
                <input type="email" name="email" required style="width:100%; padding:0.8rem; border-radius:10px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem; font-weight:bold;">Contraseña</label>
                <input type="password" name="password" required style="width:100%; padding:0.8rem; border-radius:10px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; color:var(--text-muted); font-size:0.9rem; font-weight:bold;">Rol del Sistema</label>
                <select name="role" required style="width:100%; padding:0.8rem; border-radius:10px; background:rgba(0,0,0,0.3); border:1px solid var(--border); color:#fff;">
                    <option value="moderator">Moderator (Solo Ver Reportes)</option>
                    <option value="admin">Admin (Noticias y Reportes)</option>
                    <option value="manager">Manager (Postulaciones)</option>
                    <option value="owner">Owner (Acceso Total)</option>
                </select>
            </div>
            <button type="submit" style="width:100%; background:var(--primary); color:#fff; border:none; padding:1rem; border-radius:10px; font-weight:800; cursor:pointer;">Crear Usuario</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
