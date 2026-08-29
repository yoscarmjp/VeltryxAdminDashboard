<?php
require 'src/assets/config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit();
}

$error = '';
$rate = check_login_rate_limit();

if ($rate['locked']) {
    $mins = ceil($rate['remaining'] / 60);
    $error = "Demasiados intentos fallidos. Intenta de nuevo en {$mins} minuto(s).";
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Por favor, completa todos los campos.";
        register_failed_login();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del correo electrónico es inválido.";
        register_failed_login();
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);

        $valid = false;
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password'])) {
            $valid = true;
            reset_login_attempts();
            session_regenerate_id(true);
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role']     = $row['role'];
            log_action("LOGIN", "El usuario inició sesión.");
            header("Location: dashboard");
            exit();
        }

        if (!$valid) {
            register_failed_login();
            $rate = check_login_rate_limit();
            $remaining_attempts = max(0, 5 - $rate['attempts']);
            if ($remaining_attempts > 0) {
                $error = "Credenciales incorrectas. Te quedan {$remaining_attempts} intento(s).";
            } else {
                $error = "Cuenta bloqueada temporalmente por múltiples intentos fallidos.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso Admin | Veltryx</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #02040a; height: 100vh; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        body::before { content: ''; position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(59,130,246,0.15), transparent 70%); top: -200px; left: -200px; border-radius: 50%; pointer-events: none; }
        body::after { content: ''; position: absolute; width: 800px; height: 800px; background: radial-gradient(circle, rgba(16,185,129,0.1), transparent 70%); bottom: -300px; right: -300px; border-radius: 50%; pointer-events: none; }
        .login-wrapper { position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center; width: 100%; max-width: 420px; padding: 0 1rem; }
        .logo { height: 70px; margin-bottom: 2.5rem; filter: drop-shadow(0 0 20px rgba(59,130,246,0.4)); }
        .login-card { background: rgba(13,20,33,0.6); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.05); padding: 3rem; border-radius: 24px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); width: 100%; position: relative; overflow: hidden; }
        .login-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #3b82f6, transparent); }
        .login-card h2 { color: #fff; font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem; text-align: center; }
        .login-card p.subtitle { color: #94a3b8; text-align: center; margin-bottom: 2.5rem; font-size: 0.95rem; }
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-group i { position: absolute; left: 1.2rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.1rem; }
        .form-group input { width: 100%; padding: 1.1rem 1.1rem 1.1rem 3rem; border-radius: 12px; background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); color: #fff; font-family: inherit; font-size: 1rem; transition: all 0.3s ease; }
        .form-group input:focus { outline: none; border-color: #3b82f6; background: rgba(0,0,0,0.6); box-shadow: 0 0 15px rgba(59,130,246,0.2); }
        .btn-submit { width: 100%; padding: 1.2rem; border-radius: 12px; background: #3b82f6; border: none; color: #fff; font-weight: 800; font-size: 1.1rem; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 1px; margin-top: 1rem; }
        .btn-submit:hover { background: #2563eb; box-shadow: 0 10px 25px rgba(59,130,246,0.4); transform: translateY(-2px); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .error { background: rgba(239,68,68,0.1); color: #ef4444; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; text-align: center; font-size: 0.9rem; border: 1px solid rgba(239,68,68,0.2); }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <img src="src/img/Veltryx.webp" alt="Veltryx Admin" class="logo">
        <div class="login-card">
            <h2>Acceso Restringido</h2>
            <p class="subtitle">Solo personal autorizado</p>

            <?php if($error): ?>
            <div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" placeholder="Correo electrónico" required autocomplete="email" <?php echo $rate['locked'] ? 'disabled' : ''; ?>>
                </div>
                <div class="form-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password" <?php echo $rate['locked'] ? 'disabled' : ''; ?>>
                </div>
                <button type="submit" class="btn-submit" <?php echo $rate['locked'] ? 'disabled' : ''; ?>>Entrar al Sistema</button>
            </form>
        </div>
    </div>
</body>
</html>
