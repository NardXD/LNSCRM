@php
    $voiceWebhook = route('twilio.voice');
    $smsWebhook = route('twilio.sms-webhook');
    $integrationsUrl = route('integrations');
    $smsPageUrl = route('sms');
@endphp
<details class="phone-setup-tips">
    <summary class="phone-setup-tips-summary">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 16v-4M12 8h.01"/>
        </svg>
        Setup checklist — connect Twilio to use Phone System
    </summary>
    <div class="phone-setup-tips-body">
        <ol class="phone-setup-steps">
            <li>
                <strong>Connect your company Twilio account</strong> in
                <a href="{{ $integrationsUrl }}">Integrations</a>:
                Account SID, Auth Token, and (for browser calling) App SID, API Key, and API Secret.
            </li>
            <li>
                <strong>Phone numbers</strong> — Company admins with <em>Manage Twilio Numbers</em> can search and buy numbers on the <strong>Numbers</strong> tab, or buy in the Twilio Console and click <strong>Sync</strong>.
            </li>
            <li>
                <strong>Assign a number</strong> to each employee who will make calls or send SMS (Numbers tab → Assign, or User Management).
            </li>
            <li>
                <strong>Twilio Console webhooks</strong> (required for inbound calls, call history, and SMS if numbers were not bought in-app):
                <ul>
                    <li>Voice URL: <code class="phone-setup-code">{{ $voiceWebhook }}</code></li>
                    <li>SMS URL: <code class="phone-setup-code">{{ $smsWebhook }}</code></li>
                </ul>
                Set both to <strong>HTTP POST</strong>. Numbers purchased in-app are configured automatically.
            </li>
            <li>
                <strong>SMS conversations</strong> are on the dedicated
                <a href="{{ $smsPageUrl }}">SMS</a> page in the sidebar (alongside Viber and WhatsApp).
            </li>
            <li>
                <strong>Call recording</strong> — Calls are recorded automatically. Listen from <strong>Call History</strong> after a call completes. Inbound callers hear a short “may be recorded” notice.
            </li>
            <li>
                <strong>Employees</strong> need an assigned Twilio number before outbound calls or SMS will work.
            </li>
        </ol>
        <p class="phone-setup-note">Each company uses its own Twilio account. Trial accounts may need verified caller IDs and upgraded accounts for purchasing numbers.</p>
    </div>
</details>

@once
@push('styles')
<style>
    .phone-setup-tips {
        margin-bottom: 1.25rem;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: var(--bg-card);
        max-width: 1400px;
    }
    .phone-setup-tips-summary {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.85rem 1rem;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--text-primary);
        list-style: none;
    }
    .phone-setup-tips-summary::-webkit-details-marker { display: none; }
    .phone-setup-tips-body {
        padding: 0 1rem 1rem 1rem;
        font-size: 0.85rem;
        color: var(--text-secondary);
        line-height: 1.55;
    }
    .phone-setup-steps { margin: 0; padding-left: 1.25rem; }
    .phone-setup-steps li { margin-bottom: 0.65rem; }
    .phone-setup-steps ul { margin: 0.35rem 0 0; padding-left: 1rem; }
    .phone-setup-code {
        display: inline-block;
        margin-top: 0.2rem;
        padding: 0.15rem 0.4rem;
        font-size: 0.78rem;
        word-break: break-all;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 4px;
    }
    .phone-setup-note {
        margin: 0.75rem 0 0;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border);
        font-size: 0.8rem;
    }
</style>
@endpush
@endonce
