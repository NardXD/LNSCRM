@extends('layouts.app')

@section('title', 'SMS')

@push('styles')
    <style>
    @include('partials.channel-reply-templates-styles')
    </style>
    @vite(['resources/css/pages/sms.css'])
@endpush

@push('scripts')
    <script>
    @include('partials.channel-reply-templates-script')
    </script>
    @vite(['resources/js/pages/sms.js'])
@endpush

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
            <div class="sms-thread-list" id="smsThreadList" aria-busy="true">
                @include('partials.skeleton-threads')
            </div>
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

@endsection

