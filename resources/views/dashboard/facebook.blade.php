@extends('layouts.app')

@section('title', 'Facebook & Instagram')

@push('styles')
    <style>
    @include('partials.channel-reply-templates-styles')
    </style>
    @vite(['resources/css/pages/facebook.css'])
@endpush

@push('scripts')
    <script>
    @include('partials.channel-reply-templates-script')
    </script>
    @vite(['resources/js/pages/facebook.js'])
@endpush

@section('content')
<div class="fb-page-wrapper">
<div class="fb-page" id="fbApp"
     data-api-base="{{ url('api/facebook') }}"
     data-csrf="{{ csrf_token() }}"
     data-connected="{{ $integrationConnected ? '1' : '0' }}"
     data-timezone="{{ $appTimezone ?? config('app.timezone') }}"
     data-integrations-url="{{ route('integrations') }}">
    <div class="fb-layout">
        <aside class="fb-sidebar">
            <div class="fb-sidebar-header">
                <div>
                    <h2>Messenger &amp; Instagram</h2>
                    <p class="fb-sub" id="fbAccountLabel">
                        @if($pageName && $instagramUsername)
                            {{ $pageName }} · {{ '@'.$instagramUsername }}
                        @elseif($pageName)
                            {{ $pageName }}
                        @elseif($instagramUsername)
                            {{ '@'.$instagramUsername }}
                        @else
                            Page messages
                        @endif
                    </p>
                </div>
                <div class="fb-header-actions">
                    @if(auth()->user()?->hasPermission('view_leads'))
                        <a class="fb-icon-btn" href="{{ route('leads') }}?openRules=1" target="_blank" rel="noopener" title="Automation rules for Facebook &amp; Instagram messages">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="10" y2="18"/><circle cx="18" cy="15" r="3"/><path d="m20.5 17.5 1.5 1.5"/></svg>
                        </a>
                    @endif
                    <button type="button" class="fb-icon-btn" id="fbSyncBtn" title="Sync Messenger inbox (also auto-syncs every 45s)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </button>
                    <button type="button" class="fb-icon-btn" id="fbRefreshBtn" title="Refresh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>
            <div class="fb-sync-note" id="fbSyncNote"></div>
            <div class="fb-filters">
                <button type="button" class="fb-chip active" data-channel="">All</button>
                <button type="button" class="fb-chip" data-channel="messenger">Messenger</button>
                <button type="button" class="fb-chip" data-channel="instagram">Instagram</button>
            </div>
            <div class="fb-filters fb-read-filters">
                <button type="button" class="fb-chip active" data-read="">All</button>
                <button type="button" class="fb-chip" data-read="unread">Unread</button>
                <button type="button" class="fb-chip" data-read="read">Read</button>
            </div>
            <div class="fb-search">
                <input type="search" id="fbSearch" placeholder="Search conversations…" autocomplete="off">
            </div>
            @include('partials.channel-reply-templates', [
                'prefix' => 'fb',
                'label' => 'Facebook Templates',
            ])
            <div class="fb-thread-list" id="fbThreadList" aria-busy="true">
                <div class="fb-skel-list" aria-hidden="true">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="fb-skel-thread">
                            <div class="fb-skel-avatar"></div>
                            <div class="fb-skel-lines">
                                <span class="fb-skel-line w-55"></span>
                                <span class="fb-skel-line w-80"></span>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </aside>

        <main class="fb-main">
            <div class="fb-empty" id="fbEmpty">
                <div class="fb-empty-card">
                    <h3 id="fbEmptyTitle">Select a conversation</h3>
                    <p id="fbEmptyText">Facebook Page and Instagram Direct messages appear here. Use Sync to import replies sent from Messenger / Page Inbox.</p>
                    <a href="{{ route('integrations') }}" class="fb-link-btn" id="fbConnectLink" style="{{ $integrationConnected ? 'display:none' : '' }}">Connect Facebook in Integrations</a>
                </div>
            </div>

            <div class="fb-chat" id="fbChat" style="display:none;">
                <header class="fb-chat-header">
                    <button type="button" class="fb-icon-btn fb-back" id="fbBackBtn" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="fb-avatar" id="fbHeaderAvatar"></div>
                    <div class="fb-chat-meta">
                        <h3 id="fbHeaderName">Customer</h3>
                        <span id="fbHeaderStatus">Messenger</span>
                    </div>
                    <button type="button" class="fb-icon-btn" id="fbMarkUnreadBtn" title="Mark as unread">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    </button>
                </header>

                <div class="fb-messages" id="fbMessages">
                    <div class="fb-load-older" id="fbLoadOlder" hidden>Loading earlier messages…</div>
                    <div class="fb-message-list" id="fbMessageList"></div>
                </div>

                <footer class="fb-composer">
                    <div class="fb-attach">
                        <button type="button" class="fb-icon-btn" id="fbAttachImage" title="Send image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="fb-icon-btn" id="fbAttachVideo" title="Send video">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                        </button>
                        <button type="button" class="fb-icon-btn" id="fbAttachFile" title="Send file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <input type="file" id="fbFileInput" hidden>
                    </div>
                    <div class="ch-tpl-picker-wrap" id="fbTemplatePickerWrap">
                        <button type="button" class="fb-icon-btn" id="fbTemplateBtn" title="Insert template">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </button>
                        @include('partials.channel-reply-templates-picker', ['prefix' => 'fb'])
                    </div>
                    <textarea id="fbTextInput" rows="1" placeholder="Type a message…"></textarea>
                    <button type="button" class="fb-send-btn" id="fbSendBtn" title="Send" aria-label="Send">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.4 1.4 5.6 5.6H4v2h12.2l-5.6 5.6L12 20l8-8z"/></svg>
                    </button>
                </footer>
            </div>
        </main>
        @include('partials.contact-history-panel', ['panelId' => 'fbContactHistory'])
    </div>

    @include('partials.channel-reply-templates-list-modal', [
        'prefix' => 'fb',
        'label' => 'Facebook Templates',
        'help' => 'Plain-text snippets for Messenger & Instagram replies only. SMS has its own separate templates.',
    ])
    @include('partials.channel-reply-templates-modal', [
        'prefix' => 'fb',
        'bodyMax' => 2000,
        'label' => 'Facebook Templates',
        'help' => 'Plain-text snippets for Messenger & Instagram replies only. SMS has its own separate templates.',
    ])
</div>
</div>

@endsection

