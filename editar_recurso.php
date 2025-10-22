<?php
require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

$resourcePath = $_POST['resource_path'] ?? '';
$newFilename = trim($_POST['new_filename'] ?? '');
$brightness = (int)($_POST['brightness'] ?? 0);
$contrast = (int)($_POST['contrast'] ?? 0);
$cropX = (int)($_POST['crop_x'] ?? 0);
$cropY = (int)($_POST['crop_y'] ?? 0);
$cropWidth = (int)($_POST['crop_width'] ?? 0);
$cropHeight = (int)($_POST['crop_height'] ?? 0);

if ($resourcePath === '' || $newFilename === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Datos insuficientes.']);
    exit;
}

$normalized = str_replace(['\\', '..'], ['/', ''], $resourcePath);
$fullPath = realpath(__DIR__ . '/' . $normalized);
$resourcesDir = realpath(__DIR__ . '/recursos');

if ($fullPath === false || $resourcesDir === false || strpos($fullPath, $resourcesDir) !== 0 || !is_file($fullPath)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Recurso no encontrado.']);
    exit;
}

$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Solo se pueden editar imágenes.']);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._-]+$/', $newFilename)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Nombre de archivo inválido.']);
    exit;
}

if (strtolower(substr($newFilename, -strlen($extension) - 1)) !== '.' . $extension) {
    $newFilename .= '.' . $extension;
}

$targetPath = $resourcesDir . DIRECTORY_SEPARATOR . $newFilename;
if (file_exists($targetPath)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Ya existe un archivo con ese nombre.']);
    exit;
}

$loaders = [
    'jpg' => 'imagecreatefromjpeg',
    'jpeg' => 'imagecreatefromjpeg',
    'png' => 'imagecreatefrompng',
    'gif' => 'imagecreatefromgif',
    'webp' => 'imagecreatefromwebp',
];

$savers = [
    'jpg' => fn($img, $path) => imagejpeg($img, $path, 90),
    'jpeg' => fn($img, $path) => imagejpeg($img, $path, 90),
    'png' => fn($img, $path) => imagepng($img, $path, 9),
    'gif' => fn($img, $path) => imagegif($img, $path),
    'webp' => fn($img, $path) => imagewebp($img, $path, 90),
];

$loader = $loaders[$extension] ?? null;
$saver = $savers[$extension] ?? null;

if (!$loader || !$saver || !function_exists($loader) || !function_exists('imagefilter')) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No es posible procesar la imagen en este servidor.']);
    exit;
}

$sourceImage = @$loader($fullPath);
if (!$sourceImage) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo abrir la imagen.']);
    exit;
}

$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);

$brightness = max(-100, min(100, $brightness));
$contrast = max(-100, min(100, $contrast));
$cropX = max(0, min($sourceWidth - 1, $cropX));
$cropY = max(0, min($sourceHeight - 1, $cropY));
$cropWidth = max(1, min($sourceWidth - $cropX, $cropWidth));
$cropHeight = max(1, min($sourceHeight - $cropY, $cropHeight));

// Apply filters (brightness first, then contrast)
if ($brightness !== 0) {
    $phpBrightness = (int)round($brightness * 2.55);
    @imagefilter($sourceImage, IMG_FILTER_BRIGHTNESS, $phpBrightness);
}
if ($contrast !== 0) {
    // Note: PHP contrast filter expects -100..100 where positive reduces contrast.
    $phpContrast = -$contrast;
    @imagefilter($sourceImage, IMG_FILTER_CONTRAST, $phpContrast);
}

$croppedImage = imagecrop($sourceImage, [
    'x' => $cropX,
    'y' => $cropY,
    'width' => $cropWidth,
    'height' => $cropHeight,
]);

if ($croppedImage === false) {
    $croppedImage = $sourceImage;
}

$saveResult = $saver($croppedImage, $targetPath);
imagedestroy($croppedImage);
if ($croppedImage !== $sourceImage) {
    imagedestroy($sourceImage);
}

if (!$saveResult) {
    @unlink($targetPath);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la imagen editada.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'resource_path' => 'recursos/' . $newFilename,
]);
