<?php
require_once 'config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$VALID_API_KEY = defined('REPORT_API_KEY') ? REPORT_API_KEY : 'KEY';

$headers = getallheaders();
$providedKey = $headers['X-Api-Key'] ?? $headers['x-api-key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');

if (empty($providedKey) || !hash_equals($VALID_API_KEY, $providedKey)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido. Use POST.']);
    exit();
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || !is_array($data)) {
    $data = $_POST;
}

$required_fields = ['server', 'reporting_user', 'reported_user', 'reason'];
$missing = [];

foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        $missing[] = $field;
    }
}

if (!empty($missing)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan campos obligatorios.', 'missing' => $missing]);
    exit();
}

$server_name     = strip_tags(trim($data['server']));
$reporting_user  = strip_tags(trim($data['reporting_user']));
$reported_user   = strip_tags(trim($data['reported_user']));
$reason          = strip_tags(trim($data['reason']));
$additional_info = isset($data['additional_info']) ? strip_tags(trim($data['additional_info'])) : '';

if (strlen($server_name) > 100 || strlen($reporting_user) > 50 || strlen($reported_user) > 50) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos exceden longitud máxima permitida.']);
    exit();
}

$stmt = $conn->prepare("INSERT INTO reports (server_name, reporting_user, reported_user, reason, additional_info) VALUES (?, ?, ?, ?, ?)");

try {
    $stmt->execute([$server_name, $reporting_user, $reported_user, $reason, $additional_info]);
    http_response_code(201);
    echo json_encode([
        'status'    => 'success',
        'message'   => 'Reporte guardado correctamente.',
        'report_id' => $conn->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar el reporte.']);
}
?>
