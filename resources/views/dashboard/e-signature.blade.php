@extends('layouts.app')

@section('title', 'E-Signature')

@section('content')
    <div class="page-header">
        <h1 class="page-title">E-Signature</h1>
        <p class="page-subtitle">Create and manage your digital signature</p>
    </div>

    <div class="esignature-container">
        <div class="esignature-grid">
            <!-- Signature Preview -->
            <div class="esignature-card">
                <div class="card-header">
                    <h3 class="card-title">Your Signature</h3>
                    <button class="btn-secondary" onclick="clearSignature()">Clear</button>
                </div>
                <div class="card-body">
                    <div class="signature-preview" id="signaturePreview">
                        <div class="preview-placeholder" id="previewPlaceholder">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            <p>No signature created yet</p>
                        </div>
                        <canvas id="signatureCanvas" style="display: none;"></canvas>
                        <img id="signatureImage" style="display: none; max-width: 100%; height: auto;" alt="Signature">
                    </div>
                </div>
            </div>

            <!-- Signature Creation Options -->
            <div class="esignature-card">
                <div class="card-header">
                    <h3 class="card-title">Create Signature</h3>
                </div>
                <div class="card-body">
                    <div class="signature-options">
                        <button class="option-btn active" data-method="draw" onclick="selectMethod('draw')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                                <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                                <path d="M2 2l7.586 7.586"/>
                                <circle cx="11" cy="11" r="2"/>
                            </svg>
                            <span>Draw</span>
                        </button>
                        <button class="option-btn" data-method="type" onclick="selectMethod('type')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="4 7 4 4 20 4 20 7"/>
                                <line x1="9" y1="20" x2="15" y2="20"/>
                                <line x1="12" y1="4" x2="12" y2="20"/>
                            </svg>
                            <span>Type</span>
                        </button>
                        <button class="option-btn" data-method="upload" onclick="selectMethod('upload')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <span>Upload</span>
                        </button>
                    </div>

                    <!-- Draw Method -->
                    <div class="signature-method active" id="drawMethod">
                        <div class="drawing-area" id="drawingArea">
                            <canvas id="drawCanvas" width="600" height="200"></canvas>
                        </div>
                        <div class="draw-controls">
                            <button class="btn-secondary" onclick="clearDrawing()">Clear</button>
                            <input type="color" id="penColor" value="#000000" title="Pen Color">
                            <input type="range" id="penSize" min="1" max="10" value="2" title="Pen Size">
                            <span class="pen-size-label" id="penSizeLabel">2px</span>
                        </div>
                    </div>

                    <!-- Type Method -->
                    <div class="signature-method" id="typeMethod">
                        <div class="type-input-group">
                            <input type="text" class="form-input signature-text-input" id="signatureText" placeholder="Enter your name" value="John Doe">
                            <div class="font-options">
                                <label class="font-option">
                                    <input type="radio" name="fontStyle" value="cursive" checked onchange="updateTypedSignature()">
                                    <span>Cursive</span>
                                </label>
                                <label class="font-option">
                                    <input type="radio" name="fontStyle" value="script" onchange="updateTypedSignature()">
                                    <span>Script</span>
                                </label>
                                <label class="font-option">
                                    <input type="radio" name="fontStyle" value="handwriting" onchange="updateTypedSignature()">
                                    <span>Handwriting</span>
                                </label>
                            </div>
                            <div class="color-options">
                                <label>Color:</label>
                                <input type="color" id="textColor" value="#000000" onchange="updateTypedSignature()">
                            </div>
                        </div>
                        <div class="typed-preview" id="typedPreview">
                            <p class="signature-text-preview" id="signatureTextPreview">John Doe</p>
                        </div>
                    </div>

                    <!-- Upload Method -->
                    <div class="signature-method" id="uploadMethod">
                        <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <p>Click to upload or drag and drop</p>
                            <p class="upload-hint">PNG, JPG, SVG up to 2MB</p>
                            <input type="file" id="fileInput" accept="image/*" style="display: none;" onchange="handleFileUpload(event)">
                        </div>
                        <div class="uploaded-preview" id="uploadedPreview" style="display: none;">
                            <img id="uploadedImage" alt="Uploaded signature">
                            <button class="btn-secondary" onclick="removeUploaded()">Remove</button>
                        </div>
                    </div>

                    <div class="signature-actions">
                        <button class="btn-secondary" onclick="cancelSignature()">Cancel</button>
                        <button class="btn-primary" onclick="saveSignature()">Save Signature</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Signature Settings -->
        <div class="esignature-card">
            <div class="card-header">
                <h3 class="card-title">Signature Settings</h3>
            </div>
            <div class="card-body">
                <div class="setting-item">
                    <div class="setting-info">
                        <h4 class="setting-name">Default Signature</h4>
                        <p class="setting-description">Use this signature by default when signing documents</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="defaultSignature" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <h4 class="setting-name">Include Date</h4>
                        <p class="setting-description">Automatically include the date when signing</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="includeDate">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="setting-item">
                    <div class="setting-info">
                        <h4 class="setting-name">Include Title</h4>
                        <p class="setting-description">Include your job title below the signature</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="includeTitle">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="form-group" id="titleInputGroup" style="display: none;">
                    <label class="form-label">Job Title</label>
                    <input type="text" class="form-input" id="jobTitle" placeholder="e.g., CEO, Manager">
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .esignature-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .esignature-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }

    .esignature-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .card-body {
        padding: 1.5rem;
    }

    /* Signature Preview */
    .signature-preview {
        min-height: 200px;
        border: 2px dashed var(--border);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-primary);
        position: relative;
    }

    .preview-placeholder {
        text-align: center;
        color: var(--text-muted);
    }

    .preview-placeholder svg {
        width: 48px;
        height: 48px;
        margin-bottom: 0.5rem;
        color: var(--text-muted);
    }

    .preview-placeholder p {
        font-size: 0.875rem;
        margin: 0;
    }

    /* Signature Options */
    .signature-options {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .option-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .option-btn:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .option-btn.active {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .option-btn svg {
        width: 24px;
        height: 24px;
        color: var(--text-secondary);
    }

    .option-btn.active svg {
        color: var(--accent);
    }

    .option-btn span {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .option-btn.active span {
        color: var(--accent);
    }

    /* Signature Methods */
    .signature-method {
        display: none;
    }

    .signature-method.active {
        display: block;
    }

    /* Draw Method */
    .drawing-area {
        border: 1px solid var(--border);
        border-radius: 8px;
        background: white;
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .drawing-area canvas {
        display: block;
        cursor: crosshair;
        width: 100%;
        height: auto;
    }

    .draw-controls {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .pen-size-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        min-width: 40px;
    }

    /* Type Method */
    .type-input-group {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .signature-text-input {
        font-size: 1.25rem;
        padding: 0.75rem;
        text-align: center;
    }

    .font-options {
        display: flex;
        gap: 0.75rem;
        justify-content: center;
    }

    .font-option {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .font-option:hover {
        border-color: var(--accent);
    }

    .font-option input[type="radio"]:checked + span {
        color: var(--accent);
        font-weight: 600;
    }

    .color-options {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        justify-content: center;
    }

    .color-options label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .typed-preview {
        min-height: 150px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .signature-text-preview {
        margin: 0;
        font-size: 2rem;
        font-family: 'Brush Script MT', cursive;
    }

    .signature-text-preview.script {
        font-family: 'Lucida Handwriting', cursive;
    }

    .signature-text-preview.handwriting {
        font-family: 'Comic Sans MS', cursive;
    }

    /* Upload Method */
    .upload-area {
        border: 2px dashed var(--border);
        border-radius: 8px;
        padding: 3rem 2rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s;
        background: var(--bg-primary);
    }

    .upload-area:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .upload-area svg {
        width: 48px;
        height: 48px;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .upload-area p {
        margin: 0.5rem 0;
        color: var(--text-primary);
        font-weight: 500;
    }

    .upload-hint {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .uploaded-preview {
        text-align: center;
        margin-bottom: 1rem;
    }

    .uploaded-preview img {
        max-width: 100%;
        max-height: 200px;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-bottom: 0.75rem;
    }

    /* Signature Actions */
    .signature-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
    }

    /* Buttons */
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    /* Settings */
    .setting-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border);
    }

    .setting-item:last-child {
        border-bottom: none;
    }

    .setting-info {
        flex: 1;
        min-width: 0;
    }

    .setting-name {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .setting-description {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--border);
        transition: 0.3s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--accent);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(24px);
    }

    .form-group {
        margin-top: 1rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .esignature-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .signature-options {
            grid-template-columns: 1fr;
        }

        .drawing-area canvas {
            width: 100%;
            height: 150px;
        }

        .signature-text-preview {
            font-size: 1.5rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    let currentMethod = 'draw';
    let isDrawing = false;
    let signatureData = null;

    // Canvas setup for drawing
    const drawCanvas = document.getElementById('drawCanvas');
    const ctx = drawCanvas.getContext('2d');
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Method selection
    function selectMethod(method) {
        currentMethod = method;
        document.querySelectorAll('.option-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-method="${method}"]`).classList.add('active');
        document.querySelectorAll('.signature-method').forEach(m => m.classList.remove('active'));
        document.getElementById(`${method}Method`).classList.add('active');
    }

    // Drawing functionality
    function startDrawing(e) {
        isDrawing = true;
        const rect = drawCanvas.getBoundingClientRect();
        const scaleX = drawCanvas.width / rect.width;
        const scaleY = drawCanvas.height / rect.height;
        ctx.beginPath();
        ctx.moveTo((e.clientX - rect.left) * scaleX, (e.clientY - rect.top) * scaleY);
    }

    function draw(e) {
        if (!isDrawing) return;
        const rect = drawCanvas.getBoundingClientRect();
        const scaleX = drawCanvas.width / rect.width;
        const scaleY = drawCanvas.height / rect.height;
        ctx.lineTo((e.clientX - rect.left) * scaleX, (e.clientY - rect.top) * scaleY);
        ctx.stroke();
    }

    function stopDrawing() {
        isDrawing = false;
    }

    drawCanvas.addEventListener('mousedown', startDrawing);
    drawCanvas.addEventListener('mousemove', draw);
    drawCanvas.addEventListener('mouseup', stopDrawing);
    drawCanvas.addEventListener('mouseout', stopDrawing);

    // Touch events for mobile
    drawCanvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent('mousedown', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        drawCanvas.dispatchEvent(mouseEvent);
    });

    drawCanvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent('mousemove', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        drawCanvas.dispatchEvent(mouseEvent);
    });

    drawCanvas.addEventListener('touchend', (e) => {
        e.preventDefault();
        const mouseEvent = new MouseEvent('mouseup', {});
        drawCanvas.dispatchEvent(mouseEvent);
    });

    // Drawing controls
    document.getElementById('penColor').addEventListener('change', function() {
        ctx.strokeStyle = this.value;
    });

    document.getElementById('penSize').addEventListener('input', function() {
        ctx.lineWidth = this.value;
        document.getElementById('penSizeLabel').textContent = this.value + 'px';
    });

    function clearDrawing() {
        ctx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
    }

    // Typed signature
    function updateTypedSignature() {
        const text = document.getElementById('signatureText').value || 'Your Name';
        const fontStyle = document.querySelector('input[name="fontStyle"]:checked').value;
        const color = document.getElementById('textColor').value;
        const preview = document.getElementById('signatureTextPreview');
        
        preview.textContent = text;
        preview.className = 'signature-text-preview ' + fontStyle;
        preview.style.color = color;
    }

    document.getElementById('signatureText').addEventListener('input', updateTypedSignature);

    // File upload
    function handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('uploadedImage');
            img.src = e.target.result;
            document.getElementById('uploadArea').style.display = 'none';
            document.getElementById('uploadedPreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    function removeUploaded() {
        document.getElementById('fileInput').value = '';
        document.getElementById('uploadArea').style.display = 'block';
        document.getElementById('uploadedPreview').style.display = 'none';
    }

    // Save signature
    function saveSignature() {
        let signature = null;

        if (currentMethod === 'draw') {
            signature = drawCanvas.toDataURL('image/png');
        } else if (currentMethod === 'type') {
            const preview = document.getElementById('signatureTextPreview');
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = 600;
            canvas.height = 200;
            
            ctx.font = '48px "Brush Script MT", cursive';
            ctx.fillStyle = preview.style.color || '#000000';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(preview.textContent, canvas.width / 2, canvas.height / 2);
            
            signature = canvas.toDataURL('image/png');
        } else if (currentMethod === 'upload') {
            const img = document.getElementById('uploadedImage');
            if (img.src) {
                signature = img.src;
            } else {
                alert('Please upload a signature image');
                return;
            }
        }

        if (signature) {
            signatureData = signature;
            displaySignature(signature);
            alert('Signature saved successfully!');
        }
    }

    function displaySignature(signature) {
        const placeholder = document.getElementById('previewPlaceholder');
        const canvas = document.getElementById('signatureCanvas');
        const image = document.getElementById('signatureImage');
        
        placeholder.style.display = 'none';
        canvas.style.display = 'none';
        image.style.display = 'block';
        image.src = signature;
    }

    function clearSignature() {
        if (confirm('Are you sure you want to clear your signature?')) {
            signatureData = null;
            document.getElementById('previewPlaceholder').style.display = 'block';
            document.getElementById('signatureCanvas').style.display = 'none';
            document.getElementById('signatureImage').style.display = 'none';
            clearDrawing();
        }
    }

    function cancelSignature() {
        if (confirm('Cancel signature creation? All changes will be lost.')) {
            clearDrawing();
            document.getElementById('signatureText').value = 'John Doe';
            updateTypedSignature();
            removeUploaded();
        }
    }

    // Settings
    document.getElementById('includeTitle').addEventListener('change', function() {
        const titleGroup = document.getElementById('titleInputGroup');
        titleGroup.style.display = this.checked ? 'block' : 'none';
    });

    // Initialize
    updateTypedSignature();
</script>
@endpush

