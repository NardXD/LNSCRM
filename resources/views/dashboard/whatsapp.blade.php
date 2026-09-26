@extends('layouts.app')

@section('title', 'WhatsApp')

@push('styles')
    <style>
    @include('partials.channel-reply-templates-styles')
    </style>
    @vite(['resources/css/pages/whatsapp.css'])
@endpush

@push('scripts')
    <script>
    @include('partials.channel-reply-templates-script')
    </script>
    @vite(['resources/js/pages/whatsapp.js'])
@endpush

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
            <div class="wa-thread-list" id="waThreadList" aria-busy="true">
                @include('partials.skeleton-threads')
            </div>
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

@endsection

