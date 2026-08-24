@extends('layouts.app')

@section('title', 'SMS')

@section('content')
<div class="sms-page-wrapper">
<div class="sms-page" id="smsApp"
     data-api-base="{{ url('api/sms') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-can-send="{{ !empty($canSendSms) && $canSendSms ? '1' : '0' }}"
     data-twilio-number="{{ $twilioNumber ?: '' }}"
     data-integrations-url="{{ route('integrations') }}"
     data-phone-url="{{ route('twilio.call') }}">
    <div class="sms-layout">
        <aside class="sms-sidebar">
            <div class="sms-sidebar-header">
                <div>
                    <h2>SMS</h2>
                    <p class="sms-sub" id="smsAccountLabel">{{ $twilioNumber ?: 'Twilio text messages' }}</p>
                </div>
                <div class="sms-header-actions">
                    @if(!empty($canSendSms) && $canSendSms)
                    <button type="button" class="sms-icon-btn" id="smsNewBtn" title="New conversation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    @endif
                    <button type="button" class="sms-icon-btn" id="smsRefreshBtn" title="Refresh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>
            <div class="sms-search">
                <input type="search" id="smsSearch" placeholder="Search conversations…" autocomplete="off">
            </div>
            @include('partials.channel-reply-templates', [
                'prefix' => 'sms',
                'label' => 'SMS Templates',
            ])
            <div class="sms-thread-list" id="smsThreadList"></div>
        </aside>

        <main class="sms-main">
            <div class="sms-empty" id="smsEmpty">
                <div class="sms-empty-card">
                    <h3 id="smsEmptyTitle">Select a conversation</h3>
                    <p id="smsEmptyText">SMS messages sent and received through your Twilio numbers appear here.</p>
                    <a href="{{ route('integrations') }}" class="sms-link-btn" id="smsConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect Twilio in Integrations</a>
                </div>
            </div>

            <div class="sms-chat" id="smsChat" style="display:none;">
                <header class="sms-chat-header">
                    <button type="button" class="sms-icon-btn sms-back" id="smsBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="sms-avatar" id="smsHeaderAvatar"></div>
                    <div class="sms-chat-meta">
                        <h3 id="smsHeaderName">Contact</h3>
                        <span id="smsHeaderStatus">SMS</span>
                    </div>
                    <div class="sms-chat-actions">
                        <button type="button" class="sms-icon-btn" id="smsCallBtn" title="Call">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                    </div>
                </header>

                <div class="sms-messages" id="smsMessages">
                    <div class="sms-load-older" id="smsLoadOlder" hidden>Loading earlier messages…</div>
                    <div class="sms-message-list" id="smsMessageList"></div>
                </div>

                <footer class="sms-composer" @if(empty($canSendSms) || !$canSendSms) style="display:none;" @endif>
                    <div class="ch-tpl-picker-wrap" id="smsTemplatePickerWrap">
                        <button type="button" class="sms-icon-btn" id="smsTemplateBtn" title="Insert template">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </button>
                        @include('partials.channel-reply-templates-picker', ['prefix' => 'sms'])
                    </div>
                    <textarea id="smsTextInput" rows="1" placeholder="Text Message" maxlength="1600"></textarea>
                    <button type="button" class="sms-send-btn" id="smsSendBtn" title="Send" aria-label="Send">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.4 1.4 5.6 5.6H4v2h12.2l-5.6 5.6L12 20l8-8z"/></svg>
                    </button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'smsContactHistory'])
    </div>

    <div class="sms-modal" id="smsNewModal" hidden>
        <div class="sms-modal-card">
            <h3>New SMS</h3>
            <p class="sms-modal-help">Enter a phone number in E.164 format (e.g. +15551234567).</p>
            <label class="sms-label">To</label>
            <input type="text" id="smsNewTo" class="sms-input" placeholder="+15551234567">
            <label class="sms-label">Name (optional)</label>
            <input type="text" id="smsNewName" class="sms-input" placeholder="Contact name">
            <div class="sms-modal-actions">
                <button type="button" class="sms-btn-secondary" id="smsNewCancel">Cancel</button>
                <button type="button" class="sms-btn-primary" id="smsNewStart">Start</button>
            </div>
        </div>
    </div>

    @include('partials.channel-reply-templates-list-modal', [
        'prefix' => 'sms',
        'label' => 'SMS Templates',
        'help' => 'Plain-text snippets for SMS replies only. Facebook has its own separate templates.',
    ])
    @include('partials.channel-reply-templates-modal', [
        'prefix' => 'sms',
        'bodyMax' => 1600,
        'label' => 'SMS Templates',
        'help' => 'Plain-text snippets for SMS replies only. Facebook has its own separate templates.',
    ])
</div>
</div>

<style>
.main-content > .content:has(.sms-page-wrapper) {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

.sms-page-wrapper {
    --sms-bg: #f4f5f7;
    --sms-panel: #ffffff;
    --sms-accent: #0ea5e9;
    --sms-accent-soft: #e0f2fe;
    --sms-green: #34C759;
    --sms-gray-bubble: #E9E9EB;
    --sms-imessage-bg: #ffffff;
    margin: 0;
    width: 100%;
    height: calc(100vh - 64px);
    min-height: calc(100vh - 64px);
    padding: 10px 12px 12px;
    background: var(--bg-primary, #fafafa);
}

.sms-page {
    height: 100%;
    width: 100%;
    position: relative;
    display: flex;
    flex-direction: column;
    background: var(--sms-panel);
    overflow: hidden;
    border: 1px solid var(--border);
    border-radius: 10px;
}

.sms-layout {
    display: grid;
    grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
    height: 100%;
    width: 100%;
    min-height: 0;
    background: var(--sms-panel);
}
.sms-layout.with-history { grid-template-columns: minmax(260px, 320px) minmax(0, 1fr) 300px; }

.sms-sidebar {
    display: flex;
    flex-direction: column;
    min-height: 0;
    min-width: 0;
    background: var(--sms-panel);
    border-right: 1px solid var(--border);
}
.sms-sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.sms-sidebar-header h2 { margin: 0; font-size: 1rem; font-weight: 700; }
.sms-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.75rem; }
.sms-header-actions { display: flex; gap: 0.15rem; }
.sms-search { padding: 0.7rem 1.15rem 0.8rem; flex-shrink: 0; }
.sms-search input {
    width: 100%;
    padding: 0.45rem 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--sms-bg);
    color: var(--text-primary);
    font-size: 0.82rem;
}
.sms-thread-list { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0.35rem 0.55rem 0.75rem; }
.sms-thread {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    padding: 0.55rem 0.65rem;
    cursor: pointer;
    border-radius: 10px;
}
.sms-thread:hover { background: var(--sms-bg); }
.sms-thread.active { background: #eef0f3; }
.sms-thread.unread .sms-thread-name { font-weight: 700; }
.sms-thread-body { min-width: 0; flex: 1; }
.sms-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; }
.sms-thread-name { font-weight: 600; font-size: 0.84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); }
.sms-thread-time { color: var(--text-secondary); font-size: 0.68rem; white-space: nowrap; }
.sms-thread-preview { color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
.sms-badge {
    display: inline-flex; min-width: 1.05rem; height: 1.05rem; padding: 0 0.3rem;
    align-items: center; justify-content: center; border-radius: 999px;
    background: var(--sms-accent); color: #fff; font-size: 0.64rem; font-weight: 700; flex-shrink: 0;
}
.sms-list-hint { text-align: center; padding: 0.7rem; font-size: 0.72rem; color: var(--text-secondary); }
.sms-avatar {
    width: 32px; height: 32px; border-radius: 8px; background: var(--sms-accent); color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;
}

.sms-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden; background: var(--sms-bg); }
.sms-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
.sms-empty-card { text-align: center; max-width: 360px; }
.sms-empty-card h3 { margin: 0 0 0.4rem; font-size: 1.05rem; }
.sms-empty-card p { color: var(--text-secondary); margin: 0 0 1rem; font-size: 0.88rem; line-height: 1.45; }
.sms-link-btn { display: inline-block; padding: 0.5rem 0.85rem; border-radius: 8px; background: var(--sms-accent); color: #fff; text-decoration: none; font-weight: 600; font-size: 0.84rem; }

.sms-chat { display: flex; flex-direction: column; height: 100%; min-height: 0; background: var(--sms-imessage-bg); }
.sms-chat-header {
    display: flex; align-items: center; gap: 0.75rem;
    min-height: 64px;
    padding: 1rem 1.15rem;
    border-bottom: 1px solid var(--border);
    background: var(--sms-panel); flex-shrink: 0;
}
.sms-chat-meta { flex: 1; min-width: 0; }
.sms-chat-meta h3 { margin: 0; font-size: 0.92rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sms-chat-meta span { color: var(--text-secondary); font-size: 0.72rem; }
.sms-chat-actions { display: flex; gap: 0.25rem; }

.sms-messages {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 0.75rem 1rem 1.1rem;
    display: flex;
    flex-direction: column;
    background: var(--sms-imessage-bg);
}
.sms-load-older {
    text-align: center;
    font-size: 0.7rem;
    color: #8e8e93;
    padding: 0.35rem 0 0.5rem;
    flex-shrink: 0;
}
.sms-message-list {
    margin-top: auto;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-height: min-content;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
}
.sms-stamp {
    align-self: center;
    font-size: 11px;
    font-weight: 600;
    color: #8e8e93;
    letter-spacing: -0.01em;
    margin: 12px 0 8px;
    text-align: center;
    line-height: 1.3;
}
.sms-bubble {
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
.sms-bubble.inbound {
    align-self: flex-start;
    background: var(--sms-gray-bubble);
    color: #000;
    margin-left: 10px;
}
.sms-bubble.outbound {
    align-self: flex-end;
    background: var(--sms-green);
    color: #fff;
    margin-right: 10px;
}
.sms-bubble.solo,
.sms-bubble.group-start { margin-top: 8px; }
.sms-stamp + .sms-bubble { margin-top: 0; }
.sms-bubble.inbound.group-start:not(.solo) { border-bottom-left-radius: 5px; }
.sms-bubble.inbound.group-mid { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }
.sms-bubble.inbound.group-end { border-top-left-radius: 5px; }
.sms-bubble.outbound.group-start:not(.solo) { border-bottom-right-radius: 5px; }
.sms-bubble.outbound.group-mid { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
.sms-bubble.outbound.group-end { border-top-right-radius: 5px; }
.sms-bubble.inbound.tail::before,
.sms-bubble.outbound.tail::before {
    content: "";
    position: absolute;
    bottom: 0;
    width: 16px;
    height: 16px;
}
.sms-bubble.inbound.tail::after,
.sms-bubble.outbound.tail::after {
    content: "";
    position: absolute;
    bottom: 0;
    width: 10px;
    height: 16px;
    background: var(--sms-imessage-bg);
}
.sms-bubble.inbound.tail::before {
    left: -6px;
    background: var(--sms-gray-bubble);
    border-bottom-right-radius: 12px;
}
.sms-bubble.inbound.tail::after {
    left: -10px;
    border-bottom-right-radius: 8px;
}
.sms-bubble.outbound.tail::before {
    right: -6px;
    background: var(--sms-green);
    border-bottom-left-radius: 12px;
}
.sms-bubble.outbound.tail::after {
    right: -10px;
    border-bottom-left-radius: 8px;
}
.sms-delivered {
    align-self: flex-end;
    font-size: 11px;
    font-weight: 500;
    color: #8e8e93;
    margin: 2px 18px 2px 0;
    letter-spacing: -0.01em;
}
.sms-delivered.is-failed { color: #ff3b30; font-weight: 600; }
.sms-bubble.failed { opacity: 0.9; }

.sms-composer {
    display: flex; align-items: flex-end; gap: 0.45rem;
    padding: 0.55rem 0.85rem 0.75rem; border-top: 1px solid #e5e5ea;
    background: var(--sms-imessage-bg); flex-shrink: 0;
}
.sms-layout .chp-header {
    min-height: 64px;
    align-items: center;
    padding: 1rem 1.15rem;
}
.sms-composer textarea {
    flex: 1; resize: none; min-height: 36px; max-height: 110px;
    padding: 8px 14px; border: 1px solid #c7c7cc; border-radius: 20px;
    background: #fff; color: #000; font: inherit; font-size: 15px; line-height: 1.3;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
}
.sms-composer textarea::placeholder { color: #8e8e93; }
.sms-send-btn {
    width: 32px; height: 32px; border: 0; border-radius: 50%; padding: 0;
    background: var(--sms-green); color: #fff; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sms-send-btn svg { width: 16px; height: 16px; transform: rotate(-90deg); }
.sms-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.sms-icon-btn {
    width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent;
    color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
}
.sms-icon-btn:hover { background: var(--sms-bg); color: var(--text-primary); }
.sms-icon-btn svg { width: 16px; height: 16px; }
.sms-back { display: none; }
@include('partials.channel-reply-templates-styles')

.sms-modal {
    position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45);
    display: flex; align-items: center; justify-content: center; z-index: 20; padding: 1rem;
}
.sms-modal[hidden] { display: none; }
.sms-modal-card {
    width: min(420px, 100%); background: var(--sms-panel); border: 1px solid var(--border);
    border-radius: 12px; padding: 1.25rem; box-shadow: 0 12px 40px rgba(0,0,0,.18);
}
.sms-modal-card h3 { margin: 0 0 0.35rem; }
.sms-modal-help { margin: 0 0 1rem; color: var(--text-secondary); font-size: 0.85rem; }
.sms-label { display: block; font-size: 0.8rem; font-weight: 600; margin: 0.65rem 0 0.3rem; }
.sms-input { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--sms-bg); color: var(--text-primary); }
.sms-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.1rem; }
.sms-btn-primary, .sms-btn-secondary { border: 0; border-radius: 8px; padding: 0.55rem 0.9rem; font-weight: 600; cursor: pointer; }
.sms-btn-primary { background: var(--sms-accent); color: #fff; }
.sms-btn-secondary { background: var(--sms-bg); color: var(--text-primary); border: 1px solid var(--border); }

@media (max-width: 900px) {
    .sms-page-wrapper { padding: 8px; }
    .sms-page-wrapper, .sms-page { height: auto; min-height: calc(100vh - 64px); overflow: visible; }
    .sms-layout, .sms-layout.with-history { grid-template-columns: 1fr; height: auto; min-height: calc(100vh - 80px); }
    .sms-sidebar { min-height: calc(100vh - 64px); }
    .sms-sidebar.hidden-mobile { display: none; }
    .sms-main { min-height: calc(100vh - 64px); }
    .sms-main.hidden-mobile { display: none; }
    .sms-back { display: inline-flex; }
    .sms-chat { min-height: calc(100vh - 64px); }
}
</style>

<script>
@include('partials.channel-reply-templates-script')
(function () {
    const root = document.getElementById('smsApp');
    if (!root) return;

    const apiBase = root.dataset.apiBase;
    const csrf = root.dataset.csrf;
    const canSend = root.dataset.canSend === '1';
    const PAGE_SIZE = 40;
    let connected = root.dataset.connected === '1';
    let conversations = [];
    let activeId = null;
    let pollTimer = null;
    let searchTimer = null;
    let convHasMore = false;
    let convLoading = false;
    let messagesHasMore = false;
    let loadOlderInProgress = false;
    let messageIds = new Set();
    let oldestMessageId = null;
    let lastDirection = null;
    let lastDayKey = null;

    const els = {
        list: document.getElementById('smsThreadList'),
        empty: document.getElementById('smsEmpty'),
        chat: document.getElementById('smsChat'),
        messages: document.getElementById('smsMessages'),
        messageList: document.getElementById('smsMessageList'),
        loadOlder: document.getElementById('smsLoadOlder'),
        search: document.getElementById('smsSearch'),
        text: document.getElementById('smsTextInput'),
        send: document.getElementById('smsSendBtn'),
        headerName: document.getElementById('smsHeaderName'),
        headerStatus: document.getElementById('smsHeaderStatus'),
        headerAvatar: document.getElementById('smsHeaderAvatar'),
        sidebar: document.querySelector('.sms-sidebar'),
        main: document.querySelector('.sms-main'),
        connectLink: document.getElementById('smsConnectLink'),
        emptyTitle: document.getElementById('smsEmptyTitle'),
        emptyText: document.getElementById('smsEmptyText'),
        accountLabel: document.getElementById('smsAccountLabel'),
        modal: document.getElementById('smsNewModal'),
        newTo: document.getElementById('smsNewTo'),
        newName: document.getElementById('smsNewName'),
    };

    async function api(path, options = {}) {
        const res = await fetch(apiBase + path, {
            ...options,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Content-Type': 'application/json',
                ...(options.headers || {}),
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || data.error || 'Request failed');
        return data;
    }

    function initials(name) {
        return (name || 'S').split(/\s+/).map(p => p[0]).join('').slice(0, 2).toUpperCase();
    }

    function formatListTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        if (sameDay) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function formatStamp(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        if (d.toDateString() === now.toDateString()) return 'Today ' + time;
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + time;
        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 6);
        if (d > weekAgo) {
            return d.toLocaleDateString([], { weekday: 'long' }) + ' ' + time;
        }
        if (d.getFullYear() === now.getFullYear()) {
            return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' at ' + time;
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + time;
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

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    function assignedLeadLine(c) {
        if (window.LnsAssignedLead?.markup) return window.LnsAssignedLead.markup(c.lead, escapeHtml);
        const name = c?.lead?.assigned_user?.name;
        const chips = (c?.lead?.labels || []).filter(l => l?.name).map(l => escapeHtml(l.name)).join(', ');
        if (!name && !chips) return '';
        return `${name ? `<div class="channel-assigned">Assigned to ${escapeHtml(name)}</div>` : ''}${chips ? `<div class="channel-assigned">${chips}</div>` : ''}`;
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
        els.headerStatus.textContent = (conv.peer_phone || 'SMS') + assignedLeadSuffix(conv);
    }

    function contactHistoryOpts(conv) {
        return {
            phone: conv.peer_phone || '',
            name: conv.name || '',
            excludeChannel: 'sms',
            excludeId: conv.id,
            canEditLead: true,
            onLeadUpdated: applyLeadToActive,
            onSaved(data) {
                if (data?.data) applyLeadToActive(data.data);
                const current = conversations.find(c => c.id === activeId) || conv;
                window.loadChannelContactHistory('#smsContactHistory', contactHistoryOpts(current));
            },
        };
    }

    function nearBottom() {
        return els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 80;
    }

    function lastBubble() {
        const nodes = els.messageList.querySelectorAll('.sms-bubble');
        return nodes[nodes.length - 1] || null;
    }

    function renderThreads() {
        if (!conversations.length) {
            els.list.innerHTML = `<div class="sms-list-hint">No SMS conversations yet.</div>`;
            return;
        }

        els.list.innerHTML = conversations.map(c => `
            <div class="sms-thread ${c.id === activeId ? 'active' : ''} ${c.unread_count ? 'unread' : ''}" data-id="${c.id}">
                <div class="sms-avatar">${initials(c.name)}</div>
                <div class="sms-thread-body">
                    <div class="sms-thread-top">
                        <div class="sms-thread-name">${escapeHtml(c.name || c.peer_phone)}</div>
                        <div class="sms-thread-time">${formatListTime(c.last_message_at)}</div>
                    </div>
                    <div class="sms-thread-preview">${escapeHtml(c.last_message_preview || '')}</div>
                    ${assignedLeadLine(c)}
                </div>
                ${c.unread_count ? `<span class="sms-badge">${c.unread_count}</span>` : ''}
            </div>
        `).join('') + (convHasMore ? `<div class="sms-list-hint" id="smsListMore">Scroll for older chats</div>` : '');

        els.list.querySelectorAll('.sms-thread').forEach(node => {
            node.addEventListener('click', () => openConversation(Number(node.dataset.id)));
        });
    }

    function stampMarkup(iso) {
        return `<div class="sms-stamp" data-day="${dayKey(iso)}" data-ts="${iso}">${escapeHtml(formatStamp(iso))}</div>`;
    }

    function messageMarkup(m) {
        const status = (m.status || '').toLowerCase();
        const failed = status === 'failed' || status === 'undelivered';
        const iso = m.sent_at || m.created_at || '';
        return `<div class="sms-bubble ${m.direction}${failed ? ' failed' : ''}" data-id="${m.id}" data-direction="${m.direction}" data-day="${dayKey(iso)}" data-ts="${iso}" data-status="${escapeHtml(status)}">${escapeHtml(m.body || '')}</div>`;
    }

    function refreshThreadChrome() {
        const nodes = [...els.messageList.querySelectorAll('.sms-bubble')];
        els.messageList.querySelectorAll('.sms-delivered').forEach(n => n.remove());
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
            node.classList.toggle('follow', !!samePrev);
        });

        const lastOut = [...nodes].reverse().find(n => n.dataset.direction === 'outbound');
        if (!lastOut) return;
        const st = (lastOut.dataset.status || '').toLowerCase();
        const failed = lastOut.classList.contains('failed') || st === 'failed' || st === 'undelivered';
        let label = 'Delivered';
        if (failed) label = 'Not Delivered';
        else if (st === 'queued' || st === 'accepted' || st === 'sending') label = 'Sending';
        lastOut.insertAdjacentHTML('afterend', `<div class="sms-delivered${failed ? ' is-failed' : ''}">${label}</div>`);
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
        lastDirection = m.direction;
        lastDayKey = dayKey(iso);
        if (!oldestMessageId || m.id < oldestMessageId) oldestMessageId = m.id;
        refreshThreadChrome();
    }

    function prependMessages(items) {
        if (!items.length) return;
        const firstStamp = els.messageList.firstElementChild?.classList.contains('sms-stamp')
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
            lastDirection = lastDirection || m.direction;
            prevIso = iso;
            if (!oldestMessageId || m.id < oldestMessageId) oldestMessageId = m.id;
        });
        els.messageList.insertAdjacentHTML('afterbegin', html);
        if (firstStamp && prevIso && !shouldStamp(prevIso, firstStamp.dataset.ts)) {
            firstStamp.remove();
        }
        refreshThreadChrome();
    }

    function resetMessages() {
        els.messageList.innerHTML = '';
        messageIds = new Set();
        oldestMessageId = null;
        lastDirection = null;
        lastDayKey = null;
        messagesHasMore = false;
        loadOlderInProgress = false;
        els.loadOlder.hidden = true;
    }

    async function loadBootstrap() {
        try {
            const data = await api('/bootstrap');
            connected = !!data.connected;
            window.smsTemplates?.applyBootstrap(data);
            if (data.account?.twilio_number) {
                els.accountLabel.textContent = data.account.twilio_number;
            }
            if (!connected) {
                els.emptyTitle.textContent = 'Connect Twilio';
                els.emptyText.textContent = 'Add your Twilio credentials under Integrations, then assign a number to start texting.';
                els.connectLink.style.display = '';
            } else if (!data.account?.has_number) {
                els.emptyTitle.textContent = 'Assign an SMS number';
                els.emptyText.textContent = 'Your account needs an assigned SMS number before you can send or receive texts.';
                els.connectLink.href = root.dataset.phoneUrl;
                els.connectLink.textContent = 'Open Phone System';
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

    async function loadConversations({ append = false, merge = false } = {}) {
        if (convLoading) return;
        convLoading = true;
        try {
            const params = new URLSearchParams({ limit: String(PAGE_SIZE) });
            const q = (els.search.value || '').trim();
            if (q) params.set('q', q);
            if (append && conversations.length) {
                params.set('before_id', String(conversations[conversations.length - 1].id));
            }
            const data = await api('/conversations?' + params.toString());
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
        els.loadOlder.hidden = false;
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
            els.loadOlder.hidden = !messagesHasMore;
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

    async function openConversation(id) {
        id = Number(id);
        if (!id) return;

        let conv = conversations.find(c => Number(c.id) === id) || null;
        if (!conv) {
            try {
                const preview = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
                conv = preview.conversation ? { ...preview.conversation, unread_count: 0 } : null;
                if (conv && !conversations.some(c => Number(c.id) === id)) {
                    conversations.unshift(conv);
                } else if (conv) {
                    const idx = conversations.findIndex(c => Number(c.id) === id);
                    conversations[idx] = { ...conversations[idx], ...conv };
                    conv = conversations[idx];
                }
            } catch (e) {
                return;
            }
            if (!conv) return;
        }

        activeId = id;

        conv.unread_count = 0;
        els.empty.style.display = 'none';
        els.chat.style.display = 'flex';
        els.headerName.textContent = conv.name || conv.peer_phone || 'Contact';
        els.headerStatus.textContent = (conv.peer_phone || 'SMS') + assignedLeadSuffix(conv);
        els.headerAvatar.textContent = initials(conv.name || conv.peer_phone);
        renderThreads();
        resetMessages();

        if (window.matchMedia('(max-width: 900px)').matches) {
            els.sidebar.classList.add('hidden-mobile');
            els.main.classList.remove('hidden-mobile');
        }

        const data = await api(`/conversations/${id}/messages?limit=${PAGE_SIZE}`);
        messagesHasMore = !!data.has_more;
        els.loadOlder.hidden = !messagesHasMore;
        (data.data || []).forEach(appendMessage);
        els.messages.scrollTop = els.messages.scrollHeight;
        await fillUntilScrollable();
        els.messages.scrollTop = els.messages.scrollHeight;

        if (data.conversation) {
            const idx = conversations.findIndex(c => c.id === id);
            if (idx >= 0) conversations[idx] = { ...conversations[idx], ...data.conversation, unread_count: 0 };
            Object.assign(conv, conversations[idx] || data.conversation, { unread_count: 0 });
            els.headerStatus.textContent = (conv.peer_phone || 'SMS') + assignedLeadSuffix(conv);
            renderThreads();
        }

        document.querySelector('.sms-layout')?.classList.add('with-history');
        window.loadChannelContactHistory('#smsContactHistory', contactHistoryOpts(conv));
        window.updateHeaderNotificationsBadge?.();
        window.updateSidebarUnreadBadges?.();
    }

    async function pollActiveMessages() {
        if (!activeId || loadOlderInProgress) return;
        const data = await api(`/conversations/${activeId}/messages?limit=${PAGE_SIZE}`);
        const incoming = data.data || [];
        const newer = incoming.filter(m => !messageIds.has(m.id));
        if (!newer.length) return;
        const pin = nearBottom();
        newer.forEach(appendMessage);
        if (pin) els.messages.scrollTop = els.messages.scrollHeight;
    }

    async function sendText() {
        if (!activeId || !canSend) return;
        const body = els.text.value.trim();
        if (!body) return;
        els.send.disabled = true;
        try {
            const data = await api(`/conversations/${activeId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body }),
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

    async function startConversation() {
        const to = els.newTo.value.trim();
        if (!to) {
            alert('Enter a phone number.');
            return;
        }
        try {
            const data = await api('/conversations', {
                method: 'POST',
                body: JSON.stringify({
                    to,
                    name: els.newName.value.trim() || null,
                }),
            });
            els.modal.hidden = true;
            els.newTo.value = '';
            els.newName.value = '';
            await loadConversations();
            if (data.data?.id) {
                await openConversation(data.data.id);
            }
        } catch (e) {
            alert(e.message);
        }
    }

    async function callPeer() {
        if (!activeId) return;
        try {
            const data = await api(`/conversations/${activeId}/call-link`);
            if (data.data?.tel) window.location.href = data.data.tel;
            else alert('No phone number available.');
        } catch (e) {
            alert(e.message);
        }
    }

    document.getElementById('smsRefreshBtn').addEventListener('click', () => loadConversations().catch(console.error));
    document.getElementById('smsBackBtn').addEventListener('click', () => {
        els.sidebar.classList.remove('hidden-mobile');
        els.main.classList.add('hidden-mobile');
    });
    document.getElementById('smsNewBtn')?.addEventListener('click', () => { els.modal.hidden = false; els.newTo.focus(); });
    document.getElementById('smsNewCancel')?.addEventListener('click', () => { els.modal.hidden = true; });
    document.getElementById('smsNewStart')?.addEventListener('click', startConversation);
    els.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadConversations().catch(console.error), 250);
    });
    els.send?.addEventListener('click', sendText);
    els.text?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendText();
        }
    });
    els.text?.addEventListener('input', () => {
        els.text.style.height = 'auto';
        els.text.style.height = Math.min(els.text.scrollHeight, 110) + 'px';
    });
    document.getElementById('smsCallBtn').addEventListener('click', callPeer);

    els.list.addEventListener('scroll', () => {
        if (convLoading || !convHasMore) return;
        const remaining = els.list.scrollHeight - els.list.scrollTop - els.list.clientHeight;
        if (remaining < 120) loadConversations({ append: true }).catch(console.error);
    });

    els.messages.addEventListener('scroll', () => {
        if (els.messages.scrollTop < 48) loadOlderMessages();
    });

    window.smsTemplates = window.initChannelReplyTemplates({
        prefix: 'sms',
        bodyMax: 1600,
        label: 'SMS Templates',
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
            pollTimer = setInterval(() => {
                loadConversations({ merge: true }).catch(() => {});
                pollActiveMessages().catch(() => {});
            }, 12000);
        }
    })();
})();
</script>
@endsection
