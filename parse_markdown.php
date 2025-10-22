<?php
require_once __DIR__ . '/auth.php';

// Only logged-in users can parse markdown
if (!is_logged_in()) {
    http_response_code(403);
    die('Acceso denegado.');
}

require_once __DIR__ . '/vendor/autoload.php';

if (isset($_POST['markdown'])) {
    $parsedown = new Parsedown();
    echo $parsedown->text($_POST['markdown']);
}
?>