<?php
require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$resourcePath = $input['resource_path'] ?? '';

if (!$resourcePath || !is_string($resourcePath)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Ruta de recurso inválida.']);
    exit;
}

$normalized = str_replace(['\\', '..'], ['/', ''], $resourcePath);
$fullPath = realpath(__DIR__ . '/' . $normalized);
$resourcesDir = realpath(__DIR__ . '/recursos');

if ($fullPath === false || $resourcesDir === false || strpos($fullPath, $resourcesDir) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Recurso no encontrado.']);
    exit;
}

if (!@unlink($fullPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No se pudo borrar el recurso.']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
