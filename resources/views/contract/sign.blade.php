<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign Contract - {{ $contract->title }}</title>
    <style>
        :root {
            --accent: #5f61e6;
            --accent-hover: #4f51d6;
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; min-height: 100vh; }
        .container { max-width: 960px; margin: 0 auto; padding: 2rem 1rem; }
        .brand { text-align: center; margin-bottom: 2rem; }
        .brand-logo { max-height: 56px; max-width: 180px; margin-bottom: 0.75rem; object-fit: contain; }
        .brand h1 { font-size: 1.25rem; color: var(--muted); font-weight: 500; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .card h2 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .meta { color: var(--muted); font-size: 0.875rem; margin-bottom: 1rem; }
        .contract-document { margin-top: 0.5rem; }
        .contract-document-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
        .contract-document-label { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.25rem; }
        .contract-document-ref { font-size: 1.125rem; font-weight: 600; color: var(--text); margin-bottom: 0.5rem; }
        .contract-document-meta { display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; font-size: 0.875rem; color: var(--muted); }
        .contract-parties { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .contract-party { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 1rem; }
        .contract-section-title { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }
        .contract-party-info { font-size: 0.9375rem; line-height: 1.7; color: var(--text); }
        .contract-party-info strong { font-size: 1rem; }
        .contract-title-block { text-align: center; margin: 0 0 1.5rem; padding: 1rem 1.25rem; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; }
        .contract-title-block h2 { font-size: 1.375rem; line-height: 1.35; }
        .contract-agreement { margin-bottom: 0.5rem; }
        .contract-agreement-body { background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem 1.5rem; font-size: 0.9375rem; line-height: 1.7; color: var(--text); }
        .contract-agreement-body p { margin-bottom: 0.75rem; }
        .contract-agreement-body ul, .contract-agreement-body ol { margin: 0.5rem 0 0.75rem 1.5rem; }
        .contract-agreement-body li { margin-bottom: 0.35rem; }
        .contract-agreement-body h2 { font-size: 1.125rem; font-weight: 700; margin: 1rem 0 0.5rem; }
        .contract-agreement-body h3 { font-size: 1rem; font-weight: 600; margin: 0.85rem 0 0.4rem; }
        .contract-agreement-body blockquote { margin: 0.75rem 0; padding: 0.75rem 1rem; border-left: 4px solid var(--accent); background: #f8fafc; color: var(--muted); font-style: italic; }
        .contract-agreement-body a { color: var(--accent); }
        .contract-empty-content { color: var(--muted); font-style: italic; }
        @media (max-width: 720px) {
            .contract-parties { grid-template-columns: 1fr; }
        }
        .alert { padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .sign-section h3 { font-size: 1.125rem; margin-bottom: 1rem; }
        .signer-info { font-size: 0.875rem; color: var(--muted); margin-bottom: 1rem; }
        .drawing-area { border: 2px dashed var(--border); border-radius: 8px; background: white; margin-bottom: 1rem; }
        .drawing-area canvas { display: block; width: 100%; height: 180px; cursor: crosshair; }
        .controls { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.625rem 1.25rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer; border: none; transition: background 0.15s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-secondary { background: white; color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--bg); }
        .checkbox-row { display: flex; align-items: flex-start; gap: 0.75rem; margin: 1.25rem 0; font-size: 0.875rem; }
        .checkbox-row input { margin-top: 0.2rem; }
        .type-input { width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 1.25rem; text-align: center; margin-bottom: 1rem; }
        .typed-preview { min-height: 120px; border: 1px solid var(--border); border-radius: 8px; background: white; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
        .typed-preview p { font-size: 2rem; font-family: 'Brush Script MT', cursive; }
        .method-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
        .method-tab { padding: 0.5rem 1rem; border: 1px solid var(--border); border-radius: 8px; background: white; cursor: pointer; font-size: 0.875rem; }
        .method-tab.active { border-color: var(--accent); background: #eef0ff; color: var(--accent); }
        .method-panel { display: none; }
        .method-panel.active { display: block; }
        .footer { text-align: center; color: var(--muted); font-size: 0.8125rem; margin-top: 2rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">
            @if($contract->company?->logoUrl())
                <img src="{{ $contract->company->logoUrl() }}" alt="{{ $contract->company->name }}" class="brand-logo">
            @endif
            <h1>{{ $contract->company->name ?? 'Contract Signing' }}</h1>
        </div>

        <div class="card">
            <h2 style="font-size:1.125rem;margin-bottom:0.25rem;">Review Contract</h2>
            <div class="meta">Please read the full agreement below before signing.</div>
            @include('contract.partials.sign-document', ['contract' => $contract])
        </div>

        @if($contractComplete || $alreadySigned)
            <div class="alert alert-success">
                @if($alreadySigned)
                    You have already signed this contract. Thank you!
                @else
                    This contract has been fully signed by all parties.
                @endif
            </div>
        @elseif($invalid || $expired)
            <div class="alert alert-error">
                This signing link is invalid or has expired. Please contact the sender for a new link.
            </div>
        @else
            <div class="card sign-section">
                <h3>Sign as {{ $signer->name }}</h3>
                <div class="signer-info">{{ $signer->email }} &mdash; {{ ucfirst($signer->role) }} signer</div>

                <div class="method-tabs">
                    <button type="button" class="method-tab active" data-method="draw">Draw</button>
                    <button type="button" class="method-tab" data-method="type">Type</button>
                </div>

                <div class="method-panel active" id="panel-draw">
                    <div class="drawing-area">
                        <canvas id="drawCanvas" width="800" height="180"></canvas>
                    </div>
                    <div class="controls">
                        <button type="button" class="btn btn-secondary" id="clearDraw">Clear</button>
                        <input type="color" id="penColor" value="#000000" title="Pen color">
                        <input type="range" id="penSize" min="1" max="8" value="2" title="Pen size">
                    </div>
                </div>

                <div class="method-panel" id="panel-type">
                    <input type="text" class="type-input" id="signatureText" value="{{ $signer->name }}" placeholder="Type your full name">
                    <div class="typed-preview">
                        <p id="typedPreview">{{ $signer->name }}</p>
                    </div>
                </div>

                <label class="checkbox-row">
                    <input type="checkbox" id="agreeTerms">
                    <span>I have read and agree to the terms of this contract. I understand that my electronic signature is legally binding.</span>
                </label>

                <button type="button" class="btn btn-primary" id="submitSignature" disabled>Submit Signature</button>
                <p id="signMessage" style="margin-top: 1rem; font-size: 0.875rem; display: none;"></p>
            </div>
        @endif

        <div class="footer">
            Secure electronic signature &mdash; {{ $contract->company->name ?? 'Logon' }}
        </div>
    </div>

    @if(!$contractComplete && !$alreadySigned && !$invalid && !$expired)
    <script>
        const token = @json($token);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let currentMethod = 'draw';

        const canvas = document.getElementById('drawCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasDrawn = false;

        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
        }

        function startDraw(e) { isDrawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); }
        function draw(e) {
            if (!isDrawing) return;
            const p = getPos(e);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            hasDrawn = true;
            e.preventDefault();
        }
        function stopDraw() { isDrawing = false; }

        canvas.addEventListener('mousedown', startDraw);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDraw);
        canvas.addEventListener('mouseout', stopDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDraw);

        document.getElementById('penColor').addEventListener('change', e => ctx.strokeStyle = e.target.value);
        document.getElementById('penSize').addEventListener('input', e => ctx.lineWidth = e.target.value);
        document.getElementById('clearDraw').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
        });

        document.querySelectorAll('.method-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                currentMethod = tab.dataset.method;
                document.querySelectorAll('.method-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.method-panel').forEach(p => p.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('panel-' + currentMethod).classList.add('active');
            });
        });

        const signatureText = document.getElementById('signatureText');
        const typedPreview = document.getElementById('typedPreview');
        signatureText.addEventListener('input', () => {
            typedPreview.textContent = signatureText.value || 'Your Name';
        });

        const agreeTerms = document.getElementById('agreeTerms');
        const submitBtn = document.getElementById('submitSignature');
        agreeTerms.addEventListener('change', () => submitBtn.disabled = !agreeTerms.checked);

        function getTypedSignature() {
            const text = signatureText.value.trim();
            if (!text) return null;
            const off = document.createElement('canvas');
            off.width = 600;
            off.height = 160;
            const c = off.getContext('2d');
            c.font = '48px "Brush Script MT", cursive';
            c.fillStyle = '#000';
            c.textAlign = 'center';
            c.textBaseline = 'middle';
            c.fillText(text, 300, 80);
            return off.toDataURL('image/png');
        }

        submitBtn.addEventListener('click', async () => {
            let signature = null;
            if (currentMethod === 'draw') {
                if (!hasDrawn) {
                    alert('Please draw your signature first.');
                    return;
                }
                signature = canvas.toDataURL('image/png');
            } else {
                signature = getTypedSignature();
                if (!signature) {
                    alert('Please type your name.');
                    return;
                }
            }

            submitBtn.disabled = true;
            const msg = document.getElementById('signMessage');

            try {
                const res = await fetch(`/contracts/sign/${token}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ signature, method: currentMethod }),
                });
                const data = await res.json();
                msg.style.display = 'block';
                if (data.success) {
                    msg.style.color = '#065f46';
                    msg.textContent = data.message;
                    document.querySelector('.sign-section').querySelectorAll('button, input, canvas').forEach(el => el.disabled = true);
                } else {
                    msg.style.color = '#991b1b';
                    msg.textContent = data.message || 'Failed to submit signature.';
                    submitBtn.disabled = false;
                }
            } catch (e) {
                msg.style.display = 'block';
                msg.style.color = '#991b1b';
                msg.textContent = 'An error occurred. Please try again.';
                submitBtn.disabled = false;
            }
        });
    </script>
    @endif
</body>
</html>
