<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$base = dirname($_SERVER['SCRIPT_NAME']);
if ($base !== '/' && $base !== '\\' && strpos($uri, $base) === 0) {
    $uri = substr($uri, strlen($base));
}
$uri = trim($uri, '/');

if (empty($uri) || $uri === 'index.php' || $uri === 'index') {
    header("Location: dashboard");
    exit();
}

$file = $uri . '.php';
if (file_exists($file) && !in_array($uri, ['config', 'api_report'])) {
    require $file;
    exit();
}

http_response_code(404);
echo "<h3>404 - Página no encontrada</h3>";
exit();
