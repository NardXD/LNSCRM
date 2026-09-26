@extends('layouts.app')

@section('title', 'Integrations')

@push('styles')
    @vite(['resources/css/pages/integrations.css'])
@endpush

@push('scripts')
<script>
    window.__integrationsConfig = {
        voiceWebhook: @json(route('twilio.voice')),
        smsWebhook: @json(route('twilio.sms-webhook')),
        phoneSystemUrl: @json(route('twilio.call')),
        viberChatUrl: @json(route('viber')),
        whatsappChatUrl: @json(route('whatsapp')),
        facebookChatUrl: @json(route('facebook')),
        calendarOauthSettingsUrl: @json(route('api.calendar.oauth-settings')),
        calendarOauthSettingsStoreUrl: @json(route('api.calendar.oauth-settings.store')),
        stripeStoreUrl: @json(route('api.integrations.stripe.store')),
        stripeWebhookUrl: @json(url('/webhooks/stripe/company/' . (auth()->user()?->company_id ?? ''))),
        googleCalendarCallbackUrl: @json(url('/calendar/connect/google/callback')),
        outlookMailCallbackUrl: @json(url('/inbox/connect/outlook/callback')),
        wiseRecipientsUrl: @json(route('wise-recipients')),
        canViewWiseRecipients: @json((bool) auth()->user()?->hasPermission('view_wise_recipients')),
    };
</script>
    @vite(['resources/js/pages/integrations.js'])
@endpush

@section('content')
    <div class="page-header" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h1 class="page-title">Integrations</h1>
            <p class="page-subtitle">Connect and manage third-party services and tools</p>
        </div>
        <a href="{{ url('/apiguide/index.html') }}" target="_blank" rel="noopener" class="btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
            </svg>
            API Guide
        </a>
    </div>

    <div class="integrations-container">
        <!-- Integration Categories -->
        <div class="integration-categories">
            <button class="category-btn active" data-category="all">All</button>
            <button class="category-btn" data-category="payment">Payment</button>
            <button class="category-btn" data-category="accounting">Accounting</button>
            <button class="category-btn" data-category="communication">Communication</button>
            <button class="category-btn" data-category="productivity">Productivity</button>
            <button class="category-btn" data-category="automation">Automation</button>
        </div>

        <!-- Integrations Grid -->
        <div class="integrations-grid" id="integrationsGrid">
            <!-- Integrations will be populated by JavaScript -->
        </div>
    </div>

    <!-- Integration Modal -->
    <div class="integration-modal" id="integrationModal">
        <div class="integration-modal-content">
            <button class="modal-close" onclick="closeIntegrationModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <div class="modal-integration-info">
                    <div class="modal-integration-icon" id="modalIcon">
                        <!-- Icon will be populated by JavaScript -->
                    </div>
                    <div>
                        <h2 class="modal-integration-name" id="modalName">Integration Name</h2>
                        <p class="modal-integration-description" id="modalDescription">Integration description</p>
                    </div>
                </div>
                <div class="modal-status" id="modalStatus">
                    <!-- Status will be populated by JavaScript -->
                </div>
            </div>

            <div class="modal-body">
                <div class="integration-details" id="integrationDetails">
                    <!-- Details will be populated by JavaScript -->
                </div>

                <div class="integration-config" id="integrationConfig">
                    <!-- Configuration form will be populated by JavaScript -->
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeIntegrationModal()">Cancel</button>
                <button class="btn-primary" id="modalActionBtn" onclick="handleIntegrationAction()">
                    Connect
                </button>
            </div>
        </div>
    </div>
@endsection
