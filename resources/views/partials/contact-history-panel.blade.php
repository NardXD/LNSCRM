{{-- Shared contact-history side panel. Pass $panelId (default: contactHistoryPanel). --}}
@php
    $panelId = $panelId ?? 'contactHistoryPanel';
@endphp
<aside class="chp-panel" id="{{ $panelId }}" hidden
       data-api="/api/crm/contact-history">
    <div class="chp-header">
        <strong>Contact history</strong>
        <span class="chp-hint">All channels</span>
    </div>
    <div class="chp-body" id="{{ $panelId }}Body">
        <p class="chp-empty">Select a conversation to see history across WhatsApp, Viber, SMS, Inbox, Calls, and Facebook.</p>
    </div>
</aside>

<style>
.chp-panel {
    display: none;
    flex-direction: column;
    width: 300px;
    min-width: 260px;
    max-width: 340px;
    border-left: 1px solid var(--border, #d8dee6);
    background: var(--bg-primary, #f7f8fa);
    min-height: 0;
    height: 100%;
    overflow: hidden;
}
.chp-panel.chp-visible,
.chp-panel:not([hidden]) {
    display: flex;
}
.chp-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.85rem 1rem;
    border-bottom: 1px solid var(--border, #d8dee6);
    flex-shrink: 0;
}
.chp-header strong { font-size: 0.92rem; color: var(--text-primary, #1a2332); }
.chp-hint { font-size: 0.72rem; color: var(--text-secondary, #5b6b7c); text-transform: uppercase; letter-spacing: 0.04em; }
.chp-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding: 0.75rem 0.9rem 1.25rem;
}
.chp-section { margin-bottom: 1.1rem; }
.chp-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-secondary, #5b6b7c);
    margin-bottom: 0.45rem;
}
.chp-name { font-weight: 700; font-size: 0.98rem; color: var(--text-primary, #1a2332); margin-bottom: 0.25rem; }
.chp-meta { font-size: 0.8rem; color: var(--text-secondary, #5b6b7c); margin: 0.15rem 0; word-break: break-all; }
.chp-link { display: inline-block; margin-top: 0.45rem; font-size: 0.82rem; font-weight: 600; color: #0b5cab; text-decoration: none; }
.chp-item {
    display: block;
    text-decoration: none;
    color: inherit;
    padding: 0.55rem 0;
    border-bottom: 1px solid var(--border, #e6ebf0);
}
.chp-item:last-child { border-bottom: 0; }
.chp-item:hover .chp-item-title { color: #0b5cab; }
.chp-item-title { font-size: 0.86rem; font-weight: 600; margin: 0.2rem 0; color: var(--text-primary, #1a2332); }
.chp-item-preview { font-size: 0.78rem; color: var(--text-secondary, #5b6b7c); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chp-event { padding: 0.45rem 0; border-bottom: 1px solid var(--border, #e6ebf0); }
.chp-event:last-child { border-bottom: 0; }
.chp-dir { font-size: 0.72rem; color: var(--text-secondary, #5b6b7c); }
.chp-empty { font-size: 0.84rem; color: var(--text-secondary, #5b6b7c); margin: 0; line-height: 1.4; }
.chp-badge {
    display: inline-block;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.12rem 0.4rem;
    border-radius: 4px;
    background: #e8f1fb;
    color: #0b5cab;
}
.chp-badge.whatsapp { background: #e8f8ef; color: #128c7e; }
.chp-badge.viber { background: #efeaff; color: #5b3cc4; }
.chp-badge.sms { background: #e8f8ef; color: #0f7b4c; }
.chp-badge.inbox { background: #fff4e5; color: #b45309; }
.chp-badge.call { background: #f1f3f5; color: #495057; }
.chp-badge.facebook { background: #e8f1ff; color: #1877f2; }
.chp-panel .chp-body > .chp-section:first-child { padding-bottom: 0.5rem; border-bottom: 1px solid var(--border, #e6ebf0); margin-bottom: 0.9rem; }
@media (max-width: 900px) {
    .chp-panel { display: none !important; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.LnsContactHistory) {
        console.error('Contact history panel script is missing. Rebuild frontend assets with npm run build.');
    }
});
</script>
