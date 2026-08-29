<?php
define('REPORT_API_KEY', 'KEY');
define('UPLOAD_API_KEY', 'KEY');
define('WEB_PUBLIC_URL', 'https://web.veltryx.net'); 
session_start();

$host = '';
$user = '';
$pass = '';
$db   = '';

$auto_init_db = false;

if ($auto_init_db) {
    try {
        $tempPdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tempPdo = null;
    } catch (PDOException $e) {
        die("Error de conexión al crear base de datos: " . $e->getMessage());
    }
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage() . ". Si la base de datos aún no existe, activa \$auto_init_db = true; en config.php para crearla.");
}

$fq_check = $conn->query("SELECT COUNT(*) FROM form_questions")->fetchColumn();
if ($fq_check == 0) {
    $json_seed_path = dirname(__DIR__, 2) . '/VeltryxWeb/src/assets/form_config.json';
    if (file_exists($json_seed_path)) {
        $seed_data = json_decode(file_get_contents($json_seed_path), true);
        if (!empty($seed_data['staff_questions'])) {
            $ins = $conn->prepare(
                "INSERT IGNORE INTO form_questions (id, label, type, required, sort_order, conditional_on, conditional_value)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($seed_data['staff_questions'] as $i => $q) {
                $ins->execute([
                    $q['id'],
                    $q['label'],
                    $q['type'],
                    isset($q['required']) && $q['required'] ? 1 : 0,
                    $i,
                    $q['conditional_on'] ?? null,
                    $q['conditional_value'] ?? null,
                ]);
            }
        }
    }
}
}


function clean_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function log_action($action_type, $description) {
    global $conn;
    if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
        $user_id     = (int)$_SESSION['user_id'];
        $role        = $_SESSION['role'];
        $action      = $action_type;
        $desc        = $description;

        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, role, action_type, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $role, $action, $desc]);
    }
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify() {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Solicitud inválida. Token CSRF incorrecto.');
    }
}

function check_login_rate_limit() {
    $max_attempts = 5;
    $lockout_seconds = 900;
    $now = time();

    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = $now;
        $_SESSION['login_locked_until'] = 0;
    }

    if ($now < $_SESSION['login_locked_until']) {
        $remaining = $_SESSION['login_locked_until'] - $now;
        return ['locked' => true, 'remaining' => $remaining];
    }

    if (($now - $_SESSION['login_first_attempt']) > $lockout_seconds) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = $now;
    }

    return ['locked' => false, 'attempts' => $_SESSION['login_attempts'], 'max' => $max_attempts];
}

function register_failed_login() {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_locked_until'] = time() + 900;
    }
}

function reset_login_attempts() {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_locked_until'] = 0;
    $_SESSION['login_first_attempt'] = 0;
}

function sanitize_html($html) {
    if (empty($html)) return '';
    $allowed_tags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
        'h1', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'blockquote', 'pre', 'code',
        'a', 'img',
        'span', 'div', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'sub', 'sup',
    ];

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//*');

    foreach ($nodes as $node) {
        if (strtolower($node->nodeName) === 'body') continue;
        if (!in_array(strtolower($node->nodeName), $allowed_tags)) {
            $parent = $node->parentNode;
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            continue;
        }

        $attrs_to_remove = [];
        foreach ($node->attributes as $attr) {
            $name = strtolower($attr->name);
            $val  = $attr->value;

            $allowed_attrs = ['class', 'style', 'href', 'src', 'alt', 'title', 'target', 'rel', 'width', 'height', 'colspan', 'rowspan'];
            if (!in_array($name, $allowed_attrs)) {
                $attrs_to_remove[] = $attr->name;
                continue;
            }

            if ($name === 'href' || $name === 'src') {
                if (preg_match('/^\s*(javascript|data:text\/html|vbscript)/i', $val)) {
                    $attrs_to_remove[] = $attr->name;
                }
            }

            if ($name === 'style') {
                $clean_style = preg_replace('/(expression|javascript|behavior|vbscript)/i', '', $val);
                $node->setAttribute('style', $clean_style);
            }

            if ($name === 'target') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        foreach ($attrs_to_remove as $a) {
            $node->removeAttribute($a);
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        $result = '';
        foreach ($body->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        return $result;
    }

    return '';
}

function upload_to_web_api($temp_file_path, $filename) {
    if (!defined('UPLOAD_API_KEY') || !defined('WEB_PUBLIC_URL')) {
        return ['success' => false, 'error' => 'API de subida no configurada en config.php.'];
    }

    $url = rtrim(WEB_PUBLIC_URL, '/') . '/upload_api.php';
    
    if (!file_exists($temp_file_path)) {
        return ['success' => false, 'error' => 'El archivo temporal local no existe.'];
    }

    $mime = mime_content_type($temp_file_path);
    $cfile = new CURLFile($temp_file_path, $mime, $filename);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'api_key' => UPLOAD_API_KEY,
        'image' => $cfile,
        'filename' => $filename
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'error' => 'Error de conexión (cURL): ' . $curl_error];
    }

    $result = json_decode($response, true);
    if ($http_code === 200 && isset($result['success']) && $result['success']) {
        return ['success' => true, 'image_url' => $result['image_url']];
    }

    return ['success' => false, 'error' => $result['error'] ?? 'Respuesta inválida del servidor (' . $http_code . ')'];
}

function delete_from_web_api($image_url) {
    if (!defined('UPLOAD_API_KEY') || !defined('WEB_PUBLIC_URL')) {
        return false;
    }

    $url = rtrim(WEB_PUBLIC_URL, '/') . '/upload_api.php';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'api_key' => UPLOAD_API_KEY,
        'action' => 'delete',
        'file_to_delete' => $image_url
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    return ($http_code === 200 && isset($result['success']) && $result['success']);
}
?>
