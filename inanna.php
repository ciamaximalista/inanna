<?php
require_once __DIR__ . '/auth.php';

// --- Auth POST Handling ---
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        if (register_first_user($_POST['username'], $_POST['password'])) {
            login_user($_POST['username'], $_POST['password']);
            header('Location: inanna.php');
            exit;
        } else {
            $error_message = 'Error: Ya existe un usuario.';
        }
    } elseif (isset($_POST['login'])) {
        if (login_user($_POST['username'], $_POST['password'])) {
            header('Location: inanna.php');
            exit;
        } else {
            $error_message = 'Usuario o contraseña incorrectos.';
        }
    } elseif (isset($_POST['logout'])) {
        logout();
        header('Location: inanna.php');
        exit;
    }
}


// --- Data Handling for Logged-in user ---
if (is_logged_in()) {
    // --- DATA HANDLING DEFINES ---
    define('STYLES_FILE', __DIR__ . '/data/styles.json');
    define('RESOURCES_DIR', __DIR__ . '/recursos');

    // --- Load styles (FIRST THING TO DO) ---
    if (file_exists(STYLES_FILE)) {
        $styles = json_decode(file_get_contents(STYLES_FILE), true);
    } else {
        $styles = [
            'font_title' => 'Gabarito',
            'font_text' => 'Noto Sans',
            'color_h1' => '#1b8eed',
            'color_h2' => '#1b8eed',
            'color_h3' => '#1b8eed',
            'color_highlight' => '#ea2f28',
            'color_text' => '#2f2f2f',
            'color_bg' => '#ffffff',
            'color_box' => '#f4f6f8',
        ];
    }

    // --- Presentation Loading Logic ---
    $loaded_presentation_data = null;
    if (isset($_GET['edit'])) {
        error_log("DEBUG: Edit parameter received: " . $_GET['edit']);
        $filename = basename($_GET['edit']); // Sanitize filename
        $file_path = __DIR__ . '/data/archivo/' . $filename;
        error_log("DEBUG: Attempting to load file: " . $file_path);

        if (file_exists($file_path)) {
            error_log("DEBUG: File exists. Loading XML.");
            $xml = simplexml_load_file($file_path);
            if ($xml) {
                error_log("DEBUG: XML loaded successfully.");
                $loaded_presentation_data = [
                    'slides' => [],
                    'styles' => [],
                ];

                // Load styles
                if (isset($xml->styles)) {
                    error_log("DEBUG: Loading styles from XML.");
                    foreach ($xml->styles->children() as $key => $value) {
                        $loaded_presentation_data['styles'][$key] = (string)$value;
                    }
                    error_log("DEBUG: Loaded styles: " . print_r($loaded_presentation_data['styles'], true));
                }

                // Load slides
                if (isset($xml->slides)) {
                    error_log("DEBUG: Loading slides from XML.");
                    foreach ($xml->slides->slide as $slide_node) {
                        $loaded_presentation_data['slides'][] = [
                            'template' => (string)$slide_node->template,
                            'image' => (string)$slide_node->image,
                            'markdown' => (string)$slide_node->markdown,
                        ];
                    }
                    error_log("DEBUG: Loaded slides count: " . count($loaded_presentation_data['slides']));
                }
            } else {
                error_log("ERROR: Failed to load XML from file: " . $file_path);
            }
        } else {
            error_log("ERROR: File does not exist: " . $file_path);
        }
    }

    // If a presentation was loaded, update the $styles variable
    if ($loaded_presentation_data && isset($loaded_presentation_data['styles'])) {
        error_log("DEBUG: Merging loaded styles with current styles.");
        $styles = array_merge($styles, $loaded_presentation_data['styles']);
        error_log("DEBUG: Final styles after merge: " . print_r($styles, true));
    }

    if (!isset($styles['color_title'])) {
        $styles['color_title'] = $styles['color_h1'] ?? '#1b8eed';
    }

    // --- CONFIGURATION ---
    define('CONFIG_FILE', __DIR__ . '/data/config.json');
    $config = [];
    if (file_exists(CONFIG_FILE)) {
        $config = json_decode(file_get_contents(CONFIG_FILE), true);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
        $config['google_fonts_api_key'] = $_POST['google_fonts_api_key'];
        file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
        header('Location: inanna.php');
        exit;
    }

    // Handle style saving
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_styles'])) {
        $styles_to_save = [
            'font_title' => $_POST['font_title'],
            'font_text' => $_POST['font_text'],
            'color_h1' => $_POST['color_h1'],
            'color_h2' => $_POST['color_h2'],
            'color_h3' => $_POST['color_h3'],
            'color_highlight' => $_POST['color_highlight'],
            'color_text' => $_POST['color_text'],
            'color_bg' => $_POST['color_bg'],
            'color_box' => $_POST['color_box'],
        ];
        file_put_contents(STYLES_FILE, json_encode($styles_to_save, JSON_PRETTY_PRINT));
        header('Location: inanna.php');
        exit;
    }

    // Handle resource upload
    $upload_message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resource_file'])) {
        $file = $_FILES['resource_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (!is_dir(RESOURCES_DIR)) { mkdir(RESOURCES_DIR, 0755, true); }
            $target_path = RESOURCES_DIR . '/' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $upload_message = 'Archivo subido con éxito.';
            } else {
                $upload_message = 'Error al mover el archivo subido.';
            }
        } else {
            $upload_message = 'Error al subir el archivo (' . $file['error'] . ').';
        }
    }

    // List resources
    $resources = [];
    if (is_dir(RESOURCES_DIR)) {
        $files = scandir(RESOURCES_DIR);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') { $resources[] = 'recursos/' . $file; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inanna - Creador de Presentaciones</title>
    <link rel="icon" type="image/png" href="inanna.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
    <?php if (is_logged_in()): ?>
    <script src="js/composition.js" defer></script>
    <script src="js/resources.js" defer></script>
    <?php endif; ?>
    <style>
        /* --- Base & Typography --- */
        :root {
            --font-title: <?php echo $styles['font_title'] ?? 'Gabarito, sans-serif'; ?>;
            --font-text: <?php echo $styles['font_text'] ?? 'Noto Sans, sans-serif'; ?>;
            --color-title: <?php echo $styles['color_title'] ?? '#1b8eed'; ?>;
            --color-highlight: <?php echo $styles['color_highlight'] ?? '#ea2f28'; ?>;
            --color-text: <?php echo $styles['color_text'] ?? '#2f2f2f'; ?>;
            --color-bg: <?php echo $styles['color_bg'] ?? '#ffffff'; ?>;
            --color-box: <?php echo $styles['color_box'] ?? '#f4f6f8'; ?>;
        }
        body { 
            font-family: var(--font-text);
            margin: 0;
            background-color: #f0f2f5; /* Slightly darker bg for contrast */
            color: var(--color-text);
            line-height: 1.6;
        }
        .container { 
            max-width: 1200px; 
            margin: 30px auto; 
            padding: 30px;
            background-color: var(--color-bg);
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-title);
            color: var(--color-title);
            font-weight: 600;
        }

        /* --- Auth Page --- */
        .auth-container { 
            max-width: 400px; 
            margin: 80px auto; 
            padding: 40px; 
            background-color: var(--color-bg); 
            border-radius: 12px; 
            text-align: center; 
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
        }

        /* --- Forms & Buttons --- */
        input[type="text"], input[type="password"], input[type="file"], select {
            width: 100%;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color 0.2s ease;
        }
        input[type="text"]:focus, input[type="password"]:focus, select:focus {
            outline: none;
            border-color: var(--color-title);
            box-shadow: 0 0 0 2px rgba(27, 139, 237, 0.2);
        }
        button, input[type="submit"] {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            background-color: var(--color-title);
            color: white;
            cursor: pointer;
            font-family: var(--font-text);
            font-weight: 600;
            transition: background-color 0.2s ease, transform 0.1s ease;
        }
        button:hover, input[type="submit"]:hover {
            background-color: var(--color-highlight);
        }
        button:active, input[type="submit"]:active {
            transform: scale(0.98);
        }
        .logout-btn {
            background-color: transparent;
            color: var(--color-text);
            padding: 8px 15px;
        }
        .logout-btn:hover {
            background-color: var(--color-box);
            color: var(--color-highlight);
        }

        /* --- Tabs --- */
        .tabs { 
            display: flex; 
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 20px;
        } 
        .tab-link { 
            padding: 12px 20px;
            cursor: pointer; 
            border: none;
            background-color: transparent;
            margin-right: 10px;
            font-family: var(--font-title);
            font-weight: 600;
            font-size: 1.1rem;
            color: #666;
            position: relative;
            transition: color 0.2s ease;
        } 
        .tab-link:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--color-title);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .tab-link.active { 
            color: var(--color-title);
        }
        .tab-link.active:after {
            transform: scaleX(1);
        }
        .tab-content { display: none; padding: 10px; }
        .tab-content.active { display: block; }

        /* --- Specific Elements --- */
        .error { color: var(--color-highlight); background-color: rgba(234, 47, 40, 0.1); padding: 10px; border-radius: 8px; }
        .template-option { cursor: pointer; border: 2px solid transparent; border-radius: 8px; transition: all 0.2s ease; padding: 5px; }
        .template-option:hover { border-color: #ccc; }
        .template-option.selected { border-color: var(--color-title); box-shadow: 0 0 10px rgba(27, 139, 237, 0.3); }
        #slide-preview { border-radius: 8px; background-color: #fafafa; }
        .resources-gallery img { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .resources-gallery img:hover { transform: scale(1.05); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }

        /* --- Choices.js Overrides --- */
        .choices {
            margin-bottom: 12px;
        }
        .choices__inner {
            background-color: white;
            padding: 7.5px 7.5px 4px 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        .is-open .choices__inner {
            border-color: var(--color-title);
            border-radius: 8px;
        }
        .choices__list--dropdown {
            border-radius: 8px;
        }
    </style>
</head>
<body>

<?php if (is_logged_in()): ?>
    <script>
        const appStyles = <?php echo json_encode($styles); ?>;
        const initialPresentationData = <?php echo json_encode($loaded_presentation_data); ?>;
    </script>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <img src="inanna.png" alt="Inanna Logo" style="height: 50px; display: block; margin-bottom: 10px;">
            <form method="POST" action="inanna.php">
                <button type="submit" name="logout" class="logout-btn">Salir</button>
            </form>
        </div>

        <div class="tabs">
            <div class="tab-link <?php echo isset($_GET['edit']) ? 'active' : ''; ?>" onclick="openTab(event, 'Texto')">Texto</div>
            <div class="tab-link" onclick="openTab(event, 'Estetica')">Estética</div>
            <div class="tab-link" onclick="openTab(event, 'Recursos')">Recursos</div>
            <div class="tab-link" onclick="openTab(event, 'Composicion')">Composición</div>
            <div class="tab-link" onclick="openTab(event, 'Configuracion')">Configuración</div>
            <div class="tab-link" onclick="openTab(event, 'Archivo')">Archivo</div>
        </div>

        <div id="Texto" class="tab-content active">
            <h3>Contenido de la Presentación (Markdown)</h3>
            <p>Escribe el contenido de tus diapositivas. Separa cada diapositiva con tres guiones (---).</p>
            <textarea id="markdown-source" style="width: 100%; height: 400px; font-family: monospace;"><?php 
                if ($loaded_presentation_data && isset($loaded_presentation_data['slides'])) {
                    $all_markdown = [];
                    foreach ($loaded_presentation_data['slides'] as $s) {
                        $all_markdown[] = $s['markdown'];
                    }
                    echo htmlspecialchars(implode("\n---\n", $all_markdown));
                }
            ?></textarea>
        </div>

        <div id="Estetica" class="tab-content">
            <h3>Estilos de la Presentación</h3>

            <div style="padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 20px; background-color: #f9f9f9;">
                <h4>Fuentes de Google</h4>
                <?php if (!empty($config['google_fonts_api_key'])): ?>
                    <form action="fetch_fonts.php" method="POST">
                        <button type="submit">Actualizar lista de fuentes de Google</button>
                    </form>
                    <?php 
                        if (isset($_SESSION['font_fetch_error'])) {
                            echo '<p class="error">' . htmlspecialchars($_SESSION['font_fetch_error']) . '</p>';
                            unset($_SESSION['font_fetch_error']);
                        }
                    ?>
                <?php else: ?>
                    <p>Para usar Google Fonts, por favor, añade una clave de API en la pestaña de <a href="#" onclick="openTab(event, 'Configuracion')">Configuración</a>.</p>
                <?php endif; ?>
            </div>

            <?php
                define('FONTS_CACHE_FILE', __DIR__ . '/data/google_fonts.json');
                $google_fonts = [];
                if (file_exists(FONTS_CACHE_FILE)) {
                    $fonts_data = json_decode(file_get_contents(FONTS_CACHE_FILE), true);
                    if (isset($fonts_data['items'])) {
                        foreach ($fonts_data['items'] as $font) {
                            $google_fonts[] = $font['family'];
                        }
                    }
                }
            ?>

            <form method="POST" action="inanna.php">
                <table style="width: 100%;">
                    <tr>
                        <td style="width: 50%; padding: 10px;">
                            <label for="font_title">Fuente de Título</label><br>
                            <?php if (!empty($google_fonts)): ?>
                                <select id="font_title_select" name="font_title">
                                    <?php foreach($google_fonts as $font): ?>
                                        <option value="<?php echo htmlspecialchars($font); ?>" <?php echo ($styles['font_title'] == $font) ? 'selected' : ''; ?>><?php echo htmlspecialchars($font); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" id="font_title" name="font_title" value="<?php echo htmlspecialchars($styles['font_title']); ?>" style="width: 100%;">
                                <?php if(!empty($config['google_fonts_api_key'])) echo '<small>La lista de fuentes no se ha cargado. Intenta actualizarla.</small>'; ?>
                            <?php endif; ?>
                        </td>
                        <td style="width: 50%; padding: 10px;">
                            <label for="font_text">Fuente de Texto</label><br>
                             <?php if (!empty($google_fonts)): ?>
                                <select id="font_text_select" name="font_text">
                                    <?php foreach($google_fonts as $font): ?>
                                        <option value="<?php echo htmlspecialchars($font); ?>" <?php echo ($styles['font_text'] == $font) ? 'selected' : ''; ?>><?php echo htmlspecialchars($font); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" id="font_text" name="font_text" value="<?php echo htmlspecialchars($styles['font_text']); ?>" style="width: 100%;">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Color inputs remain the same -->
                    <tr>
                        <td style="padding: 10px;">
                            <label for="color_h1">Color H1</label><br>
                            <input type="color" id="color_h1" name="color_h1" value="<?php echo htmlspecialchars($styles['color_h1'] ?? '#1b8eed'); ?>">
                        </td>
                        <td style="padding: 10px;">
                            <label for="color_h2">Color H2</label><br>
                            <input type="color" id="color_h2" name="color_h2" value="<?php echo htmlspecialchars($styles['color_h2'] ?? '#1b8eed'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;">
                            <label for="color_h3">Color H3</label><br>
                            <input type="color" id="color_h3" name="color_h3" value="<?php echo htmlspecialchars($styles['color_h3'] ?? '#1b8eed'); ?>">
                        </td>
                        <td style="padding: 10px;">
                            <label for="color_highlight">Color Destacado</label><br>
                            <input type="color" id="color_highlight" name="color_highlight" value="<?php echo htmlspecialchars($styles['color_highlight']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;">
                            <label for="color_text">Color de Texto</label><br>
                            <input type="color" id="color_text" name="color_text" value="<?php echo htmlspecialchars($styles['color_text']); ?>">
                        </td>
                        <td style="padding: 10px;">
                            <label for="color_bg">Color de Fondo</label><br>
                            <input type="color" id="color_bg" name="color_bg" value="<?php echo htmlspecialchars($styles['color_bg']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 10px;">
                            <label for="color_box">Color de Cajas Destacadas</label><br>
                            <input type="color" id="color_box" name="color_box" value="<?php echo htmlspecialchars($styles['color_box']); ?>">
                        </td>
                        <td></td>
                    </tr>
                </table>
                <br>
                <button type="submit" name="save_styles">Guardar Estilos</button>
            </form>
        </div>

        <div id="Recursos" class="tab-content">
            <h3>Recursos Multimedia</h3>
            <p>Sube imágenes y vídeos para tu presentación.</p>
            <form method="POST" action="inanna.php" enctype="multipart/form-data">
                <input type="file" name="resource_file" required>
                <button type="submit">Subir Archivo</button>
            </form>
            <?php if (!empty($upload_message)): ?><p><?php echo $upload_message; ?></p><?php endif; ?>
            <hr style="margin: 20px 0;">
            <h4>Archivos Subidos</h4>
            <div class="resources-gallery" style="display: flex; flex-wrap: wrap; gap: 10px;">
                <?php if (empty($resources)): ?>
                    <p>No has subido ningún archivo todavía.</p>
                <?php else: ?>
                    <?php foreach ($resources as $resource): ?>
                        <div class="resource-item" style="width: 180px; display:flex; flex-direction:column; gap:6px;">
                            <?php 
                                $file_ext = strtolower(pathinfo($resource, PATHINFO_EXTENSION));
                                $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                if ($is_image):
                            ?>
                                <img src="<?php echo htmlspecialchars($resource); ?>" alt="<?php echo basename($resource); ?>" style="width: 100%; height: auto; border-radius: 4px;">
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($resource); ?>" target="_blank"><?php echo basename($resource); ?></a>
                            <?php endif; ?>
                            <div style="display:flex; justify-content:space-between; gap:6px; font-family:'Gabarito', sans-serif;">
                                <button type="button" class="resource-edit-btn" data-resource-path="<?php echo htmlspecialchars($resource); ?>" <?php echo $is_image ? '' : 'disabled'; ?> style="flex:1; font-family:inherit;">Editar</button>
                                <button type="button" class="resource-delete-btn" data-resource-path="<?php echo htmlspecialchars($resource); ?>" style="flex:1; font-family:inherit;">Borrar</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div id="resource-edit-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1100; align-items:center; justify-content:center;">
                <div style="background:#fff; padding:20px; border-radius:10px; max-width:900px; width:95%; max-height:90vh; overflow:auto; position:relative;">
                    <span id="resource-edit-close" style="position:absolute; top:10px; right:15px; font-size:28px; cursor:pointer;">&times;</span>
                    <h3>Editar recurso</h3>
                    <form id="resource-edit-form">
                        <div style="display:flex; flex-wrap:wrap; gap:20px;">
                            <div style="flex:1 1 360px;">
                                <canvas id="resource-edit-canvas" style="width:100%; border:1px solid #ccc; border-radius:8px; background:#f9f9f9;"></canvas>
                            </div>
                            <div style="flex:1 1 280px; min-width:260px; display:flex; flex-direction:column; gap:12px;">
                                <label for="resource-edit-filename">Nombre de archivo</label>
                                <input type="text" id="resource-edit-filename" style="width:100%;" required>

                                <label for="resource-brightness">Brillo: <span id="resource-brightness-value">0</span></label>
                                <input type="range" id="resource-brightness" min="-100" max="100" value="0">

                                <label for="resource-contrast">Contraste: <span id="resource-contrast-value">0</span></label>
                                <input type="range" id="resource-contrast" min="-100" max="100" value="0">

                                <fieldset style="border:1px solid #ccc; border-radius:6px; padding:10px;">
                                    <legend>Recorte</legend>
                                    <div style="display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:10px;">
                                        <div>
                                            <label for="resource-crop-x">X</label>
                                            <input type="number" id="resource-crop-x" min="0" value="0" style="width:100%;">
                                        </div>
                                        <div>
                                            <label for="resource-crop-y">Y</label>
                                            <input type="number" id="resource-crop-y" min="0" value="0" style="width:100%;">
                                        </div>
                                        <div>
                                            <label for="resource-crop-width">Ancho</label>
                                            <input type="number" id="resource-crop-width" min="1" value="100" style="width:100%;">
                                        </div>
                                        <div>
                                            <label for="resource-crop-height">Alto</label>
                                            <input type="number" id="resource-crop-height" min="1" value="100" style="width:100%;">
                                        </div>
                                    </div>
                                </fieldset>

                                <div style="display:flex; gap:10px; justify-content:flex-end;">
                                    <button type="button" id="resource-edit-cancel">Cancelar</button>
                                    <button type="submit" id="resource-edit-save">Guardar</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div id="Composicion" class="tab-content">
            <div style="display: flex; gap: 20px;">
                <div id="slide-thumbnails" style="width: 20%; border-right: 1px solid #ccc; padding-right: 10px; display: flex; flex-direction: column; gap: 10px;"></div>
                <div style="width: 80%;">
                    <h4>Previsualización de Diapositiva</h4>
                    <div id="slide-preview" style="width: 100%; height: 0; padding-bottom: 70.71428%; margin-bottom: 20px; position: relative;"></div>
                    <h4>Plantillas para la diapositiva actual</h4>
                    <div id="template-selector" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px;">
                        <div class="template-option" data-template="a" title="Título 1: Centrado"><div style="border:1px solid #333; height: 80px; display:flex; align-items:center; justify-content:center; flex-direction:column;">T</div></div>
                        <div class="template-option" data-template="z" title="Título 2: Título a la izquierda, imagen a la derecha"><div style="border:1px solid #333; height: 80px; display:flex;"><div style="width:50%; display:flex; align-items:center; justify-content:center;">T</div><div style="width:50%; background:#ccc; display:flex; align-items:center; justify-content:center;">IMG</div></div></div>
                        <div class="template-option" data-template="y" title="Título 3: Título a la derecha, imagen a la izquierda"><div style="border:1px solid #333; height: 80px; display:flex;"><div style="width:50%; background:#ccc; display:flex; align-items:center; justify-content:center;">IMG</div><div style="width:50%; display:flex; align-items:center; justify-content:center;">T</div></div></div>
                        <div class="template-option" data-template="g" title="Texto izquierda, imagen cuadrada derecha"><div style="border:1px solid #333; height: 80px; display:flex; align-items:center; justify-content:center;"><div style="width:55%; display:flex; align-items:center; justify-content:center;">T</div><div style="width:45%; display:flex; align-items:center; justify-content:center;"><div style="width:60%; height:60%; background:#ccc;"></div></div></div></div>
                        <div class="template-option" data-template="h" title="Imagen cuadrada izquierda, texto derecha"><div style="border:1px solid #333; height: 80px; display:flex; align-items:center; justify-content:center;"><div style="width:45%; display:flex; align-items:center; justify-content:center;"><div style="width:60%; height:60%; background:#ccc;"></div></div><div style="width:55%; display:flex; align-items:center; justify-content:center;">T</div></div></div>
                        <div class="template-option" data-template="b" title="Imagen apaisada superior"><div style="border:1px solid #333; height: 80px; display:flex; flex-direction:column;"><div style="height:20%; background:#ccc;"></div><div style="height:80%; display:flex; align-items:center; justify-content:center;">T</div></div></div>
                        <div class="template-option" data-template="c" title="Imagen apaisada inferior"><div style="border:1px solid #333; height: 80px; display:flex; flex-direction:column;"><div style="height:80%; display:flex; align-items:center; justify-content:center;">T</div><div style="height:20%; background:#ccc;"></div></div></div>
                        <div class="template-option" data-template="e" title="Imagen vertical derecha"><div style="border:1px solid #333; height: 80px; display:flex;"><div style="width:75%; display:flex; align-items:center; justify-content:center;">T</div><div style="width:25%; background:#ccc;"></div></div></div>
                        <div class="template-option" data-template="f" title="Imagen vertical izquierda"><div style="border:1px solid #333; height: 80px; display:flex;"><div style="width:25%; background:#ccc;"></div><div style="width:75%; display:flex; align-items:center; justify-content:center;">T</div></div></div>
                    </div>
                    <br>
                    <form id="download-form" method="POST" action="generar_pdf.php" target="_blank">
                        <input type="hidden" name="presentation_data" id="presentation-data-input">
                                            <button type="submit" id="download-pdf">Descargar PDF</button>
                                            <button type="button" id="save-presentation" style="margin-left: 10px;">Guardar</button>
                                        </form>                </div>
            </div>
        </div>

        <div id="Configuracion" class="tab-content">
            <h3>Configuración General</h3>
            <form method="POST" action="inanna.php">
                <label for="google_fonts_api_key">Clave de API de Google Fonts</label><br>
                <input type="text" id="google_fonts_api_key" name="google_fonts_api_key" value="<?php echo htmlspecialchars($config['google_fonts_api_key'] ?? ''); ?>" style="width: 50%; margin-top: 5px;">
                <p style="font-size: 0.8rem; color: #666;">Necesaria para cargar la lista de todas las fuentes de Google en la pestaña de "Estética".</p>
                <button type="submit" name="save_config">Guardar Configuración</button>
            </form>
        </div>

        <div id="Archivo" class="tab-content">
            <h3>Presentaciones Guardadas</h3>
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Nombre de Archivo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $archive_dir = __DIR__ . '/data/archivo/';
                        $saved_files = glob($archive_dir . '*.xml');
                        if (empty($saved_files)):
                    ?>
                        <tr>
                            <td colspan="2" style="text-align: center; padding: 20px;">No hay presentaciones guardadas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($saved_files as $file): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(basename($file)); ?></td>
                                <td>
                                    <a href="inanna.php?edit=<?php echo urlencode(basename($file)); ?>">Editar</a>
                                    <a href="#" onclick="deletePresentation('<?php echo urlencode(basename($file)); ?>')" style="color: var(--color-highlight); margin-left: 15px;">Borrar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="resource-modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
            <div style="background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 900px; border-radius: 8px;">
                <span id="close-modal" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                <h3>Elige un Recurso</h3>
                <div id="modal-resource-gallery" style="display: flex; flex-wrap: wrap; gap: 10px;">
                    <?php foreach ($resources as $resource): ?>
                        <?php 
                            $file_ext = strtolower(pathinfo($resource, PATHINFO_EXTENSION));
                            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])):
                        ?>
                            <img src="<?php echo htmlspecialchars($resource); ?>" class="modal-resource-item" style="width: 150px; height: auto; border-radius: 4px; cursor: pointer;" data-resource-path="<?php echo htmlspecialchars($resource); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="auth-container">
        <img src="inanna.png" alt="Inanna Logo" style="height: 60px; margin-bottom: 20px;">
        <?php if ($error_message): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <?php if (has_users()): ?>
            <h2>Iniciar Sesión</h2>
            <form method="POST" action="inanna.php">
                <input type="text" name="username" placeholder="Usuario" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit" name="login">Entrar</button>
            </form>
        <?php else: ?>
            <h2>Crear Usuario Administrador</h2>
            <p>Es el primer uso. Crea el único usuario para esta instancia.</p>
            <form method="POST" action="inanna.php">
                <input type="text" name="username" placeholder="Usuario" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit" name="register">Registrar</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
}
</script>

<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fontTitleSelect = document.getElementById('font_title_select');
        if (fontTitleSelect) {
            new Choices(fontTitleSelect);
        }
        const fontTextSelect = document.getElementById('font_text_select');
        if (fontTextSelect) {
            new Choices(fontTextSelect);
        }
    });
</script>
<script>
    function deletePresentation(filename) {
        if (confirm(`¿Estás seguro de que quieres borrar "${filename}"?`)) {
            fetch('borrar_presentacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ filename: filename })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(`Error al borrar: ${data.message}`);
                }
            })
            .catch(error => {
                console.error('Error al borrar la presentación:', error);
                alert('Ocurrió un error de red al intentar borrar la presentación.');
            });
        }
    }
</script>
</body>
</html>
