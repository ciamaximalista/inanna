<?php
require_once __DIR__ . '/auth.php';

// Security check
if (!is_logged_in()) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Acceso denegado']));
}

// Get data from POST request
$input = json_decode(file_get_contents('php://input'), true);
$filename = $input['filename'] ?? null;
$presentation_data = $input['presentation_data'] ?? null;

// Validate filename
if (!$filename || !preg_match('/^[a-zA-Z0-9_-]+\.xml$/', $filename)) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'Nombre de archivo inválido. Solo se permiten caracteres alfanuméricos, guiones y guiones bajos, y debe terminar en .xml']));
}

if (!$presentation_data) {
    http_response_code(400);
    die(json_encode(['status' => 'error', 'message' => 'No se han recibido datos de la presentación.']));
}

// Create XML structure
$xml = new SimpleXMLElement('<presentation/>');

// Add styles
if (isset($presentation_data['styles'])) {
    $styles_node = $xml->addChild('styles');
    foreach ($presentation_data['styles'] as $key => $value) {
        $styles_node->addChild($key, htmlspecialchars($value));
    }
}

// Add slides
if (isset($presentation_data['slides'])) {
    $slides_node = $xml->addChild('slides');
    foreach ($presentation_data['slides'] as $slide_data) {
        $slide_node = $slides_node->addChild('slide');
        $slide_node->addChild('template', $slide_data['template'] ?? 'a');
        $slide_node->addChild('image', $slide_data['image'] ?? '');
        // Use CDATA for markdown to avoid XML parsing issues
        $markdown_node = $slide_node->addChild('markdown');
        $dom = dom_import_simplexml($markdown_node);
        $dom->appendChild($dom->ownerDocument->createCDATASection($slide_data['markdown'] ?? ''));
    }
}

// Save XML file
$archive_dir = __DIR__ . '/data/archivo/';
if (!is_dir($archive_dir)) {
    mkdir($archive_dir, 0755, true);
}
$file_path = $archive_dir . $filename;

// Save the formatted XML
$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

if ($dom->save($file_path)) {
    echo json_encode(['status' => 'success', 'message' => 'Presentación guardada con éxito.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo en el servidor.']);
}
?>