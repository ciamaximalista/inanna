<?php
require_once __DIR__ . '/auth.php';

function inanna_get_style_keys() {
    return [
        'font_title',
        'font_text',
        'color_h1',
        'color_h2',
        'color_h3',
        'color_highlight',
        'color_text',
        'color_bg',
        'color_box',
    ];
}

function inanna_default_style_values() {
    return [
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

function inanna_collect_styles_from_input($input, $fallback = []) {
    $input = is_array($input) ? $input : [];
    $fallback = is_array($fallback) ? $fallback : [];
    $defaults = array_merge(inanna_default_style_values(), $fallback);
    $result = [];
    foreach (inanna_get_style_keys() as $key) {
        if (array_key_exists($key, $input) && $input[$key] !== '') {
            $result[$key] = $input[$key];
        } elseif (array_key_exists($key, $defaults)) {
            $result[$key] = $defaults[$key];
        }
    }
    return $result;
}

function inanna_extract_slide_summary($markdown) {
    $title = '';
    $summary = '';
    $lines = preg_split("/\r\n|\n|\r/", (string)$markdown);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') { continue; }
        if ($title === '' && preg_match('/^#{1,6}\s*(.+)$/', $trimmed, $matches)) {
            $title = trim($matches[1]);
            continue;
        }
        if ($summary === '') {
            $summary = trim(preg_replace('/[*_`>#-]/', '', $trimmed));
        }
        if ($title !== '' && $summary !== '') { break; }
    }
    if ($title === '' && $summary !== '') {
        $title = $summary;
    }
    return [$title, $summary];
}

function inanna_truncate_text($text, $limit = 120) {
    $text = trim((string)$text);
    if ($text === '') {
        return $text;
    }
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '…', 'UTF-8');
    }
    if (strlen($text) <= $limit) {
        return $text;
    }
    return substr($text, 0, $limit - 1) . '…';
}

function inanna_redirect_with_tab($tab, $edit = '') {
    $params = ['tab=' . urlencode($tab)];
    if ($edit !== '') {
        $params[] = 'edit=' . urlencode($edit);
    }
    header('Location: inanna.php?' . implode('&', $params));
    exit;
}

function inanna_render_slide_preview($slide, $styles) {
    $mmToPx = function ($mm) { return ($mm / 25.4) * 96; };

    $scale_factor = 1.16;
    $page_width_px = $mmToPx(297);
    $page_height_px = $mmToPx(210);
    $padding_top_px = $mmToPx(8);
    $padding_left_px = $mmToPx(10);
    $padding_right_px = $mmToPx(1);
    $padding_bottom_px = $mmToPx(1);
    $content_padding_right_px = $mmToPx(2);
    $content_padding_left_px = $mmToPx(6 * $scale_factor);
    $gap_large_px = $mmToPx(12 * $scale_factor);
    $gap_small_px = $mmToPx(6 * $scale_factor);
    $shift_right_px = $mmToPx(2.5);
    $extra_right_px = $mmToPx(85);
    $extra_bottom_px = $mmToPx(60);
    $total_width_px = $page_width_px + $extra_right_px + $shift_right_px;
    $total_height_px = $page_height_px + $extra_bottom_px;
    $translate_x = $mmToPx(46);
    $translate_y = $mmToPx(25);

    $paragraph_font_pt = 20 * $scale_factor;
    $h1_font_pt = 44 * $scale_factor;
    $h2_font_pt = 36 * $scale_factor;
    $h3_font_pt = 30 * $scale_factor;
    $media_placeholder_font_pt = 12 * $scale_factor;

    $styles = inanna_collect_styles_from_input($styles, inanna_default_style_values());

    if (!is_array($slide)) {
        return '<div class="archive-card-preview"><div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#666;">Sin diapositivas</div></div>';
    }

    $template = $slide['template'] ?? 'a';
    $image_src = trim((string)($slide['image'] ?? ''));
    $markdown_html = (string)($slide['markdown_html'] ?? '');
    if (trim(strip_tags($markdown_html)) === '') {
        $markdown_html = '<p style="opacity:0.55;">Sin contenido en la primera diapositiva.</p>';
    }

    $highlight = $styles['color_highlight'] ?? '#ea2f28';
    $content_class = $template === 'a' ? 'archive-preview-content centered' : 'archive-preview-content';
    $content_block = '<div class="' . $content_class . '"><div class="archive-preview-content-inner"><div>' . $markdown_html . '</div></div></div>';

    $media_block = '';
    if ($template !== 'a') {
        $base_media_class = ($template === 'g' || $template === 'h') ? 'archive-preview-media square' : 'archive-preview-media';
        if ($image_src !== '') {
            $media_block = '<div class="' . $base_media_class . ' has-image" style="background-image:url(\'' . htmlspecialchars($image_src, ENT_QUOTES, 'UTF-8') . '\');"></div>';
        } else {
            $media_block = '<div class="' . $base_media_class . ' placeholder"><span>Sin imagen</span></div>';
        }
        if ($template === 'g' || $template === 'h') {
            $media_block = '<div class="archive-preview-square-wrapper">' . $media_block . '</div>';
        }
    }

    $gap_large = number_format($gap_large_px, 2, '.', '');
    $gap_small = number_format($gap_small_px, 2, '.', '');

    $slide_table = '';
    switch ($template) {
        case 'z':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell content" style="width:52%;padding-right:' . $gap_large . 'px;">' . $content_block . '</td><td class="archive-preview-cell media" style="width:48%;">' . $media_block . '</td></tr></table>';
            break;
        case 'y':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell media" style="width:48%;padding-right:' . $gap_large . 'px;">' . $media_block . '</td><td class="archive-preview-cell content" style="width:52%;padding-left:' . $gap_large . 'px;">' . $content_block . '</td></tr></table>';
            break;
        case 'g':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell content" style="width:70%;padding-right:' . $gap_large . 'px;">' . $content_block . '</td><td class="archive-preview-cell media" style="width:30%;">' . $media_block . '</td></tr></table>';
            break;
        case 'h':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell media" style="width:30%;">' . $media_block . '</td><td class="archive-preview-cell content" style="width:70%;padding-left:' . $gap_large . 'px;">' . $content_block . '</td></tr></table>';
            break;
        case 'e':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell content" style="width:78%;padding-right:' . $gap_large . 'px;">' . $content_block . '</td><td class="archive-preview-cell media" style="width:22%;">' . $media_block . '</td></tr></table>';
            break;
        case 'f':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell media" style="width:22%;">' . $media_block . '</td><td class="archive-preview-cell content" style="width:78%;padding-left:' . $gap_large . 'px;">' . $content_block . '</td></tr></table>';
            break;
        case 'b':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell media" style="height:24%;padding-bottom:' . $gap_small . 'px;">' . $media_block . '</td></tr><tr><td class="archive-preview-cell content" style="height:76%;padding-top:' . $gap_small . 'px;">' . $content_block . '</td></tr></table>';
            break;
        case 'c':
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell content" style="height:76%;padding-bottom:' . $gap_small . 'px;">' . $content_block . '</td></tr><tr><td class="archive-preview-cell media" style="height:24%;padding-top:' . $gap_small . 'px;">' . $media_block . '</td></tr></table>';
            break;
        default:
            $slide_table = '<table class="archive-preview-table"><tr><td class="archive-preview-cell content" style="height:100%;text-align:center;">' . $content_block . '</td></tr></table>';
            break;
    }

    $layout_class_map = [
        'a' => 'layout-a',
        'z' => 'layout-z',
        'y' => 'layout-y',
        'g' => 'layout-g',
        'h' => 'layout-h',
        'b' => 'layout-b',
        'c' => 'layout-c',
        'e' => 'layout-e',
        'f' => 'layout-f',
    ];
    $layout_class = $layout_class_map[$template] ?? 'layout-a';

    $font_title_css = "'" . addslashes($styles['font_title'] ?? 'Gabarito') . "', sans-serif";
    $font_text_css = "'" . addslashes($styles['font_text'] ?? 'Gabarito') . "', sans-serif";

    $page_style = sprintf(
        'width:%1$.2fpx;height:%2$.2fpx;padding:%3$.2fpx %4$.2fpx %5$.2fpx %6$.2fpx;background-color:%7$s;color:%8$s;font-family:%9$s;font-size:%10$.2fpt;--archive-highlight:%11$s;--archive-box:%12$s;--archive-font-title:%13$s;--archive-font-text:%14$s;',
        $page_width_px,
        $page_height_px,
        $padding_top_px,
        $padding_right_px,
        $padding_bottom_px,
        $padding_left_px,
        htmlspecialchars($styles['color_bg'] ?? '#ffffff', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($styles['color_text'] ?? '#2f2f2f', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($font_text_css, ENT_QUOTES, 'UTF-8'),
        $paragraph_font_pt,
        htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($styles['color_box'] ?? '#f4f6f8', ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($font_title_css, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($font_text_css, ENT_QUOTES, 'UTF-8')
    );

    $root_style = 'position:absolute;inset:0;display:flex;align-items:center;justify-content:center;box-sizing:border-box;overflow:hidden;';

    $container_width = 240;
    $stage_scale = max(0.12, min(0.22, $container_width / $total_width_px));

    $stage_style = sprintf(
        'position:absolute;top:50%%;left:50%%;width:%1$.2fpx;height:%2$.2fpx;transform-origin:top left;transform:translate(-50%%,-50%%) scale(%3$.4f);',
        $total_width_px,
        $total_height_px,
        $stage_scale
    );

    $wrapper_style = sprintf(
        'position:relative;width:%1$.2fpx;height:%2$.2fpx;',
        $total_width_px,
        $total_height_px
    );

    $html  = '<div class="archive-card-preview">';
    $html .= '<div class="archive-preview-root" style="' . $root_style . '">';
    $html .= '<div class="archive-preview-stage" style="' . $stage_style . '">';
    $html .= '<div class="archive-preview-wrapper" style="' . $wrapper_style . '">';
    $html .= '<div class="archive-preview-page ' . $layout_class . '" style="' . $page_style . '"';
    $html .= ' data-highlight="' . htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8') . '"';
    $html .= ' data-box="' . htmlspecialchars($styles['color_box'] ?? '#f4f6f8', ENT_QUOTES, 'UTF-8') . '">';
    $html .= $slide_table;
    $html .= '</div></div></div></div></div>';

    return $html;
}


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
    define('STYLE_PRESETS_FILE', __DIR__ . '/data/style_presets.json');
    define('RESOURCES_DIR', __DIR__ . '/recursos');

    // --- Load styles (FIRST THING TO DO) ---
    if (file_exists(STYLES_FILE)) {
        $decoded_styles = json_decode(file_get_contents(STYLES_FILE), true);
        $styles = inanna_collect_styles_from_input($decoded_styles, inanna_default_style_values());
    } else {
        $styles = inanna_default_style_values();
    }

    $style_presets = [];
    if (file_exists(STYLE_PRESETS_FILE)) {
        $decoded_presets = json_decode(file_get_contents(STYLE_PRESETS_FILE), true);
        if (is_array($decoded_presets)) {
            foreach ($decoded_presets as $preset_name => $preset_values) {
                $style_presets[$preset_name] = inanna_collect_styles_from_input($preset_values, $styles);
            }
            if (!empty($style_presets)) {
                ksort($style_presets, SORT_NATURAL | SORT_FLAG_CASE);
            }
        }
    }

    // --- Presentation Loading Logic ---
    $loaded_presentation_data = null;
    $current_edit_param = '';
    if (isset($_GET['edit'])) {
        error_log("DEBUG: Edit parameter received: " . $_GET['edit']);
        $filename = basename($_GET['edit']); // Sanitize filename
        $current_edit_param = $filename;
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
        $styles = array_merge($styles, inanna_collect_styles_from_input($loaded_presentation_data['styles'], $styles));
        error_log("DEBUG: Final styles after merge: " . print_r($styles, true));
    }

    if (!isset($styles['color_title'])) {
        $styles['color_title'] = $styles['color_h1'] ?? '#1b8eed';
    }

    if (isset($_SESSION['applied_styles_override']) && is_array($_SESSION['applied_styles_override'])) {
        $override_styles = inanna_collect_styles_from_input($_SESSION['applied_styles_override'], $styles);
        $styles = array_merge($styles, $override_styles);
        if ($loaded_presentation_data && isset($loaded_presentation_data['styles'])) {
            $loaded_presentation_data['styles'] = array_merge($loaded_presentation_data['styles'], $override_styles);
        }
        unset($_SESSION['applied_styles_override']);
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
        $_SESSION['config_message'] = 'Configuración actualizada.';
        header('Location: inanna.php?tab=Configuracion');
        exit;
    }

    $post_current_edit = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_current_edit_raw = $_POST['current_edit'] ?? '';
        if ($post_current_edit_raw !== '') {
            $post_current_edit = basename($post_current_edit_raw);
        }
    }

    // Handle style saving
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['load_style_preset'])) {
        $preset_to_load = $_POST['preset_to_load'] ?? '';
        if ($preset_to_load !== '' && isset($style_presets[$preset_to_load])) {
            $loaded_styles = inanna_collect_styles_from_input($style_presets[$preset_to_load], $styles);
            file_put_contents(STYLES_FILE, json_encode($loaded_styles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['style_message'] = 'Combinación "' . $preset_to_load . '" cargada.';
            unset($_SESSION['style_error']);
            $styles = array_merge($styles, $loaded_styles);
            $_SESSION['applied_styles_override'] = $styles;
        } else {
            $_SESSION['style_error'] = 'No se encontró la combinación seleccionada.';
            unset($_SESSION['style_message']);
        }
        inanna_redirect_with_tab('Estetica', $post_current_edit);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_styles'])) {
        $styles_to_save = inanna_collect_styles_from_input($_POST, $styles);
        $preset_name = trim($_POST['preset_name'] ?? '');
        file_put_contents(STYLES_FILE, json_encode($styles_to_save, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $styles = $styles_to_save;
        $messages = [];
        $messages[] = 'Estilos actualizados.';
        if ($preset_name !== '') {
            $style_presets[$preset_name] = $styles_to_save;
            ksort($style_presets, SORT_NATURAL | SORT_FLAG_CASE);
            file_put_contents(STYLE_PRESETS_FILE, json_encode($style_presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $messages[] = 'Combinación "' . $preset_name . '" guardada.';
        }
        $_SESSION['style_message'] = implode(' ', $messages);
        unset($_SESSION['style_error']);
        $_SESSION['applied_styles_override'] = $styles;
        inanna_redirect_with_tab('Estetica', $post_current_edit);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_style_preset'])) {
        $preset_to_delete = $_POST['preset_to_delete'] ?? '';
        if ($preset_to_delete !== '' && isset($style_presets[$preset_to_delete])) {
            unset($style_presets[$preset_to_delete]);
            if (!empty($style_presets)) {
                ksort($style_presets, SORT_NATURAL | SORT_FLAG_CASE);
            }
            file_put_contents(STYLE_PRESETS_FILE, json_encode($style_presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $_SESSION['style_message'] = 'Combinación eliminada.';
            unset($_SESSION['style_error']);
        } else {
            $_SESSION['style_error'] = 'No se encontró la combinación a eliminar.';
            unset($_SESSION['style_message']);
        }
        inanna_redirect_with_tab('Estetica', $post_current_edit);
    }

    // Handle resource upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['resource_file'])) {
        $file = $_FILES['resource_file'];
        if (!isset($file['name']) || $file['name'] === '') {
            $_SESSION['resource_error'] = 'No se seleccionó ningún archivo.';
        } elseif ($file['error'] === UPLOAD_ERR_OK) {
            if (!is_dir(RESOURCES_DIR)) { mkdir(RESOURCES_DIR, 0755, true); }
            $target_path = RESOURCES_DIR . '/' . basename($file['name']);
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $_SESSION['resource_message'] = 'Archivo subido con éxito.';
            } else {
                $_SESSION['resource_error'] = 'Error al mover el archivo subido.';
            }
        } else {
            $_SESSION['resource_error'] = 'Error al subir el archivo (' . $file['error'] . ').';
        }
        inanna_redirect_with_tab('Recursos', $post_current_edit);
    }

    // List resources
    $resources = [];
    if (is_dir(RESOURCES_DIR)) {
        $files = scandir(RESOURCES_DIR);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') { $resources[] = 'recursos/' . $file; }
        }
    }

    $presentations = [];
    $archive_dir = __DIR__ . '/data/archivo/';
    if (is_dir($archive_dir)) {
        $saved_files = glob($archive_dir . '*.xml') ?: [];
        $parsedown = null;
        foreach ($saved_files as $file_path) {
            $basename = basename($file_path);
            $xml_styles = [];
            $slide_title = '';
            $slide_summary = '';
            $first_slide_data = null;
            $xml = @simplexml_load_file($file_path);
            if ($xml) {
                if (isset($xml->styles)) {
                    foreach ($xml->styles->children() as $key => $value) {
                        $xml_styles[$key] = (string)$value;
                    }
                }
                if (isset($xml->slides->slide[0])) {
                    $first_slide = $xml->slides->slide[0];
                    $markdown = (string)$first_slide->markdown;
                     if ($parsedown === null) {
                         if (!class_exists('Parsedown')) {
                             $autoload = __DIR__ . '/vendor/autoload.php';
                             if (file_exists($autoload)) {
                                 require_once $autoload;
                             }
                         }
                         if (class_exists('Parsedown')) {
                             $parsedown = new Parsedown();
                         }
                     }
                    $markdown_html = $parsedown ? $parsedown->text($markdown) : htmlspecialchars($markdown);
                    [$title_candidate, $summary_candidate] = inanna_extract_slide_summary($markdown);
                    $slide_title = $title_candidate !== '' ? $title_candidate : 'Sin título';
                    $summary_source = $summary_candidate !== '' ? $summary_candidate : $markdown;
                    $summary_source = trim(preg_replace('/\s+/', ' ', (string)$summary_source));
                    $slide_summary = inanna_truncate_text($summary_source, 140);
                    $first_slide_data = [
                        'template' => (string)($first_slide->template ?? 'a') ?: 'a',
                        'image' => (string)($first_slide->image ?? ''),
                        'markdown_html' => $markdown_html,
                    ];
                }
            }
            if ($first_slide_data === null) {
                $first_slide_data = [
                    'template' => 'a',
                    'image' => '',
                    'markdown_html' => '<p style="opacity:0.55;">Sin contenido en la primera diapositiva.</p>',
                ];
            }

            $thumb_styles = inanna_collect_styles_from_input($xml_styles, inanna_default_style_values());
            if (!isset($thumb_styles['color_title'])) {
                $thumb_styles['color_title'] = $thumb_styles['color_h1'] ?? '#1b8eed';
            }
            $presentations[] = [
                'file' => $basename,
                'modified' => filemtime($file_path) ?: 0,
                'styles' => $thumb_styles,
                'title' => $slide_title !== '' ? $slide_title : 'Sin título',
                'summary' => $slide_summary,
                'first_slide' => $first_slide_data,
            ];
        }
        usort($presentations, function ($a, $b) {
            return ($b['modified'] <=> $a['modified']) ?: strcasecmp($a['file'], $b['file']);
        });
    }

    $style_session_message = $_SESSION['style_message'] ?? null;
    $style_session_error = $_SESSION['style_error'] ?? null;
    unset($_SESSION['style_message'], $_SESSION['style_error']);

    $resource_session_message = $_SESSION['resource_message'] ?? null;
    $resource_session_error = $_SESSION['resource_error'] ?? null;
    unset($_SESSION['resource_message'], $_SESSION['resource_error']);

    $config_session_message = $_SESSION['config_message'] ?? null;
    unset($_SESSION['config_message']);

    $valid_tabs = ['Texto', 'Estetica', 'Recursos', 'Composicion', 'Configuracion', 'Archivo'];
    $requested_tab = $_GET['tab'] ?? null;
    $active_tab = in_array($requested_tab, $valid_tabs, true) ? $requested_tab : 'Texto';
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Gabarito:wght@400;600;700&display=swap">
    <?php if (is_logged_in()): ?>
    <script src="js/composition.js" defer></script>
    <script src="js/resources.js" defer></script>
    <?php endif; ?>
    <style>
        /* --- Base & Typography --- */
        :root {
            --font-title: <?php echo $styles['font_title'] ?? 'Gabarito, sans-serif'; ?>;
            --font-text: <?php echo $styles['font_text'] ?? 'Noto Sans, sans-serif'; ?>;
            --interface-font: 'Gabarito', sans-serif;
            --color-title: <?php echo $styles['color_title'] ?? '#1b8eed'; ?>;
            --color-highlight: <?php echo $styles['color_highlight'] ?? '#ea2f28'; ?>;
            --color-text: <?php echo $styles['color_text'] ?? '#2f2f2f'; ?>;
            --color-bg: <?php echo $styles['color_bg'] ?? '#ffffff'; ?>;
            --color-box: <?php echo $styles['color_box'] ?? '#f4f6f8'; ?>;
        }
        body { 
            font-family: var(--interface-font);
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
            font-family: var(--interface-font);
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
            font-family: var(--interface-font);
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
            font-family: var(--interface-font);
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
        .success { color: var(--color-title); background-color: rgba(27, 139, 237, 0.1); padding: 10px; border-radius: 8px; }
        .template-option { cursor: pointer; border: 2px solid transparent; border-radius: 8px; transition: all 0.2s ease; padding: 5px; }
        .template-option:hover { border-color: #ccc; }
        .template-option.selected { border-color: var(--color-title); box-shadow: 0 0 10px rgba(27, 139, 237, 0.3); }
        #slide-preview { border-radius: 8px; background-color: #fafafa; }
        .resources-gallery img { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .resources-gallery img:hover { transform: scale(1.05); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .styles-actions { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-top: 20px; padding: 15px; background-color: var(--color_box); border-radius: 10px; border: 1px solid rgba(0,0,0,0.05); }
        .styles-actions-primary { display: flex; flex-direction: column; gap: 6px; }
        .styles-actions small { color: #555; }
        .style-presets-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
        .style-preset-card { border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 16px; background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(245,248,255,0.95)); box-shadow: 0 6px 20px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 12px; }
        .style-preset-header { display: flex; flex-direction: column; gap: 4px; }
        .style-preset-header strong { font-size: 1.1rem; color: var(--color-title); }
        .style-preset-header span { font-size: 0.85rem; color: #555; }
        .style-preset-swatches { display: flex; flex-wrap: wrap; gap: 8px; }
        .style-preset-swatch { display: flex; align-items: center; gap: 6px; padding: 6px 10px; background: rgba(255,255,255,0.7); border-radius: 8px; border: 1px solid rgba(0,0,0,0.05); }
        .style-preset-swatch-color { width: 22px; height: 22px; border-radius: 50%; border: 1px solid rgba(0,0,0,0.1); display: inline-block; }
        .style-preset-swatch-label { font-size: 0.8rem; color: #444; }
        .style-preset-actions { display: flex; gap: 10px; }
        .style-preset-actions form { flex: 1; }
        .style-preset-actions button { width: 100%; }
        button.secondary, input[type="submit"].secondary { background-color: #f2f2f2; color: #333; border: 1px solid rgba(0,0,0,0.08); }
        button.secondary:hover, input[type="submit"].secondary:hover { background-color: #e0e0e0; color: #111; }
        .archive-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 18px; }
        .archive-card { border: 1px solid rgba(0,0,0,0.08); border-radius: 16px; padding: 18px; background: #fff; box-shadow: 0 12px 30px rgba(0,0,0,0.06); display: grid; grid-template-columns: minmax(240px, 260px) 1fr; grid-template-rows: auto auto; grid-template-areas: "thumb meta" "thumb actions"; gap: 18px; align-items: flex-start; }
        .archive-card-thumb { position: relative; border-radius: 14px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08); background: rgba(0,0,0,0.02); width: 100%; display: flex; flex-direction: column; grid-area: thumb; }
        .archive-card-preview { position: relative; width: 100%; padding-bottom: 70.714%; border-radius: 12px; border: 1px solid rgba(0,0,0,0.1); background: rgba(255,255,255,0.6); overflow: hidden; }
        .archive-card-preview > * { position: absolute; inset: 0; }
        .archive-preview-root { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; box-sizing: border-box; overflow: hidden; }
        .archive-preview-stage { position: relative; display: flex; align-items: flex-start; justify-content: flex-start; transform-origin: top left; }
        .archive-preview-wrapper { position: relative; }
        .archive-preview-page { box-sizing: border-box; position: relative; overflow: visible; line-height: 1.45; font-family: var(--archive-font-text, 'Gabarito', sans-serif); }
        .archive-preview-page.layout-a .archive-preview-table { height: 100%; }
        .archive-preview-page h1, .archive-preview-page h2, .archive-preview-page h3, .archive-preview-page h4, .archive-preview-page h5, .archive-preview-page h6 { margin: 0; line-height: 1.15; font-weight: 700; font-family: var(--archive-font-title, 'Gabarito', sans-serif); }
        .archive-preview-page blockquote { margin: 0.8em 0; padding: 1em; border-left: 5px solid var(--archive-highlight, #ea2f28); background-color: var(--archive-box, #f4f6f8); }
        .archive-preview-table { width: 100%; height: 100%; border-collapse: collapse; table-layout: fixed; }
        .archive-preview-cell { padding: 0; vertical-align: middle; height: 100%; }
        .archive-preview-content { margin: 0; height: 100%; display: table; width: 100%; box-sizing: border-box; padding: 0 7.56px 0 26.31px; }
        .archive-preview-content-inner { display: table-cell; vertical-align: middle; height: 100%; }
        .archive-preview-content.centered .archive-preview-content-inner > div { text-align: center; }
        .archive-preview-content ul, .archive-preview-content ol { margin: 0.35em 0; padding-left: 0; list-style: none; text-align: left; }
        .archive-preview-content li { position: relative; padding-left: 1.3em; }
        .archive-preview-content ul li::before { content: '•'; position: absolute; left: 0; top: 0.1em; font-weight: 700; color: var(--archive-highlight, #ea2f28); font-family: var(--archive-font-title, 'Gabarito', sans-serif); }
        .archive-preview-content ol { counter-reset: archiveOrdered; }
        .archive-preview-content ol li { counter-increment: archiveOrdered; }
        .archive-preview-content ol li::before { content: counter(archiveOrdered) '.'; position: absolute; left: 0; top: 0.05em; font-weight: 700; color: var(--archive-highlight, #ea2f28); font-family: var(--archive-font-title, 'Gabarito', sans-serif); }
        .archive-preview-media { width: 100%; height: 100%; border-radius: 18px; background-position: center; background-repeat: no-repeat; background-size: cover; display: flex; align-items: center; justify-content: center; text-align: center; }
        .archive-preview-media.placeholder { border: 2px dashed rgba(0,0,0,0.15); color: #666; background-color: rgba(255,255,255,0.7); font-weight: 600; font-size: 13.92pt; padding: 26.31px; }
        .archive-preview-media.square { width: 80%; aspect-ratio: 1 / 1; margin: 0 auto; }
        .archive-preview-square-wrapper { display: flex; align-items: center; justify-content: center; height: 100%; width: 100%; }
        .archive-card-ping { position: absolute; top: 14px; right: 16px; width: 14px; height: 14px; border-radius: 50%; box-shadow: 0 0 0 6px rgba(255,255,255,0.65); z-index: 2; }
        .archive-card-meta { display: flex; flex-direction: column; gap: 6px; grid-area: meta; min-width: 0; }
        .archive-card-name { font-weight: 600; color: #222; word-break: break-word; }
        .archive-card-date { font-size: 0.8rem; color: #555; }
        .archive-card-summary { font-size: 0.85rem; color: #555; line-height: 1.4; }
        .archive-card-actions { display: flex; gap: 10px; grid-area: actions; align-self: end; flex-wrap: wrap; }
        .archive-card-actions form { flex: 1; }
        .archive-card-actions button { width: 100%; }
        .archive-card-actions button.secondary { background-color: rgba(0,0,0,0.05); color: #333; border: 1px solid rgba(0,0,0,0.08); }
        .archive-card-actions button.secondary:hover { background-color: rgba(0,0,0,0.12); }
        .archive-empty { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 60px 20px; border: 2px dashed rgba(0,0,0,0.1); border-radius: 14px; background-color: rgba(255,255,255,0.7); }
        .archive-empty p { margin: 0; color: #555; font-weight: 500; }
        .archive-empty-illustration { width: 120px; height: 80px; border-radius: 12px; background: linear-gradient(135deg, rgba(27,142,237,0.15), rgba(234,47,40,0.12)); position: relative; overflow: hidden; }
        .archive-empty-illustration span { position: absolute; inset: 15px; border-radius: 10px; background: rgba(255,255,255,0.8); border: 1px dashed rgba(27,142,237,0.25); }

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
            <div class="tab-link<?php echo $active_tab === 'Texto' ? ' active' : ''; ?>" onclick="openTab(event, 'Texto')">Texto</div>
            <div class="tab-link<?php echo $active_tab === 'Estetica' ? ' active' : ''; ?>" onclick="openTab(event, 'Estetica')">Estética</div>
            <div class="tab-link<?php echo $active_tab === 'Recursos' ? ' active' : ''; ?>" onclick="openTab(event, 'Recursos')">Recursos</div>
            <div class="tab-link<?php echo $active_tab === 'Composicion' ? ' active' : ''; ?>" onclick="openTab(event, 'Composicion')">Composición</div>
            <div class="tab-link<?php echo $active_tab === 'Configuracion' ? ' active' : ''; ?>" onclick="openTab(event, 'Configuracion')">Configuración</div>
            <div class="tab-link<?php echo $active_tab === 'Archivo' ? ' active' : ''; ?>" onclick="openTab(event, 'Archivo')">Archivo</div>
        </div>

        <div id="Texto" class="tab-content<?php echo $active_tab === 'Texto' ? ' active' : ''; ?>">
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
            <div style="margin-top: 12px; display: flex; justify-content: flex-end;">
                <button type="button" id="save-presentation-text" class="save-presentation-btn" style="margin-left: 10px;">Guardar</button>
            </div>
        </div>

        <div id="Estetica" class="tab-content<?php echo $active_tab === 'Estetica' ? ' active' : ''; ?>">
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

            <?php if ($style_session_message): ?>
                <p class="success"><?php echo htmlspecialchars($style_session_message); ?></p>
            <?php endif; ?>
            <?php if ($style_session_error): ?>
                <p class="error"><?php echo htmlspecialchars($style_session_error); ?></p>
            <?php endif; ?>

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
                <?php if ($current_edit_param !== ''): ?>
                    <input type="hidden" name="current_edit" value="<?php echo htmlspecialchars($current_edit_param, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
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
                <div class="styles-actions">
                    <div class="styles-actions-primary">
                        <button type="submit" name="save_styles">Guardar estilos</button>
                        <small>Introduce un nombre para guardar la combinación como preset (opcional).</small>
                    </div>
                    <input type="text" name="preset_name" placeholder="Nombre de la combinación" style="min-width: 240px;">
                </div>
            </form>

            <?php if (!empty($style_presets)): ?>
                <hr style="margin: 30px 0;">
                <h4>Combinaciones guardadas</h4>
                <div class="style-presets-grid">
                    <?php foreach ($style_presets as $preset_name => $preset_values): ?>
                        <?php
                            $preview_colors = [
                                'Título' => $preset_values['color_h1'] ?? '#1b8eed',
                                'Destacado' => $preset_values['color_highlight'] ?? '#ea2f28',
                                'Texto' => $preset_values['color_text'] ?? '#2f2f2f',
                                'Fondo' => $preset_values['color_bg'] ?? '#ffffff',
                                'Caja' => $preset_values['color_box'] ?? '#f4f6f8',
                            ];
                        ?>
                        <div class="style-preset-card">
                            <div class="style-preset-header">
                                <strong><?php echo htmlspecialchars($preset_name); ?></strong>
                                <span><?php echo htmlspecialchars($preset_values['font_title'] ?? 'Gabarito'); ?> / <?php echo htmlspecialchars($preset_values['font_text'] ?? 'Gabarito'); ?></span>
                            </div>
                            <div class="style-preset-swatches">
                                <?php foreach ($preview_colors as $label => $hex): ?>
                                    <div class="style-preset-swatch">
                                        <span class="style-preset-swatch-color" style="background-color: <?php echo htmlspecialchars($hex); ?>"></span>
                                        <span class="style-preset-swatch-label"><?php echo htmlspecialchars($label); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="style-preset-actions">
                                <form method="POST" action="inanna.php">
                                    <?php if ($current_edit_param !== ''): ?>
                                        <input type="hidden" name="current_edit" value="<?php echo htmlspecialchars($current_edit_param, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <input type="hidden" name="preset_to_load" value="<?php echo htmlspecialchars($preset_name); ?>">
                                    <button type="submit" name="load_style_preset">Aplicar</button>
                                </form>
                                <form method="POST" action="inanna.php" onsubmit="return confirm('¿Seguro que quieres borrar esta combinación?');">
                                    <?php if ($current_edit_param !== ''): ?>
                                        <input type="hidden" name="current_edit" value="<?php echo htmlspecialchars($current_edit_param, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php endif; ?>
                                    <input type="hidden" name="preset_to_delete" value="<?php echo htmlspecialchars($preset_name); ?>">
                                    <button type="submit" name="delete_style_preset" class="secondary">Borrar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="Recursos" class="tab-content<?php echo $active_tab === 'Recursos' ? ' active' : ''; ?>">
            <h3>Recursos Multimedia</h3>
            <p>Sube imágenes y vídeos para tu presentación.</p>
            <form method="POST" action="inanna.php" enctype="multipart/form-data">
                <?php if ($current_edit_param !== ''): ?>
                    <input type="hidden" name="current_edit" value="<?php echo htmlspecialchars($current_edit_param, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <input type="file" name="resource_file" required>
                <button type="submit">Subir Archivo</button>
            </form>
            <?php if ($resource_session_message): ?><p class="success"><?php echo htmlspecialchars($resource_session_message); ?></p><?php endif; ?>
            <?php if ($resource_session_error): ?><p class="error"><?php echo htmlspecialchars($resource_session_error); ?></p><?php endif; ?>
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
                            <div style="display:flex; justify-content:space-between; gap:6px;">
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
                                <canvas id="resource-edit-canvas" style="width:100%; border:1px solid #ccc; background:#ffffff;"></canvas>
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

        <div id="Composicion" class="tab-content<?php echo $active_tab === 'Composicion' ? ' active' : ''; ?>">
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
                                            <button type="button" id="save-presentation" class="save-presentation-btn" style="margin-left: 10px;">Guardar</button>
                                        </form>                </div>
            </div>
        </div>

        <div id="Configuracion" class="tab-content<?php echo $active_tab === 'Configuracion' ? ' active' : ''; ?>">
            <h3>Configuración General</h3>
            <?php if ($config_session_message): ?><p class="success"><?php echo htmlspecialchars($config_session_message); ?></p><?php endif; ?>
            <form method="POST" action="inanna.php">
                <label for="google_fonts_api_key">Clave de API de Google Fonts</label><br>
                <input type="text" id="google_fonts_api_key" name="google_fonts_api_key" value="<?php echo htmlspecialchars($config['google_fonts_api_key'] ?? ''); ?>" style="width: 50%; margin-top: 5px;">
                <p style="font-size: 0.8rem; color: #666;">Necesaria para cargar la lista de todas las fuentes de Google en la pestaña de "Estética".</p>
                <button type="submit" name="save_config">Guardar Configuración</button>
            </form>
        </div>

        <div id="Archivo" class="tab-content<?php echo $active_tab === 'Archivo' ? ' active' : ''; ?>">
            <h3>Presentaciones guardadas</h3>
            <?php if (empty($presentations)): ?>
                <div class="archive-empty">
                    <div class="archive-empty-illustration">
                        <span></span>
                    </div>
                    <p>No hay presentaciones guardadas todavía.</p>
                </div>
            <?php else: ?>
                <div class="archive-grid">
                    <?php foreach ($presentations as $presentation): ?>
                        <?php
                            $pStyles = $presentation['styles'];
                            $thumbAccent = htmlspecialchars($pStyles['color_highlight'] ?? '#ea2f28', ENT_QUOTES);
                        ?>
                        <article class="archive-card">
                            <div class="archive-card-thumb" style="border-color: <?php echo $thumbAccent; ?>;">
                                <span class="archive-card-ping" style="background-color: <?php echo $thumbAccent; ?>;"></span>
                                <?php echo inanna_render_slide_preview($presentation['first_slide'], $presentation['styles']); ?>
                            </div>
                            <div class="archive-card-meta">
                                <span class="archive-card-name"><?php echo htmlspecialchars($presentation['file']); ?></span>
                                <?php if (!empty($presentation['summary'])): ?>
                                    <span class="archive-card-summary"><?php echo htmlspecialchars($presentation['summary']); ?></span>
                                <?php endif; ?>
                                <span class="archive-card-date">Actualizada el <?php echo date('d/m/Y H:i', $presentation['modified']); ?></span>
                            </div>
                            <div class="archive-card-actions">
                                <form method="GET" action="inanna.php">
                                    <input type="hidden" name="edit" value="<?php echo htmlspecialchars($presentation['file']); ?>">
                                    <button type="submit">Editar</button>
                                </form>
                                <button type="button" class="secondary" onclick="deletePresentation('<?php echo htmlspecialchars($presentation['file'], ENT_QUOTES); ?>')">Borrar</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
        if (tabcontent[i].classList) {
            tabcontent[i].classList.remove("active");
        }
    }
    tablinks = document.getElementsByClassName("tab-link");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    var targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.style.display = "block";
        if (targetTab.classList) {
            targetTab.classList.add("active");
        }
    }
    if (evt && evt.currentTarget) {
        evt.currentTarget.className += " active";
    }
    if (typeof history.replaceState === 'function' && typeof URL === 'function') {
        var url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        history.replaceState({}, '', url);
    }
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

<footer style="text-align: center; font-family: inherit; font-size: 0.9em; color: #555; padding: 20px 0;">
    <img src="maximalista.png" alt="Logo Maximalista" style="height: 1.5em; margin-bottom: 0.5em;">
    <br>
    Inanna es software libre bajo licencia <a href="https://interoperable-europe.ec.europa.eu/collection/eupl/eupl-text-eupl-12" target="_blank">EUPL v1.2</a>
    <br>
    Creado por <a href="https://maximalista.coop" target="_blank">Compañía Maximalista S.Coop.</a>
</footer>

</body>
</html>
