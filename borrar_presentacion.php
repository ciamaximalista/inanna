<?php
require_once __DIR__ . '/auth.php';

// Security check
if (!is_logged_in()) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Acceso denegado']));
}

// Get filename from POST request
$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? null;

// Validate filename
if (!$filename || !preg_match('/^[a-zA-Z0-9_-]+\.xml$/', $filename)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Nombre de archivo inválido.']));
}

$file_path = __DIR__ . '/data/archivo/' . $filename;

if (file_exists($file_path)) {
    if (unlink($file_path)) {
        echo json_encode(['status' => 'success', 'message' => 'Presentación borrada con éxito.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'No se pudo borrar el archivo.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Archivo no encontrado.']);
}
?>