@extends('layouts.app')

@section('title', 'Microsoft 365 Mail')

@section('content')
    <div class="ld-page">
        <div class="ld-top">
            <div class="ld-top-main">
                <h1 class="ld-title">Microsoft 365 Mail</h1>
                <p class="ld-subtitle">Connect a Microsoft 365 mailbox to send storage quote emails from Quotation Builder.</p>
            </div>
        </div>

        @if(session('status') === 'outlook-mail-connected')
            <div class="ld-flash success" role="alert">Microsoft 365 mailbox connected. You can now send quotation emails from this account.</div>
        @endif
        @if(session('error'))
            <div class="ld-flash error" role="alert">{{ session('error') }}</div>
        @endif

        <div class="ld-settings-layout">
            <div class="ld-settings-card">
                <div class="ld-settings-card-header">
                    <div class="ld-settings-icon m365">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                    </div>
                    <div class="ld-settings-heading">
                        <h2>Sender mailbox</h2>
                        <p>Sign in with the Microsoft 365 account that should appear as the sender when you email a quote. If not connected, outbound email falls back to Gmail from <a href="{{ route('integrations') }}">Integrations</a>.</p>
                    </div>
                </div>

                <div class="ld-status-row">
                    <span class="ld-status-pill" id="m365-status-badge" data-status="loading">Loading connection…</span>
                </div>

                <div class="ld-connected-box" id="m365-connected-box">
                    <span class="ld-connected-label">Connected mailbox</span>
                    <div class="ld-connected-email" id="m365-connected-email"></div>
                    <div class="ld-connected-name" id="m365-connected-name"></div>
                </div>

                @if(empty($outlookConfigured))
                    <div class="ld-flash error" role="alert">
                        Microsoft OAuth is not configured yet. Add Microsoft Client ID and Client Secret in
                        <a href="{{ route('integrations') }}">Integrations</a>, then return here to sign in.
                    </div>
                @else
                    <div class="ld-form-actions" style="border-top: none; padding-top: 0; margin-top: 0;">
                        <a href="{{ route('inbox.connect.outlook', ['intent' => 'quotation']) }}" class="btn btn-primary btn-sm" id="m365-sign-in-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            Sign in with Microsoft 365
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" id="m365-disconnect-btn" style="display: none;">Disconnect</button>
                    </div>
                @endif

                <div id="m365-alert" class="ld-flash ld-inline-alert" style="display: none;" role="alert"></div>
            </div>

            <aside class="ld-settings-aside">
                <h3 class="ld-aside-title">How it works</h3>
                <p class="ld-aside-text">This uses the same Microsoft sign-in flow as Broadcast Messaging.</p>
                <ol class="ld-steps">
                    <li>
                        <span class="ld-step-num">1</span>
                        <span>Configure Microsoft OAuth in <a href="{{ route('integrations') }}">Integrations</a>.</span>
                    </li>
                    <li>
                        <span class="ld-step-num">2</span>
                        <span>Click <strong>Sign in with Microsoft 365</strong> and choose the sender mailbox.</span>
                    </li>
                    <li>
                        <span class="ld-step-num">3</span>
                        <span>Send quotes from the storage quote page — emails go out from this account.</span>
                    </li>
                </ol>
            </aside>
        </div>
    </div>
@endsection

@push('styles')
    @include('partials.leads-page-base-styles')
@endpush

@push('scripts')
<script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const apiUrl = @json(route('api.quotation-builder.microsoft-365-mail.get'));
        const statusBadge = document.getElementById('m365-status-badge');
        const disconnectBtn = document.getElementById('m365-disconnect-btn');
        const signInBtn = document.getElementById('m365-sign-in-btn');
        const alertEl = document.getElementById('m365-alert');
        const connectedBox = document.getElementById('m365-connected-box');
        const connectedEmail = document.getElementById('m365-connected-email');
        const connectedName = document.getElementById('m365-connected-name');
        let hasIntegration = false;

        function setStatus(status, label) {
            statusBadge.dataset.status = status;
            statusBadge.textContent = label;
        }

        function showAlert(message, type) {
            alertEl.textContent = message;
            alertEl.className = 'ld-flash ld-inline-alert ' + type;
            alertEl.style.display = 'block';
        }

        async function loadIntegration() {
            setStatus('loading', 'Loading connection…');
            try {
                const response = await fetch(apiUrl);
                const data = await response.json();
                hasIntegration = data.status === 'connected' && data.integration;

                if (hasIntegration) {
                    const email = data.integration.email || '';
                    const name = data.integration.name || '';
                    setStatus('connected', 'Connected');
                    connectedBox?.classList.add('visible');
                    if (connectedEmail) connectedEmail.textContent = email;
                    if (connectedName) connectedName.textContent = name && name !== email ? name : '';
                    disconnectBtn && (disconnectBtn.style.display = 'inline-flex');
                    if (signInBtn) signInBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> Reconnect Microsoft 365';
                } else {
                    setStatus('disconnected', 'Not connected');
                    connectedBox?.classList.remove('visible');
                    disconnectBtn && (disconnectBtn.style.display = 'none');
                    if (signInBtn) signInBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg> Sign in with Microsoft 365';
                }
            } catch (e) {
                setStatus('disconnected', 'Could not load status');
            }
        }

        disconnectBtn?.addEventListener('click', async function () {
            if (!confirm('Disconnect Microsoft 365? Quotation emails will fall back to Gmail if configured.')) {
                return;
            }

            disconnectBtn.disabled = true;

            try {
                const response = await fetch(apiUrl, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                });

                const data = await response.json();

                if (!response.ok) {
                    showAlert(data.message || data.error || 'Failed to disconnect.', 'error');
                    return;
                }

                showAlert(data.message || 'Disconnected.', 'success');
                await loadIntegration();
            } catch (err) {
                showAlert('Error disconnecting. Please try again.', 'error');
            } finally {
                disconnectBtn.disabled = false;
            }
        });

        loadIntegration();
    })();
</script>
@endpush
