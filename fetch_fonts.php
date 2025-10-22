<?php
require_once __DIR__ . '/auth.php';

// Only logged-in users can fetch fonts
if (!is_logged_in()) {
    header('Location: inanna.php');
    exit;
}

define('CONFIG_FILE', __DIR__ . '/data/config.json');
define('FONTS_CACHE_FILE', __DIR__ . '/data/google_fonts.json');

$config = [];
if (file_exists(CONFIG_FILE)) {
    $config = json_decode(file_get_contents(CONFIG_FILE), true);
}

$apiKey = $config['google_fonts_api_key'] ?? null;

if ($apiKey) {
    $apiUrl = "https://www.googleapis.com/webfonts/v1/webfonts?key=" . $apiKey . "&sort=popularity";
    
    // Use file_get_contents with a stream context to handle potential errors
    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true // Allows reading the response body on error
        ]
    ]);
    $response = file_get_contents($apiUrl, false, $context);
    
    if ($response !== false) {
        // Check for a successful HTTP status code
        if (strpos($http_response_header[0], "200 OK") !== false) {
            file_put_contents(FONTS_CACHE_FILE, $response);
        } else {
            // Handle API error (e.g., invalid key)
            // You could store an error message in the session to display to the user
            $_SESSION['font_fetch_error'] = "Error al contactar con la API de Google Fonts. Verifica tu clave de API. Detalle: " . $http_response_header[0];
        }
    } else {
        $_SESSION['font_fetch_error'] = "No se pudo conectar con el servidor de Google Fonts.";
    }
} else {
    $_SESSION['font_fetch_error'] = "No se ha configurado una clave de API de Google Fonts.";
}

// Redirect back to the main page
header('Location: inanna.php?tab=Estetica');
exit;
?>