<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>Hiring Assistant - {{ $company->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #ffffff;
            --accent: #2563eb;
            --accent-light: #e6f0ff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); min-height: 100vh; color: var(--text-primary); display: flex; flex-direction: column; }
        .chat-layout { flex: 1; display: flex; flex-direction: column; max-width: 768px; margin: 0 auto; width: 100%; padding: 0 1.5rem; }
        /* Header */
        .chat-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid var(--border); }
        .header-left { display: flex; align-items: center; gap: 0.75rem; }
        .header-logo { width: 40px; height: 40px; background: var(--accent-light); border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; color: var(--accent); }
        .header-logo img { width: 100%; height: 100%; object-fit: contain; }
        .header-logo svg { width: 22px; height: 22px; }
        .header-title { font-weight: 700; font-size: 1.125rem; }
        .header-subtitle { font-size: 0.8125rem; color: var(--text-muted); margin-top: 0.125rem; }
        .btn-back { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.5rem; background: transparent; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; color: var(--text-primary); text-decoration: none; transition: background 0.15s; margin-right: 0.5rem; }
        .btn-back:hover { background: #f9fafb; }
        .btn-back svg { width: 18px; height: 18px; }
        .btn-new { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; background: transparent; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); cursor: pointer; transition: background 0.15s; }
        .btn-new:hover { background: #f9fafb; }
        .btn-new svg { width: 16px; height: 16px; }
        /* Chat area */
        .chat-area { flex: 1; padding: 2rem 0; min-height: 400px; overflow-y: auto; }
        .message { display: flex; gap: 0.75rem; max-width: 600px; margin: 0 auto 1.5rem; }
        .message-avatar { flex-shrink: 0; width: 36px; height: 36px; background: var(--accent-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--accent); }
        .message-avatar svg { width: 18px; height: 18px; }
        .message-content { flex: 1; }
        .message-text { font-size: 0.9375rem; line-height: 1.6; color: var(--text-primary); }
        .message-text strong { font-weight: 600; }
        .message.user-msg { flex-direction: row-reverse; }
        .message.user-msg .message-avatar { background: var(--accent); color: white; }
        .message.user-msg .message-content { text-align: right; }
        .message.loading .message-text::after { content: ''; display: inline-block; width: 4px; height: 1em; background: var(--accent); margin-left: 2px; animation: blink 1s infinite; vertical-align: -0.2em; }
        @keyframes blink { 50% { opacity: 0; } }
        .save-queue-card { display: none; max-width: 400px; margin: 0 auto 1rem; padding: 1.5rem; background: var(--accent-light); border: 1px solid #bfdbfe; border-radius: 12px; text-align: center; }
        .save-queue-card.visible { display: block; }
        .save-queue-card .check-icon { width: 48px; height: 48px; margin: 0 auto 1rem; background: var(--accent); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .save-queue-card h4 { font-size: 1rem; margin-bottom: 0.5rem; }
        .save-queue-card p { font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem; }
        .save-queue-card .btn-save { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: var(--accent); color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.9375rem; cursor: pointer; }
        .save-queue-card .btn-save:hover:not(:disabled) { background: #1d4ed8; }
        .save-queue-card .btn-save:disabled { opacity: 0.8; cursor: not-allowed; }
        .save-queue-card .btn-save svg { width: 20px; height: 20px; }
        /* Input */
        .input-area { padding: 1.5rem 0 2rem; }
        .input-wrap { display: flex; align-items: flex-end; gap: 0.5rem; background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 0.5rem 0.75rem 0.5rem 1rem; }
        .input-wrap:focus-within { border-color: var(--accent); outline: 2px solid rgba(37, 99, 235, 0.1); outline-offset: 0; }
        .chat-input { flex: 1; border: none; background: transparent; font-size: 0.9375rem; padding: 0.5rem 0; resize: none; min-height: 24px; max-height: 120px; font-family: inherit; }
        .chat-input::placeholder { color: var(--text-muted); }
        .chat-input:focus { outline: none; }
        .btn-send { width: 36px; height: 36px; border-radius: 50%; background: var(--accent); color: white; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: background 0.15s; }
        .btn-send:hover { background: #1d4ed8; }
        .btn-send:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-send svg { width: 18px; height: 18px; }
        .input-hint { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; text-align: center; }
        /* Disabled / suspended notice */
        .disabled-notice { display: flex; align-items: flex-start; gap: 0.75rem; max-width: 600px; margin: 0 auto 1.5rem; padding: 1rem 1.25rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; color: #991b1b; }
        .disabled-notice svg { width: 22px; height: 22px; flex-shrink: 0; color: #dc2626; }
        .disabled-notice .notice-title { font-weight: 600; font-size: 0.9375rem; margin-bottom: 0.25rem; }
        .disabled-notice .notice-text { font-size: 0.875rem; line-height: 1.5; }
        .input-wrap.is-disabled { background: #f9fafb; opacity: 0.6; cursor: not-allowed; }
        .chat-input:disabled { cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="chat-layout">
        <header class="chat-header">
            <div class="header-left">
                <a href="{{ url('/') }}" class="btn-back" title="Back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back
                </a>
                <div class="header-logo">
                    @if($company->logo)
                        <img src="{{ public_media_url($company->logo) }}" alt="{{ $company->name }}">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15 9 22 9 17 14 19 22 12 18 5 22 7 14 2 9 9 9"/>
                        </svg>
                    @endif
                </div>
                <div>
                    <div class="header-title">Hiring Assistant</div>
                    <div class="header-subtitle">AI-powered job description builder.</div>
                </div>
            </div>
            <button type="button" class="btn-new" onclick="window.location.reload()">
                New
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 4v6h6"/>
                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                </svg>
            </button>
        </header>

        <main class="chat-area" id="chatArea">
            @if(! empty($disabled))
                <div class="disabled-notice" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <div>
                        <div class="notice-title">Hiring Assistant disabled</div>
                        <div class="notice-text">{{ $disabledReason }}</div>
                    </div>
                </div>
            @endif
            <div class="message" data-role="assistant">
                <div class="message-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15 9 22 9 17 14 19 22 12 18 5 22 7 14 2 9 9 9"/>
                    </svg>
                </div>
                <div class="message-content">
                    <div class="message-text">
                        <p style="margin-bottom: 0.75rem;">👋 Hi there! I'm your hiring assistant. I'll help you create a detailed job description in just a few minutes.</p>
                        <p>First off — <strong>are you a client hiring directly, or a sales rep creating this for a client?</strong> I'll also collect the client company name and client email as part of this flow.</p>
                    </div>
                </div>
            </div>
        </main>

        <footer class="input-area">
            <div class="save-queue-card" id="saveQueueCard">
                <div class="check-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h4>Job description is ready!</h4>
                <p>Confirm to add it to the hiring queue.</p>
                <button type="button" class="btn-save" id="saveQueueBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <span id="saveQueueBtnText">Add to Hiring Queue</span>
                </button>
            </div>
            <div class="input-wrap {{ ! empty($disabled) ? 'is-disabled' : '' }}">
                <textarea class="chat-input" id="message" placeholder="{{ ! empty($disabled) ? 'Hiring Assistant is currently unavailable' : 'Type your message...' }}" rows="1" {{ ! empty($disabled) ? 'disabled' : '' }}></textarea>
                <button type="button" class="btn-send" id="sendBtn" aria-label="Send" {{ ! empty($disabled) ? 'disabled' : '' }}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 2L11 13"/>
                        <path d="M22 2L15 22L11 13L2 9L22 2Z"/>
                    </svg>
                </button>
            </div>
            <p class="input-hint">Press Enter to send · Shift+Enter for new line</p>
        </footer>
    </div>

    <script>
        const chatArea = document.getElementById('chatArea');
        const textarea = document.getElementById('message');
        const sendBtn = document.getElementById('sendBtn');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const creatorName = @json(optional(auth()->user())->name ?? optional(auth()->user())->email);
        const companyName = @json($company->name);
        const assistantDisabled = @json((bool) ($disabled ?? false));

        let messages = [
            { role: 'assistant', content: "👋 Hi there! I'm your hiring assistant. I'll help you create a detailed job description in just a few minutes.\n\nFirst off — **are you a client hiring directly, or a sales rep creating this for a client?** I'll also collect the client company name and client email as part of this flow." }
        ];

        function formatText(text) {
            return text
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
        }

        function addMessage(role, content, isLoading = false) {
            const div = document.createElement('div');
            div.className = 'message' + (role === 'user' ? ' user-msg' : '') + (isLoading ? ' loading' : '');
            div.dataset.role = role;
            div.innerHTML = `
                <div class="message-avatar">
                    ${role === 'user' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>' : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15 9 22 9 17 14 19 22 12 18 5 22 7 14 2 9 9 9"/></svg>'}
                </div>
                <div class="message-content">
                    <div class="message-text">${isLoading ? '' : formatText(content)}</div>
                </div>
            `;
            chatArea.appendChild(div);
            chatArea.scrollTop = chatArea.scrollHeight;
            return div;
        }

        function updateMessage(el, content) {
            el.classList.remove('loading');
            el.querySelector('.message-text').innerHTML = formatText(content);
            chatArea.scrollTop = chatArea.scrollHeight;
        }

        function resizeTextarea() {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }
        textarea.addEventListener('input', resizeTextarea);

        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        async function sendMessage() {
            if (assistantDisabled) return;
            const msg = textarea.value.trim();
            if (!msg) return;

            textarea.value = '';
            resizeTextarea();
            sendBtn.disabled = true;

            messages.push({ role: 'user', content: msg });
            addMessage('user', msg);

            const loadingEl = addMessage('assistant', '', true);

            try {
                const response = await fetch('{{ route("api.hiring-assistant.chat") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ messages })
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Something went wrong');
                }

                messages.push({ role: 'assistant', content: data.content });
                updateMessage(loadingEl, data.content);
                checkAndShowSaveOption(data.content);
            } catch (err) {
                loadingEl.remove();
                addMessage('assistant', 'Sorry, ' + (err.message || 'I encountered an error. Please try again.'));
            }
            sendBtn.disabled = false;
        }
        sendBtn.addEventListener('click', sendMessage);

        function isJobDescriptionComplete(content) {
            if (!content || typeof content !== 'string') return false;
            const hasHeader = /Job Description:\s*.+/i.test(content);
            const hasStructure = /Key Responsibilities|Compensation|Summary/i.test(content);
            const hasClientEmail = /Client Email:\s*[^\s]+@[^\s]+/i.test(content);
            return hasHeader && hasStructure && hasClientEmail;
        }

        function extractJobTitle(content) {
            const m = content.match(/Job Description:\s*(.+?)(?:\n|$)/i);
            return m ? m[1].trim() : 'Job Position';
        }

        function extractClientCompany(content) {
            const m = content.match(/Client Company:\s*(.+?)(?:\n|$)/i);
            return m ? m[1].trim() : '';
        }

        function extractClientEmail(content) {
            const m = content.match(/Client Email:\s*([^\s\r\n]+@[^\s\r\n]+)/i);
            return m ? m[1].trim() : '';
        }

        function sanitizeDescriptionForQueue(content) {
            if (!content || typeof content !== 'string') return '';

            let clean = content.trim();
            const jobHeaderMatch = clean.match(/Job Description\s*:/i);
            if (jobHeaderMatch && typeof jobHeaderMatch.index === 'number') {
                clean = clean.slice(jobHeaderMatch.index);
            }

            clean = clean
                .replace(/^\s*---+\s*$/gm, '')
                .replace(/\n?\s*Let me know if.*$/is, '')
                .replace(/\n{3,}/g, '\n\n')
                .trim();

            return clean;
        }

        function checkAndShowSaveOption(content) {
            if (isJobDescriptionComplete(content)) {
                const card = document.getElementById('saveQueueCard');
                card.classList.add('visible');
                card.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        }

        document.getElementById('saveQueueBtn').addEventListener('click', async function() {
            const btn = this;
            const lastAssistant = messages.filter(m => m.role === 'assistant').pop();
            if (!lastAssistant || !isJobDescriptionComplete(lastAssistant.content)) return;
            const sanitizedDescription = sanitizeDescriptionForQueue(lastAssistant.content);
            const extractedClientCompany = extractClientCompany(sanitizedDescription);
            const extractedClientEmail = extractClientEmail(sanitizedDescription);

            if (!extractedClientEmail) {
                addMessage('assistant', 'Please provide the client email first before saving to hiring queue.');
                return;
            }

            btn.disabled = true;
            document.getElementById('saveQueueBtnText').textContent = 'Adding...';
            try {
                const r = await fetch('{{ route("api.hiring-queue.save-from-assistant") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        job_title: extractJobTitle(sanitizedDescription),
                        full_description: sanitizedDescription,
                        source: extractedClientCompany || extractedClientEmail || companyName || 'Client',
                        client_email: extractedClientEmail || null,
                        creator_name: creatorName
                    })
                });
                const data = await r.json();
                if (r.ok) {
                    document.getElementById('saveQueueBtnText').textContent = 'Added!';
                    addMessage('assistant', '✅ Job description has been added to the hiring queue. You can manage it and add candidates from the Hiring Queue page.');
                    document.getElementById('saveQueueCard').classList.remove('visible');
                } else {
                    throw new Error(data.error || 'Failed to save');
                }
            } catch (e) {
                document.getElementById('saveQueueBtnText').textContent = 'Add to Hiring Queue';
                btn.disabled = false;
                addMessage('assistant', 'Sorry, could not add to hiring queue. ' + (e.message || 'Please try again.'));
            }
        });
    </script>
</body>
</html>
