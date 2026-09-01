@extends('layouts.app')

@section('title', 'Microsoft 365 Mail')

@section('content')
    @if(session('status') === 'outlook-mail-connected')
        <div class="m365-alert success" role="alert">Microsoft 365 mailbox connected. You can now send quotation emails from this account.</div>
    @endif
    @if(session('error'))
        <div class="m365-alert error" role="alert">{{ session('error') }}</div>
    @endif

    <div class="page-header">
        <h1 class="page-title">Microsoft 365 Mail</h1>
        <p class="page-subtitle">
            Sign in with Microsoft 365 to send quotation and storage quote emails from Quotation Builder.
            If not connected here, outbound email falls back to Gmail when configured in <a href="{{ route('integrations') }}">Integrations</a>.
        </p>
    </div>

    <div class="m365-mail-container">
        <div class="m365-mail-card">
            <div class="m365-mail-card-header">
                <div class="m365-mail-status" id="m365-status-badge" data-status="loading">Loading…</div>
            </div>

            <div class="m365-mail-body">
                <p class="m365-mail-lead">
                    Connect the Microsoft 365 mailbox that should appear as the sender for quote emails sent from this CRM.
                </p>

                @if(empty($outlookConfigured))
                    <div class="m365-alert error" role="alert">
                        Microsoft OAuth is not configured yet. Add Microsoft Client ID and Client Secret in
                        <a href="{{ route('integrations') }}">Integrations</a> first, then return here to sign in.
                    </div>
                @else
                    <div class="m365-mail-actions">
                        <a href="{{ route('inbox.connect.outlook', ['intent' => 'quotation']) }}" class="btn-primary" id="m365-sign-in-btn">
                            Sign in M365
                        </a>
                        <button type="button" class="btn-secondary" id="m365-disconnect-btn" style="display: none;">Disconnect</button>
                    </div>
                @endif

                <p class="form-help">
                    Uses the same Microsoft sign-in flow as Broadcast Messaging. The connected account is shared for all quotation emails in your company.
                </p>
            </div>

            <div id="m365-alert" class="m365-alert" style="display: none;" role="alert"></div>
        </div>
    </div>

    <style>
        .m365-mail-container {
            max-width: 640px;
        }

        .m365-mail-card {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border-color, #e5e7eb);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .m365-mail-card-header {
            margin-bottom: 1.25rem;
        }

        .m365-mail-status {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .m365-mail-status[data-status="connected"] {
            background: #dcfce7;
            color: #166534;
        }

        .m365-mail-status[data-status="disconnected"] {
            background: #f3f4f6;
            color: #4b5563;
        }

        .m365-mail-status[data-status="loading"] {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .m365-mail-lead {
            margin: 0 0 1rem;
            color: var(--text-muted, #4b5563);
        }

        .form-help {
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--text-muted, #6b7280);
        }

        .m365-mail-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .m365-alert {
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .m365-alert.success {
            background: #dcfce7;
            color: #166534;
        }

        .m365-alert.error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>

    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const apiUrl = @json(route('api.quotation-builder.microsoft-365-mail.get'));
            const statusBadge = document.getElementById('m365-status-badge');
            const disconnectBtn = document.getElementById('m365-disconnect-btn');
            const signInBtn = document.getElementById('m365-sign-in-btn');
            const alertEl = document.getElementById('m365-alert');
            let hasIntegration = false;

            function setStatus(status, label) {
                statusBadge.dataset.status = status;
                statusBadge.textContent = label;
            }

            function showAlert(message, type) {
                alertEl.textContent = message;
                alertEl.className = 'm365-alert ' + type;
                alertEl.style.display = 'block';
            }

            async function loadIntegration() {
                setStatus('loading', 'Loading…');
                try {
                    const response = await fetch(apiUrl);
                    const data = await response.json();
                    hasIntegration = data.status === 'connected' && data.integration;

                    if (hasIntegration) {
                        const email = data.integration.email || '';
                        const name = data.integration.name ? data.integration.name + ' (' + email + ')' : email;
                        setStatus('connected', 'Connected: ' + name);
                        if (disconnectBtn) {
                            disconnectBtn.style.display = 'inline-flex';
                        }
                        if (signInBtn) {
                            signInBtn.textContent = 'Reconnect M365';
                        }
                    } else {
                        setStatus('disconnected', 'Not connected');
                        if (disconnectBtn) {
                            disconnectBtn.style.display = 'none';
                        }
                        if (signInBtn) {
                            signInBtn.textContent = 'Sign in M365';
                        }
                    }
                } catch (e) {
                    setStatus('disconnected', 'Could not load connection status');
                }
            }

            if (disconnectBtn) {
                disconnectBtn.addEventListener('click', async function () {
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
            }

            loadIntegration();
        })();
    </script>
@endsection
