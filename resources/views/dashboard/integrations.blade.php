@extends('layouts.app')

@section('title', 'Integrations')

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

@push('styles')
<style>
    .integrations-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Categories */
    .integration-categories {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .category-btn {
        padding: 0.625rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .category-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .category-btn.active {
        background: var(--accent);
        color: white;
    }

    /* Integrations Grid */
    .integrations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .integration-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
        position: relative;
    }

    .integration-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(95, 97, 230, 0.1);
    }

    .integration-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .integration-icon-wrapper {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
        margin-bottom: 1rem;
    }

    .integration-icon-wrapper.paypal {
        background: #f0f8ff;
    }

    .integration-icon-wrapper.stripe {
        background: #f5f5ff;
    }

    .integration-icon-wrapper.wise {
        background: #e6f7f7;
    }

    .integration-icon-wrapper.google,
    .integration-icon-wrapper.gmail {
        background: #f0f4ff;
    }

    .integration-icon-wrapper.openai {
        background: #f0f0ff;
    }

    .integration-icon-wrapper.calendar {
        background: #f0f7ff;
    }

    .integration-icon-wrapper.outlook {
        background: #e8f1fb;
    }

    .integration-icon-wrapper.twilio {
        background: #e6f3ff;
    }

    .integration-icon-wrapper.viber {
        background: #efeaff;
    }

    .integration-icon-wrapper.whatsapp {
        background: #e8f8ef;
    }

    .integration-icon-wrapper.facebook {
        background: #e8f1ff;
    }

    .integration-icon-wrapper.storeganise {
        background: #eef6ff;
    }

    .integration-icon-wrapper.front {
        background: #fff4eb;
    }

    .front-import-panel {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--border);
    }

    .front-import-results {
        margin-top: 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 0.875rem 1rem;
        font-size: 0.8125rem;
    }

    .front-import-results h4 {
        margin: 0 0 0.75rem;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .front-import-results dl {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.35rem 1rem;
        margin: 0;
    }

    .front-import-results dt {
        color: var(--text-secondary);
    }

    .front-import-results dd {
        margin: 0;
        font-weight: 600;
        text-align: right;
    }

    .front-import-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .front-mapping-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
        margin-top: 0.75rem;
    }

    .front-mapping-table th,
    .front-mapping-table td {
        padding: 0.5rem 0.35rem;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: middle;
    }

    .front-mapping-table th {
        color: var(--text-secondary);
        font-weight: 600;
    }

    .front-unmatched-list {
        margin: 0.75rem 0 0;
        padding-left: 1.1rem;
        color: var(--text-secondary);
    }

    .front-import-loading {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 1rem;
        padding: 0.875rem 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 10px;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .front-import-loading[hidden] {
        display: none !important;
    }

    .front-import-loading-bar {
        flex: 1;
        height: 6px;
        background: #e5e7eb;
        border-radius: 999px;
        overflow: hidden;
    }

    .front-import-loading-bar > span {
        display: block;
        height: 100%;
        width: 35%;
        background: linear-gradient(90deg, #5f61e6, #818cf8);
        border-radius: 999px;
        animation: frontImportIndeterminate 1.2s ease-in-out infinite;
    }

    @keyframes frontImportIndeterminate {
        0% { transform: translateX(-120%); }
        100% { transform: translateX(320%); }
    }

    .front-import-spinner {
        width: 18px;
        height: 18px;
        border: 2px solid #dbeafe;
        border-top-color: #5f61e6;
        border-radius: 50%;
        animation: frontImportSpin 0.8s linear infinite;
        flex-shrink: 0;
    }

    @keyframes frontImportSpin {
        to { transform: rotate(360deg); }
    }

    .integration-status {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .integration-status.connected {
        background: #d1fae5;
        color: #059669;
    }

    .integration-status.disconnected {
        background: #e5e7eb;
        color: #6b7280;
    }

    .integration-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .integration-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    .integration-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }

    .integration-category {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .integration-action {
        padding: 0.5rem 1rem;
        border: 1px solid var(--border);
        background: var(--bg-primary);
        border-radius: 6px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .integration-action:hover {
        background: var(--accent);
        border-color: var(--accent);
        color: white;
    }

    /* Integration Modal */
    .integration-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .integration-modal.active {
        display: flex;
        opacity: 1;
    }

    .integration-modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .integration-modal.active .integration-modal-content {
        transform: scale(1);
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.5);
        border: none;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: background 0.15s;
    }

    .modal-close:hover {
        background: rgba(0, 0, 0, 0.7);
    }

    .modal-close svg {
        width: 20px;
        height: 20px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .modal-integration-info {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        flex: 1;
    }

    .modal-integration-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        flex-shrink: 0;
    }

    .modal-integration-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .modal-integration-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .modal-status {
        flex-shrink: 0;
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
    }

    .integration-details {
        margin-bottom: 2rem;
    }

    .details-section {
        margin-bottom: 1.5rem;
    }

    .details-section:last-child {
        margin-bottom: 0;
    }

    .details-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .details-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .detail-item svg {
        width: 18px;
        height: 18px;
        color: var(--accent);
        flex-shrink: 0;
    }

    .integration-config {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1.5rem;
    }

    .config-form {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .form-input {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-help {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .connected-info {
        background: #d1fae5;
        border: 1px solid #10b981;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }

    .connected-info-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: #059669;
        margin-bottom: 0.5rem;
    }

    .connected-info-text {
        font-size: 0.8125rem;
        color: #047857;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    /* Buttons */
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    .btn-danger {
        background: #fee2e2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .btn-danger:hover {
        background: #fecaca;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .integration-categories {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .integrations-grid {
            grid-template-columns: 1fr;
        }

        .integration-modal-content {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 0;
        }

        .modal-header {
            flex-direction: column;
        }

        .modal-footer {
            flex-direction: column;
        }

        .modal-footer .btn-primary,
        .modal-footer .btn-secondary {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const TWILIO_SETUP = {
        voiceWebhook: @json(route('twilio.voice')),
        smsWebhook: @json(route('twilio.sms-webhook')),
        phoneSystemUrl: @json(route('twilio.call')),
        viberChatUrl: @json(route('viber')),
        whatsappChatUrl: @json(route('whatsapp')),
        facebookChatUrl: @json(route('facebook')),
    };

    // Integrations Data
    const integrationsData = [
        {
            id: 'paypal',
            name: 'PayPal',
            description: 'Accept payments via PayPal. Process transactions securely and manage your PayPal account.',
            category: 'payment',
            icon: '💳',
            status: 'connected',
            features: ['Payment processing', 'Refund management', 'Transaction history', 'Webhook support']
        },
        {
            id: 'stripe',
            name: 'Stripe',
            description: 'Accept credit card payments with Stripe. Secure payment processing with global support.',
            category: 'payment',
            icon: '💳',
            status: 'disconnected',
            features: ['Credit card processing', 'Subscription billing', 'Payment intents', '3D Secure']
        },
        {
            id: 'wise',
            name: 'Wise',
            description: 'Send and receive international payments with Wise. Low-cost transfers in 50+ currencies.',
            category: 'payment',
            icon: '🌍',
            status: 'disconnected',
            features: ['International transfers', 'Multi-currency accounts', 'Batch payments', 'Real-time exchange rates']
        },
        {
            id: 'gmail',
            name: 'Gmail',
            description: 'Connect your Gmail account to send emails (e.g. quotations, invoices). Uses Gmail SMTP with App Password.',
            category: 'communication',
            icon: '📧',
            status: 'disconnected',
            features: ['Send emails via SMTP', 'Quotation emails', 'Invoice emails', 'Company-wide configuration']
        },
        {
            id: 'google-login',
            name: 'Google Login',
            description: 'Enable users to sign in with their Google account. Quick and secure authentication.',
            category: 'productivity',
            icon: '🔐',
            status: 'connected',
            features: ['OAuth 2.0', 'Single sign-on', 'Account linking', 'Security compliance']
        },
        {
            id: 'openai',
            name: 'OpenAI',
            description: 'AI-powered assistance using OpenAI. Get intelligent responses and automation suggestions.',
            category: 'automation',
            icon: '🤖',
            status: 'disconnected',
            features: ['Chat assistance', 'Content generation', 'Smart suggestions', 'Text analysis']
        },
        {
            id: 'twilio',
            name: 'Twilio',
            description: 'Connect your Twilio account for phone, WhatsApp, Viber, Facebook Messenger, and SMS using standard Twilio APIs (Voice, Messages).',
            category: 'communication',
            icon: '📞',
            status: 'disconnected',
            features: ['Phone system', 'WhatsApp, Viber & Messenger', 'SMS', 'Browser calling', 'Call logging']
        },
        {
            id: 'viber',
            name: 'Viber Business',
            description: 'Send and receive Viber messages through your Twilio account using a Viber Business Sender.',
            category: 'communication',
            icon: '💬',
            status: 'disconnected',
            features: ['1:1 chat via Twilio', 'Images & files', 'Welcome message', 'Webhook callbacks', 'Open / call in Viber']
        },
        {
            id: 'whatsapp',
            name: 'WhatsApp Business',
            description: 'Send and receive WhatsApp messages through your Twilio account using a WhatsApp-enabled sender number.',
            category: 'communication',
            icon: '📱',
            status: 'disconnected',
            features: ['1:1 chat via Twilio', 'Images & documents', 'Webhook callbacks', '24h messaging window', 'Open in WhatsApp']
        },
        {
            id: 'facebook',
            name: 'Facebook & Instagram',
            description: 'Facebook Messenger via Twilio, plus Instagram Direct via native Meta webhooks.',
            category: 'communication',
            icon: '📘',
            status: 'disconnected',
            features: ['Twilio Messenger', 'Instagram Direct via Meta', 'Images & files', 'Meta webhooks', 'Welcome message']
        },
        {
            id: 'calendar',
            name: 'Google Calendar',
            description: 'Configure Google Calendar OAuth so users can connect their calendars from the Calendar page.',
            category: 'productivity',
            icon: '📅',
            status: 'disconnected',
            features: ['Google Calendar sync', 'Per-company OAuth credentials', 'Connect from Calendar page']
        },
        {
            id: 'outlook',
            name: 'Microsoft Outlook',
            description: 'Configure Microsoft Outlook OAuth for calendar sync and Inbox mail (personal & shared mailboxes).',
            category: 'productivity',
            icon: '📧',
            status: 'disconnected',
            features: ['Outlook Calendar sync', 'Outlook Inbox / shared mail', 'Per-company OAuth credentials', 'Personal & shared mailbox connection']
        },
        {
            id: 'storeganise',
            name: 'Storeganise',
            description: 'Connect your Storeganise self-storage platform. Sync unit rentals, sites, and storage operations with the CRM.',
            category: 'productivity',
            icon: '🏢',
            status: 'disconnected',
            features: ['Unit rental sync', 'Site management', 'Admin API access', 'Webhook notifications']
        },
        {
            id: 'front',
            name: 'Front.com',
            description: 'One-time import of Front conversation tags into LNSCRM shared inboxes. Connect your Front API token, map inboxes, and run the import.',
            category: 'communication',
            icon: '🏷️',
            status: 'disconnected',
            features: ['Import inbox tags', 'Inbox mapping', 'Dry-run preview', 'Import history']
        }
    ];

    let currentCategory = 'all';
    let currentIntegration = null;

    // Render Integrations
    function renderIntegrations(category = 'all') {
        const grid = document.getElementById('integrationsGrid');
        const filtered = category === 'all' 
            ? integrationsData 
            : integrationsData.filter(integration => integration.category === category);

        grid.innerHTML = filtered.map(integration => `
            <div class="integration-card" onclick="openIntegrationModal('${integration.id}')">
                <div class="integration-header">
                    <div class="integration-icon-wrapper ${integration.id}">
                        ${integration.icon}
                    </div>
                    <span class="integration-status ${integration.status}">
                        ${integration.status === 'connected' ? 'Connected' : 'Not Connected'}
                    </span>
                </div>
                <h3 class="integration-name">${integration.name}</h3>
                <p class="integration-description">${integration.description}</p>
                <div class="integration-footer">
                    <span class="integration-category">${integration.category}</span>
                    <button class="integration-action" onclick="event.stopPropagation(); openIntegrationModal('${integration.id}')">
                        ${integration.status === 'connected' ? 'Configure' : 'Connect'}
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Category Switching
    document.querySelectorAll('.category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            renderIntegrations(currentCategory);
        });
    });

    // Load integration status from server
    async function loadIntegrationStatus(integrationId) {
        if (integrationId === 'gmail') {
            try {
                const response = await fetch('/api/integrations/gmail');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'gmail');
                    if (integration) {
                        integration.status = data.status || (data.integration.is_active ? 'connected' : 'disconnected');
                        return data.integration;
                    }
                }
            } catch (error) {
                console.error('Error loading Gmail integration:', error);
            }
            return null;
        }
        if (integrationId === 'wise') {
            try {
                const response = await fetch('/api/integrations/wise');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'wise');
                    if (integration) {
                        integration.status = data.status || (data.integration.is_active ? 'connected' : 'disconnected');
                        return data.integration;
                    }
                }
            } catch (error) {
                console.error('Error loading Wise integration:', error);
            }
            return null;
        }
        if (integrationId === 'twilio') {
            try {
                const response = await fetch('/api/integrations/twilio');
                if (!response.ok) {
                    return null;
                }
                const data = await response.json();
                const integration = integrationsData.find(i => i.id === 'twilio');
                if (integration) {
                    integration.status = data.status ?? 'disconnected';
                }
                if (data.integration) {
                    return {
                        ...data.integration,
                        status: data.status ?? 'disconnected',
                        missing_fields: data.missing_fields || [],
                    };
                }
                return {
                    status: data.status ?? 'disconnected',
                    missing_fields: data.missing_fields || [],
                };
            } catch (error) {
                console.error('Error loading Twilio integration:', error);
            }
            return null;
        }
        if (integrationId === 'viber') {
            try {
                const response = await fetch('/api/integrations/viber');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'viber');
                    if (integration) {
                        integration.status = data.status ?? 'disconnected';
                    }
                    return {
                        ...data.integration,
                        status: data.status ?? 'disconnected',
                    };
                }
            } catch (error) {
                console.error('Error loading Viber integration:', error);
            }
            return null;
        }
        if (integrationId === 'whatsapp') {
            try {
                const response = await fetch('/api/integrations/whatsapp');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'whatsapp');
                    if (integration) {
                        integration.status = data.status ?? 'disconnected';
                    }
                    return {
                        ...data.integration,
                        status: data.status ?? 'disconnected',
                    };
                }
            } catch (error) {
                console.error('Error loading WhatsApp integration:', error);
            }
            return null;
        }
        if (integrationId === 'facebook') {
            try {
                const response = await fetch('/api/integrations/facebook');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'facebook');
                    if (integration) {
                        integration.status = data.status ?? 'disconnected';
                    }
                    return {
                        ...data.integration,
                        status: data.status ?? 'disconnected',
                    };
                }
            } catch (error) {
                console.error('Error loading Facebook integration:', error);
            }
            return null;
        }
        if (integrationId === 'openai') {
            try {
                const response = await fetch('/api/integrations/openai');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'openai');
                    if (integration) {
                        integration.status = data.status || (data.integration.is_active ? 'connected' : 'disconnected');
                    }
                    return data.integration;
                }
            } catch (error) {
                console.error('Error loading OpenAI integration:', error);
            }
            return null;
        }
        if (integrationId === 'storeganise') {
            try {
                const response = await fetch('/api/integrations/storeganise');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'storeganise');
                    if (integration) {
                        integration.status = data.status || (data.integration.is_active ? 'connected' : 'disconnected');
                    }
                    return data.integration;
                }
            } catch (error) {
                console.error('Error loading Storeganise integration:', error);
            }
            return null;
        }
        if (integrationId === 'front') {
            try {
                const response = await fetch('/api/integrations/front');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'front');
                    if (integration) {
                        integration.status = data.status || (data.integration.is_active ? 'connected' : 'disconnected');
                    }
                    return {
                        ...data.integration,
                        status: data.status ?? 'disconnected',
                    };
                }
            } catch (error) {
                console.error('Error loading Front integration:', error);
            }
            return null;
        }
        if (integrationId === 'stripe') {
            try {
                const response = await fetch('/api/integrations/stripe');
                if (!response.ok) return null;
                const data = await response.json();
                if (data.integration) {
                    const integration = integrationsData.find(i => i.id === 'stripe');
                    if (integration) {
                        integration.status = data.status || (data.integration.is_active && data.integration.secret_key ? 'connected' : 'disconnected');
                        return data.integration;
                    }
                }
            } catch (error) {
                console.error('Error loading Stripe integration:', error);
            }
        }
        if (integrationId === 'calendar') {
            try {
                const response = await fetch('{{ route("api.calendar.oauth-settings") }}');
                if (!response.ok) return null;
                const data = await response.json();
                const integration = integrationsData.find(i => i.id === 'calendar');
                if (integration) {
                    integration.status = data.google_configured ? 'connected' : 'disconnected';
                }
                return data;
            } catch (error) {
                console.error('Error loading Google Calendar OAuth settings:', error);
            }
            return null;
        }
        if (integrationId === 'outlook') {
            try {
                const response = await fetch('{{ route("api.calendar.oauth-settings") }}');
                if (!response.ok) return null;
                const data = await response.json();
                const integration = integrationsData.find(i => i.id === 'outlook');
                if (integration) {
                    integration.status = data.outlook_configured ? 'connected' : 'disconnected';
                }
                return data;
            } catch (error) {
                console.error('Error loading Outlook OAuth settings:', error);
            }
            return null;
        }
        return null;
    }

    // Open Integration Modal
    async function openIntegrationModal(integrationId) {
        const integration = integrationsData.find(i => i.id === integrationId);
        if (!integration) return;

        currentIntegration = integration;

        // Load existing integration data
        let existingIntegration = null;
        if (integrationId === 'gmail' || integrationId === 'twilio' || integrationId === 'viber' || integrationId === 'whatsapp' || integrationId === 'facebook' || integrationId === 'wise' || integrationId === 'stripe' || integrationId === 'openai' || integrationId === 'storeganise' || integrationId === 'front' || integrationId === 'calendar' || integrationId === 'outlook') {
            existingIntegration = await loadIntegrationStatus(integrationId);
        }

        // Store existingIntegration in global scope for use in handleIntegrationAction
        window.existingIntegration = existingIntegration;

        // Update modal header
        document.getElementById('modalIcon').innerHTML = `
            <div class="integration-icon-wrapper ${integration.id}" style="width: 64px; height: 64px; font-size: 2rem;">
                ${integration.icon}
            </div>
        `;
        document.getElementById('modalName').textContent = integration.name;
        document.getElementById('modalDescription').textContent = integration.description;
        
        // Update status
        if (existingIntegration?.status) {
            integration.status = existingIntegration.status;
        }
        const statusHtml = integration.status === 'connected'
            ? '<span class="integration-status connected">Connected</span>'
            : '<span class="integration-status disconnected">Not Connected</span>';
        document.getElementById('modalStatus').innerHTML = statusHtml;

        // Update details
        const detailsHtml = `
            <div class="details-section">
                <div class="details-title">Features</div>
                <div class="details-list">
                    ${integration.features.map(feature => `
                        <div class="detail-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>${feature}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        document.getElementById('integrationDetails').innerHTML = detailsHtml;

        // Update configuration
        let configHtml = '';
        const twilioFieldLabels = {
            account_sid: 'Account SID',
            auth_token: 'Auth Token',
            app_sid: 'App SID',
            api_key: 'API Key',
            api_secret: 'API Secret',
        };
        const missingTwilioFields = integration.id === 'twilio' && existingIntegration?.missing_fields?.length
            ? existingIntegration.missing_fields.map(field => twilioFieldLabels[field] || field)
            : [];

        if (integration.status === 'connected') {
            configHtml = `
                <div class="connected-info">
                    <div class="connected-info-title">✓ Successfully Connected</div>
                    <div class="connected-info-text">This integration is active and working properly.</div>
                </div>
                <div class="config-form">
                    ${getIntegrationConfig(integration.id, existingIntegration)}
                </div>
            `;
        } else if (integration.id === 'twilio' && existingIntegration?.account_sid) {
            configHtml = `
                <div class="connected-info" style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.35);">
                    <div class="connected-info-title" style="color: #b45309;">Incomplete Twilio configuration</div>
                    <div class="connected-info-text">Saved settings are missing required values${missingTwilioFields.length ? ': ' + missingTwilioFields.join(', ') : ''}. Fill in all fields and save to verify with Twilio.</div>
                </div>
                <div class="config-form">
                    ${getIntegrationConfig(integration.id, existingIntegration)}
                </div>
            `;
        } else {
            configHtml = `
                <div class="config-form">
                    ${getIntegrationConfig(integration.id, existingIntegration)}
                </div>
            `;
        }
        
        document.getElementById('integrationConfig').innerHTML = configHtml;
        
        // Update action button
        const actionBtn = document.getElementById('modalActionBtn');
        const footer = document.querySelector('.modal-footer');
        if (integration.status === 'connected' && integrationId === 'wise') {
            actionBtn.textContent = 'Save';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleWiseSave();
            const disconnectBtn = footer?.querySelector('.wise-disconnect-btn');
            if (disconnectBtn) disconnectBtn.style.display = '';
            if (footer && !footer.querySelector('.wise-disconnect-btn')) {
                const d = document.createElement('button');
                d.className = 'btn-secondary btn-danger wise-disconnect-btn';
                d.textContent = 'Disconnect';
                d.onclick = () => { if (confirm('Disconnect Wise?')) handleWiseDisconnect(); };
                footer.insertBefore(d, actionBtn);
            }
        } else if (integration.status === 'connected' && integrationId === 'stripe') {
            actionBtn.textContent = 'Save';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleStripeSave();
            const disconnectBtn = footer?.querySelector('.stripe-disconnect-btn');
            if (disconnectBtn) disconnectBtn.style.display = '';
            if (footer && !footer.querySelector('.stripe-disconnect-btn')) {
                const d = document.createElement('button');
                d.className = 'btn-secondary btn-danger stripe-disconnect-btn';
                d.textContent = 'Disconnect';
                d.onclick = () => { if (confirm('Disconnect Stripe?')) handleStripeDisconnect(); };
                footer.insertBefore(d, actionBtn);
            }
        } else if (integration.status === 'connected' && integrationId === 'openai') {
            actionBtn.textContent = 'Save';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleOpenAiSave();
            const disconnectBtn = footer?.querySelector('.openai-disconnect-btn');
            if (disconnectBtn) disconnectBtn.style.display = '';
            if (footer && !footer.querySelector('.openai-disconnect-btn')) {
                const d = document.createElement('button');
                d.className = 'btn-secondary btn-danger openai-disconnect-btn';
                d.textContent = 'Disconnect';
                d.onclick = () => { if (confirm('Disconnect OpenAI?')) handleOpenAiDisconnect(); };
                footer.insertBefore(d, actionBtn);
            }
        } else if (integration.status === 'connected' && integrationId === 'storeganise') {
            actionBtn.textContent = 'Save';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleStoreganiseSave();
            const disconnectBtn = footer?.querySelector('.storeganise-disconnect-btn');
            if (disconnectBtn) disconnectBtn.style.display = '';
            if (footer && !footer.querySelector('.storeganise-disconnect-btn')) {
                const d = document.createElement('button');
                d.className = 'btn-secondary btn-danger storeganise-disconnect-btn';
                d.textContent = 'Disconnect';
                d.onclick = () => { if (confirm('Disconnect Storeganise?')) handleStoreganiseDisconnect(); };
                footer.insertBefore(d, actionBtn);
            }
        } else if (integration.status === 'connected' && integrationId === 'front') {
            actionBtn.textContent = 'Save token';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleFrontSave(false);
            const disconnectBtn = footer?.querySelector('.front-disconnect-btn');
            if (disconnectBtn) disconnectBtn.style.display = '';
            if (footer && !footer.querySelector('.front-disconnect-btn')) {
                const d = document.createElement('button');
                d.className = 'btn-secondary btn-danger front-disconnect-btn';
                d.textContent = 'Disconnect';
                d.onclick = () => { if (confirm('Disconnect Front?')) handleFrontDisconnect(); };
                footer.insertBefore(d, actionBtn);
            }
        } else if (integration.status === 'connected' && integrationId === 'twilio') {
            actionBtn.textContent = 'Save';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleTwilioSave();
            const disconnectBtn = footer?.querySelector('.twilio-disconnect-btn');
            if (disconnectBtn) disconnectBtn.style.display = '';
            if (footer && !footer.querySelector('.twilio-disconnect-btn')) {
                const d = document.createElement('button');
                d.className = 'btn-secondary btn-danger twilio-disconnect-btn';
                d.textContent = 'Disconnect';
                d.onclick = () => { if (confirm('Disconnect Twilio?')) handleTwilioDisconnect(); };
                footer.insertBefore(d, actionBtn);
            }
        } else if (integration.status === 'connected' && integrationId === 'facebook') {
            actionBtn.textContent = 'Save';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => saveFacebookIntegration();
            const disconnectBtn = footer?.querySelector('.facebook-disconnect-btn');
            if (disconnectBtn) disconnectBtn.style.display = '';
            if (footer && !footer.querySelector('.facebook-disconnect-btn')) {
                const d = document.createElement('button');
                d.className = 'btn-secondary btn-danger facebook-disconnect-btn';
                d.textContent = 'Disconnect';
                d.onclick = () => { if (confirm('Disconnect Facebook?')) handleIntegrationAction(); };
                footer.insertBefore(d, actionBtn);
            }
        } else if (integrationId === 'calendar') {
            actionBtn.textContent = 'Save settings';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleCalendarOauthSave('google');
            footer?.querySelectorAll('.wise-disconnect-btn, .gmail-disconnect-btn, .stripe-disconnect-btn, .openai-disconnect-btn, .storeganise-disconnect-btn, .front-disconnect-btn, .twilio-disconnect-btn, .facebook-disconnect-btn').forEach(d => { if (d) d.style.display = 'none'; });
        } else if (integrationId === 'outlook') {
            actionBtn.textContent = 'Save settings';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleCalendarOauthSave('outlook');
            footer?.querySelectorAll('.wise-disconnect-btn, .gmail-disconnect-btn, .stripe-disconnect-btn, .openai-disconnect-btn, .storeganise-disconnect-btn, .front-disconnect-btn, .twilio-disconnect-btn, .facebook-disconnect-btn').forEach(d => { if (d) d.style.display = 'none'; });
        } else {
            footer?.querySelectorAll('.wise-disconnect-btn, .gmail-disconnect-btn, .stripe-disconnect-btn, .openai-disconnect-btn, .storeganise-disconnect-btn, .front-disconnect-btn, .twilio-disconnect-btn, .facebook-disconnect-btn').forEach(d => { if (d) d.style.display = 'none'; });
        }
        if (integrationId === 'calendar' || integrationId === 'outlook') {
            // Handled above - Save settings button
        } else if (integration.status === 'connected' && integrationId !== 'wise' && integrationId !== 'gmail' && integrationId !== 'stripe' && integrationId !== 'openai' && integrationId !== 'storeganise' && integrationId !== 'front' && integrationId !== 'twilio' && integrationId !== 'facebook') {
            actionBtn.textContent = 'Disconnect';
            actionBtn.className = 'btn-primary btn-danger';
            actionBtn.onclick = () => handleIntegrationAction();
        } else if (integration.status !== 'connected' || (integrationId !== 'wise' && integrationId !== 'stripe' && integrationId !== 'openai' && integrationId !== 'storeganise' && integrationId !== 'front' && integrationId !== 'twilio' && integrationId !== 'facebook')) {
            actionBtn.textContent = 'Connect';
            actionBtn.className = 'btn-primary';
            actionBtn.onclick = () => handleIntegrationAction();
        }

        if (integrationId === 'wise') {
            // Disable Connect button until a profile is selected; bindWiseFetchProfiles re-enables when ready
            const connectBtn = document.getElementById('modalActionBtn');
            if (connectBtn) {
                connectBtn.disabled = true;
                connectBtn.style.opacity = '0.5';
                connectBtn.style.cursor = 'not-allowed';
            }
            bindWiseFetchProfiles();
        }

        // Show modal
        document.getElementById('integrationModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        if (integrationId === 'front' && integration.status === 'connected') {
            loadFrontImportPanel(existingIntegration);
        }
    }

    function getIntegrationConfig(integrationId, existingData = null) {
        const configs = {
            'paypal': `
                <div class="form-group">
                    <label class="form-label">PayPal Client ID</label>
                    <input type="text" class="form-input" placeholder="Enter PayPal Client ID" value="AK-1234567890">
                    <span class="form-help">Find this in your PayPal Developer Dashboard</span>
                </div>
                <div class="form-group">
                    <label class="form-label">PayPal Secret</label>
                    <input type="password" class="form-input" placeholder="Enter PayPal Secret" value="••••••••">
                </div>
            `,
            'stripe': `
                <div class="form-group">
                    <label class="form-label">Stripe Publishable Key</label>
                    <input type="text" class="form-input" id="stripe-publishable-key" placeholder="pk_live_... or pk_test_..." value="${(existingData && existingData.publishable_key) ? existingData.publishable_key : ''}">
                    <span class="form-help">Find this in your Stripe Dashboard under Developers → API keys</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Stripe Secret Key</label>
                    <input type="password" class="form-input" id="stripe-secret-key" placeholder="sk_live_... or sk_test_..." value="">
                    <span class="form-help">Required for payment links.${existingData && existingData.secret_key ? ' Leave blank to keep current value.' : ''}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Webhook Signing Secret</label>
                    <input type="password" class="form-input" id="stripe-webhook-secret" placeholder="whsec_..." value="">
                    <span class="form-help">Required for automatic invoice status updates when customers pay via Stripe Checkout.${existingData && existingData.webhook_secret ? ' Leave blank to keep current value.' : ' See setup instructions below.'}</span>
                </div>
                <div class="webhook-setup-section" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-top: 1rem;">
                    <div class="details-title" style="margin-bottom: 0.75rem;">How to set up Stripe Webhooks</div>
                    <ol style="margin: 0; padding-left: 1.25rem; font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.7;">
                        <li>Go to <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Stripe Dashboard → Developers → Webhooks</a></li>
                        <li>Click <strong>Add endpoint</strong></li>
                        <li>Enter your webhook URL: <code style="background: var(--bg-primary); padding: 0.2em 0.4em; border-radius: 4px; font-size: 0.8em; word-break: break-all;">{{ url("/webhooks/stripe/company/" . (auth()->user()?->company_id ?? "YOUR_COMPANY_ID")) }}</code></li>
                        <li>Select events: <strong>checkout.session.completed</strong> (invoices), <strong>customer.subscription.updated</strong>, <strong>customer.subscription.deleted</strong> (subscriptions)</li>
                        <li>Click <strong>Add endpoint</strong>, then reveal and copy the <strong>Signing secret</strong> (starts with whsec_)</li>
                        <li>Paste the signing secret into the Webhook Signing Secret field above and click Save</li>
                    </ol>
                    <p style="margin: 0.75rem 0 0; font-size: 0.75rem; color: var(--text-muted);">Your webhook URL: <strong>{{ url("/webhooks/stripe/company/" . (auth()->user()?->company_id ?? "")) }}</strong></p>
                </div>
            `,
            'gmail': `
                <div class="form-group">
                    <label class="form-label">Gmail Address</label>
                    <input type="email" class="form-input" id="gmail-email" placeholder="your-email@gmail.com" value="${(existingData && existingData.email) ? existingData.email : ''}">
                    <span class="form-help">The Gmail address used to send emails (quotations, invoices, etc.)</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Gmail App Password</label>
                    <input type="password" class="form-input" id="gmail-app-password" placeholder="Enter 16-character App Password" value="">
                    <span class="form-help">Generate an App Password in Google Account: Security → 2-Step Verification → App passwords. Leave blank to keep current when updating.</span>
                </div>
            `,
            'wise': `
                <div class="form-group">
                    <label class="form-label">API Token</label>
                    <input type="password" class="form-input" id="wise-api-token" placeholder="${existingData && existingData.api_token ? 'Leave blank to keep current token' : 'Enter Wise API Token'}" value="">
                    <span class="form-help">Generate an API token from your Wise Business account settings${existingData && existingData.api_token ? ' — paste a new token to reload profiles' : ''}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Profile ID</label>
                    <select class="form-input" id="wise-profile-id" disabled style="cursor: not-allowed;">
                        <option value="">${existingData && existingData.api_token ? 'Loading profiles…' : '— Paste your API token above to load profiles —'}</option>
                    </select>
                    <span class="form-help" id="wise-profile-help">${existingData && existingData.api_token ? 'Fetching your Wise profiles…' : 'Profiles will load automatically once you enter your API token.'}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="wise-sandbox" ${existingData && existingData.is_sandbox ? 'checked' : ''}> Use Sandbox (testing)
                    </label>
                    <span class="form-help">Enable for testing with Wise Sandbox</span>
                </div>
                @if(auth()->user()?->hasPermission('view_wise_recipients'))
                <div class="form-group">
                    <a href="{{ route('wise-recipients') }}" class="btn-secondary" style="display:inline-flex;text-decoration:none;">Manage recipients &amp; employees</a>
                    <span class="form-help">Assign Wise recipient IDs to employees on the dedicated page.</span>
                </div>
                @endif
            `,
            'google-login': `
                <div class="form-group">
                    <label class="form-label">Google Client ID</label>
                    <input type="text" class="form-input" placeholder="Enter Google Client ID" value="1234567890.apps.googleusercontent.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Google Client Secret</label>
                    <input type="password" class="form-input" placeholder="Enter Client Secret" value="••••••••">
                </div>
            `,
            'openai': `
                <div class="form-group">
                    <label class="form-label">OpenAI API Key</label>
                    <input type="password" class="form-input" id="openai-api-key" placeholder="${existingData && existingData.api_key ? 'Leave blank to keep current key' : 'sk-...'}">
                    <span class="form-help">Get your API key from platform.openai.com. Used by the AI Assistant.</span>
                </div>
            `,
            'storeganise': `
                <div class="form-group">
                    <label class="form-label">Business code</label>
                    <input type="text" class="form-input" id="storeganise-business-code" placeholder="yourbusiness" value="${(existingData && existingData.business_code) ? existingData.business_code : ''}">
                    <span class="form-help">The subdomain from your Storeganise admin URL, e.g. <code>locnstor</code> for https://locnstor.storeganise.com — not your email address. You can also paste the full URL.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin API key</label>
                    <input type="password" class="form-input" id="storeganise-api-key" placeholder="${existingData && existingData.api_key ? 'Leave blank to keep current key' : 'Enter API key'}">
                    <span class="form-help">Create an API key in Storeganise → Admin → Settings → Developer. Use <code>Authorization: ApiKey &lt;key&gt;</code>.</span>
                </div>
                ${existingData && existingData.webhook_url ? `
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${existingData.webhook_url}</code>
                    <span class="form-help">Add this URL in Storeganise Developer settings to receive move-in, move-out, and rental events.</span>
                </div>
                ` : ''}
            `,
            'front': `
                <div class="form-group">
                    <label class="form-label">Front API token</label>
                    <input type="password" class="form-input" id="front-api-token" placeholder="${existingData && existingData.api_token ? 'Leave blank to keep current token' : 'Paste bearer token'}">
                    <span class="form-help">Create a token in Front → Settings → Developers with scopes <code>tags:read</code>, <code>conversations:read</code>, and optionally <code>inboxes:read</code>. Paste the token only — do not include <code>Bearer</code>.</span>
                    <div id="front-token-error" class="form-help" style="color:#b91c1c;display:none;margin-top:0.5rem;"></div>
                </div>
                <div id="front-import-panel" class="front-import-panel" ${existingData && existingData.api_token ? '' : 'hidden'}>
                    <h4 style="font-size:0.9375rem;font-weight:600;margin:0 0 0.5rem;">Import inbox tags</h4>
                    <p class="form-help" style="margin-bottom:0.75rem;">Sync mail into LNSCRM first (<strong>Inbox → Sync</strong> or <code>php artisan inbox:sync-mail --full</code>), then run the import below.</p>
                    <div id="front-mapping-wrap">
                        <span class="form-help">Loading inbox mapping…</span>
                    </div>
                    <label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.75rem;font-size:0.8125rem;">
                        <input type="checkbox" id="front-include-private">
                        Include private Front tags
                    </label>
                    <div class="front-import-actions">
                        <button type="button" class="btn-secondary" id="front-dry-run-btn" onclick="handleFrontImport(true)">Preview (dry run)</button>
                        <button type="button" class="btn-primary" id="front-import-btn" onclick="handleFrontImport(false)">Run import</button>
                    </div>
                    <div id="front-import-loading" class="front-import-loading" hidden>
                        <div class="front-import-spinner" aria-hidden="true"></div>
                        <div style="flex:1;">
                            <div id="front-import-loading-label">Processing…</div>
                            <div class="front-import-loading-bar" aria-hidden="true"><span></span></div>
                        </div>
                    </div>
                    <div id="front-import-results"></div>
                </div>
            `,
            'calendar': `
                <p class="form-help" style="margin-bottom: 1rem;">Configure Google Calendar OAuth so users can connect their calendars. Add credentials and copy the redirect URL when creating the OAuth app.</p>
                <div class="oauth-section" style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.75rem;">Google Calendar</h4>
                    <details class="oauth-steps" style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                        <summary style="cursor: pointer; font-size: 0.8125rem; font-weight: 500; color: var(--accent);">How to configure Google Calendar OAuth</summary>
                        <ol style="margin: 0.75rem 0 0; padding-left: 1.25rem; font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.6;">
                            <li>Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a></li>
                            <li>Create or select a project → APIs &amp; Services → Credentials</li>
                            <li>Create credentials → OAuth 2.0 Client ID (Application type: Web application)</li>
                            <li>Add Authorized redirect URI: <code id="calendarGoogleRedirectUrl" style="background: var(--bg-card); padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.75rem; word-break: break-all;">${(existingData && existingData.redirect_url_google) ? existingData.redirect_url_google : '{{ url("/calendar/connect/google/callback") }}'}</code></li>
                            <li>Enable Google Calendar API: APIs &amp; Services → Library → search "Google Calendar API" → Enable</li>
                            <li>Copy the Client ID and Client Secret below</li>
                        </ol>
                    </details>
                    <div class="form-group">
                        <label class="form-label">Client ID</label>
                        <input type="text" class="form-input" id="oauth-google-client-id" placeholder="${(existingData && existingData.google_configured) ? '(configured)' : 'xxxxx.apps.googleusercontent.com'}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Client Secret</label>
                        <input type="password" class="form-input" id="oauth-google-client-secret" placeholder="${(existingData && existingData.google_configured) ? '(leave blank to keep)' : 'GOCSPX-xxxxx'}">
                    </div>
                </div>
                <p class="form-help">Leave fields blank to keep existing values. Credentials are stored per company.</p>
            `,
            'outlook': `
                <p class="form-help" style="margin-bottom: 1rem;">Configure Microsoft Outlook OAuth for calendar sync and Inbox mail. Users connect calendars from Calendar and mailboxes from Inbox.</p>
                <div class="oauth-section" style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.75rem;">Microsoft Outlook</h4>
                    <details class="oauth-steps" style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                        <summary style="cursor: pointer; font-size: 0.8125rem; font-weight: 500; color: var(--accent);">How to configure Outlook (Calendar + Inbox)</summary>
                        <ol style="margin: 0.75rem 0 0; padding-left: 1.25rem; font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.6;">
                            <li>Go to <a href="https://portal.azure.com/" target="_blank" rel="noopener">Azure Portal</a> → Microsoft Entra ID</li>
                            <li>App registrations → New registration. For single-tenant apps, also copy the <strong>Directory (tenant) ID</strong>.</li>
                            <li>Add Redirect URIs (Platform Web):
                                <div style="margin-top:0.35rem;">Calendar: <code id="calendarOutlookRedirectUrl" style="background: var(--bg-card); padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.75rem; word-break: break-all;">${(existingData && existingData.redirect_url_outlook) ? existingData.redirect_url_outlook : '{{ url("/calendar/connect/outlook/callback") }}'}</code></div>
                                <div style="margin-top:0.25rem;">Inbox: <code id="inboxOutlookRedirectUrl" style="background: var(--bg-card); padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.75rem; word-break: break-all;">${(existingData && existingData.redirect_url_outlook_mail) ? existingData.redirect_url_outlook_mail : '{{ url("/inbox/connect/outlook/callback") }}'}</code></div>
                            </li>
                            <li>Certificates &amp; secrets → New client secret → copy the value</li>
                            <li>API permissions → Add: Calendars.Read, User.Read, Mail.ReadWrite, Mail.Send, Mail.ReadWrite.Shared, offline_access</li>
                            <li>Copy Application (client) ID and client secret below. If the app is <strong>single-tenant</strong>, paste the Directory (tenant) ID too (required — using /common will fail with AADSTS50194).</li>
                            <li>Users connect calendars from <strong>Calendar</strong> and personal/shared mail from <strong>Inbox</strong></li>
                        </ol>
                    </details>
                    <div class="form-group">
                        <label class="form-label">Client ID (Application ID)</label>
                        <input type="text" class="form-input" id="oauth-microsoft-client-id" placeholder="${(existingData && existingData.outlook_configured) ? '(configured)' : 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx'}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Client Secret</label>
                        <input type="password" class="form-input" id="oauth-microsoft-client-secret" placeholder="${(existingData && existingData.outlook_configured) ? '(leave blank to keep)' : 'xxxx~xxxxxxxxxxxxxxxxxxxx'}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tenant ID (required for single-tenant apps)</label>
                        <input type="text" class="form-input" id="oauth-microsoft-tenant-id" value="${(existingData && existingData.microsoft_tenant_id) ? existingData.microsoft_tenant_id : ''}" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx or leave blank for multi-tenant (/common)">
                        <span class="form-help">Azure Portal → Microsoft Entra ID → Overview → Directory (tenant) ID</span>
                    </div>
                </div>
                <p class="form-help">Leave fields blank to keep existing values. Credentials are stored per company.</p>
            `,
            'twilio': `
                <div class="form-group">
                    <label class="form-label">Account SID</label>
                    <input type="text" class="form-input" id="twilio-account-sid" placeholder="AC..." value="${existingData ? existingData.account_sid || '' : ''}">
                    <span class="form-help">Twilio Console → Account → API keys & tokens (starts with AC).</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Auth Token</label>
                    <input type="password" class="form-input" id="twilio-auth-token" placeholder="Enter Auth Token" value="">
                    <span class="form-help">Live Auth Token${existingData && existingData.auth_token ? ' (leave blank to keep current)' : ''}.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">App SID (optional — CRM browser calling)</label>
                    <input type="text" class="form-input" id="twilio-app-sid" placeholder="AP..." value="${existingData ? existingData.app_sid || '' : ''}">
                    <span class="form-help">TwiML App SID from Twilio Console → Voice → TwiML Apps.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">API Key (optional — CRM browser calling)</label>
                    <input type="text" class="form-input" id="twilio-api-key" placeholder="SK..." value="${existingData ? existingData.api_key || '' : ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">API Secret (optional — CRM browser calling)</label>
                    <input type="password" class="form-input" id="twilio-api-secret" placeholder="Enter API Secret" value="">
                    <span class="form-help">${existingData && existingData.api_secret ? 'Leave blank to keep current.' : 'From Twilio Console → Account → API keys & tokens.'}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Voice webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${TWILIO_SETUP.voiceWebhook}</code>
                    <span class="form-help">Set this as the Voice URL (HTTP POST) on your Twilio numbers / TwiML App.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">SMS webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${TWILIO_SETUP.smsWebhook}</code>
                    <span class="form-help">Set this as the Messaging URL (HTTP POST) on your Twilio numbers. Numbers bought in-app are configured automatically.</span>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">Powers the phone system &amp; messaging</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Paste live <strong>Account SID</strong> + <strong>Auth Token</strong> (required for WhatsApp, Viber, Facebook Messenger, SMS, phone).</li>
                        <li>For in-CRM browser calling, also add <strong>App SID</strong>, <strong>API Key</strong>, and <strong>API Secret</strong>.</li>
                        <li>Then configure WhatsApp / Viber / Facebook senders under their own cards.</li>
                    </ol>
                </div>
            `,
            'viber': `
                <div class="form-group">
                    <label class="form-label">Viber Sender ID</label>
                    <input type="text" class="form-input" id="viber-sender-id" value="${existingData && existingData.sender_id ? existingData.sender_id : ''}" placeholder="From Twilio Console → Messaging → Senders → Viber">
                    <span class="form-help">Your Twilio Viber Business Sender ID (not a Meta/Viber bot token).</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Display name (optional)</label>
                    <input type="text" class="form-input" id="viber-bot-name" value="${existingData && existingData.bot_name ? existingData.bot_name : ''}" placeholder="Support">
                </div>
                <div class="form-group">
                    <label class="form-label">Welcome Message (optional)</label>
                    <textarea class="form-input" id="viber-welcome-message" rows="3" placeholder="Hi! Thanks for messaging us. How can we help?">${existingData && existingData.welcome_message ? existingData.welcome_message : ''}</textarea>
                    <span class="form-help">Sent once when a customer starts a new conversation.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${existingData && existingData.webhook_url ? existingData.webhook_url : 'Saved after you connect — must be public HTTPS'}</code>
                    <span class="form-help">Paste this as the inbound webhook URL on your Twilio Viber sender / Messaging Service.</span>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">How it works</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Connect <strong>Twilio</strong> first under Integrations (Account SID / Auth Token).</li>
                        <li>Enable Viber Business Messaging in the Twilio Console and create a Viber sender.</li>
                        <li>Paste the Sender ID here, then set the Webhook URL above on that sender.</li>
                        <li>Customer messages appear in <a href="${TWILIO_SETUP.viberChatUrl}">Viber</a>.</li>
                    </ol>
                </div>
            `,
            'whatsapp': `
                <div class="form-group">
                    <label class="form-label">WhatsApp From Number</label>
                    <input type="text" class="form-input" id="whatsapp-from-number" value="${existingData && existingData.from_number ? existingData.from_number : ''}" placeholder="+15551234567">
                    <span class="form-help">E.164 WhatsApp-enabled number from Twilio (Sandbox or approved sender).</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Business name (optional)</label>
                    <input type="text" class="form-input" id="whatsapp-business-name" value="${existingData && existingData.business_name ? existingData.business_name : ''}" placeholder="Acme Support">
                </div>
                <div class="form-group">
                    <label class="form-label">Welcome Message (optional)</label>
                    <textarea class="form-input" id="whatsapp-welcome-message" rows="3" placeholder="Hi! Thanks for messaging us. How can we help?">${existingData && existingData.welcome_message ? existingData.welcome_message : ''}</textarea>
                </div>
                ${existingData && (existingData.business_name || existingData.display_phone_number || existingData.from_number) ? `
                <div class="form-group">
                    <label class="form-label">Connected number</label>
                    <div style="font-size:0.9rem;color:var(--text-primary);">${existingData.business_name || ''}${(existingData.display_phone_number || existingData.from_number) ? ' · ' + (existingData.display_phone_number || existingData.from_number) : ''}</div>
                </div>` : ''}
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${existingData && existingData.webhook_url ? existingData.webhook_url : 'Saved after you connect — must be public HTTPS'}</code>
                    <span class="form-help">Paste this as the inbound webhook URL on your Twilio WhatsApp sender / Messaging Service. Status callbacks use the shared Twilio SMS status URL.</span>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">How it works</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Connect <strong>Twilio</strong> first under Integrations.</li>
                        <li>Enable WhatsApp in the Twilio Console (Sandbox or production sender).</li>
                        <li>Paste the WhatsApp from number here and point the sender webhook to the URL above.</li>
                        <li>Customer messages appear in <a href="${TWILIO_SETUP.whatsappChatUrl}">WhatsApp</a>. Free-form replies work within the 24-hour window.</li>
                    </ol>
                </div>
            `,
            'facebook': `
                <div class="form-group">
                    <label class="form-label">Facebook Page ID</label>
                    <input type="text" class="form-input" id="facebook-page-id" value="${existingData && existingData.page_id ? existingData.page_id : ''}" placeholder="222764457920914">
                    <span class="form-help">From Twilio Console → Facebook Messenger, or from Graph <code>/me/accounts</code>.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Page name (optional)</label>
                    <input type="text" class="form-input" id="facebook-page-name" value="${existingData && existingData.page_name ? existingData.page_name : ''}" placeholder="Loc&amp;Stor 24/7 Self Storage Philippines">
                </div>
                <div class="form-group">
                    <label class="form-label">Page Access Token (required for Instagram)</label>
                    <input type="password" class="form-input" id="facebook-page-access-token" value="" placeholder="${existingData && existingData.has_page_access_token ? '•••••••• (leave blank to keep)' : 'EAAB… long-lived Page token'}">
                    <span class="form-help">Must be a <strong>Page</strong> token (not User) that does not expire. In <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> get a User token with <code>pages_messaging</code>, <code>pages_manage_metadata</code>, and <code>pages_read_engagement</code>, then switch the token dropdown to your Page. Explorer tokens expire in 1–2 hours.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Meta App Secret (recommended)</label>
                    <input type="password" class="form-input" id="facebook-app-secret" value="" placeholder="${existingData && existingData.has_app_secret ? '•••••••• (leave blank to keep)' : 'From Meta App → Settings → Basic'}">
                    <span class="form-help">Verifies Instagram webhook signatures from Meta.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Instagram account ID</label>
                    <input type="text" class="form-input" id="facebook-instagram-id" value="${existingData && existingData.instagram_business_account_id ? existingData.instagram_business_account_id : ''}" placeholder="17841400107695807">
                    <span class="form-help">Instagram Business Account ID (not @username). Saved automatically from the Page token when possible.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Instagram username (optional)</label>
                    <input type="text" class="form-input" id="facebook-instagram-username" value="${existingData && existingData.instagram_username ? existingData.instagram_username : ''}" placeholder="locnstor247">
                </div>
                <div class="form-group">
                    <label class="form-label">Welcome Message (optional)</label>
                    <textarea class="form-input" id="facebook-welcome-message" rows="3" placeholder="Hi! Thanks for messaging us. How can we help?">${existingData && existingData.welcome_message ? existingData.welcome_message : ''}</textarea>
                    <span class="form-help">Sent once when a customer starts a new conversation.</span>
                </div>
                ${existingData && (existingData.page_name || existingData.page_id) ? `
                <div class="form-group">
                    <label class="form-label">Connected sender</label>
                    <div style="font-size:0.9rem;color:var(--text-primary);">${existingData.page_name || 'Facebook Page'} · ${existingData.page_id || ''}${existingData.instagram_username ? ' · @' + existingData.instagram_username : ''}</div>
                </div>` : ''}
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${existingData && existingData.webhook_url ? existingData.webhook_url : 'Saved after you connect — must be public HTTPS'}</code>
                    <span class="form-help">Paste this in Meta App → Messenger settings for both Facebook and Instagram (Callback URL). Also keep it on the Twilio Messenger sender for Facebook chat.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Verify token</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${existingData && existingData.webhook_verify_token ? existingData.webhook_verify_token : 'Generated after you save'}</code>
                    <span class="form-help">Paste this as Verify Token in the same Meta webhook settings.</span>
                </div>
                ${existingData && existingData.page_id ? `
                <div class="form-group">
                    <label class="form-label">Sync old Messenger messages</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <select class="form-input" id="facebook-sync-days" style="max-width:11rem;">
                            <option value="30">Last 30 days</option>
                            <option value="90" selected>Last 90 days</option>
                            <option value="365">Last 12 months</option>
                            <option value="0">All available in Twilio</option>
                        </select>
                        <button type="button" class="btn-secondary" id="facebook-sync-btn" onclick="syncFacebookHistory(event)">Sync Messenger inbox</button>
                    </div>
                    <span class="form-help" id="facebook-sync-help">Imports the Facebook Page inbox, including replies sent from Messenger. Instagram Direct arrives live through Meta webhooks.</span>
                </div>` : ''}
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">Replies sent from Messenger</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Save a <strong>Page Access Token that does not expire</strong>. Graph API Explorer tokens die in 1–2 hours. Use a long-lived User token, then switch the dropdown to the Page.</li>
                        <li>In Meta for Developers open your app → <strong>Webhooks</strong> → <strong>Page</strong> (not Graph API Explorer). Add the CRM Callback URL and Verify Token, then subscribe to <code>messages</code>. Optionally also subscribe to <code>message_echoes</code> there — that is a webhook field, not a Graph <code>?fields=</code> value. Sync still imports Page Inbox replies through Graph conversations.</li>
                        <li>On <a href="${TWILIO_SETUP.facebookChatUrl}">Facebook &amp; Instagram</a>, click the download Sync button to import Page Inbox history, including messages you sent from Messenger to customers.</li>
                    </ol>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">Instagram Direct</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Save this form with a <strong>Page Access Token</strong> (and Instagram account ID).</li>
                        <li>In <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">Meta for Developers</a> open your app → <strong>Messenger → Instagram settings</strong>.</li>
                        <li>Set Callback URL to the Webhook URL above and Verify Token to the token above. Subscribe to <code>messages</code>.</li>
                        <li>In Instagram: Settings → Messages and story replies → Message controls → allow <strong>Connected tools</strong>.</li>
                        <li>The webhook URL must be public HTTPS. Then DMs to @${existingData && existingData.instagram_username ? existingData.instagram_username : 'yourpage'} appear in <a href="${TWILIO_SETUP.facebookChatUrl}">Facebook &amp; Instagram</a>.</li>
                    </ol>
                </div>
            `
        };

        return configs[integrationId] || '<p>No configuration required.</p>';
    }

    function setConnectBtnState() {
        const connectBtn = document.getElementById('modalActionBtn');
        const profileSelect = document.getElementById('wise-profile-id');
        if (!connectBtn || !profileSelect) return;
        const hasProfile = profileSelect.value && profileSelect.value !== '';
        connectBtn.disabled = !hasProfile;
        connectBtn.style.opacity = hasProfile ? '' : '0.5';
        connectBtn.style.cursor = hasProfile ? '' : 'not-allowed';
    }

    async function loadWiseProfilesIntoSelect(apiToken, isSandbox, preselectId) {
        const profileSelect = document.getElementById('wise-profile-id');
        const profileHelp = document.getElementById('wise-profile-help');
        if (!profileSelect) return;

        profileSelect.disabled = true;
        profileSelect.style.cursor = 'not-allowed';
        profileSelect.innerHTML = '<option value="">Loading profiles…</option>';
        if (profileHelp) profileHelp.textContent = 'Fetching profiles from Wise…';
        setConnectBtnState();

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const body = { is_sandbox: isSandbox ? true : false };
            if (apiToken) body.api_token = apiToken;

            const r = await fetch('/api/integrations/wise/profiles', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(body)
            });

            let data = {};
            try {
                data = await r.json();
            } catch (jsonErr) {
                console.error('Wise profiles: server returned non-JSON (status ' + r.status + '):', jsonErr);
                if (profileHelp) profileHelp.textContent = 'Server error (HTTP ' + r.status + '). Check console for details.';
                profileSelect.innerHTML = '<option value="">— Server error —</option>';
                setConnectBtnState();
                return;
            }

            if (r.ok && data.profiles && data.profiles.length) {
                if (typeof data.resolved_is_sandbox === 'boolean') {
                    const sandboxCheckbox = document.getElementById('wise-sandbox');
                    if (sandboxCheckbox) {
                        sandboxCheckbox.checked = data.resolved_is_sandbox;
                    }
                }

                profileSelect.innerHTML = '<option value="">— Select a profile —</option>' +
                    data.profiles.map(p =>
                        `<option value="${p.id}">${p.name} (${p.type}) — ID: ${p.id}</option>`
                    ).join('');

                const target = preselectId || (data.profiles.length === 1 ? String(data.profiles[0].id) : '');
                if (target) {
                    const opt = profileSelect.querySelector(`option[value="${target}"]`);
                    if (opt) {
                        profileSelect.value = target;
                    } else {
                        const extra = document.createElement('option');
                        extra.value = target;
                        extra.textContent = `Profile ID: ${target} (current)`;
                        profileSelect.insertBefore(extra, profileSelect.options[1]);
                        profileSelect.value = target;
                    }
                }

                profileSelect.disabled = false;
                profileSelect.style.cursor = '';
                if (profileHelp) {
                    const baseMessage = data.profiles.length === 1
                        ? 'Profile loaded and selected.'
                        : `${data.profiles.length} profiles found. Select one to continue.`;
                    profileHelp.textContent = data.warning ? `${data.warning} ${baseMessage}` : baseMessage;
                }
            } else {
                profileSelect.innerHTML = '<option value="">— Could not load profiles —</option>';
                if (profileHelp) profileHelp.textContent = data.error || 'Could not load profiles. Check your API token.';
            }
        } catch (e) {
            console.error(e);
            profileSelect.innerHTML = '<option value="">— Error loading profiles —</option>';
            if (profileHelp) profileHelp.textContent = 'Error fetching profiles. Please try again.';
        }

        setConnectBtnState();
    }

    async function bindWiseFetchProfiles() {
        const tokenInput = document.getElementById('wise-api-token');
        const profileSelect = document.getElementById('wise-profile-id');
        if (!tokenInput || !profileSelect) return;

        profileSelect.addEventListener('change', setConnectBtnState);

        const existing = window.existingIntegration;
        if (existing && existing.api_token) {
            await loadWiseProfilesIntoSelect(null, existing.is_sandbox, existing.profile_id ? String(existing.profile_id) : null);
        } else {
            setConnectBtnState();
        }

        function triggerProfileFetch() {
            const token = tokenInput.value;
            const isSandbox = document.getElementById('wise-sandbox')?.checked || false;
            const preselectId = existing?.profile_id ? String(existing.profile_id) : null;

            if (!token && existing && existing.api_token) {
                loadWiseProfilesIntoSelect(null, isSandbox, preselectId);
                return;
            }
            if (!token || token.length < 20) return;
            loadWiseProfilesIntoSelect(token, isSandbox, preselectId);
        }

        let debounceTimer = null;
        tokenInput.addEventListener('input', function () {
            const token = this.value;
            clearTimeout(debounceTimer);

            if (!token) {
                if (existing && existing.api_token) {
                    loadWiseProfilesIntoSelect(null, document.getElementById('wise-sandbox')?.checked, existing.profile_id ? String(existing.profile_id) : null);
                } else {
                    profileSelect.innerHTML = '<option value="">— Paste your API token above to load profiles —</option>';
                    profileSelect.disabled = true;
                    profileSelect.style.cursor = 'not-allowed';
                    const profileHelp = document.getElementById('wise-profile-help');
                    if (profileHelp) profileHelp.textContent = 'Profiles will load automatically once you enter your API token.';
                    setConnectBtnState();
                }
                return;
            }

            if (token.length < 20) {
                profileSelect.innerHTML = '<option value="">— Keep typing… —</option>';
                profileSelect.disabled = true;
                profileSelect.style.cursor = 'not-allowed';
                setConnectBtnState();
                return;
            }

            profileSelect.innerHTML = '<option value="">Loading profiles…</option>';
            profileSelect.disabled = true;
            profileSelect.style.cursor = 'not-allowed';
            setConnectBtnState();

            debounceTimer = setTimeout(() => {
                triggerProfileFetch();
            }, 800);
        });

        // Re-fetch when sandbox is toggled (sandbox token needs sandbox API URL)
        const sandboxCheckbox = document.getElementById('wise-sandbox');
        if (sandboxCheckbox) {
            sandboxCheckbox.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                const token = tokenInput.value;
                if (token.length >= 20 || (existing && existing.api_token)) {
                    triggerProfileFetch();
                }
            });
        }
    }

    async function handleGmailDisconnect() {
        try {
            const r = await fetch('/api/integrations/gmail', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' }
            });
            if (r.ok) {
                currentIntegration.status = 'disconnected';
                alert('Gmail has been disconnected.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert('Error disconnecting. Please try again.');
            }
        } catch (e) {
            console.error(e);
            alert('Error disconnecting Gmail. Please try again.');
        }
    }

    async function handleTwilioSave() {
        const accountSid = document.getElementById('twilio-account-sid')?.value?.trim() || '';
        const authToken = document.getElementById('twilio-auth-token')?.value || '';
        const appSid = document.getElementById('twilio-app-sid')?.value?.trim() || '';
        const apiKey = document.getElementById('twilio-api-key')?.value?.trim() || '';
        const apiSecret = document.getElementById('twilio-api-secret')?.value || '';
        const existing = window.existingIntegration;

        if (!accountSid) {
            alert('Please enter your Account SID.');
            return;
        }
        if (!accountSid.startsWith('AC')) {
            alert('Account SID must start with AC.');
            return;
        }
        if (!authToken && !(existing && existing.auth_token)) {
            alert('Please provide your Auth Token.');
            return;
        }
        if (appSid && !appSid.startsWith('AP')) {
            alert('App SID must start with AP.');
            return;
        }
        if (apiKey && !apiKey.startsWith('SK')) {
            alert('API Key must start with SK.');
            return;
        }
        if ((apiKey && !apiSecret && !(existing && existing.api_secret)) || (!apiKey && apiSecret)) {
            alert('API Key and API Secret must both be provided together (or left blank).');
            return;
        }

        try {
            const response = await fetch('/api/integrations/twilio', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    account_sid: accountSid,
                    auth_token: authToken,
                    app_sid: appSid || null,
                    api_key: apiKey || null,
                    api_secret: apiSecret || null,
                })
            });
            const data = await response.json();
            if (response.ok) {
                currentIntegration.status = 'connected';
                alert('Twilio connected successfully.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                const fieldErrors = data.errors ? Object.values(data.errors).flat().join('\n') : '';
                alert((data.error ? data.error + (fieldErrors ? '\n\n' + fieldErrors : '') : fieldErrors) || 'Error saving Twilio integration.');
            }
        } catch (e) {
            console.error(e);
            alert('Error saving Twilio integration. Please try again.');
        }
    }

    async function handleTwilioDisconnect() {
        try {
            const response = await fetch('/api/integrations/twilio', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            if (response.ok) {
                if (currentIntegration) currentIntegration.status = 'disconnected';
                alert('Twilio has been disconnected.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert('Error disconnecting Twilio. Please try again.');
            }
        } catch (e) {
            console.error(e);
            alert('Error disconnecting Twilio. Please try again.');
        }
    }

    async function handleGmailSave() {
        const email = document.getElementById('gmail-email')?.value?.trim() || '';
        const appPassword = document.getElementById('gmail-app-password')?.value || '';
        if (!email) {
            alert('Please enter your Gmail address.');
            return;
        }
        const hasExisting = window.existingIntegration && window.existingIntegration.email;
        if (!appPassword && !hasExisting) {
            alert('Please enter your Gmail App Password.');
            return;
        }
        try {
            const body = { email };
            if (appPassword) body.app_password = appPassword;
            const response = await fetch('/api/integrations/gmail', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(body)
            });
            const data = await response.json();
            if (response.ok) {
                alert('Gmail integration saved successfully. You can now send emails from Quotation Builder and other features.');
                currentIntegration.status = 'connected';
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error saving. Please try again.'));
            }
        } catch (e) {
            console.error(e);
            alert('Error saving Gmail integration. Please try again.');
        }
    }

    async function handleWiseDisconnect() {
        try {
            const r = await fetch('/api/integrations/wise', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' }
            });
            if (r.ok) {
                currentIntegration.status = 'disconnected';
                alert('Wise has been disconnected.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert('Error disconnecting. Please try again.');
            }
        } catch (e) {
            console.error(e);
            alert('Error disconnecting Wise. Please try again.');
        }
    }

    async function handleWiseSave() {
        const profileId = document.getElementById('wise-profile-id')?.value?.trim() || '';
        const isSandbox = document.getElementById('wise-sandbox')?.checked || false;
        const apiToken = document.getElementById('wise-api-token')?.value || '';
        try {
            const body = { profile_id: profileId || null, is_sandbox: isSandbox };
            if (apiToken) body.api_token = apiToken;
            const response = await fetch('/api/integrations/wise', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(body)
            });
            const data = await response.json();
            if (response.ok) {
                alert('Wise integration saved successfully.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error saving. Please try again.'));
            }
        } catch (e) {
            console.error(e);
            alert('Error saving Wise integration. Please try again.');
        }
    }

    async function handleStripeDisconnect() {
        try {
            const r = await fetch('/api/integrations/stripe', {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' }
            });
            if (r.ok) {
                currentIntegration.status = 'disconnected';
                alert('Stripe has been disconnected.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert('Error disconnecting. Please try again.');
            }
        } catch (e) {
            console.error(e);
            alert('Error disconnecting Stripe. Please try again.');
        }
    }

    async function handleStripeSave() {
        const publishableKey = document.getElementById('stripe-publishable-key')?.value?.trim() || '';
        const secretKey = document.getElementById('stripe-secret-key')?.value || '';
        const webhookSecret = document.getElementById('stripe-webhook-secret')?.value || '';
        const hasExisting = window.existingIntegration && window.existingIntegration.secret_key;
        if (!secretKey && !hasExisting) {
            alert('Please enter your Stripe Secret Key.');
            return;
        }
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        if (!csrfToken) {
            alert('CSRF token missing. Please refresh the page and try again.');
            return;
        }
        try {
            const body = { publishable_key: publishableKey || null, _token: csrfToken };
            if (secretKey) body.secret_key = secretKey;
            if (webhookSecret) body.webhook_secret = webhookSecret;
            const response = await fetch('{{ route("api.integrations.stripe.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            });
            let data = {};
            try {
                const text = await response.text();
                data = text ? JSON.parse(text) : {};
            } catch (parseErr) {
                console.error('Stripe response parse failed. Status:', response.status, 'Body:', text?.substring?.(0, 200));
                alert('Invalid server response (status ' + response.status + '). Check browser console (F12).');
                return;
            }
            if (response.ok) {
                alert('Stripe integration saved successfully. You can now generate payment links in Billing.');
                currentIntegration.status = 'connected';
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                const errMsg = data.error || data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : null);
                alert(errMsg || ('Request failed (status ' + response.status + ')'));
                if (!errMsg) console.error('Stripe save failed:', response.status, data);
            }
        } catch (e) {
            console.error('Stripe save error:', e);
            alert('Error saving Stripe integration: ' + (e.message || 'Please try again.'));
        }
    }

    async function handleOpenAiSave() {
        const apiKey = document.getElementById('openai-api-key')?.value?.trim() || '';
        const hasExisting = window.existingIntegration && window.existingIntegration.api_key;

        if (!apiKey && !hasExisting) {
            alert('Please enter your OpenAI API key. Leave blank to keep the current key.');
            return;
        }

        try {
            const response = await fetch('/api/integrations/openai', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ api_key: apiKey || null })
            });

            const data = await response.json();

            if (response.ok) {
                currentIntegration.status = 'connected';
                alert('OpenAI integration saved successfully. The AI Assistant will use this API key.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error saving. Please try again.'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error saving OpenAI integration. Please try again.');
        }
    }

    async function handleStoreganiseSave() {
        const businessCode = document.getElementById('storeganise-business-code')?.value?.trim().toLowerCase() || '';
        const apiKey = document.getElementById('storeganise-api-key')?.value?.trim() || '';
        const hasExisting = window.existingIntegration && window.existingIntegration.api_key;

        if (!businessCode) {
            alert('Please enter your Storeganise business code.');
            return;
        }

        if (!apiKey && !hasExisting) {
            alert('Please enter your Storeganise Admin API key.');
            return;
        }

        try {
            const response = await fetch('/api/integrations/storeganise', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    business_code: businessCode,
                    api_key: apiKey || null
                })
            });

            const data = await response.json();

            if (response.ok) {
                currentIntegration.status = 'connected';
                alert('Storeganise integration saved successfully.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error saving. Please try again.'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error saving Storeganise integration. Please try again.');
        }
    }

    async function handleStoreganiseDisconnect() {
        try {
            const response = await fetch('/api/integrations/storeganise', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            if (response.ok) {
                currentIntegration.status = 'disconnected';
                alert('Storeganise has been disconnected.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert('Error disconnecting. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error disconnecting. Please try again.');
        }
    }

    function renderFrontImportResults(stats, dryRun = false, lastImportAt = null) {
        const wrap = document.getElementById('front-import-results');
        if (!wrap || !stats) return;

        const samples = Array.isArray(stats.unmatched_samples) ? stats.unmatched_samples : [];
        const when = lastImportAt ? new Date(lastImportAt).toLocaleString() : new Date().toLocaleString();
        const modeLabel = stats.import_mode === 'tags'
            ? 'Tag-based matching across all shared inboxes'
            : 'Front inbox mapping';

        wrap.innerHTML = `
            <div class="front-import-results">
                <h4>${dryRun ? 'Preview results' : 'Import results'} <span style="font-weight:400;color:var(--text-secondary);">· ${when}</span></h4>
                <p class="form-help" style="margin:0 0 0.75rem;">${escapeHtml(modeLabel)}</p>
                ${stats.front_inbox_warning ? `<p class="form-help" style="margin:0 0 0.75rem;color:#b45309;">${escapeHtml(String(stats.front_inbox_warning))}</p>` : ''}
                <dl>
                    <dt>Mapped inboxes</dt><dd>${stats.mapped_inboxes ?? 0}</dd>
                    <dt>Front conversations with tags</dt><dd>${stats.front_conversations_with_tags ?? 0}</dd>
                    <dt>Matched conversations</dt><dd>${stats.conversations_matched ?? 0}</dd>
                    <dt>Unmatched conversations</dt><dd>${stats.conversations_unmatched ?? 0}</dd>
                    <dt>Tags created</dt><dd>${stats.tags_created ?? 0}</dd>
                    <dt>Existing tags reused</dt><dd>${stats.tags_existing ?? 0}</dd>
                    <dt>Tag links ${dryRun ? 'would apply' : 'applied'}</dt><dd>${stats.tags_applied ?? 0}</dd>
                </dl>
                ${samples.length ? `<ul class="front-unmatched-list">${samples.map(s => `<li>${escapeHtml(String(s))}</li>`).join('')}</ul>` : ''}
            </div>
        `;
    }

    function renderFrontImportError(message) {
        const wrap = document.getElementById('front-import-results');
        if (!wrap) return;

        wrap.innerHTML = `
            <div class="front-import-results" style="border-color:#fecaca;">
                <h4 style="color:#b91c1c;margin-bottom:0.5rem;">Import failed</h4>
                <p class="form-help" style="margin:0;color:#b91c1c;">${escapeHtml(String(message || 'Unknown error'))}</p>
            </div>
        `;
    }

    function escapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function collectFrontInboxMap() {
        const map = {};
        document.querySelectorAll('[data-front-inbox-id]').forEach(select => {
            const frontId = select.getAttribute('data-front-inbox-id');
            const sharedId = parseInt(select.value, 10);
            if (frontId && sharedId > 0) {
                map[frontId] = sharedId;
            }
        });
        return map;
    }

    function collectFrontInboxEntries() {
        const entries = [];
        document.querySelectorAll('[data-front-inbox-id]').forEach(select => {
            const frontId = select.getAttribute('data-front-inbox-id');
            const sharedId = parseInt(select.value, 10);
            const frontName = select.closest('tr')?.querySelector('td')?.textContent?.trim() || frontId;
            if (frontId && sharedId > 0) {
                entries.push({ frontId, sharedId, frontName });
            }
        });
        return entries;
    }

    function emptyFrontImportStats() {
        return {
            mapped_inboxes: 0,
            front_conversations_with_tags: 0,
            conversations_matched: 0,
            conversations_unmatched: 0,
            tags_created: 0,
            tags_existing: 0,
            tags_applied: 0,
            unmatched_samples: [],
        };
    }

    function mergeFrontImportStats(target, source) {
        if (!source) return target;
        [
            'mapped_inboxes',
            'front_conversations_with_tags',
            'conversations_matched',
            'conversations_unmatched',
            'tags_created',
            'tags_existing',
            'tags_applied',
        ].forEach(key => {
            target[key] = (Number(target[key]) || 0) + (Number(source[key]) || 0);
        });
        if (source.import_mode) target.import_mode = source.import_mode;
        if (source.front_inbox_warning) target.front_inbox_warning = source.front_inbox_warning;
        if (source.inbox_errors?.length) {
            target.inbox_errors = [...(target.inbox_errors || []), ...source.inbox_errors];
        }
        const samples = source.unmatched_samples || [];
        samples.forEach(sample => {
            if ((target.unmatched_samples || []).length < 10 && !(target.unmatched_samples || []).includes(sample)) {
                target.unmatched_samples = [...(target.unmatched_samples || []), sample];
            }
        });
        return target;
    }

    function setFrontImportLoading(isLoading, message = 'Processing…') {
        const loading = document.getElementById('front-import-loading');
        const label = document.getElementById('front-import-loading-label');
        const dryRunBtn = document.getElementById('front-dry-run-btn');
        const importBtn = document.getElementById('front-import-btn');
        [dryRunBtn, importBtn].forEach(btn => { if (btn) btn.disabled = !!isLoading; });
        if (label) label.textContent = message;
        if (loading) loading.hidden = !isLoading;
    }

    async function parseFrontImportResponse(response) {
        const text = await response.text();
        let data = {};
        if (text) {
            try {
                data = JSON.parse(text);
            } catch (error) {
                throw new Error(`Server returned HTTP ${response.status} with a non-JSON response. ${text.slice(0, 240)}`);
            }
        }
        if (!response.ok) {
            throw new Error(data.error || data.message || `Request failed with HTTP ${response.status}.`);
        }
        return data;
    }

    async function runFrontImportRequest(dryRun, inboxMap, frontInboxId, persistResults = true, pageUrl = null, resultStats = null) {
        const payload = {
            dry_run: dryRun,
            include_private: !!document.getElementById('front-include-private')?.checked,
            inbox_map: inboxMap,
            front_inbox_id: frontInboxId || null,
            persist_results: persistResults,
        };
        if (pageUrl) {
            payload.page_url = pageUrl;
        }
        if (resultStats) {
            payload.result_stats = resultStats;
        }

        const response = await fetch('/api/integrations/front/import-tags', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify(payload)
        });

        return parseFrontImportResponse(response);
    }

    async function runFrontInboxImportPaged(dryRun, entry, actionLabel) {
        let pageUrl = null;
        let page = 0;
        const partial = emptyFrontImportStats();

        do {
            page += 1;
            setFrontImportLoading(
                true,
                `${actionLabel} ${entry.frontName} – page ${page}…`
            );
            const data = await runFrontImportRequest(
                dryRun,
                { [entry.frontId]: entry.sharedId },
                entry.frontId,
                false,
                pageUrl
            );
            mergeFrontImportStats(partial, data.stats || {});
            pageUrl = data.has_more && data.next_page_url ? data.next_page_url : null;
        } while (pageUrl);

        return partial;
    }

    async function loadFrontImportPanel(existingIntegration = null) {
        const panel = document.getElementById('front-import-panel');
        const mappingWrap = document.getElementById('front-mapping-wrap');
        if (!panel || !mappingWrap) return;

        panel.hidden = false;

        if (existingIntegration?.last_import_stats) {
            renderFrontImportResults(
                existingIntegration.last_import_stats,
                !!existingIntegration.last_import_dry_run,
                existingIntegration.last_import_at || null
            );
        }

        try {
            const response = await fetch('/api/integrations/front/mapping', {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            if (!response.ok) {
                const message = data.error || 'Could not load inbox mapping.';
                mappingWrap.innerHTML = `
                    <div class="form-help" style="color:#b45309;margin-bottom:0.75rem;">${escapeHtml(message)}</div>
                    <div class="form-help">Preview and import can still run using tag-based matching across all shared inboxes.</div>
                `;
                return;
            }

            if (data.front_error) {
                mappingWrap.innerHTML = `
                    <div class="form-help" style="color:#b45309;margin-bottom:0.75rem;">Could not list Front inboxes: ${escapeHtml(data.front_error)}</div>
                    <div class="form-help" style="margin-bottom:0.75rem;">Preview and import will use tag-based matching across your ${(data.shared_inboxes || []).length} shared inbox(es).</div>
                `;
            }

            const sharedOptions = (data.shared_inboxes || []).map(inbox => {
                const label = inbox.email ? `${inbox.name} (${inbox.email})` : inbox.name;
                return `<option value="${inbox.id}">${escapeHtml(label)}</option>`;
            }).join('');

            const rows = (data.rows || []).map(row => `
                <tr>
                    <td>${escapeHtml(row.front_name || row.front_id)}</td>
                    <td>
                        <select class="form-input" data-front-inbox-id="${escapeHtml(row.front_id)}" style="min-width:220px;">
                            <option value="">— Skip —</option>
                            ${sharedOptions}
                        </select>
                    </td>
                </tr>
            `).join('');

            if (rows) {
                const prefix = data.front_error ? mappingWrap.innerHTML : '';
                mappingWrap.innerHTML = `${prefix}
                    <label class="form-label">Map Front inboxes to LNSCRM shared inboxes</label>
                    <table class="front-mapping-table">
                        <thead><tr><th>Front inbox</th><th>LNSCRM shared inbox</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                `;
            } else if (!data.front_error) {
                mappingWrap.innerHTML = `
                    <div class="form-help" style="margin-bottom:0.75rem;">No Front inboxes returned. Preview and import will use tag-based matching across your ${(data.shared_inboxes || []).length} shared inbox(es).</div>
                    <div class="form-help">Local shared inboxes: ${(data.shared_inboxes || []).map(inbox => escapeHtml(inbox.name)).join(', ') || 'none found'}</div>
                `;
            }

            (data.rows || []).forEach(row => {
                const select = mappingWrap.querySelector(`[data-front-inbox-id="${row.front_id}"]`);
                if (select && row.shared_inbox_id) {
                    select.value = String(row.shared_inbox_id);
                }
            });
        } catch (error) {
            console.error('Error loading Front mapping:', error);
            mappingWrap.innerHTML = '<span class="form-help" style="color:#ef4444;">Could not load inbox mapping. You can still try Preview — import will match by tags across all shared inboxes.</span>';
        }
    }

    function showFrontTokenError(message, tone = 'error') {
        const el = document.getElementById('front-token-error');
        if (!el) return;
        if (message) {
            el.textContent = message;
            el.style.display = 'block';
            el.style.color = tone === 'warning' ? '#b45309' : '#b91c1c';
        } else {
            el.style.display = 'none';
            el.textContent = '';
        }
    }

    async function handleFrontSave(closeOnSuccess = true) {
        const apiToken = document.getElementById('front-api-token')?.value?.trim() || '';
        const hasExisting = window.existingIntegration && window.existingIntegration.api_token;

        if (!apiToken && !hasExisting) {
            showFrontTokenError('Please enter your Front API token.');
            return false;
        }

        showFrontTokenError('');

        try {
            const response = await fetch('/api/integrations/front', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ api_token: apiToken || null })
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = data.error
                    || (data.errors ? Object.values(data.errors).flat().join(', ') : '')
                    || `Could not save Front token (HTTP ${response.status}).`;
                showFrontTokenError(message);
                return false;
            }

            currentIntegration.status = 'connected';
            window.existingIntegration = {
                ...(window.existingIntegration || {}),
                api_token: '***hidden***',
                is_active: true,
                status: 'connected',
            };

            if (data.verify_warning) {
                showFrontTokenError(`Token saved, but Front could not verify it yet: ${data.verify_warning}`, 'warning');
            }

            const panel = document.getElementById('front-import-panel');
            if (panel) panel.hidden = false;

            if (closeOnSuccess && !data.verify_warning) {
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                const tokenInput = document.getElementById('front-api-token');
                if (tokenInput) tokenInput.value = '';
                await loadFrontImportPanel(window.existingIntegration);
            }

            return true;
        } catch (error) {
            console.error('Error:', error);
            showFrontTokenError('Error saving Front integration. Please try again.');
            return false;
        }
    }

    async function handleFrontDisconnect() {
        try {
            const response = await fetch('/api/integrations/front', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            if (response.ok) {
                currentIntegration.status = 'disconnected';
                alert('Front has been disconnected.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert('Error disconnecting Front.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error disconnecting Front.');
        }
    }

    async function handleFrontImport(dryRun = false) {
        const hasExisting = window.existingIntegration && window.existingIntegration.api_token;
        const apiToken = document.getElementById('front-api-token')?.value?.trim() || '';

        if (!hasExisting && !apiToken) {
            showFrontTokenError('Save your Front API token first.');
            return;
        }

        if (apiToken) {
            const saved = await handleFrontSave(false);
            if (!saved) return;
        }

        const entries = collectFrontInboxEntries();
        const aggregated = emptyFrontImportStats();
        const actionLabel = dryRun ? 'Previewing' : 'Importing';

        setFrontImportLoading(true, `${actionLabel}…`);
        document.getElementById('front-import-results')?.replaceChildren();

        try {
            if (entries.length === 0) {
                setFrontImportLoading(true, `${actionLabel} tags across all shared inboxes…`);
                const data = await runFrontImportRequest(dryRun, {}, null);
                mergeFrontImportStats(aggregated, data.stats || {});
            } else {
                for (let index = 0; index < entries.length; index++) {
                    const entry = entries[index];
                    setFrontImportLoading(
                        true,
                        `${actionLabel} ${entry.frontName} (${index + 1} of ${entries.length})…`
                    );
                    const inboxStats = await runFrontInboxImportPaged(dryRun, entry, actionLabel);
                    mergeFrontImportStats(aggregated, inboxStats);
                }

                await runFrontImportRequest(dryRun, {}, null, true, null, aggregated);
            }

            const finishedAt = new Date().toISOString();
            renderFrontImportResults(aggregated, dryRun, finishedAt);
            window.existingIntegration = {
                ...(window.existingIntegration || {}),
                last_import_stats: aggregated,
                last_import_dry_run: dryRun,
                last_import_at: finishedAt,
            };
        } catch (error) {
            console.error('Front import error:', error);
            renderFrontImportError(error.message || 'Front tag import failed. Please try again.');
        } finally {
            setFrontImportLoading(false);
        }
    }

    async function handleCalendarOauthSave(provider = 'google') {
        const payload = provider === 'outlook'
            ? {
                microsoft_client_id: document.getElementById('oauth-microsoft-client-id')?.value?.trim() || '',
                microsoft_client_secret: document.getElementById('oauth-microsoft-client-secret')?.value || '',
                microsoft_tenant_id: document.getElementById('oauth-microsoft-tenant-id')?.value?.trim() || '',
            }
            : {
                google_client_id: document.getElementById('oauth-google-client-id')?.value?.trim() || '',
                google_client_secret: document.getElementById('oauth-google-client-secret')?.value || '',
            };
        const label = provider === 'outlook' ? 'Microsoft Outlook' : 'Google Calendar';
        try {
            const response = await fetch('{{ route("api.calendar.oauth-settings.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (response.ok) {
                const googleCard = integrationsData.find(i => i.id === 'calendar');
                const outlookCard = integrationsData.find(i => i.id === 'outlook');
                if (googleCard) googleCard.status = data.google_configured ? 'connected' : 'disconnected';
                if (outlookCard) outlookCard.status = data.outlook_configured ? 'connected' : 'disconnected';
                alert(data.message || `${label} OAuth settings saved.`);
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert(data.error || data.message || 'Failed to save settings.');
            }
        } catch (e) {
            console.error(e);
            alert(`Failed to save ${label} OAuth settings.`);
        }
    }

    async function handleOpenAiDisconnect() {
        try {
            const response = await fetch('/api/integrations/openai', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            if (response.ok) {
                currentIntegration.status = 'disconnected';
                alert('OpenAI has been disconnected.');
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert('Error disconnecting. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error disconnecting. Please try again.');
        }
    }

    async function handleIntegrationAction() {
        if (!currentIntegration) return;

        if (currentIntegration.status === 'connected' && currentIntegration.id !== 'wise') {
            if (confirm(`Are you sure you want to disconnect ${currentIntegration.name}?`)) {
                if (currentIntegration.id === 'wise') {
                    try {
                        const response = await fetch('/api/integrations/wise', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        if (response.ok) {
                            currentIntegration.status = 'disconnected';
                            alert(currentIntegration.name + ' has been disconnected.');
                            closeIntegrationModal();
                            renderIntegrations(currentCategory);
                        } else {
                            alert('Error disconnecting integration. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error disconnecting integration. Please try again.');
                    }
                } else if (currentIntegration.id === 'twilio') {
                    await handleTwilioDisconnect();
                } else if (currentIntegration.id === 'viber') {
                    try {
                        const response = await fetch('/api/integrations/viber', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        if (response.ok) {
                            currentIntegration.status = 'disconnected';
                            alert(`${currentIntegration.name} has been disconnected.`);
                            closeIntegrationModal();
                            renderIntegrations(currentCategory);
                        } else {
                            alert('Error disconnecting integration. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error disconnecting integration. Please try again.');
                    }
                } else if (currentIntegration.id === 'whatsapp') {
                    try {
                        const response = await fetch('/api/integrations/whatsapp', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        if (response.ok) {
                            currentIntegration.status = 'disconnected';
                            alert(`${currentIntegration.name} has been disconnected.`);
                            closeIntegrationModal();
                            renderIntegrations(currentCategory);
                        } else {
                            alert('Error disconnecting integration. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error disconnecting integration. Please try again.');
                    }
                } else if (currentIntegration.id === 'facebook') {
                    try {
                        const response = await fetch('/api/integrations/facebook', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            }
                        });
                        if (response.ok) {
                            currentIntegration.status = 'disconnected';
                            alert(`${currentIntegration.name} has been disconnected.`);
                            closeIntegrationModal();
                            renderIntegrations(currentCategory);
                        } else {
                            alert('Error disconnecting integration. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Error disconnecting integration. Please try again.');
                    }
                } else {
                    currentIntegration.status = 'disconnected';
                    alert(`${currentIntegration.name} has been disconnected.`);
                    closeIntegrationModal();
                    renderIntegrations(currentCategory);
                }
            }
        } else {
            if (currentIntegration.id === 'stripe') {
                handleStripeSave();
                return;
            }
            if (currentIntegration.id === 'gmail') {
                const email = document.getElementById('gmail-email')?.value?.trim() || '';
                const appPassword = document.getElementById('gmail-app-password')?.value || '';
                if (!email) {
                    alert('Please enter your Gmail address.');
                    return;
                }
                if (!appPassword) {
                    alert('Please enter your Gmail App Password.');
                    return;
                }
                try {
                    const response = await fetch('/api/integrations/gmail', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({ email, app_password: appPassword })
                    });
                    const data = await response.json();
                    if (response.ok) {
                        currentIntegration.status = 'connected';
                        alert('Gmail has been connected successfully! You can now send emails from Quotation Builder and other features.');
                        closeIntegrationModal();
                        renderIntegrations(currentCategory);
                    } else {
                        alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error connecting. Please try again.'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error connecting Gmail. Please try again.');
                }
            } else if (currentIntegration.id === 'openai') {
                handleOpenAiSave();
            } else if (currentIntegration.id === 'storeganise') {
                handleStoreganiseSave();
            } else if (currentIntegration.id === 'front') {
                handleFrontSave(false);
            } else if (currentIntegration.id === 'wise') {
                const apiToken = document.getElementById('wise-api-token')?.value || '';
                const profileId = document.getElementById('wise-profile-id')?.value || '';
                const isSandbox = document.getElementById('wise-sandbox')?.checked || false;
                const hasExisting = window.existingIntegration && window.existingIntegration.api_token;

                if (!apiToken && !hasExisting) {
                    alert('Please enter your Wise API Token.');
                    return;
                }

                try {
                    const response = await fetch('/api/integrations/wise', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            api_token: apiToken,
                            profile_id: profileId || null,
                            is_sandbox: isSandbox
                        })
                    });

                    const data = await response.json();

                    if (response.ok) {
                        currentIntegration.status = 'connected';
                        alert(currentIntegration.name + ' has been connected successfully!');
                        closeIntegrationModal();
                        renderIntegrations(currentCategory);
                    } else {
                        alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error connecting integration. Please try again.'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error connecting Wise. Please try again.');
                }
            } else if (currentIntegration.id === 'twilio') {
                await handleTwilioSave();
            } else if (currentIntegration.id === 'viber') {
                const senderId = document.getElementById('viber-sender-id')?.value?.trim() || '';
                const botName = document.getElementById('viber-bot-name')?.value?.trim() || '';
                const welcomeMessage = document.getElementById('viber-welcome-message')?.value || '';

                if (!senderId) {
                    alert('Please enter your Twilio Viber Sender ID.');
                    return;
                }

                try {
                    const response = await fetch('/api/integrations/viber', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            sender_id: senderId,
                            bot_name: botName || null,
                            welcome_message: welcomeMessage,
                        })
                    });
                    const data = await response.json();
                    if (response.ok) {
                        currentIntegration.status = 'connected';
                        let msg = 'Viber Business has been connected successfully via Twilio!';
                        if (data.integration?.webhook_url) {
                            msg += '\n\nPaste this webhook URL on your Twilio Viber sender:\n' + data.integration.webhook_url;
                        }
                        alert(msg);
                        closeIntegrationModal();
                        renderIntegrations(currentCategory);
                    } else {
                        alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error connecting Viber.'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error connecting Viber. Please try again.');
                }
            } else if (currentIntegration.id === 'whatsapp') {
                const fromNumber = document.getElementById('whatsapp-from-number')?.value?.trim() || '';
                const businessName = document.getElementById('whatsapp-business-name')?.value?.trim() || '';
                const welcomeMessage = document.getElementById('whatsapp-welcome-message')?.value || '';

                if (!fromNumber) {
                    alert('Please enter your WhatsApp from number (E.164).');
                    return;
                }

                try {
                    const response = await fetch('/api/integrations/whatsapp', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            from_number: fromNumber,
                            business_name: businessName || null,
                            welcome_message: welcomeMessage,
                        })
                    });
                    const data = await response.json();
                    if (response.ok) {
                        currentIntegration.status = 'connected';
                        let msg = 'WhatsApp Business has been connected successfully via Twilio!';
                        if (data.integration?.webhook_url) {
                            msg += '\n\nPaste this webhook URL on your Twilio WhatsApp sender:\n' + data.integration.webhook_url;
                        }
                        alert(msg);
                        closeIntegrationModal();
                        renderIntegrations(currentCategory);
                    } else {
                        alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error connecting WhatsApp.'));
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error connecting WhatsApp. Please try again.');
                }
            } else if (currentIntegration.id === 'facebook') {
                await saveFacebookIntegration();
            } else {
                // Simulate connection process for other integrations
                alert(`Connecting to ${currentIntegration.name}...`);
                currentIntegration.status = 'connected';
                alert(`${currentIntegration.name} has been connected successfully!`);
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            }
        }
    }

    async function saveFacebookIntegration() {
        const pageId = document.getElementById('facebook-page-id')?.value?.trim() || '';
        const pageName = document.getElementById('facebook-page-name')?.value?.trim() || '';
        const pageAccessToken = document.getElementById('facebook-page-access-token')?.value?.trim() || '';
        const appSecret = document.getElementById('facebook-app-secret')?.value?.trim() || '';
        const instagramId = document.getElementById('facebook-instagram-id')?.value?.trim() || '';
        const instagramUsername = document.getElementById('facebook-instagram-username')?.value?.trim() || '';
        const welcomeMessage = document.getElementById('facebook-welcome-message')?.value || '';

        if (!pageId) {
            alert('Please enter your Facebook Page ID from Twilio Console.');
            return;
        }

        try {
            const payload = {
                page_id: pageId,
                page_name: pageName || null,
                instagram_business_account_id: instagramId || null,
                instagram_username: instagramUsername || null,
                welcome_message: welcomeMessage,
            };
            if (pageAccessToken) payload.page_access_token = pageAccessToken;
            if (appSecret) payload.app_secret = appSecret;

            const response = await fetch('/api/integrations/facebook', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (response.ok) {
                currentIntegration.status = 'connected';
                let msg = 'Facebook & Instagram settings saved.';
                if (data.integration?.instagram_graph) {
                    msg += '\n\nInstagram Direct is ready. Paste the Webhook URL and Verify Token into Meta App → Messenger → Instagram settings.';
                } else if (!data.integration?.has_page_access_token) {
                    msg += '\n\nAdd a Page Access Token so Instagram DMs can use Meta webhooks.';
                }
                if (data.integration?.webhook_url) {
                    msg += '\n\nWebhook URL:\n' + data.integration.webhook_url;
                }
                if (data.integration?.webhook_verify_token) {
                    msg += '\nVerify token:\n' + data.integration.webhook_verify_token;
                }
                alert(msg);
                closeIntegrationModal();
                renderIntegrations(currentCategory);
            } else {
                alert(data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Error saving Facebook.'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error saving Facebook. Please try again.');
        }
    }

    async function syncFacebookHistory(event) {
        event?.preventDefault();
        const days = document.getElementById('facebook-sync-days')?.value || '90';
        const btn = document.getElementById('facebook-sync-btn');
        const help = document.getElementById('facebook-sync-help');
        const original = btn?.textContent;
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Syncing…';
        }
        if (help) help.textContent = 'Importing Facebook inbox and Twilio history… this can take a few minutes.';
        try {
            let response;
            try {
                response = await fetch('/api/facebook/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ days: Number(days), limit: 2000 })
                });
            } catch (e) {
                throw new Error('Could not reach the server. If Sync was still Pending, it timed out — try Last 30 days.');
            }
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const timeout = [502, 504, 524, 408].includes(response.status);
                throw new Error(
                    data.message || data.error || (timeout
                        ? 'Sync timed out. Try Last 30 days, or add a Page Access Token under Integrations.'
                        : 'Sync failed (HTTP ' + response.status + ').')
                );
            }
            const result = data.data || {};
            const imported = Number(result.imported || 0);
            const skipped = Number(result.skipped || 0);
            const scanned = Number(result.scanned || 0);
            const rangeLabel = Number(result.days || days) === 0 ? 'all available Twilio history' : `the last ${result.days || days} days`;
            const hint = result.hint || '';
            const summary = imported
                ? `Imported ${imported} message${imported === 1 ? '' : 's'} from ${rangeLabel}${skipped ? ` (${skipped} already in CRM)` : ''}.`
                : (scanned
                    ? `No new messages. Found ${scanned} in ${rangeLabel}; they are already in the CRM.`
                    : `No Messenger history found in ${rangeLabel}.`);
            const full = hint ? summary + '\n\n' + hint : summary;
            if (help) help.textContent = full;
            alert(full);
        } catch (error) {
            console.error('Error:', error);
            const msg = error.message || 'Could not sync old Facebook messages.';
            if (help) help.textContent = msg;
            alert(msg);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = original || 'Sync Messenger inbox';
            }
        }
    }

    function closeIntegrationModal() {
        document.getElementById('integrationModal').classList.remove('active');
        document.body.style.overflow = '';
        currentIntegration = null;
    }

    document.getElementById('integrationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeIntegrationModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeIntegrationModal();
        }
    });

    // Load integration status on page load
    async function initializeIntegrations() {
        // Load Gmail integration
        const gmailIntegration = integrationsData.find(i => i.id === 'gmail');
        if (gmailIntegration) {
            try {
                const response = await fetch('/api/integrations/gmail');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration && data.status === 'connected') {
                        gmailIntegration.status = 'connected';
                    }
                }
            } catch (error) {
                console.error('Error loading Gmail integration on init:', error);
            }
        }
        // Load Wise integration
        const wiseIntegration = integrationsData.find(i => i.id === 'wise');
        if (wiseIntegration) {
            try {
                const response = await fetch('/api/integrations/wise');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration && data.status === 'connected') {
                        wiseIntegration.status = 'connected';
                    }
                }
            } catch (error) {
                console.error('Error loading Wise integration on init:', error);
            }
        }
        // Load Twilio integration
        const twilioIntegration = integrationsData.find(i => i.id === 'twilio');
        if (twilioIntegration) {
            try {
                const response = await fetch('/api/integrations/twilio');
                if (response.ok) {
                    const data = await response.json();
                    twilioIntegration.status = data.status ?? 'disconnected';
                }
            } catch (error) {
                console.error('Error loading Twilio integration on init:', error);
            }
        }
        // Load Viber integration
        const viberIntegration = integrationsData.find(i => i.id === 'viber');
        if (viberIntegration) {
            try {
                const response = await fetch('/api/integrations/viber');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration) {
                        viberIntegration.status = data.status ?? 'disconnected';
                    }
                }
            } catch (error) {
                console.error('Error loading Viber integration on init:', error);
            }
        }
        // Load WhatsApp integration
        const whatsappIntegration = integrationsData.find(i => i.id === 'whatsapp');
        if (whatsappIntegration) {
            try {
                const response = await fetch('/api/integrations/whatsapp');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration) {
                        whatsappIntegration.status = data.status ?? 'disconnected';
                    }
                }
            } catch (error) {
                console.error('Error loading WhatsApp integration on init:', error);
            }
        }
        // Load Facebook integration
        const facebookIntegration = integrationsData.find(i => i.id === 'facebook');
        if (facebookIntegration) {
            try {
                const response = await fetch('/api/integrations/facebook');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration) {
                        facebookIntegration.status = data.status ?? 'disconnected';
                    }
                }
            } catch (error) {
                console.error('Error loading Facebook integration on init:', error);
            }
        }
        // Load Stripe integration
        const stripeIntegration = integrationsData.find(i => i.id === 'stripe');
        if (stripeIntegration) {
            try {
                const response = await fetch('/api/integrations/stripe');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration && data.status === 'connected') {
                        stripeIntegration.status = 'connected';
                    }
                }
            } catch (error) {
                console.error('Error loading Stripe integration on init:', error);
            }
        }
        // Load OpenAI integration
        const openaiIntegration = integrationsData.find(i => i.id === 'openai');
        if (openaiIntegration) {
            try {
                const response = await fetch('/api/integrations/openai');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration && data.status === 'connected') {
                        openaiIntegration.status = 'connected';
                    }
                }
            } catch (error) {
                console.error('Error loading OpenAI integration on init:', error);
            }
        }
        // Load Storeganise integration
        const storeganiseIntegration = integrationsData.find(i => i.id === 'storeganise');
        if (storeganiseIntegration) {
            try {
                const response = await fetch('/api/integrations/storeganise');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration && data.status === 'connected') {
                        storeganiseIntegration.status = 'connected';
                    }
                }
            } catch (error) {
                console.error('Error loading Storeganise integration on init:', error);
            }
        }
        // Load Front integration
        const frontIntegration = integrationsData.find(i => i.id === 'front');
        if (frontIntegration) {
            try {
                const response = await fetch('/api/integrations/front');
                if (response.ok) {
                    const data = await response.json();
                    if (data.integration && data.status === 'connected') {
                        frontIntegration.status = 'connected';
                    }
                }
            } catch (error) {
                console.error('Error loading Front integration on init:', error);
            }
        }
        // Load Google Calendar + Outlook OAuth status
        try {
            const response = await fetch('{{ route("api.calendar.oauth-settings") }}');
            if (response.ok) {
                const data = await response.json();
                const calendarIntegration = integrationsData.find(i => i.id === 'calendar');
                const outlookIntegration = integrationsData.find(i => i.id === 'outlook');
                if (calendarIntegration) {
                    calendarIntegration.status = data.google_configured ? 'connected' : 'disconnected';
                }
                if (outlookIntegration) {
                    outlookIntegration.status = data.outlook_configured ? 'connected' : 'disconnected';
                }
            }
        } catch (error) {
            console.error('Error loading Calendar/Outlook OAuth status on init:', error);
        }

        renderIntegrations();
    }

    // Initialize
    initializeIntegrations();
</script>
@endpush

