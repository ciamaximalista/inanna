document.addEventListener('DOMContentLoaded', () => {
    const compositionTab = document.querySelector('div[onclick*="Composicion"]');
    if (!compositionTab) return;

    // --- STATE ---
    let presentationData = [];
    let currentSlideIndex = 0;
    let activeImagePlaceholder = null;

    // --- ELEMENTS ---
    const markdownSource = document.getElementById('markdown-source');
    const thumbnailsContainer = document.getElementById('slide-thumbnails');
    const previewContainer = document.getElementById('slide-preview');
    const templateSelector = document.getElementById('template-selector');
    const resourceModal = document.getElementById('resource-modal');
    const closeModal = document.getElementById('close-modal');
    const modalGallery = document.getElementById('modal-resource-gallery');
    const downloadBtn = document.getElementById('download-pdf');
    const downloadForm = document.getElementById('download-form');
    const presentationDataInput = document.getElementById('presentation-data-input');

    // --- FUNCTIONS ---

    const parseMarkdown = async (markdown) => {
        const formData = new FormData();
        formData.append('markdown', markdown);
        try {
            const response = await fetch('parse_markdown.php', {
                method: 'POST',
                body: formData
            });
            return await response.text();
        } catch (error) {
            console.error('Error parsing markdown:', error);
            return '<p>Error al cargar contenido</p>';
        }
    };

    const renderPreview = async () => {
        if (presentationData.length === 0) {
            previewContainer.innerHTML = '<p style="text-align:center; padding-top: 20px;">Escribe algo en la pestaña de Texto para empezar.</p>';
            return;
        }

        const slide = presentationData[currentSlideIndex];
        const slideHtmlContent = await parseMarkdown(slide.markdown);

        const template = slide.template || 'a';
        const imageSrc = slide.image || '';

        const fontTitle = appStyles.font_title.replace(/ /g, '+');
        const fontText = appStyles.font_text.replace(/ /g, '+');
        const fontsLink = fontTitle !== fontText
            ? `<link href="https://fonts.googleapis.com/css2?family=${fontTitle}:wght@600;800&family=${fontText}:wght@400;600&display=swap" rel="stylesheet">`
            : `<link href="https://fonts.googleapis.com/css2?family=${fontTitle}:wght@400;600;800&display=swap" rel="stylesheet">`;

        const mmToPx = (mm) => (mm / 25.4) * 96;
        const pageWidthMm = 297;
        const pageHeightMm = 210;
        const scaleFactor = 1.16;
        const pageWidthPx = mmToPx(pageWidthMm);
        const pageHeightPx = mmToPx(pageHeightMm);

        const paddingTopPx = mmToPx(8);
        const paddingLeftPx = mmToPx(10);
        const paddingRightPx = mmToPx(1);
        const paddingBottomPx = mmToPx(1);

        const contentPaddingRightPx = mmToPx(2);
        const contentPaddingLeftPx = mmToPx(6 * scaleFactor);
        const gapLargePx = mmToPx(12 * scaleFactor);
        const gapSmallPx = mmToPx(6 * scaleFactor);
        const shiftRightPx = mmToPx(2.5);
        const extraRightPx = mmToPx(85);
        const extraBottomPx = mmToPx(60);
        const totalWidthPx = pageWidthPx + extraRightPx + shiftRightPx;
        const totalHeightPx = pageHeightPx + extraBottomPx;

        const paragraphFontPt = 20 * scaleFactor;
        const h1FontPt = 44 * scaleFactor;
        const h2FontPt = 36 * scaleFactor;
        const h3FontPt = 30 * scaleFactor;

        const previewRect = previewContainer.getBoundingClientRect();
        const previewWidth = previewRect.width;
        const previewHeight = previewRect.height;

        let stageScale = Math.min(previewWidth / totalWidthPx, previewHeight / totalHeightPx, 1);
        if (!Number.isFinite(stageScale) || stageScale <= 0) {
            stageScale = 0.6;
        }

        let finalHtml = fontsLink;
        finalHtml += `<style>
            .preview-root {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: ${appStyles.color_bg};
                overflow: hidden;
                box-sizing: border-box;
                padding-left: ${mmToPx(50)}px;
                padding-top: ${mmToPx(40)}px;
            }
            .preview-stage {
                position: relative;
                width: ${totalWidthPx}px;
                height: ${totalHeightPx}px;
                display: flex;
                align-items: flex-start;
                justify-content: flex-start;
                background-color: transparent;
                transform-origin: top left;
                transform: translate(${mmToPx(46)}px, ${mmToPx(25)}px) scale(${stageScale});
            }
            .slide-wrapper {
                width: ${totalWidthPx}px;
                height: ${totalHeightPx}px;
                position: relative;
                overflow: visible;
            }
            .slide-page {
                width: ${pageWidthPx}px;
                height: ${pageHeightPx}px;
                padding: ${paddingTopPx}px ${paddingRightPx}px ${paddingBottomPx}px ${paddingLeftPx}px;
                box-sizing: border-box;
                background-color: ${appStyles.color_bg};
                color: ${appStyles.color_text};
                font-family: '${appStyles.font_text}', sans-serif;
                font-size: ${paragraphFontPt}pt;
                line-height: 1.45;
                position: relative;
                overflow: visible;
            }
            .slide-table { width: calc(100% + ${extraRightPx}px); height: calc(100% + ${extraBottomPx}px); margin: 0 -${extraRightPx}px -${extraBottomPx}px 0; border-collapse: collapse; table-layout: fixed; transform: translateX(${shiftRightPx}px); }
            .slide-cell { padding: 0; vertical-align: middle; height: 100%; }
            .slide-content { margin: 0; height: 100%; padding: 0 ${contentPaddingRightPx}px 0 ${contentPaddingLeftPx}px; display: table; width: 100%; box-sizing: border-box; }
            .slide-content-inner { display: table-cell; vertical-align: middle; height: 100%; }
            .slide-content.centered .slide-content-inner > div {
                text-align: center;
            }
            .slide-content > * {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .slide-fullscreen-cell { padding: 0; }
            .slide-content ul,
            .slide-content ol {
                margin: 0.35em 0;
                padding-left: 0;
                list-style: none;
                text-align: left;
                width: 100%;
            }
            .slide-content li {
                position: relative;
                padding-left: 1.3em;
                font-size: ${paragraphFontPt}pt;
            }
            .slide-content ul li::before {
                content: '•';
                position: absolute;
                left: 0;
                top: 0.1em;
                font-family: '${appStyles.font_title}', sans-serif;
                color: ${appStyles.color_highlight};
                font-weight: 700;
            }
            .slide-content ol {
                counter-reset: ordered;
            }
            .slide-content ol li {
                counter-increment: ordered;
            }
            .slide-content ol li::before {
                content: counter(ordered) '.';
                position: absolute;
                left: 0;
                top: 0.05em;
                font-family: '${appStyles.font_title}', sans-serif;
                color: ${appStyles.color_highlight};
                font-weight: 700;
            }
            .slide-page h1,
            .slide-page h2,
            .slide-page h3,
            .slide-page h4,
            .slide-page h5,
            .slide-page h6 {
                font-family: '${appStyles.font_title}', sans-serif;
                margin: 0;
                line-height: 1.15;
            }
            .slide-page h1 { color: ${appStyles.color_h1}; margin-bottom: 0.2em; font-size: ${h1FontPt}pt; }
            .slide-page h2 { color: ${appStyles.color_h2}; font-size: ${h2FontPt}pt; }
            .slide-page h3 { color: ${appStyles.color_h3}; font-size: ${h3FontPt}pt; }
            .slide-page p { margin: 0.35em 0; font-size: ${paragraphFontPt}pt; }
            .slide-page a { color: ${appStyles.color_highlight}; text-decoration: none; }
            .slide-page strong,
            .slide-page b { color: ${appStyles.color_highlight}; font-weight: 700; }
            .slide-page.layout-fullscreen { padding: 0; }
            .slide-page.layout-fullscreen .slide-table { height: 100%; }
            .slide-page.layout-fullscreen .slide-cell { padding: 0; }
            .slide-page blockquote {
                background-color: ${appStyles.color_box};
                padding: 1em;
                border-left: 5px solid ${appStyles.color_highlight};
                margin: 0.8em 0;
                text-align: left;
                font-size: 20pt;
            }
            .slide-media {
                width: 100%;
                height: 100%;
                border-radius: 18px;
                overflow: hidden;
                background-color: ${appStyles.color_box};
                background-position: center;
                background-repeat: no-repeat;
                background-size: cover;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #555;
                text-align: center;
            }
            .slide-media.fullscreen {
                border-radius: 0;
            }
            .slide-media.placeholder {
                border: 2px dashed #d0d0d0;
                color: #777;
                font-size: ${12 * scaleFactor}pt;
                padding: ${mmToPx(6 * scaleFactor)}px;
            }
            .slide-media.placeholder span {
                display: block;
            }
            .slide-media.has-image {
                border: none;
                color: transparent;
            }
            .slide-square-cell {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100%;
                width: 100%;
            }
            .slide-media.square {
                width: 80%;
                max-width: ${mmToPx(90)}px;
                aspect-ratio: 1 / 1;
                height: auto;
            }
            .slide-media.square.placeholder {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        </style>`;

       const layoutClassMap = {
           a: 'layout-a',
           z: 'layout-z',
           y: 'layout-y',
           g: 'layout-g',
           h: 'layout-h',
           b: 'layout-b',
           c: 'layout-c',
           e: 'layout-e',
            f: 'layout-f',
            fullscreen: 'layout-fullscreen',
       };

        const layoutClass = layoutClassMap[template] || 'layout-a';
        const contentClass = template === 'a' ? 'slide-content centered' : 'slide-content';
        const contentBlock = `<div class="${contentClass}"><div class="slide-content-inner"><div>${slideHtmlContent}</div></div></div>`;

        const requiresSquareMedia = template === 'g' || template === 'h';
        let mediaBlock = '';
        if (template !== 'a') {
            let mediaBaseClass = requiresSquareMedia ? 'slide-media square' : 'slide-media';
            if (template === 'fullscreen') {
                mediaBaseClass = 'slide-media fullscreen';
            }
            const hasImage = Boolean(imageSrc);
            if (hasImage) {
                const escaped = imageSrc.replace(/(["'\\])/g, '\\$1');
                mediaBlock = `<div class="${mediaBaseClass} has-image" data-placeholder-id="1" style="background-image:url('${escaped}');"></div>`;
            } else {
                mediaBlock = `<div class="${mediaBaseClass} placeholder" data-placeholder-id="1"><span>Haz clic para añadir imagen</span></div>`;
            }
        }

        let pageInlineStyle = '';
        let slideTable = '';
        switch (template) {
            case 'fullscreen':
                pageInlineStyle = 'padding:0;';
                slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-fullscreen-cell">${mediaBlock}</td></tr></table>`;
                break;
            case 'z':
                {
                    let leftCell = contentBlock;
                    let rightCell = mediaBlock;
                    let leftStyle = `width:52%; padding-right:${gapLargePx}px; vertical-align: middle; height:100%;`;
                    let rightStyle = 'width:48%; vertical-align: middle; height:100%;';
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-content-cell" style="${leftStyle}">${leftCell}</td><td class="slide-cell slide-media-cell" style="${rightStyle}">${rightCell}</td></tr></table>`;
                }
                break;
            case 'y':
                {
                    let leftCell = mediaBlock;
                    let rightCell = contentBlock;
                    let leftStyle = `width:48%; padding-right:${gapLargePx}px; vertical-align: middle; height:100%;`;
                    let rightStyle = `width:52%; padding-left:${gapLargePx}px; vertical-align: middle; height:100%;`;
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-media-cell" style="${leftStyle}">${leftCell}</td><td class="slide-cell slide-content-cell" style="${rightStyle}">${rightCell}</td></tr></table>`;
                }
                break;
            case 'g':
                {
                    let leftCell = contentBlock;
                    let rightCell = `<div class="slide-square-cell">${mediaBlock}</div>`;
                    let leftStyle = `width:70%; padding-right:${gapLargePx}px; vertical-align: middle; height:100%;`;
                    let rightStyle = 'width:30%; vertical-align: middle; height:100%;';
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-content-cell" style="${leftStyle}">${leftCell}</td><td class="slide-cell slide-media-cell" style="${rightStyle}">${rightCell}</td></tr></table>`;
                }
                break;
            case 'h':
                {
                    let leftCell = `<div class="slide-square-cell">${mediaBlock}</div>`;
                    let rightCell = contentBlock;
                    let leftStyle = 'width:30%; vertical-align: middle; height:100%;';
                    let rightStyle = `width:70%; padding-left:${gapLargePx}px; vertical-align: middle; height:100%;`;
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-media-cell" style="${leftStyle}">${leftCell}</td><td class="slide-cell slide-content-cell" style="${rightStyle}">${rightCell}</td></tr></table>`;
                }
                break;
            case 'e':
                {
                    let leftCell = contentBlock;
                    let rightCell = mediaBlock;
                    let leftStyle = `width:78%; padding-right:${gapLargePx}px; vertical-align: middle; height:100%;`;
                    let rightStyle = 'width:22%; vertical-align: middle; height:100%;';
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-content-cell" style="${leftStyle}">${leftCell}</td><td class="slide-cell slide-media-cell" style="${rightStyle}">${rightCell}</td></tr></table>`;
                }
                break;
            case 'f':
                {
                    let leftCell = mediaBlock;
                    let rightCell = contentBlock;
                    let leftStyle = 'width:22%; vertical-align: middle; height:100%;';
                    let rightStyle = `width:78%; padding-left:${gapLargePx}px; vertical-align: middle; height:100%;`;
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-media-cell" style="${leftStyle}">${leftCell}</td><td class="slide-cell slide-content-cell" style="${rightStyle}">${rightCell}</td></tr></table>`;
                }
                break;
            case 'b':
                {
                    let topCell = mediaBlock;
                    let bottomCell = contentBlock;
                    let topStyle = `height:24%; vertical-align: middle; padding-bottom:${gapSmallPx}px;`;
                    let bottomStyle = `height:76%; vertical-align: top; padding-top:${gapSmallPx}px;`;
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-media-cell" style="${topStyle}">${topCell}</td></tr><tr><td class="slide-cell slide-content-cell" style="${bottomStyle}">${bottomCell}</td></tr></table>`;
                }
                break;
            case 'c':
                {
                    let topCell = contentBlock;
                    let bottomCell = mediaBlock;
                    let topStyle = `height:76%; vertical-align: top; padding-bottom:${gapSmallPx}px;`;
                    let bottomStyle = `height:24%; vertical-align: middle; padding-top:${gapSmallPx}px;`;
                    slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-content-cell" style="${topStyle}">${topCell}</td></tr><tr><td class="slide-cell slide-media-cell" style="${bottomStyle}">${bottomCell}</td></tr></table>`;
                }
                break;
            default:
                slideTable = `<table class="slide-table"><tr><td class="slide-cell slide-content-cell" style="height:100%; text-align:center; vertical-align: middle;">${contentBlock}</td></tr></table>`;
                break;
        }

        const pageStyleAttr = pageInlineStyle ? ' style="' + pageInlineStyle + '"' : '';
        const slideHtml = `<div class="slide-wrapper"><div class="slide-page ${layoutClass}"${pageStyleAttr}>${slideTable}</div></div>`;
        finalHtml += `<div class="preview-root"><div class="preview-stage">${slideHtml}</div></div>`;

        previewContainer.innerHTML = finalHtml;
        updateTemplateSelection();
    };
    const renderThumbnails = () => {
        thumbnailsContainer.innerHTML = '';
        presentationData.forEach((slide, index) => {
            const thumb = document.createElement('div');
            thumb.style.height = '70px';
            thumb.style.border = '1px solid #ccc';
            thumb.style.padding = '5px';
            thumb.style.cursor = 'pointer';
            thumb.innerHTML = `<small>Diapo ${index + 1}</small><p style="font-size:0.7rem; overflow:hidden; max-height: 30px;">${slide.markdown.substring(0, 50)}...</p>`;
            if (index === currentSlideIndex) {
                thumb.style.borderColor = appStyles.color_title;
                thumb.style.borderWidth = '2px';
            }
            thumb.addEventListener('click', () => {
                currentSlideIndex = index;
                renderThumbnails();
                renderPreview();
            });
            thumbnailsContainer.appendChild(thumb);
        });
    };
    
    const updateTemplateSelection = () => {
        const currentTemplate = presentationData[currentSlideIndex]?.template || 'a';
        document.querySelectorAll('.template-option').forEach(opt => {
            if (opt.dataset.template === currentTemplate) {
                opt.classList.add('selected');
            } else {
                opt.classList.remove('selected');
            }
        });
    };

    let presentationInitialized = false; // New flag

    const initCompositionView = () => {
        console.log('initCompositionView() called.');
        if (!presentationInitialized && initialPresentationData && initialPresentationData.slides && initialPresentationData.slides.length > 0) {
            console.log('Using initialPresentationData for first initialization.');
            presentationData = initialPresentationData.slides.map(slide => ({
                markdown: slide.markdown,
                template: slide.template || 'a',
                image: slide.image || null
            }));
            presentationInitialized = true; // Mark as initialized
        } else {
            console.log('Parsing markdown from textarea (or re-parsing after initial load).');
            const md = markdownSource.value;
            console.log('Markdown read from textarea:', md);
            const slides = md.split('---').map(s => s.trim()).filter(s => s);
            console.log('Parsed slides from textarea:', slides);

            // Preserve existing data if markdown hasn't changed drastically
            presentationData = slides.map((slideMd, index) => {
                return {
                    markdown: slideMd,
                    template: presentationData[index]?.template || 'a',
                    image: presentationData[index]?.image || null
                };
            });
        }
        
        currentSlideIndex = Math.min(currentSlideIndex, presentationData.length - 1);
        if(presentationData.length === 0) currentSlideIndex = 0;

        renderThumbnails();
        renderPreview();
    };

    // --- EVENT LISTENERS ---

    compositionTab.addEventListener('click', initCompositionView);

        // Debounce function to limit how often initCompositionView is called

        let debounceTimer;

        markdownSource.addEventListener('input', () => {

            console.log('Markdown input event fired.');

            clearTimeout(debounceTimer);

            debounceTimer = setTimeout(() => {

                console.log('Debounce finished. Calling initCompositionView().');

                initCompositionView();

            }, 500); // 500ms debounce time

        });

    

        templateSelector.addEventListener('click', (e) => {
        const templateOption = e.target.closest('.template-option');
        if (templateOption && presentationData.length > 0) {
            const template = templateOption.dataset.template;
            presentationData[currentSlideIndex].template = template;
            renderPreview();
        }
    });
    previewContainer.addEventListener('click', (e) => {
        const placeholder = e.target.closest('.slide-media, .image-placeholder, .image-container');
        if (placeholder) {
            activeImagePlaceholder = placeholder.dataset.placeholderId || '1';
            resourceModal.style.display = 'block';
        }
    });
    closeModal.addEventListener('click', () => {
        resourceModal.style.display = 'none';
    });
    modalGallery.addEventListener('click', (e) => {
        const resourceItem = e.target.closest('.modal-resource-item');
        if (resourceItem) {
            const resourcePath = resourceItem.dataset.resourcePath;
            if (presentationData.length > 0) {
                presentationData[currentSlideIndex].image = resourcePath;
                resourceModal.style.display = 'none';
                renderPreview();
            }
        }
    });
    
    downloadBtn.addEventListener('click', (e) => {
        e.preventDefault();
        presentationDataInput.value = JSON.stringify({
            slides: presentationData,
            styles: appStyles
        });
        downloadForm.submit();
    });

    window.addEventListener('click', (e) => {
        if (e.target == resourceModal) {
            resourceModal.style.display = 'none';
        }
    });

    // --- SAVE PRESENTATION LOGIC ---
    const saveButtons = document.querySelectorAll('.save-presentation-btn');

    const handleSavePresentation = async () => {
        // Ensure data reflects current markdown/template selections
        initCompositionView();

        let filename = prompt("Guardar como...", "presentacion.xml");
        if (!filename) { return; }

        if (!filename.endsWith('.xml')) {
            filename += '.xml';
        }
        if (!/^[a-zA-Z0-9_-]+\.xml$/.test(filename)) {
            alert("Nombre de archivo inválido. Solo se permiten caracteres alfanuméricos, guiones y guiones bajos, y debe terminar en .xml");
            return;
        }

        const dataToSave = {
            filename,
            presentation_data: {
                slides: presentationData,
                styles: appStyles
            }
        };

        try {
            const response = await fetch('guardar_presentacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dataToSave)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                alert(result.message);
                window.location.href = 'inanna.php?tab=Archivo';
            } else {
                alert(`Error al guardar: ${result.message}`);
            }
        } catch (error) {
            console.error('Error saving presentation:', error);
            alert('Ocurrió un error de red al intentar guardar la presentación.');
        }
    };

    saveButtons.forEach((btn) => {
        btn.addEventListener('click', handleSavePresentation);
    });
});
