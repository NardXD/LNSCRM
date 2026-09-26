@extends('layouts.app')

@section('title', 'Viber')

@push('styles')
    <style>
    @include('partials.channel-reply-templates-styles')
    </style>
    @vite(['resources/css/pages/viber.css'])
@endpush

@push('scripts')
    <script>
    @include('partials.channel-reply-templates-script')
    </script>
    @vite(['resources/js/pages/viber.js'])
@endpush

@section('content')
<div class="viber-page" id="viberApp"
     data-api-base="{{ url('api/viber') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-bot-name="{{ $botName ?? '' }}"
     data-bot-share="{{ $botShareUrl ?? '' }}"
     data-integrations-url="{{ route('integrations') }}">
    <div class="viber-layout">
        <aside class="viber-sidebar">
            <div class="viber-sidebar-header">
                <div>
                    <h2>Viber</h2>
                    <p class="viber-sub" id="viberBotLabel">{{ $botName ?: 'Business chats' }}</p>
                </div>
                <button type="button" class="viber-icon-btn" id="viberRefreshBtn" title="Refresh">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                </button>
            </div>
            <div class="viber-search">
                <input type="search" id="viberSearch" placeholder="Search conversations...">
            </div>
            @include('partials.channel-reply-templates', [
                'prefix' => 'vb',
                'label' => 'Viber Templates',
            ])
            <div class="viber-thread-list" id="viberThreadList" aria-busy="true">
                @include('partials.skeleton-threads')
            </div>
        </aside>

        <main class="viber-main">
            <div class="viber-empty" id="viberEmpty">
                <div class="viber-empty-card">
                    <h3 id="viberEmptyTitle">Select a conversation</h3>
                    <p id="viberEmptyText">Customer messages appear here after they message your Viber sender.</p>
                    <a href="{{ route('integrations') }}" class="viber-link-btn" id="viberConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect Viber in Integrations</a>
                </div>
            </div>

            <div class="viber-chat" id="viberChat" style="display:none;">
                <header class="viber-chat-header">
                    <button type="button" class="viber-icon-btn viber-back" id="viberBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="viber-avatar" id="viberHeaderAvatar"></div>
                    <div class="viber-chat-meta">
                        <h3 id="viberHeaderName">Customer</h3>
                        <span id="viberHeaderStatus">Subscribed</span>
                    </div>
                    <div class="viber-chat-actions">
                        <button type="button" class="viber-icon-btn" id="viberCallBtn" title="Call on Viber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button type="button" class="viber-icon-btn" id="viberOpenBtn" title="Open in Viber">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                        </button>
                    </div>
                </header>

                <div class="viber-messages" id="viberMessages">
                    <div class="viber-load-older" id="viberLoadOlder" hidden>Loading earlier messages…</div>
                    <div class="viber-message-list" id="viberMessageList"></div>
                </div>

                <footer class="viber-composer">
                    <div class="viber-attach">
                        <button type="button" class="viber-icon-btn" id="viberAttachImage" title="Send image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="viber-icon-btn" id="viberAttachVideo" title="Send video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                        <button type="button" class="viber-icon-btn" id="viberAttachFile" title="Send file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <input type="file" id="viberFileInput" hidden>
                    </div>
                    <div class="ch-tpl-picker-wrap" id="vbTemplatePickerWrap">
                        <button type="button" class="viber-icon-btn" id="vbTemplateBtn" title="Insert template">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </button>
                        @include('partials.channel-reply-templates-picker', ['prefix' => 'vb'])
                    </div>
                    <textarea id="viberTextInput" rows="1" placeholder="Type a message..."></textarea>
                    <button type="button" class="viber-send-btn" id="viberSendBtn">Send</button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'viberContactHistory'])
    </div>

    @include('partials.channel-reply-templates-list-modal', [
        'prefix' => 'vb',
        'label' => 'Viber Templates',
        'help' => 'Plain-text snippets for Viber replies only. Each channel has its own separate templates.',
    ])
    @include('partials.channel-reply-templates-modal', [
        'prefix' => 'vb',
        'bodyMax' => 7000,
        'label' => 'Viber Templates',
        'help' => 'Plain-text snippets for Viber replies only. Each channel has its own separate templates.',
    ])
</div>

@endsection

