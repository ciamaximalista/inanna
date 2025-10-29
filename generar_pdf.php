<?php
require_once __DIR__ . '/auth.php';

// Only logged-in users can generate PDFs
if (!is_logged_in()) {
    die('Acceso denegado. Debes iniciar sesión.');
}

file_put_contents(__DIR__ . '/data/inanna_post.log', print_r($_POST, true));

require_once __DIR__ . '/vendor/autoload.php';

function extract_primary_font(string $fontValue, string $fallback = 'Arial'): string {
    $parts = explode(',', $fontValue);
    $primary = trim($parts[0] ?? '');
    $primary = trim($primary, " \"'");
    return $primary !== '' ? $primary : $fallback;
}

function load_google_fonts_index(): array {
    $fontsFile = __DIR__ . '/data/google_fonts.json';
    if (!file_exists($fontsFile)) {
        return [];
    }

    $json = json_decode(file_get_contents($fontsFile), true);
    if (!isset($json['items']) || !is_array($json['items'])) {
        return [];
    }

    $index = [];
    foreach ($json['items'] as $item) {
        if (!isset($item['family'])) {
            continue;
        }
        $index[strtolower($item['family'])] = $item;
    }

    return $index;
}

function determine_font_format(string $extension): string {
    return match (strtolower($extension)) {
        'woff2' => 'woff2',
        'woff' => 'woff',
        'otf' => 'opentype',
        default => 'truetype',
    };
}

function try_generate_pdf_with_wkhtmltopdf(string $html, string $downloadName, float $pageWidthMm, float $pageHeightMm): bool {
    $binary = trim((string)@shell_exec('command -v wkhtmltopdf'));
    if ($binary === '') {
        return false;
    }

    $tempDir = sys_get_temp_dir();
    $htmlPathBase = tempnam($tempDir, 'inanna_html_');
    if ($htmlPathBase === false) {
        error_log('wkhtmltopdf: no se pudo crear el archivo temporal HTML.');
        return false;
    }

    $htmlPath = $htmlPathBase . '.html';
    if (!@rename($htmlPathBase, $htmlPath)) {
        @unlink($htmlPathBase);
        error_log('wkhtmltopdf: no se pudo preparar el archivo temporal HTML.');
        return false;
    }

    $pdfPathBase = tempnam($tempDir, 'inanna_pdf_');
    if ($pdfPathBase === false) {
        @unlink($htmlPath);
        error_log('wkhtmltopdf: no se pudo crear el archivo temporal PDF.');
        return false;
    }

    $pdfPath = $pdfPathBase . '.pdf';
    if (!@rename($pdfPathBase, $pdfPath)) {
        @unlink($htmlPath);
        @unlink($pdfPathBase);
        error_log('wkhtmltopdf: no se pudo preparar el archivo temporal PDF.');
        return false;
    }

    $cleanup = static function () use ($htmlPath, $pdfPath): void {
        if (is_file($htmlPath)) {
            @unlink($htmlPath);
        }
        if (is_file($pdfPath)) {
            @unlink($pdfPath);
        }
    };

    if (file_put_contents($htmlPath, $html) === false) {
        $cleanup();
        error_log('wkhtmltopdf: fallo al escribir el HTML temporal.');
        return false;
    }

    if (is_file($pdfPath)) {
        @unlink($pdfPath);
    }

    $pageWidthArg = number_format($pageWidthMm, 2, '.', '') . 'mm';
    $pageHeightArg = number_format($pageHeightMm, 2, '.', '') . 'mm';
    $viewportWidthPx = (int)round(($pageWidthMm / 25.4) * 96);
    $viewportHeightPx = (int)round(($pageHeightMm / 25.4) * 96);

    $command = escapeshellarg($binary)
        . ' --enable-local-file-access'
        . ' --page-width ' . escapeshellarg($pageWidthArg)
        . ' --page-height ' . escapeshellarg($pageHeightArg)
        . ' --margin-top 0'
        . ' --margin-right 0'
        . ' --margin-bottom 0'
        . ' --margin-left 0'
        . ' --disable-smart-shrinking'
        . ' --viewport-size ' . $viewportWidthPx . 'x' . $viewportHeightPx
        . ' ' . escapeshellarg($htmlPath)
        . ' ' . escapeshellarg($pdfPath);

    $output = [];
    $status = 0;
    exec($command . ' 2>&1', $output, $status);

    if ($status === 0 && is_file($pdfPath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . (string)filesize($pdfPath));
        readfile($pdfPath);
        $cleanup();
        return true;
    }

    error_log('wkhtmltopdf: el comando falló (' . $status . '): ' . implode("\n", $output));
    $cleanup();
    return false;
}

function ensure_font_cached(string $fontFamily, string $variantKey, string $url, string $cacheDir): ?string {
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }

    $parsedPath = parse_url($url, PHP_URL_PATH) ?? '';
    $extension = pathinfo($parsedPath, PATHINFO_EXTENSION) ?: 'ttf';
    $sanitizedFamily = preg_replace('/[^a-z0-9]+/i', '-', strtolower($fontFamily));
    $filename = $sanitizedFamily . '-' . strtolower($variantKey) . '.' . $extension;
    $targetPath = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!file_exists($targetPath)) {
        $fontBinary = @file_get_contents($url);
        if ($fontBinary === false) {
            return null;
        }
        file_put_contents($targetPath, $fontBinary);
    }

    $relativePath = str_replace('\\', '/', trim(substr($targetPath, strlen(__DIR__)), DIRECTORY_SEPARATOR));
    return $relativePath;
}

function build_font_face_css(string $fontFamily, array $requestedWeights, array $fontsIndex, string $cacheDir): array {
    $result = [
        'css' => '',
        'weights' => [],
    ];

    $key = strtolower($fontFamily);
    if (!isset($fontsIndex[$key]['files']) || !is_array($fontsIndex[$key]['files'])) {
        return $result;
    }

    $files = $fontsIndex[$key]['files'];
    foreach (array_unique($requestedWeights) as $weight) {
        $candidates = [];
        if ($weight === 400) {
            $candidates = ['regular', '400'];
        } else {
            $candidates[] = (string)$weight;
        }

        if ($weight === 700) {
            $candidates[] = '600';
            $candidates[] = '500';
        }

        $candidates[] = 'regular';

        $chosenVariant = null;
        foreach ($candidates as $candidate) {
            if (isset($files[$candidate])) {
                $chosenVariant = $candidate;
                break;
            }
        }

        if ($chosenVariant === null) {
            continue;
        }

        $fontUrl = $files[$chosenVariant];
        $relativePath = ensure_font_cached($fontFamily, $chosenVariant, $fontUrl, $cacheDir);
        if (!$relativePath) {
            continue;
        }

        $actualWeight = $chosenVariant === 'regular' ? 400 : (int)preg_replace('/[^0-9]/', '', $chosenVariant);
        if ($actualWeight === 0) {
            $actualWeight = $weight;
        }

        $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
        $format = determine_font_format($extension);

        $result['css'] .= "@font-face { font-family: '" . addslashes($fontFamily) . "'; font-style: normal; font-weight: {$actualWeight}; src: url('" . $relativePath . "') format('{$format}'); }\n";
        $result['weights'][$weight] = $actualWeight;
    }

    return $result;
}

if (isset($_POST['presentation_data'])) {
    $data = json_decode($_POST['presentation_data'], true);
    if (!$data) {
        die('Error: Datos de presentación inválidos.');
    }

    $slides = $data['slides'];
    $styles = $data['styles'];
    $parsedown = new Parsedown();

    $titleFont = extract_primary_font($styles['font_title'] ?? 'Gabarito', 'Gabarito');
    $textFont = extract_primary_font($styles['font_text'] ?? 'Noto Sans', 'Noto Sans');

    $fontsIndex = load_google_fonts_index();
    $fontsCacheDir = __DIR__ . '/data/fonts';
    $fontRequests = [];
    $fontRequests[$titleFont] = [400, 700];
    if (!isset($fontRequests[$textFont])) {
        $fontRequests[$textFont] = [400, 700];
    } else {
        $fontRequests[$textFont] = array_unique(array_merge($fontRequests[$textFont], [400, 700]));
    }

    $embeddedFontsCss = '';
    $registeredWeights = [];
    foreach ($fontRequests as $fontName => $weights) {
        $result = build_font_face_css($fontName, $weights, $fontsIndex, $fontsCacheDir);
        if (!empty($result['css'])) {
            $embeddedFontsCss .= $result['css'];
        }
        $registeredWeights[$fontName] = $result['weights'] ?? [];
    }

    $titleWeightRegular = $registeredWeights[$titleFont][400] ?? 400;
    $titleWeightBold = $registeredWeights[$titleFont][700] ?? ($registeredWeights[$titleFont][600] ?? 700);
    $textWeightRegular = $registeredWeights[$textFont][400] ?? 400;
    $textWeightBold = $registeredWeights[$textFont][700] ?? ($registeredWeights[$textFont][600] ?? 700);

    // --- Build the HTML for the PDF ---
    $scaleFactor = 1.16;
    $pageWidthMm = 297;
    $pageHeightMm = 210;

    $slidePadding = [
        'top' => 8,
        'right' => 1,
        'bottom' => 1,
        'left' => 10,
    ];

    $contentPaddingLeftMm = 6 * $scaleFactor;
    $contentPaddingRightMm = 2;
    $gapLargeMm = 12 * $scaleFactor;
    $gapSmallMm = 6 * $scaleFactor;
    $paragraphFontPt = 20 * $scaleFactor;
    $h1FontPt = 44 * $scaleFactor;
    $h2FontPt = 36 * $scaleFactor;
    $h3FontPt = 30 * $scaleFactor;
    $placeholderFontPt = 12 * $scaleFactor;
    $extraRightMm = 85;
    $extraBottomMm = 60;
    $shiftRightMm = 2.5;

    $styleParts = [
        '@page { size: ' . $pageWidthMm . 'mm ' . $pageHeightMm . 'mm; margin: 0; }',
        $embeddedFontsCss,
        'html, body { width: ' . $pageWidthMm . 'mm; height: ' . $pageHeightMm . 'mm; margin: 0; padding: 0; font-size: 20pt; line-height: 1.45; }',
        '.slide-wrapper { width: ' . $pageWidthMm . 'mm; height: ' . $pageHeightMm . 'mm; position: relative; overflow: visible; page-break-inside: avoid; }',
        '.slide-page { width: ' . $pageWidthMm . 'mm; height: ' . $pageHeightMm . 'mm; padding: ' . $slidePadding['top'] . 'mm ' . $slidePadding['right'] . 'mm ' . $slidePadding['bottom'] . 'mm ' . $slidePadding['left'] . 'mm; box-sizing: border-box; background-color: ' . $styles['color_bg'] . '; color: ' . $styles['color_text'] . '; font-family: "' . addslashes($textFont) . '", sans-serif; font-weight: ' . $textWeightRegular . '; overflow: visible; }',
        '.slide-table { width: calc(100% + ' . $extraRightMm . 'mm); height: calc(100% + ' . $extraBottomMm . 'mm); margin: 0 -' . $extraRightMm . 'mm -' . $extraBottomMm . 'mm 0; border-collapse: collapse; table-layout: fixed; transform: translateX(' . $shiftRightMm . 'mm); }',
        '.slide-cell { padding: 0; vertical-align: middle; }',
        '.slide-content { margin: 0; height: 100%; padding: 0 ' . $contentPaddingRightMm . 'mm 0 ' . $contentPaddingLeftMm . 'mm; display: table; width: 100%; }',
        '.slide-content-inner { display: table-cell; vertical-align: middle; height: 100%; }',
        '.slide-content > * { page-break-inside: avoid; break-inside: avoid; margin: 0.35em 0; }',
        '.slide-content > *:first-child { margin-top: 0; }',
        '.slide-content > *:last-child { margin-bottom: 0; }',
        '.slide-content.centered { text-align: center; }',
        '.slide-content ul, .slide-content ol { margin: 0.35em 0; padding-left: 0; list-style: none; text-align: left; width: 100%; }',
        '.slide-content li { position: relative; padding-left: 1.3em; font-size: ' . $paragraphFontPt . 'pt; }',
        '.slide-content ul li::before { content: "\\2022"; position: absolute; left: 0; top: 0.1em; font-family: "' . addslashes($titleFont) . '", sans-serif; color: ' . $styles['color_highlight'] . '; font-weight: ' . $titleWeightBold . '; }',
        '.slide-content ol { counter-reset: ordered; }',
        '.slide-content ol li { counter-increment: ordered; }',
        '.slide-content ol li::before { content: counter(ordered) "."; position: absolute; left: 0; top: 0.05em; font-family: "' . addslashes($titleFont) . '", sans-serif; color: ' . $styles['color_highlight'] . '; font-weight: ' . $titleWeightBold . '; }',
        '.slide-media { width: 100%; height: 100%; border-radius: 18px; overflow: hidden; background-color: ' . $styles['color_box'] . '; background-size: contain; background-position: center; background-repeat: no-repeat; }',
        '.slide-media.placeholder { border: 2px dashed #d0d0d0; color: #777; font-size: ' . $placeholderFontPt . 'pt; text-align: center; padding: ' . (6 * $scaleFactor) . 'mm; }',
        '.slide-media.placeholder span { display: block; }',
        '.slide-media.has-image { color: transparent; }',
        '.slide-square-cell { display: flex; align-items: center; justify-content: center; height: 100%; width: 100%; }',
        '.slide-media.square { width: 90mm; height: 90mm; max-width: 100%; border-radius: 18px; margin-left: auto; }',
        '.slide-media.square.placeholder { display: flex; align-items: center; justify-content: center; }',
        '.slide-page h1, .slide-page h2, .slide-page h3, .slide-page h4, .slide-page h5, .slide-page h6 { font-family: "' . addslashes($titleFont) . '", sans-serif; font-weight: ' . $titleWeightBold . '; margin: 0; line-height: 1.15; page-break-before: avoid; break-before: avoid; page-break-after: avoid; break-after: avoid; }',
        '.slide-page h1 { color: ' . $styles['color_h1'] . '; margin-bottom: 0.2em; font-size: ' . $h1FontPt . 'pt; }',
        '.slide-page h2 { color: ' . $styles['color_h2'] . '; font-size: ' . $h2FontPt . 'pt; }',
        '.slide-page h3 { color: ' . $styles['color_h3'] . '; font-size: ' . $h3FontPt . 'pt; }',
        '.slide-page p { margin: 0.35em 0; font-weight: ' . $textWeightRegular . '; font-size: ' . $paragraphFontPt . 'pt; }',
        '.slide-page a { color: ' . $styles['color_highlight'] . '; text-decoration: none; }',
        '.slide-page strong, .slide-page b { color: ' . $styles['color_highlight'] . '; font-weight: ' . $textWeightBold . '; }',
        '.slide-page blockquote { background-color: ' . $styles['color_box'] . '; padding: 1em; border-left: 5px solid ' . $styles['color_highlight'] . '; margin: 0.8em 0; text-align: left; font-size: ' . $paragraphFontPt . 'pt; }',
    ];

    $baseHrefPath = realpath(__DIR__) ?: __DIR__;
    $baseHref = 'file://' . rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $baseHrefPath), '/\\') . '/';
    $finalHtml = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><base href="' . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . '"><style>' . implode('', $styleParts) . '</style></head><body>';

    $layoutClassMap = [
        'a' => 'layout-a',
        'z' => 'layout-z',
        'y' => 'layout-y',
        'g' => 'layout-g',
        'h' => 'layout-h',
        'b' => 'layout-b',
        'c' => 'layout-c',
        'd' => 'layout-d',
        'i' => 'layout-i',
        'e' => 'layout-e',
        'f' => 'layout-f',
        'fullscreen' => 'layout-fullscreen',
    ];

    $total_slides = count($slides);
    foreach ($slides as $index => $slide) {
        $slideHtmlContent = $parsedown->text($slide['markdown']);
        $template = $slide['template'] ?? 'a';
        $imageSrc = $slide['image'] ?? null;

        $is_last_slide = ($index === $total_slides - 1);
        $page_break_style = $is_last_slide ? '' : 'page-break-after: always;';

        $layoutClass = $layoutClassMap[$template] ?? 'layout-a';

        $contentClass = 'slide-content';
        if ($template === 'a') {
            $contentClass .= ' centered';
        }
        $contentInner = '<div>' . $slideHtmlContent . '</div>';
        $contentBlock = '<div class="' . $contentClass . '"><div class="slide-content-inner">' . $contentInner . '</div></div>';

        $requiresSquareMedia = in_array($template, ['g', 'h'], true);
        $mediaBlock = '';

        if ($template !== 'a') {
            $mediaClass = 'slide-media' . ($requiresSquareMedia ? ' square' : '');
            $mediaBlock = '<div class="' . $mediaClass . ' placeholder"><span>Añade una imagen</span></div>';
            if (!empty($imageSrc)) {
                $backgroundUrl = null;
                if (filter_var($imageSrc, FILTER_VALIDATE_URL)) {
                    $backgroundUrl = $imageSrc;
                } else {
                    $relativeImage = ltrim($imageSrc, '/');
                    $imagePath = realpath(__DIR__ . '/' . $relativeImage);
                    if ($imagePath && strpos($imagePath, __DIR__) === 0 && is_file($imagePath)) {
                        $mime = mime_content_type($imagePath) ?: 'image/jpeg';
                        $imageData = base64_encode(@file_get_contents($imagePath));
                        if ($imageData !== false) {
                            $backgroundUrl = 'data:' . $mime . ';base64,' . $imageData;
                        }
                    }
                    if ($backgroundUrl === null) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $scriptName = $_SERVER['PHP_SELF'] ?? '';
                        $basePath = rtrim(dirname($scriptName), '/');
                        $backgroundUrl = $protocol . $host . ($basePath ? '/' . ltrim($basePath, '/') : '') . '/' . $relativeImage;
                    }
                }

                if ($backgroundUrl !== null) {
                    $safeBackground = htmlspecialchars($backgroundUrl, ENT_QUOTES, 'UTF-8');
                    $mediaBlock = '<div class="' . $mediaClass . ' has-image" style="background-image:url(' . $safeBackground . ');"></div>';
                } else {
                    $mediaBlock = '<div class="' . $mediaClass . ' placeholder"><span>Imagen no disponible</span></div>';
                }
            }
        }

        if ($layoutClass === 'layout-a') {
            $slideTable = '<table class="slide-table"><tr>'
                . '<td class="slide-cell slide-content-cell" style="height:100%; text-align:center; vertical-align: middle;">' . $contentBlock . '</td>'
                . '</tr></table>';
        } elseif ($layoutClass === 'layout-fullscreen') {
            $slideTable = '<table class="slide-table"><tr>'
                . '<td class="slide-cell slide-media-cell" style="width: 100%; vertical-align: middle; height:100%;">' . $mediaBlock . '</td>'
                . '</tr></table>';
        } elseif ($template === 'g') {
            $leftCell = $contentBlock;
            $rightCell = '<div class="slide-square-cell">' . $mediaBlock . '</div>';
            $leftStyle = 'width:70%; padding-right:' . $gapLargeMm . 'mm; vertical-align: middle; height:100%;';
            $rightStyle = 'width:30%; vertical-align: middle; height:100%;';
            $slideTable = '<table class="slide-table"><tr>'
                . '<td class="slide-cell slide-content-cell" style="' . $leftStyle . '">' . $leftCell . '</td>'
                . '<td class="slide-cell slide-media-cell" style="' . $rightStyle . '">' . $rightCell . '</td>'
                . '</tr></table>';
        } elseif ($template === 'h') {
            $leftCell = '<div class="slide-square-cell">' . $mediaBlock . '</div>';
            $rightCell = $contentBlock;
            $leftStyle = 'width:30%; vertical-align: middle; height:100%;';
            $rightStyle = 'width:70%; padding-left:' . $gapLargeMm . 'mm; vertical-align: middle; height:100%;';
            $slideTable = '<table class="slide-table"><tr>'
                . '<td class="slide-cell slide-media-cell" style="' . $leftStyle . '">' . $leftCell . '</td>'
                . '<td class="slide-cell slide-content-cell" style="' . $rightStyle . '">' . $rightCell . '</td>'
                . '</tr></table>';
        } elseif (in_array($layoutClass, ['layout-z', 'layout-y', 'layout-e', 'layout-f'], true)) {
            $leftCell = $contentBlock;
            $rightCell = $mediaBlock;
            $leftStyle = 'width:52%; padding-right:' . $gapLargeMm . 'mm; vertical-align: middle; height:100%;';
            $rightStyle = 'width:48%; vertical-align: middle; height:100%;';

            if ($layoutClass === 'layout-y') {
                $leftCell = $mediaBlock;
                $rightCell = $contentBlock;
                $leftStyle = 'width:48%; padding-right:' . $gapLargeMm . 'mm; vertical-align: middle; height:100%;';
                $rightStyle = 'width:52%; padding-left:' . $gapLargeMm . 'mm; vertical-align: middle; height:100%;';
            } elseif ($layoutClass === 'layout-e') {
                $leftCell = $contentBlock;
                $rightCell = $mediaBlock;
                $leftStyle = 'width:78%; padding-right:' . $gapLargeMm . 'mm; vertical-align: middle; height:100%;';
                $rightStyle = 'width:22%; vertical-align: middle; height:100%;';
            } elseif ($layoutClass === 'layout-f') {
                $leftCell = $mediaBlock;
                $rightCell = $contentBlock;
                $leftStyle = 'width:22%; vertical-align: middle; height:100%;';
                $rightStyle = 'width:78%; padding-left:' . $gapLargeMm . 'mm; vertical-align: middle; height:100%;';
            }

            $slideTable = '<table class="slide-table"><tr>'
                . '<td class="slide-cell slide-content-cell" style="' . $leftStyle . '">' . $leftCell . '</td>'
                . '<td class="slide-cell slide-media-cell" style="' . $rightStyle . '">' . $rightCell . '</td>'
                . '</tr></table>';
        } elseif (in_array($layoutClass, ['layout-b', 'layout-c', 'layout-d', 'layout-i'], true)) {
            if ($layoutClass === 'layout-b') {
                $topCell = $mediaBlock;
                $bottomCell = $contentBlock;
                $topStyle = 'height:24%; vertical-align: middle; padding-bottom:' . $gapSmallMm . 'mm;';
                $bottomStyle = 'height:76%; vertical-align: top; padding-top:' . $gapSmallMm . 'mm;';
            } elseif ($layoutClass === 'layout-c') {
                $topCell = $contentBlock;
                $bottomCell = $mediaBlock;
                $topStyle = 'height:76%; vertical-align: top; padding-bottom:' . $gapSmallMm . 'mm;';
                $bottomStyle = 'height:24%; vertical-align: middle; padding-top:' . $gapSmallMm . 'mm;';
            } elseif ($layoutClass === 'layout-d') {
                $topCell = $mediaBlock;
                $bottomCell = $contentBlock;
                $topStyle = 'height:50%; vertical-align: middle; padding-bottom:' . $gapSmallMm . 'mm;';
                $bottomStyle = 'height:50%; vertical-align: top; padding-top:' . $gapSmallMm . 'mm;';
            } else { // layout-i
                $topCell = $contentBlock;
                $bottomCell = $mediaBlock;
                $topStyle = 'height:50%; vertical-align: top; padding-bottom:' . $gapSmallMm . 'mm;';
                $bottomStyle = 'height:50%; vertical-align: middle; padding-top:' . $gapSmallMm . 'mm;';
            }

            $slideTable = '<table class="slide-table"><tr>'
                . '<td class="slide-cell slide-content-cell" style="' . $topStyle . '">' . $topCell . '</td>'
                . '</tr><tr>'
                . '<td class="slide-cell slide-media-cell" style="' . $bottomStyle . '">' . $bottomCell . '</td>'
                . '</tr></table>';
        } else {
            $slideTable = '<table class="slide-table"><tr>'
                . '<td class="slide-cell slide-content-cell" style="height:100%; text-align:center; vertical-align: middle;">' . $contentBlock . '</td>'
                . '</tr></table>';
        }

        $wrapperStyleAttr = $page_break_style ? ' style="' . $page_break_style . '"' : '';
        $finalHtml .= '<div class="slide-wrapper"' . $wrapperStyleAttr . '><div class="slide-page ' . $layoutClass . '">' . $slideTable . '</div></div>';
    }

    $finalHtml .= '</body></html>';

    $presentationName = 'inanna-presentacion';
    if (!empty($_POST['presentation_name'])) {
        $presentationName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['presentation_name']);
        if (empty($presentationName)) {
            $presentationName = 'inanna-presentacion';
        }
    }
    $downloadName = $presentationName . '.pdf';

    if (try_generate_pdf_with_wkhtmltopdf($finalHtml, $downloadName, $pageWidthMm, $pageHeightMm)) {
        exit;
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se pudo generar el PDF con wkhtmltopdf.';
    exit;

} else {
    die('No se han recibido datos para generar la presentación.');
}
?>
