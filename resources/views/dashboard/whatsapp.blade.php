@extends('layouts.app')

@section('title', 'WhatsApp')

@section('content')
<div class="wa-page-wrapper">
<div class="wa-page" id="waApp"
     data-api-base="{{ url('api/whatsapp') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-timezone="{{ config('app.timezone') }}"
     data-integrations-url="{{ route('integrations') }}">
    <div class="wa-layout">
        <aside class="wa-sidebar">
            <div class="wa-sidebar-header">
                <div>
                    <h2>WhatsApp</h2>
                    <p class="wa-sub" id="waAccountLabel">{{ $businessName ?: ($displayPhone ?: 'Business chats') }}</p>
                </div>
                <div class="wa-header-actions">
                    @if(auth()->user()?->hasPermission('view_leads'))
                        <a class="wa-icon-btn" href="{{ route('leads') }}?openRules=1" target="_blank" rel="noopener" title="Automation rules for WhatsApp messages">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="10" y2="18"/><circle cx="18" cy="15" r="3"/><path d="m20.5 17.5 1.5 1.5"/></svg>
                        </a>
                    @endif
                    <button type="button" class="wa-icon-btn" id="waSyncBtn" title="Sync WhatsApp messages from Twilio (also auto-syncs every 45s)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </button>
                    <button type="button" class="wa-icon-btn" id="waRefreshBtn" title="Refresh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>
            <div class="wa-sync-note" id="waSyncNote"></div>
            <div class="wa-filters wa-read-filters">
                <button type="button" class="wa-chip active" data-read="">All</button>
                <button type="button" class="wa-chip" data-read="unread">Unread</button>
                <button type="button" class="wa-chip" data-read="read">Read</button>
            </div>
            <div class="wa-search">
                <input type="search" id="waSearch" placeholder="Search conversations…" autocomplete="off">
            </div>
            @include('partials.channel-reply-templates', [
                'prefix' => 'wa',
                'label' => 'WhatsApp Templates',
            ])
            <div class="wa-thread-list" id="waThreadList"></div>
        </aside>

        <main class="wa-main">
            <div class="wa-empty" id="waEmpty">
                <div class="wa-empty-card">
                    <h3 id="waEmptyTitle">Select a conversation</h3>
                    <p id="waEmptyText">Customer messages appear here after they message your WhatsApp Business number.</p>
                    <a href="{{ route('integrations') }}" class="wa-link-btn" id="waConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect WhatsApp in Integrations</a>
                </div>
            </div>

            <div class="wa-chat" id="waChat" style="display:none;">
                <header class="wa-chat-header">
                    <button type="button" class="wa-icon-btn wa-back" id="waBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="wa-avatar" id="waHeaderAvatar"></div>
                    <div class="wa-chat-meta">
                        <h3 id="waHeaderName">Customer</h3>
                        <span id="waHeaderStatus">WhatsApp</span>
                    </div>
                    <div class="wa-chat-actions">
                        <button type="button" class="wa-icon-btn" id="waMarkUnreadBtn" title="Mark as unread">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        </button>
                        <button type="button" class="wa-icon-btn" id="waCallBtn" title="Open in WhatsApp">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button type="button" class="wa-icon-btn" id="waOpenBtn" title="Open chat">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </button>
                    </div>
                </header>

                <div class="wa-messages" id="waMessages">
                    <div class="wa-load-older" id="waLoadOlder" hidden>Loading earlier messages…</div>
                    <div class="wa-message-list" id="waMessageList"></div>
                </div>

                <footer class="wa-composer">
                    <div class="wa-attach">
                        <button type="button" class="wa-icon-btn" id="waAttachImage" title="Send image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="wa-icon-btn" id="waAttachVideo" title="Send video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                        <button type="button" class="wa-icon-btn" id="waAttachFile" title="Send file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <input type="file" id="waFileInput" hidden>
                    </div>
                    <div class="ch-tpl-picker-wrap" id="waTemplatePickerWrap">
                        <button type="button" class="wa-icon-btn" id="waTemplateBtn" title="Insert template">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </button>
                        @include('partials.channel-reply-templates-picker', ['prefix' => 'wa'])
                    </div>
                    <textarea id="waTextInput" rows="1" placeholder="Type a message…"></textarea>
                    <button type="button" class="wa-send-btn" id="waSendBtn" title="Send" aria-label="Send">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.4 1.4 5.6 5.6H4v2h12.2l-5.6 5.6L12 20l8-8z"/></svg>
                    </button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'waContactHistory'])
    </div>

    @include('partials.channel-reply-templates-list-modal', [
        'prefix' => 'wa',
        'label' => 'WhatsApp Templates',
        'help' => 'Plain-text snippets for WhatsApp replies only. Each channel has its own separate templates.',
    ])
    @include('partials.channel-reply-templates-modal', [
        'prefix' => 'wa',
        'bodyMax' => 4096,
        'label' => 'WhatsApp Templates',
        'help' => 'Plain-text snippets for WhatsApp replies only. Each channel has its own separate templates.',
    ])
</div>
</div>

<style>
.main-content > .content:has(.wa-page-wrapper) {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

.wa-page-wrapper {
    --wa-bg: #f4f5f7;
    --wa-panel: #ffffff;
    --wa-accent: #25d366;
    --wa-accent-dark: #128c7e;
    --wa-accent-soft: #e7f8ee;
    --wa-bubble: #25d366;
    --wa-gray-bubble: #E9E9EB;
    --wa-chat-bg: #ffffff;
    margin: 0;
    width: 100%;
    height: calc(100vh - 64px);
    min-height: calc(100vh - 64px);
    padding: 10px 12px 12px;
    background: var(--bg-primary, #fafafa);
}

.wa-page {
    height: 100%;
    width: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--wa-panel);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 10px;
}

.wa-layout {
    display: grid;
    grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
    height: 100%;
    width: 100%;
    min-height: 0;
    background: var(--wa-panel);
}
.wa-layout.with-history { grid-template-columns: minmax(260px, 320px) minmax(0, 1fr) 300px; }

.wa-sidebar {
    display: flex;
    flex-direction: column;
    min-height: 0;
    min-width: 0;
    background: var(--wa-panel);
    border-right: 1px solid var(--border);
}
.wa-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.wa-sidebar-header h2 { margin: 0; font-size: 1rem; font-weight: 700; }
.wa-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.75rem; }
.wa-header-actions { display: flex; gap: 0.15rem; }
.wa-filters { display: flex; gap: 0.35rem; padding: 0.7rem 1.15rem 0; flex-shrink: 0; }
.wa-chip {
    border: 1px solid var(--border);
    background: var(--wa-bg);
    color: var(--text-secondary);
    border-radius: 999px;
    padding: 0.28rem 0.7rem;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
}
.wa-chip.active { background: var(--wa-accent); border-color: var(--wa-accent); color: #fff; }
.wa-search { padding: 0.7rem 1.15rem 0.8rem; flex-shrink: 0; }
.wa-search input {
    width: 100%;
    padding: 0.45rem 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--wa-bg);
    color: var(--text-primary);
    font-size: 0.82rem;
}
.wa-thread-list { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0.35rem 0.55rem 0.75rem; }
.wa-thread {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    padding: 0.55rem 0.65rem;
    cursor: pointer;
    border-radius: 10px;
}
.wa-thread:hover { background: var(--wa-bg); }
.wa-thread.active { background: #eef0f3; }
.wa-thread.unread .wa-thread-name { font-weight: 700; }
.wa-thread-body { min-width: 0; flex: 1; }
.wa-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; }
.wa-thread-name { font-weight: 600; font-size: 0.84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); }
.wa-thread-time { color: var(--text-secondary); font-size: 0.68rem; white-space: nowrap; }
.wa-thread-preview { color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
.wa-unread-dot {
    width: 9px; height: 9px; border-radius: 50%;
    background: var(--wa-accent); flex-shrink: 0;
}
.wa-list-hint { text-align: center; padding: 0.7rem; font-size: 0.72rem; color: var(--text-secondary); }
.wa-avatar {
    width: 32px; height: 32px; border-radius: 8px; background: var(--wa-accent-dark); color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;
    background-size: cover; background-position: center;
}

.wa-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden; background: var(--wa-bg); }
.wa-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.wa-empty-card { text-align: center; max-width: 360px; }
.wa-empty-card h3 { margin: 0 0 0.4rem; font-size: 1.05rem; }
.wa-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; font-size: 0.88rem; line-height: 1.45; }
.wa-link-btn { display: inline-block; padding: 0.5rem 0.85rem; border-radius: 8px; background: var(--wa-accent-dark); color: #fff; text-decoration: none; font-weight: 600; font-size: 0.84rem; }

.wa-chat { display: flex; flex-direction: column; height: 100%; min-height: 0; background: var(--wa-chat-bg); }
.wa-chat-header {
    display: flex; align-items: center; gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    background: var(--wa-panel); flex-shrink: 0;
}
.wa-chat-meta { flex: 1; min-width: 0; }
.wa-chat-meta h3 { margin: 0; font-size: 0.92rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wa-chat-meta span { color: var(--text-secondary); font-size: 0.72rem; }
.wa-chat-actions { display: flex; gap: 0.15rem; }

.wa-messages {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0.75rem 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    background: var(--wa-chat-bg);
}
.wa-load-older {
    text-align: center;
    font-size: 0.7rem;
    color: var(--text-secondary);
    padding: 0.35rem 0 0.5rem;
    flex-shrink: 0;
}
.wa-message-list {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: min-content;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
}
.wa-stamp {
    align-self: center;
    font-size: 11px;
    font-weight: 600;
    color: #8e8e93;
    letter-spacing: -0.01em;
    margin: 12px 0 8px;
    text-align: center;
    line-height: 1.3;
}
.wa-bubble {
    position: relative;
    max-width: min(72%, 460px);
    padding: 7px 13px 8px;
    border-radius: 18px;
    font-size: 15px;
    line-height: 1.32;
    letter-spacing: -0.01em;
    word-break: break-word;
    white-space: pre-wrap;
}
.wa-bubble.inbound {
    align-self: flex-start;
    background: var(--wa-gray-bubble);
    color: #000;
    margin-left: 10px;
}
.wa-bubble.outbound {
    align-self: flex-end;
    background: var(--wa-bubble);
    color: #fff;
    margin-right: 10px;
}
.wa-bubble.solo,
.wa-bubble.group-start { margin-top: 8px; }
.wa-stamp + .wa-bubble { margin-top: 0; }
.wa-bubble.inbound.group-start:not(.solo) { border-bottom-left-radius: 5px; }
.wa-bubble.inbound.group-mid { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }
.wa-bubble.inbound.group-end { border-top-left-radius: 5px; }
.wa-bubble.outbound.group-start:not(.solo) { border-bottom-right-radius: 5px; }
.wa-bubble.outbound.group-mid { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
.wa-bubble.outbound.group-end { border-top-right-radius: 5px; }
.wa-bubble.inbound.tail::before,
.wa-bubble.outbound.tail::before {
    content: "";
    position: absolute;
    bottom: 0;
    width: 16px;
    height: 16px;
}
.wa-bubble.inbound.tail::after,
.wa-bubble.outbound.tail::after {
    content: "";
    position: absolute;
    bottom: 0;
    width: 10px;
    height: 16px;
    background: var(--wa-chat-bg);
}
.wa-bubble.inbound.tail::before {
    left: -6px;
    background: var(--wa-gray-bubble);
    border-bottom-right-radius: 12px;
}
.wa-bubble.inbound.tail::after {
    left: -10px;
    border-bottom-right-radius: 8px;
}
.wa-bubble.outbound.tail::before {
    right: -6px;
    background: var(--wa-bubble);
    border-bottom-left-radius: 12px;
}
.wa-bubble.outbound.tail::after {
    right: -10px;
    border-bottom-left-radius: 8px;
}
.wa-bubble img, .wa-bubble video { display: block; max-width: 100%; border-radius: 12px; margin: 0.2rem 0; }
.wa-bubble audio { width: 100%; margin-top: 0.2rem; }
.wa-bubble a { color: inherit; text-decoration: underline; }
.wa-delivered {
    align-self: flex-end;
    font-size: 11px;
    font-weight: 500;
    color: #8e8e93;
    margin: 2px 18px 2px 0;
    letter-spacing: -0.01em;
}
.wa-delivered.is-failed { color: #ff3b30; font-weight: 600; }
.wa-bubble.failed { opacity: 0.9; }

.wa-composer {
    display: flex; align-items: flex-end; gap: 0.45rem;
    padding: 0.55rem 0.85rem 0.75rem; border-top: 1px solid #e5e5ea;
    background: var(--wa-chat-bg); flex-shrink: 0;
}
.wa-attach { display: flex; gap: 0.05rem; flex-shrink: 0; }
.wa-layout .chp-header {
    min-height: 64px;
    align-items: center;
    padding: 1rem 1.15rem;
}
.wa-composer textarea {
    flex: 1; resize: none; min-height: 36px; max-height: 110px;
    padding: 8px 14px; border: 1px solid #c7c7cc; border-radius: 20px;
    background: #fff; color: #000; font: inherit; font-size: 15px; line-height: 1.3;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
}
.wa-composer textarea::placeholder { color: #8e8e93; }
.wa-send-btn {
    width: 32px; height: 32px; border: 0; border-radius: 50%; padding: 0;
    background: var(--wa-accent-dark); color: #fff; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.wa-send-btn svg { width: 16px; height: 16px; transform: rotate(-90deg); }
.wa-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.wa-icon-btn {
    width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent;
    color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
}
.wa-icon-btn:hover { background: var(--wa-bg); color: var(--text-primary); }
.wa-icon-btn svg { width: 16px; height: 16px; }
.wa-icon-btn.is-syncing svg { animation: wa-spin 0.9s linear infinite; }
@keyframes wa-spin { to { transform: rotate(360deg); } }
.wa-sync-note {
    display: none;
    margin: 0 1.15rem 0.55rem;
    padding: 0.45rem 0.6rem;
    border-radius: 8px;
    background: var(--wa-accent-soft);
    color: var(--text-primary);
    font-size: 0.72rem;
    line-height: 1.4;
}
.wa-sync-note.is-visible { display: block; }
.wa-back { display: none; }
@include('partials.channel-reply-templates-styles')
.wa-page .ch-tpl-sidebar-btn:hover { border-color: var(--wa-accent); }
.wa-page .ch-tpl-btn.primary { background: var(--wa-accent); border-color: var(--wa-accent); }
.wa-page .ch-tpl-link-btn { color: var(--wa-accent-dark); }
.wa-page .ch-tpl-link-btn:hover { background: rgba(37, 211, 102, 0.1); }
.wa-page .ch-tpl-search:focus,
.wa-page .ch-tpl-input:focus,
.wa-page .ch-tpl-textarea:focus { border-color: var(--wa-accent); box-shadow: 0 0 0 3px rgba(37, 211, 102, 0.12); }
.wa-page .ch-tpl-page-btn:hover:not(:disabled) { border-color: var(--wa-accent); color: var(--wa-accent-dark); }

@media (max-width: 900px) {
    .wa-page-wrapper { padding: 8px; }
    .wa-page-wrapper, .wa-page { height: auto; min-height: calc(100vh - 64px); overflow: visible; }
    .wa-layout, .wa-layout.with-history { grid-template-columns: 1fr; height: auto; min-height: calc(100vh - 80px); }
    .wa-sidebar { min-height: calc(100vh - 64px); }
    .wa-sidebar.hidden-mobile { display: none; }
    .wa-main { min-height: calc(100vh - 64px); }
    .wa-main.hidden-mobile { display: none; }
    .wa-back { display: inline-flex; }
    .wa-chat { min-height: calc(100vh - 64px); }
}
</style>

<script>
@include('partials.channel-reply-templates-script')
(function () {
    const root = document.getElementById('waApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const appTimezone = root.dataset.timezone || 'Asia/Manila';
    const PAGE_SIZE = 40;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let activeHistoryOpts = null;
    let readFilter = '';
    let pollTimer = null;
    let autoSyncTimer = null;
    let searchTimer = null;
    let uploadKind = 'document';
    let convHasMore = false;
    let convLoading = false;
    let messagesHasMore = false;
    let loadOlderInProgress = false;
    let messageIds = new Set();
    let oldestMessageId = null;

    const els = {
        list: document.getElementById('waThreadList'),
        empty: document.getElementById('waEmpty'),
        chat: document.getElementById('waChat'),
        messages: document.getElementById('waMessages'),
        messageList: document.getElementById('waMessageList'),
        loadOlder: document.getElementById('waLoadOlder'),
        search: document.getElementById('waSearch'),
        text: document.getElementById('waTextInput'),
        send: document.getElementById('waSendBtn'),
        file: document.getElementById('waFileInput'),
        headerName: document.getElementById('waHeaderName'),
        headerStatus: document.getElementById('waHeaderStatus'),
        headerAvatar: document.getElementById('waHeaderAvatar'),
        sidebar: document.querySelector('.wa-sidebar'),
        main: document.querySelector('.wa-main'),
        connectLink: document.getElementById('waConnectLink'),
        emptyTitle: document.getElementById('waEmptyTitle'),
        emptyText: document.getElementById('waEmptyText'),
        accountLabel: document.getElementById('waAccountLabel'),
        syncBtn: document.getElementById('waSyncBtn'),
        syncNote: document.getElementById('waSyncNote'),
    };
    let syncInFlight = false;

    async function api(path, options = {}) {
        const res = await fetch(apiBase + path, {
            ...options,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                ...(options.headers || {}),
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            const validation = data.errors
                ? Object.values(data.errors).flat().filter(Boolean).join(' ')
                : '';
            throw new Error(data.message || data.error || validation || `Request failed (HTTP ${res.status}).`);
        }
        return data;
    }

    function initials(name) {
        return (name || 'W').split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase();
    }

    function formatListTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        const opts = { timeZone: appTimezone };
        if (sameDay) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', ...opts });
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', ...opts });
    }

    function formatStamp(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        const now = new Date();
        const opts = { timeZone: appTimezone };
        const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', ...opts });
        if (d.toDateString() === now.toDateString()) return 'Today ' + time;
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + time;
        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 6);
        if (d > weekAgo) {
            return d.toLocaleDateString([], { weekday: 'long', ...opts }) + ' ' + time;
        }
        if (d.getFullYear() === now.getFullYear()) {
            return d.toLocaleDateString([], { month: 'short', day: 'numeric', ...opts }) + ' at ' + time;
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric', ...opts }) + ' at ' + time;
    }

    function dayKey(iso) {
        if (!iso) return '';
        return new Date(iso).toDateString();
    }

    function shouldStamp(prevIso, iso) {
        if (!iso) return false;
        if (!prevIso) return true;
        if (dayKey(prevIso) !== dayKey(iso)) return true;
        return (new Date(iso) - new Date(prevIso)) > 45 * 60 * 1000;
    }

    function setAvatar(el, name, pic) {
        if (pic) {
            el.style.backgroundImage = `url("${pic}")`;
            el.textContent = '';
            return;
        }
        el.style.backgroundImage = '';
        el.textContent = initials(name);
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function assignedLeadLine(c) {
        const leadMarkup = window.LnsAssignedLead?.markup
            ? window.LnsAssignedLead.markup(c.lead, escapeHtml)
            : (() => {
                const name = c?.lead?.assigned_user?.name;
                const chips = (c?.lead?.labels || []).filter(l => l?.name).map(l => escapeHtml(l.name)).join(', ');
                if (!name && !chips) return '';
                return `${name ? `<div class="channel-assigned">Assigned to ${escapeHtml(name)}</div>` : ''}${chips ? `<div class="channel-assigned">${chips}</div>` : ''}`;
            })();
        return leadMarkup + conversationLabelChips(c);
    }

    function conversationLabelChips(c) {
        const items = (c?.labels || []).filter(l => l?.name);
        if (!items.length) return '';
        return `<div class="channel-label-chips">${items.map(l => {
            const color = l.color || '#4338ca';
            return `<span class="channel-label-chip" style="background:${escapeHtml(color)}">${escapeHtml(l.name)}</span>`;
        }).join('')}</div>`;
    }

    function assignedLeadSuffix(c) {
        if (window.LnsAssignedLead?.suffix) return window.LnsAssignedLead.suffix(c.lead);
        const name = c?.lead?.assigned_user?.name;
        const labels = (c?.lead?.labels || []).map(l => l.name).filter(Boolean);
        return (name ? ' · Assigned to ' + name : '') + (labels.length ? ' · ' + labels.join(', ') : '');
    }

    function applyLeadToActive(lead) {
        if (!activeId || !lead) return;
        const payload = window.LnsAssignedLead?.compact ? window.LnsAssignedLead.compact(lead) : lead;
        const idx = conversations.findIndex(c => c.id === activeId);
        if (idx < 0) return;
        conversations[idx] = { ...conversations[idx], lead: payload };
        const conv = conversations[idx];
        renderThreads();
        setHeaderStatus(conv);
    }

    function applyConversationLabelsToActive(conversationId, labels) {
        const idx = conversations.findIndex(c => c.id === conversationId);
        if (idx < 0) return;
        conversations[idx] = { ...conversations[idx], labels: labels || [] };
        renderThreads();
    }

    function setHeaderStatus(conv) {
        els.headerStatus.textContent = (conv.within_window
            ? ('Within 24h window · ' + (conv.phone || conv.wa_id || ''))
            : ('Outside 24h window · ' + (conv.phone || conv.wa_id || ''))) + assignedLeadSuffix(conv);
    }

    function nearBottom() {
        return els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
    }

    function lastBubble() {
        const nodes = els.messageList.querySelectorAll('.wa-bubble');
        return nodes[nodes.length - 1] || null;
    }

    function visibleConversations() {
        if (readFilter === 'unread') return conversations.filter(c => !c.is_read);
        if (readFilter === 'read') return conversations.filter(c => c.is_read);
        return conversations;
    }

    function renderThreads() {
        const visible = visibleConversations();
        if (!visible.length) {
            els.list.innerHTML = `<div class="wa-list-hint">${readFilter ? 'No ' + readFilter + ' conversations.' : 'No conversations yet.'}</div>`;
            return;
        }

        els.list.innerHTML = visible.map(c => `
            <div class="wa-thread ${c.id === activeId ? 'active' : ''} ${!c.is_read ? 'unread' : ''}" data-id="${c.id}">
                <div class="wa-avatar">${initials(c.name)}</div>
                <div class="wa-thread-body">
                    <div class="wa-thread-top">
                        <div class="wa-thread-name">${escapeHtml(c.name || 'WhatsApp User')}</div>
                        <div class="wa-thread-time">${formatListTime(c.last_message_at)}</div>
                    </div>
                    <div class="wa-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                    ${assignedLeadLine(c)}
                </div>
                ${!c.is_read ? `<span class="wa-unread-dot" aria-hidden="true"></span>` : ''}
            </div>
        `).join('') + (convHasMore ? `<div class="wa-list-hint">Scroll for older chats</div>` : '');

        els.list.querySelectorAll('.wa-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function stampMarkup(iso) {
        return `<div class="wa-stamp" data-day="${dayKey(iso)}" data-ts="${iso}">${escapeHtml(formatStamp(iso))}</div>`;
    }

    function messageBody(m) {
        if ((m.type === 'image' || m.type === 'sticker') && m.media_url) {
            return `${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}<img src="${escapeHtml(m.media_url)}" alt="Image">`;
        }
        if (m.type === 'video' && m.media_url) {
            return `<video controls src="${escapeHtml(m.media_url)}"></video>${m.text ? `<div>${escapeHtml(m.text)}</div>` : ''}`;
        }
        if (m.type === 'audio' && m.media_url) {
            return `<audio controls src="${escapeHtml(m.media_url)}"></audio>`;
        }
        if (m.type === 'document' && m.media_url) {
            return `<a href="${escapeHtml(m.media_url)}" target="_blank" rel="noopener">${escapeHtml(m.file_name || 'Download file')}</a>`;
        }
        if (m.type === 'location' && m.latitude != null) {
            const maps = `https://maps.google.com/?q=${m.latitude},${m.longitude}`;
            return `<a href="${maps}" target="_blank" rel="noopener">📍 ${m.latitude}, ${m.longitude}</a>`;
        }
        if (m.type === 'contact') {
            return `👤 ${escapeHtml(m.contact_name || 'Contact')}${m.contact_phone ? `<br>${escapeHtml(m.contact_phone)}` : ''}`;
        }
        return escapeHtml(m.text || '');
    }

    function messageMarkup(m) {
        const status = (m.status || '').toLowerCase();
        const failed = status === 'failed' || status === 'undelivered';
        const iso = m.sent_at || m.created_at || '';
        return `<div class="wa-bubble ${m.direction}${failed ? ' failed' : ''}" data-id="${m.id}" data-direction="${m.direction}" data-day="${dayKey(iso)}" data-ts="${iso}" data-status="${escapeHtml(status)}">${messageBody(m)}</div>`;
    }

    function refreshThreadChrome() {
        const nodes = [...els.messageList.querySelectorAll('.wa-bubble')];
        els.messageList.querySelectorAll('.wa-delivered').forEach(n => n.remove());
        nodes.forEach((node, i) => {
            const prev = nodes[i - 1];
            const next = nodes[i + 1];
            const samePrev = prev && prev.dataset.direction === node.dataset.direction && !shouldStamp(prev.dataset.ts, node.dataset.ts);
            const sameNext = next && next.dataset.direction === node.dataset.direction && !shouldStamp(node.dataset.ts, next.dataset.ts);
            node.classList.toggle('solo', !samePrev && !sameNext);
            node.classList.toggle('group-start', !samePrev && sameNext);
            node.classList.toggle('group-mid', samePrev && sameNext);
            node.classList.toggle('group-end', samePrev && !sameNext);
            node.classList.toggle('tail', !sameNext);
        });

        const lastOut = [...nodes].reverse().find(n => n.dataset.direction === 'outbound');
        if (!lastOut) return;
        const st = (lastOut.dataset.status || '').toLowerCase();
        const failed = lastOut.classList.contains('failed') || st === 'failed' || st === 'undelivered';
        let label = 'Sent';
        if (failed) label = 'Not Delivered';
        else if (st === 'queued' || st === 'accepted' || st === 'sending') label = 'Sending';
        else if (st === 'delivered' || st === 'read') label = 'Delivered';
        lastOut.insertAdjacentHTML('afterend', `<div class="wa-delivered${failed ? ' is-failed' : ''}">${label}</div>`);
    }

    function appendMessage(m) {
        if (messageIds.has(m.id)) return;
        const iso = m.sent_at || m.created_at;
        const prev = lastBubble();
        if (shouldStamp(prev?.dataset.ts, iso)) {
            els.messageList.insertAdjacentHTML('beforeend', stampMarkup(iso));
        }
        els.messageList.insertAdjacentHTML('beforeend', messageMarkup(m));
        messageIds.add(m.id);
        refreshThreadChrome();
        els.messageList.querySelectorAll('img, video').forEach((media) => {
            media.addEventListener('load', () => {
                if (nearBottom()) els.messages.scrollTop = els.messages.scrollHeight;
            }, { once: true });
        });
    }

    function prependMessages(items) {
        if (!items.length) return;
        const firstStamp = els.messageList.firstElementChild?.classList.contains('wa-stamp')
            ? els.messageList.firstElementChild
            : null;
        let html = '';
        let prevIso = null;
        items.forEach(m => {
            if (messageIds.has(m.id)) return;
            const iso = m.sent_at || m.created_at;
            if (shouldStamp(prevIso, iso)) html += stampMarkup(iso);
            html += messageMarkup(m);
            messageIds.add(m.id);
            prevIso = iso;
        });
        els.messageList.insertAdjacentHTML('afterbegin', html);
        if (firstStamp && prevIso && !shouldStamp(prevIso, firstStamp.dataset.ts)) {
            firstStamp.remove();
        }
        oldestMessageId = firstLoadedMessageId();
        refreshThreadChrome();
    }

    function firstLoadedMessageId() {
        const first = els.messageList.querySelector('.wa-bubble');
        return first ? Number(first.dataset.id) : null;
    }

    function resetMessages() {
        els.messageList.innerHTML = '';
        messageIds = new Set();
        oldestMessageId = null;
        messagesHasMore = false;
        loadOlderInProgress = false;
        if (els.loadOlder) els.loadOlder.hidden = true;
    }

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            window.waTemplates?.applyBootstrap(data);
            if (data.account?.business_name) {
                els.accountLabel.textContent = data.account.business_name;
            } else if (data.account?.display_phone_number) {
                els.accountLabel.textContent = data.account.display_phone_number;
            }
            if (!connected) {
                els.emptyTitle.textContent = 'Connect WhatsApp';
                els.emptyText.textContent = 'Connect Twilio, then add your WhatsApp sender under Integrations to start chatting.';
                els.connectLink.style.display = '';
            } else {
                els.connectLink.style.display = 'none';
            }
        } catch (e) {
            console.error(e);
        }
    }

    function sortConversations(list) {
        return list.slice().sort((a, b) => {
            const ta = a.last_message_at || '';
            const tb = b.last_message_at || '';
            if (ta === tb) return (b.id || 0) - (a.id || 0);
            return tb.localeCompare(ta);
        });
    }

    function conversationParams({ append = false } = {}) {
        const params = new URLSearchParams({ limit: String(PAGE_SIZE) });
        const q = (els.search.value || '').trim();
        if (q) params.set('q', q);
        if (readFilter) params.set('read', readFilter);
        if (append && conversations.length) {
            params.set('before_id', String(conversations[conversations.length - 1].id));
        }
        return params;
    }

    async function loadConversations({ append = false, merge = false } = {}) {
        if (convLoading) return;
        convLoading = true;
        try {
            const data = await api('/conversations?' + conversationParams({ append }).toString());
            const rows = data.data || [];
            if (!merge) convHasMore = !!data.has_more;
            if (append) {
                const seen = new Set(conversations.map(c => c.id));
                conversations = conversations.concat(rows.filter(c => !seen.has(c.id)));
            } else if (merge) {
                const byId = new Map(conversations.map(c => [c.id, c]));
                rows.forEach(c => byId.set(c.id, c));
                conversations = sortConversations([...byId.values()]);
            } else {
                conversations = rows;
            }
            renderThreads();
        } finally {
            convLoading = false;
        }
    }

    async function loadOlderMessages() {
        if (!activeId || loadOlderInProgress || !messagesHasMore || !oldestMessageId) return false;
        loadOlderInProgress = true;
        if (els.loadOlder) els.loadOlder.hidden = false;
        const prevHeight = els.messages.scrollHeight;
        const prevTop = els.messages.scrollTop;
        try {
            const data = await api(`/conversations/${activeId}/messages?limit=${PAGE_SIZE}&before_id=${oldestMessageId}`);
            const rows = data.data || [];
            messagesHasMore = !!data.has_more;
            prependMessages(rows);
            els.messages.scrollTop = els.messages.scrollHeight - prevHeight + prevTop;
            return rows.length > 0;
        } catch (e) {
            console.error(e);
            return false;
        } finally {
            loadOlderInProgress = false;
            if (els.loadOlder) els.loadOlder.hidden = !messagesHasMore;
        }
    }

    async function fillUntilScrollable() {
        let guard = 0;
        while (messagesHasMore && els.messages.scrollHeight <= els.messages.clientHeight + 4 && guard < 8) {
            const loaded = await loadOlderMessages();
            if (!loaded) break;
            guard += 1;
        }
    }

    function rememberConversation(conv) {
        if (!conv || conv.id == null) return null;
        const id = Number(conv.id);
        const next = { ...conv, is_read: true, unread_count: 0 };
        const idx = conversations.findIndex(c => Number(c.id) === id);
        if (idx >= 0) {
            conversations[idx] = { ...conversations[idx], ...next };
            return conversations[idx];
        }
        conversations.unshift(next);
        return conversations[0];
    }

    async function openConversation(id) {
        id = Number(id);
        if (!id) return;

        let conv = conversations.find(c => Number(c.id) === id) || null;
        let data = null;
        if (!conv) {
            try {
                data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
            } catch (e) {
                return;
            }
            conv = rememberConversation(data.conversation);
            if (!conv) return;
        }

        activeId = id;
        conv.is_read = true;
        conv.unread_count = 0;
        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = conv.name || 'WhatsApp User';
        setHeaderStatus(conv);
        setAvatar(els.headerAvatar, conv.name);
        renderThreads();
        resetMessages();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        if (!data) {
            data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
        }
        messagesHasMore = !!data.has_more;
        if (els.loadOlder) els.loadOlder.hidden = !messagesHasMore;
        (data.data || []).forEach(appendMessage);
        oldestMessageId = firstLoadedMessageId();
        els.messages.scrollTop = els.messages.scrollHeight;
        await fillUntilScrollable();
        els.messages.scrollTop = els.messages.scrollHeight;
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();

        if (data.conversation) {
            const idx = conversations.findIndex(c => c.id === id);
            if (idx >= 0) conversations[idx] = { ...conversations[idx], ...data.conversation, is_read: true, unread_count: 0 };
            Object.assign(conv, conversations[idx] || data.conversation, { is_read: true, unread_count: 0 });
            els.headerName.textContent = conv.name || 'WhatsApp User';
            setHeaderStatus(conv);
            setAvatar(els.headerAvatar, conv.name);
            renderThreads();
        }

        document.querySelector('.wa-layout')?.classList.add('with-history');
        const extractedName = data.conversation?.extracted_name || (data.conversation?.extracted_names || [])[0] || '';
        const historyOpts = {
            name: extractedName || conv.name || conv.profile_name || '',
            excludeChannel: 'whatsapp',
            excludeId: conv.id,
            source: 'whatsapp',
            phone: conv.phone || conv.wa_id || '',
            email: (data.conversation?.extracted_emails || [])[0] || '',
            extracted_phones: data.conversation?.extracted_phones || [],
            extracted_emails: data.conversation?.extracted_emails || [],
            extracted_name: extractedName,
            extracted_names: data.conversation?.extracted_names || [],
            canEditLead: true,
            onLeadUpdated: applyLeadToActive,
            conversationLabels: conv.labels || [],
            conversationLabelsApi: `/api/whatsapp/conversations/${conv.id}/labels`,
            onConversationLabelsChange: (labels) => applyConversationLabelsToActive(conv.id, labels),
            onSaved(data, extra) {
                if (extra?.existing && data.existing_lead_id) {
                    window.location.href = '/leads?lead=' + data.existing_lead_id;
                    return;
                }
                if (data.data) applyLeadToActive(data.data);
                window.loadChannelContactHistory('#waContactHistory', historyOpts);
            },
        };
        activeHistoryOpts = historyOpts;
        window.loadChannelContactHistory('#waContactHistory', historyOpts);
    }

    async function pollActiveMessages() {
        if (!activeId || loadOlderInProgress) return;
        const data = await api(`/conversations/${activeId}/messages?limit=${PAGE_SIZE}&poll=1`);
        const incoming = data.data || [];
        const newer = incoming.filter(m => !messageIds.has(m.id));
        if (newer.length) {
            const pin = nearBottom();
            newer.forEach(appendMessage);
            if (pin) els.messages.scrollTop = els.messages.scrollHeight;
        }

        const emails = data.conversation?.extracted_emails || [];
        const names = data.conversation?.extracted_names || [];
        const extractedName = data.conversation?.extracted_name || names[0] || '';
        if (!activeHistoryOpts || document.querySelector('#waContactHistory .chp-lead-form')) {
            return;
        }
        const nextEmail = emails[0] || '';
        const sameEmails = JSON.stringify(emails) === JSON.stringify(activeHistoryOpts.extracted_emails || []);
        const sameName = extractedName === (activeHistoryOpts.extracted_name || '');
        if (sameEmails && sameName && nextEmail === (activeHistoryOpts.email || '')) {
            return;
        }
        if (data.conversation?.name && activeId) {
            const idx = conversations.findIndex(c => c.id === activeId);
            if (idx >= 0 && conversations[idx].name !== data.conversation.name) {
                conversations[idx] = { ...conversations[idx], name: data.conversation.name };
                els.headerName.textContent = data.conversation.name;
                renderThreads();
            }
        }
        activeHistoryOpts.email = nextEmail;
        activeHistoryOpts.extracted_emails = emails;
        activeHistoryOpts.extracted_name = extractedName;
        activeHistoryOpts.extracted_names = names;
        if (extractedName) {
            activeHistoryOpts.name = extractedName;
        }
        window.loadChannelContactHistory('#waContactHistory', activeHistoryOpts);
    }

    async function sendText() {
        if (!activeId) return;
        const text = els.text.value.trim();
        if (!text) return;
        els.send.disabled = true;
        try {
            const data = await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ type: 'text', text }),
            });
            els.text.value = '';
            els.text.style.height = 'auto';
            if (data.data) appendMessage(data.data);
            els.messages.scrollTop = els.messages.scrollHeight;
            await loadConversations({ merge: true });
        } catch (e) {
            alert(e.message);
        } finally {
            els.send.disabled = false;
        }
    }

    async function uploadAndSend(file, kind) {
        if (!activeId || !file) return;
        const form = new FormData();
        form.append('file', file);
        form.append('kind', kind);
        els.send.disabled = true;
        try {
            const uploaded = await api('/media', { method: 'POST', body: form, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
            const media = uploaded.data;
            const data = await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({
                    type: kind,
                    media_url: media.url,
                    file_name: media.file_name,
                    file_size: media.file_size,
                }),
            });
            if (data.data) appendMessage(data.data);
            els.messages.scrollTop = els.messages.scrollHeight;
            await loadConversations({ merge: true });
        } catch (e) {
            alert(e.message);
        } finally {
            els.send.disabled = false;
            els.file.value = '';
        }
    }

    async function openWhatsApp() {
        if (!activeId) return;
        try {
            const data = await api(`/conversations/${activeId}/call-link`);
            const links = data.data || {};
            if (links.open_chat) window.open(links.open_chat, '_blank');
            else if (links.tel) window.location.href = links.tel;
            else alert('No phone number is available for this contact yet.');
        } catch (e) {
            alert(e.message);
        }
    }

    function showSyncNote(text) {
        if (!els.syncNote) return;
        els.syncNote.textContent = text;
        els.syncNote.classList.toggle('is-visible', !!text);
    }

    async function syncMessages() {
        if (!els.syncBtn || syncInFlight) return;
        syncInFlight = true;
        els.syncBtn.disabled = true;
        els.syncBtn.classList.add('is-syncing');
        showSyncNote('Checking Twilio for messages missed by the CRM...');
        try {
            const data = await api('/sync', {
                method: 'POST',
                body: JSON.stringify({ days: 30, limit: 500 }),
            });
            const result = data.data || {};
            const imported = Number(result.imported || 0);
            const scanned = Number(result.scanned || 0);
            showSyncNote(imported
                ? `Imported ${imported} message${imported === 1 ? '' : 's'} from Twilio.`
                : (scanned ? `No new messages. Found ${scanned} already in the CRM.` : 'No WhatsApp history found on Twilio for the last 30 days.'));
            await loadConversations({ merge: true });
            if (activeId) await pollActiveMessages();
        } catch (e) {
            showSyncNote(e.message || 'Could not sync WhatsApp messages.');
            alert(e.message || 'Could not sync WhatsApp messages.');
        } finally {
            syncInFlight = false;
            els.syncBtn.disabled = false;
            els.syncBtn.classList.remove('is-syncing');
        }
    }

    async function autoSyncRecent() {
        if (!connected || syncInFlight) return;
        syncInFlight = true;
        els.syncBtn?.classList.add('is-syncing');
        try {
            const data = await api('/sync', {
                method: 'POST',
                body: JSON.stringify({ recent: true, minutes: 90 }),
            });
            const imported = Number(data.data?.imported || 0);
            if (imported > 0) {
                showSyncNote(`Auto-synced ${imported} new message${imported === 1 ? '' : 's'}.`);
                await loadConversations({ merge: true });
                if (activeId) await pollActiveMessages();
            }
        } catch (e) {
            console.warn('WhatsApp auto-sync failed', e);
        } finally {
            syncInFlight = false;
            els.syncBtn?.classList.remove('is-syncing');
        }
    }

    els.syncBtn?.addEventListener('click', () => syncMessages().catch(console.error));
    document.getElementById('waRefreshBtn').addEventListener('click', () => loadConversations().catch(console.error));
    document.getElementById('waBackBtn').addEventListener('click', () => {
        els.sidebar.classList.remove('hidden-mobile');
        els.main.classList.add('hidden-mobile');
    });
    document.getElementById('waMarkUnreadBtn').addEventListener('click', async () => {
        if (!activeId) return;
        try {
            await api(`/conversations/${activeId}/read`, {
                method: 'PATCH',
                body: JSON.stringify({ is_read: false }),
            });
        } catch (e) {
            return;
        }
        const idx = conversations.findIndex(c => Number(c.id) === activeId);
        if (idx >= 0) conversations[idx] = { ...conversations[idx], is_read: false, unread_count: 1 };
        renderThreads();
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();
    });
    els.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadConversations().catch(console.error), 250);
    });
    els.send.addEventListener('click', sendText);
    els.text.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendText();
        }
    });
    els.text.addEventListener('input', () => {
        els.text.style.height = 'auto';
        els.text.style.height = Math.min(els.text.scrollHeight, 110) + 'px';
    });
    document.querySelectorAll('.wa-read-filters .wa-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.wa-read-filters .wa-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            readFilter = chip.dataset.read || '';
            loadConversations().catch(console.error);
        });
    });
    document.getElementById('waAttachImage').addEventListener('click', () => { uploadKind = 'image'; els.file.accept = 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp'; els.file.click(); });
    document.getElementById('waAttachVideo').addEventListener('click', () => { uploadKind = 'video'; els.file.accept = 'video/mp4,.mp4,.3gp'; els.file.click(); });
    document.getElementById('waAttachFile').addEventListener('click', () => { uploadKind = 'document'; els.file.accept = '*/*'; els.file.click(); });
    els.file.addEventListener('change', () => uploadAndSend(els.file.files[0], uploadKind));
    document.getElementById('waCallBtn').addEventListener('click', openWhatsApp);
    document.getElementById('waOpenBtn').addEventListener('click', openWhatsApp);

    els.list.addEventListener('scroll', () => {
        if (convLoading || !convHasMore) return;
        const remaining = els.list.scrollHeight - els.list.scrollTop - els.list.clientHeight;
        if (remaining < 120) loadConversations({ append: true }).catch(console.error);
    });

    els.messages.addEventListener('scroll', () => {
        if (els.messages.scrollTop < 48) loadOlderMessages();
    });

    window.waTemplates = window.initChannelReplyTemplates({
        prefix: 'wa',
        bodyMax: 4096,
        label: 'WhatsApp Templates',
        api,
        getComposer: () => els.text,
        escapeHtml,
    });

    (async function init() {
        await loadBootstrap();
        if (connected) {
            await loadConversations();
            const params = new URLSearchParams(window.location.search);
            const openId = Number(params.get('conversation') || 0);
            if (openId) {
                await openConversation(openId);
            }
            autoSyncTimer = setInterval(() => {
                autoSyncRecent().catch(() => {});
            }, 45000);
            setTimeout(() => autoSyncRecent().catch(console.warn), 8000);
            pollTimer = setInterval(async () => {
                try {
                    await loadConversations({ merge: true });
                    await pollActiveMessages();
                } catch (e) {}
            }, 5000);
        }
    })();
})();
</script>
@endsection
