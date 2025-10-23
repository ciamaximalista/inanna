document.addEventListener('DOMContentLoaded', () => {
    const editButtons = document.querySelectorAll('.resource-edit-btn');
    const deleteButtons = document.querySelectorAll('.resource-delete-btn');
    if (editButtons.length === 0 && deleteButtons.length === 0) {
        return;
    }

    const editModal = document.getElementById('resource-edit-modal');
    const editClose = document.getElementById('resource-edit-close');
    const editCanvas = document.getElementById('resource-edit-canvas');
    const ctx = editCanvas ? editCanvas.getContext('2d') : null;
    const filenameInput = document.getElementById('resource-edit-filename');
    const brightnessInput = document.getElementById('resource-brightness');
    const contrastInput = document.getElementById('resource-contrast');
    const brightnessValue = document.getElementById('resource-brightness-value');
    const contrastValue = document.getElementById('resource-contrast-value');
    const cropXInput = document.getElementById('resource-crop-x');
    const cropYInput = document.getElementById('resource-crop-y');
    const cropWidthInput = document.getElementById('resource-crop-width');
    const cropHeightInput = document.getElementById('resource-crop-height');
    const saveButton = document.getElementById('resource-edit-save');
    const cancelButton = document.getElementById('resource-edit-cancel');
    const editForm = document.getElementById('resource-edit-form');

    let currentImage = null;
    let currentResourcePath = null;
    let imageOriginalWidth = 0;
    let imageOriginalHeight = 0;
    let displayScale = 1;
    let isDrawing = false;
    let drawStartImage = null;
    let updatingInputs = false;
    const selection = { x: 0, y: 0, width: 0, height: 0 };

    const MAX_CANVAS_DIMENSION = 520;

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const clampSelection = () => {
        selection.x = clamp(selection.x, 0, Math.max(0, imageOriginalWidth - 1));
        selection.y = clamp(selection.y, 0, Math.max(0, imageOriginalHeight - 1));
        selection.width = clamp(selection.width, 1, imageOriginalWidth - selection.x);
        selection.height = clamp(selection.height, 1, imageOriginalHeight - selection.y);
    };

    const updateInputsFromSelection = () => {
        if (!cropXInput) { return; }
        updatingInputs = true;
        cropXInput.value = Math.round(selection.x);
        cropYInput.value = Math.round(selection.y);
        cropWidthInput.value = Math.round(selection.width);
        cropHeightInput.value = Math.round(selection.height);
        updatingInputs = false;
    };

    const updateCropConstraints = () => {
        if (!cropXInput) { return; }
        cropXInput.max = Math.max(0, imageOriginalWidth - 1);
        cropYInput.max = Math.max(0, imageOriginalHeight - 1);
        cropWidthInput.max = imageOriginalWidth;
        cropHeightInput.max = imageOriginalHeight;
    };

    const updateSelectionFromInputs = () => {
        if (!currentImage || updatingInputs) { return; }
        selection.x = parseInt(cropXInput.value, 10) || 0;
        selection.y = parseInt(cropYInput.value, 10) || 0;
        selection.width = parseInt(cropWidthInput.value, 10) || imageOriginalWidth;
        selection.height = parseInt(cropHeightInput.value, 10) || imageOriginalHeight;
        clampSelection();
        updateInputsFromSelection();
        drawPreview();
    };

    const drawPreview = () => {
        if (!ctx || !currentImage) {
            return;
        }
        const brightness = parseInt(brightnessInput.value, 10) || 0;
        const contrast = parseInt(contrastInput.value, 10) || 0;
        brightnessValue.textContent = brightness;
        contrastValue.textContent = contrast;

        const scale = Math.min(
            MAX_CANVAS_DIMENSION / imageOriginalWidth,
            MAX_CANVAS_DIMENSION / imageOriginalHeight,
            1
        );
        displayScale = scale;
        const canvasWidth = Math.max(1, Math.round(imageOriginalWidth * scale));
        const canvasHeight = Math.max(1, Math.round(imageOriginalHeight * scale));
        editCanvas.width = canvasWidth;
        editCanvas.height = canvasHeight;
        editCanvas.style.width = `${canvasWidth}px`;
        editCanvas.style.height = `${canvasHeight}px`;

        const brightnessFactor = (100 + brightness) / 100;
        const contrastFactor = (100 + contrast) / 100;
        ctx.save();
        ctx.clearRect(0, 0, canvasWidth, canvasHeight);
        ctx.filter = `brightness(${brightnessFactor}) contrast(${contrastFactor})`;
        ctx.drawImage(
            currentImage,
            0,
            0,
            imageOriginalWidth,
            imageOriginalHeight,
            0,
            0,
            canvasWidth,
            canvasHeight
        );
        ctx.restore();

        const selCanvasX = selection.x * displayScale;
        const selCanvasY = selection.y * displayScale;
        const selCanvasW = selection.width * displayScale;
        const selCanvasH = selection.height * displayScale;

        ctx.save();
        ctx.fillStyle = 'rgba(0,0,0,0.40)';
        ctx.beginPath();
        ctx.rect(0, 0, canvasWidth, canvasHeight);
        ctx.rect(selCanvasX, selCanvasY, selCanvasW, selCanvasH);
        ctx.fill('evenodd');
        ctx.strokeStyle = '#1B8EED';
        ctx.lineWidth = 2;
        ctx.strokeRect(selCanvasX, selCanvasY, selCanvasW, selCanvasH);
        ctx.restore();
    };

    const getCanvasCoordinates = (event) => {
        const rect = editCanvas.getBoundingClientRect();
        const scaleX = editCanvas.width / rect.width;
        const scaleY = editCanvas.height / rect.height;
        const x = clamp((event.clientX - rect.left) * scaleX, 0, editCanvas.width);
        const y = clamp((event.clientY - rect.top) * scaleY, 0, editCanvas.height);
        return { x, y };
    };

    const updateSelectionFromDrag = (canvasPoint) => {
        if (!drawStartImage) { return; }
        const currentImageX = clamp(canvasPoint.x / displayScale, 0, imageOriginalWidth);
        const currentImageY = clamp(canvasPoint.y / displayScale, 0, imageOriginalHeight);
        const startImageX = clamp(drawStartImage.x, 0, imageOriginalWidth);
        const startImageY = clamp(drawStartImage.y, 0, imageOriginalHeight);

        const minX = Math.max(0, Math.min(startImageX, currentImageX));
        const minY = Math.max(0, Math.min(startImageY, currentImageY));
        const maxX = Math.min(imageOriginalWidth, Math.max(startImageX, currentImageX));
        const maxY = Math.min(imageOriginalHeight, Math.max(startImageY, currentImageY));

        selection.x = Math.round(minX);
        selection.y = Math.round(minY);
        selection.width = Math.max(1, Math.round(maxX - minX));
        selection.height = Math.max(1, Math.round(maxY - minY));
        clampSelection();
        updateInputsFromSelection();
        drawPreview();
    };

    const openEditModal = (resourcePath) => {
        if (!editModal || !ctx) {
            return;
        }

        currentResourcePath = resourcePath;
        editModal.style.display = 'block';

        brightnessInput.value = '0';
        contrastInput.value = '0';
        brightnessValue.textContent = '0';
        contrastValue.textContent = '0';

        const baseName = resourcePath.split('/').pop() || 'recurso.png';
        const dotIndex = baseName.lastIndexOf('.');
        const defaultName = dotIndex > -1 ? `${baseName.substring(0, dotIndex)}_edit${baseName.substring(dotIndex)}` : `${baseName}_edit`;
        filenameInput.value = defaultName;

        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            currentImage = img;
            imageOriginalWidth = img.naturalWidth;
            imageOriginalHeight = img.naturalHeight;

            selection.x = 0;
            selection.y = 0;
            selection.width = imageOriginalWidth;
            selection.height = imageOriginalHeight;
            updateCropConstraints();
            updateInputsFromSelection();
            drawPreview();
        };
        img.onerror = () => {
            alert('No se pudo cargar la imagen para editar.');
            closeEditModal();
        };
        img.src = resourcePath + '?t=' + Date.now();
    };

    const closeEditModal = () => {
        if (editModal) {
            editModal.style.display = 'none';
        }
        currentImage = null;
        currentResourcePath = null;
    };

    if (editClose) {
        editClose.addEventListener('click', closeEditModal);
    }
    if (cancelButton) {
        cancelButton.addEventListener('click', closeEditModal);
    }
    window.addEventListener('click', (event) => {
        if (event.target === editModal) {
            closeEditModal();
        }
    });

    if (brightnessInput) {
        brightnessInput.addEventListener('input', drawPreview);
    }
    if (contrastInput) {
        contrastInput.addEventListener('input', drawPreview);
    }
    [cropXInput, cropYInput, cropWidthInput, cropHeightInput].forEach((input) => {
        if (!input) { return; }
        input.addEventListener('input', updateSelectionFromInputs);
    });

    if (editCanvas) {
        const handlePointerMove = (event) => {
            if (!isDrawing || !currentImage) { return; }
            event.preventDefault();
            const current = getCanvasCoordinates(event);
            updateSelectionFromDrag(current);
        };

        const finishDrawing = (event) => {
            if (!isDrawing || !currentImage) { return; }
            event.preventDefault();
            isDrawing = false;
            const current = getCanvasCoordinates(event);
            updateSelectionFromDrag(current);
            drawStartImage = null;
            window.removeEventListener('mousemove', handlePointerMove);
            window.removeEventListener('mouseup', finishDrawing);
        };

        editCanvas.addEventListener('mousedown', (event) => {
            if (!currentImage) { return; }
            event.preventDefault();
            isDrawing = true;
            const canvasPoint = getCanvasCoordinates(event);
            drawStartImage = {
                x: canvasPoint.x / displayScale,
                y: canvasPoint.y / displayScale,
            };
            updateSelectionFromDrag(canvasPoint);
            window.addEventListener('mousemove', handlePointerMove);
            window.addEventListener('mouseup', finishDrawing);
        });
    }

    editButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const resourcePath = btn.dataset.resourcePath;
            const disabled = btn.hasAttribute('disabled');
            if (!resourcePath || disabled) {
                return;
            }
            openEditModal(resourcePath);
        });
    });

    deleteButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const resourcePath = btn.dataset.resourcePath;
            if (!resourcePath) {
                return;
            }
            if (!confirm('¿Seguro que quieres borrar este recurso?')) {
                return;
            }
            fetch('borrar_recurso.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ resource_path: resourcePath })
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.status === 'success') {
                        window.location.reload();
                    } else {
                        alert(data.message || 'No se pudo borrar el recurso.');
                    }
                })
                .catch(() => {
                    alert('Error al borrar el recurso.');
                });
        });
    });

    if (editForm && saveButton) {
        editForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!currentImage || !currentResourcePath) {
                alert('No se ha cargado ninguna imagen.');
                return;
            }
            const newFilename = (filenameInput.value || '').trim();
            if (newFilename === '') {
                alert('Introduce un nombre para el archivo resultante.');
                filenameInput.focus();
                return;
            }

            clampSelection();
            updateInputsFromSelection();

            const payload = new FormData();
            payload.append('resource_path', currentResourcePath);
            payload.append('new_filename', newFilename);
            payload.append('brightness', brightnessInput.value);
            payload.append('contrast', contrastInput.value);
            payload.append('crop_x', selection.x);
            payload.append('crop_y', selection.y);
            payload.append('crop_width', selection.width);
            payload.append('crop_height', selection.height);

            fetch('editar_recurso.php', {
                method: 'POST',
                body: payload
            })
                .then((response) => response.json())
                .then((data) => {
                    if (data.status === 'success') {
                        closeEditModal();
                        window.location.reload();
                    } else {
                        alert(data.message || 'No se pudo guardar la imagen editada.');
                    }
                })
                .catch(() => {
                    alert('Ocurrió un error durante la edición.');
                });
        });
    }
});
