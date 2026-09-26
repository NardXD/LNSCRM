@extends('layouts.app')

@section('title', 'AI Assistant')

@push('styles')
    @vite(['resources/css/pages/openai.css'])
@endpush

@push('scripts')
<script>
    window.__openaiConfig = {
        chatUrl: @json(route('api.openai.chat')),
    };
</script>
    @vite(['resources/js/pages/openai.js'])
@endpush

@section('content')
    @php
        use App\Helpers\SidebarHelper;
        $canSummarize = fn ($permission, $moduleSlug) => SidebarHelper::canAccessModule($userPermissions ?? [], $companyModuleSlugs ?? null, $permission, $moduleSlug);
    @endphp

    <div class="page-header">
        <h1 class="page-title">AI Assistant</h1>
        <p class="page-subtitle">Your intelligent assistant for leads, shared inboxes, Viber, WhatsApp, Facebook/Instagram, SMS, broadcast messaging, and the knowledge base</p>
    </div>

    <div class="openai-container">
        <div class="openai-layout">
            <!-- Sidebar -->
            <div class="openai-sidebar">
                <div class="sidebar-section">
                    <button class="btn-primary new-chat-btn" onclick="startNewChat()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Chat
                    </button>
                </div>

                <div class="sidebar-section">
                    <h3 class="sidebar-title">Chat History</h3>
                    <div class="chat-history" id="chatHistory">
                        <!-- Chat history will be populated by JavaScript -->
                    </div>
                </div>

                <div class="sidebar-section">
                    <h3 class="sidebar-title">Quick Actions</h3>
                    <div class="quick-actions-list">
                        <button class="quick-action-btn" onclick="useQuickAction('email')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                            Write Email
                        </button>
                        <button class="quick-action-btn" onclick="useQuickAction('summary')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            Summarize Text
                        </button>
                        <button class="quick-action-btn" onclick="useQuickAction('translate')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            Translate
                        </button>
                        <button class="quick-action-btn" onclick="useQuickAction('reply')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="9 14 4 9 9 4"/>
                                <path d="M20 20v-7a4 4 0 0 0-4-4H4"/>
                            </svg>
                            Draft Inbox Reply
                        </button>
                    </div>
                </div>

                <div class="sidebar-section">
                    <h3 class="sidebar-title">Generate Summary</h3>
                    <div class="quick-actions-list">
                        @if($canSummarize('view_leads', 'client-management'))
                        <button class="quick-action-btn" onclick="useSummaryAction('leads')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            Leads Summary
                        </button>
                        @endif
                        @if($canSummarize('view_inbox', 'inbox'))
                        <button class="quick-action-btn" onclick="useSummaryAction('shared-inbox')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 12h-6l-2 3h-4l-2-3H2"/>
                                <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                            </svg>
                            Shared Inbox Summary
                        </button>
                        @endif
                        @if($canSummarize('view_viber', 'viber'))
                        <button class="quick-action-btn" onclick="useSummaryAction('viber')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                            </svg>
                            Viber Summary
                        </button>
                        @endif
                        @if($canSummarize('view_whatsapp', 'whatsapp'))
                        <button class="quick-action-btn" onclick="useSummaryAction('whatsapp')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            WhatsApp Summary
                        </button>
                        @endif
                        @if($canSummarize('view_facebook', 'facebook'))
                        <button class="quick-action-btn" onclick="useSummaryAction('facebook')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                            Facebook &amp; Instagram Summary
                        </button>
                        @endif
                        @if($canSummarize('view_sms', 'sms'))
                        <button class="quick-action-btn" onclick="useSummaryAction('sms')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            SMS Summary
                        </button>
                        @endif
                        @if($canSummarize('view_broadcast_messaging', 'broadcast-messaging'))
                        <button class="quick-action-btn" onclick="useSummaryAction('broadcast')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 11a9 9 0 0 1 9-9"/>
                                <path d="M4 5a15 15 0 0 1 15 15"/>
                                <circle cx="5" cy="19" r="2"/>
                            </svg>
                            Broadcast Summary
                        </button>
                        @endif
                        @if($canSummarize('view_knowledge_base', 'knowledge-base'))
                        <button class="quick-action-btn" onclick="useSummaryAction('knowledge-base')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                            </svg>
                            Knowledge Base Summary
                        </button>
                        @endif
                    </div>
                </div>

                <div class="sidebar-section">
                    <button class="btn-secondary settings-btn" onclick="openSettings()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"/>
                        </svg>
                        Settings
                    </button>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="openai-main">
                <!-- Model Selector -->
                <div class="model-selector">
                    <select class="model-select" id="modelSelect">
                        <option value="gpt-5.2">GPT-5.2</option>
                        <option value="gpt-5-mini">GPT-5 mini</option>
                        <option value="gpt-5-nano">GPT-5 nano</option>
                        <option value="gpt-4.1">GPT-4.1</option>
                        <option value="gpt-4.1-mini">GPT-4.1 mini</option>
                        <option value="gpt-4.1-nano">GPT-4.1 nano</option>
                        <option value="gpt-4o" selected>GPT-4o</option>
                        <option value="gpt-4o-mini">GPT-4o mini</option>
                        <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                    </select>
                    <div class="model-info">
                        <span class="model-badge" id="modelBadge">GPT-4o</span>
                        <span class="model-desc">Fast, intelligent, flexible</span>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="chat-messages" id="chatMessages">
                    <div class="welcome-message">
                        <div class="welcome-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                <line x1="12" y1="22.08" x2="12" y2="12"/>
                            </svg>
                        </div>
                        <h2>How can I help you today?</h2>
                        <p>Ask about your leads, shared inboxes, Viber, WhatsApp, Facebook/Instagram, SMS, broadcast campaigns, or the knowledge base.</p>
                        <div class="suggestions-grid">
                            @if($canSummarize('view_leads', 'client-management'))
                            <button class="suggestion-card" onclick="useSummaryAction('leads')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                                <span>Leads summary</span>
                            </button>
                            @endif
                            @if($canSummarize('view_inbox', 'inbox'))
                            <button class="suggestion-card" onclick="useSummaryAction('shared-inbox')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 12h-6l-2 3h-4l-2-3H2"/>
                                    <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                                </svg>
                                <span>Shared inbox summary</span>
                            </button>
                            @endif
                            @if($canSummarize('view_broadcast_messaging', 'broadcast-messaging'))
                            <button class="suggestion-card" onclick="useSummaryAction('broadcast')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 11a9 9 0 0 1 9-9"/>
                                    <path d="M4 5a15 15 0 0 1 15 15"/>
                                    <circle cx="5" cy="19" r="2"/>
                                </svg>
                                <span>Broadcast summary</span>
                            </button>
                            @endif
                            @if($canSummarize('view_knowledge_base', 'knowledge-base'))
                            <button class="suggestion-card" onclick="useSummaryAction('knowledge-base')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                </svg>
                                <span>Knowledge base summary</span>
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="chat-input-area">
                    <div class="input-wrapper">
                        <textarea 
                            class="chat-input" 
                            id="chatInput" 
                            placeholder="Type your message here... (Press Enter to send, Shift+Enter for new line)"
                            rows="1"
                            onkeydown="handleInputKeydown(event)"
                        ></textarea>
                        <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </button>
                    </div>
                    <div class="input-footer">
                        <div class="data-source-toggle">
                            <span class="data-source-label">Data source:</span>
                            <div class="data-source-options">
                                <button type="button" class="data-source-btn active" data-source="database" onclick="setDataSource('database')" title="Use your CRM database">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                        <ellipse cx="12" cy="5" rx="9" ry="3"/>
                                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                                    </svg>
                                    Database
                                </button>
                                <button type="button" class="data-source-btn" data-source="openai" onclick="setDataSource('openai')" title="Use OpenAI general knowledge">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                                        <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/>
                                    </svg>
                                    OpenAI
                                </button>
                            </div>
                        </div>
                        <span class="input-hint">AI can make mistakes. Verify important information.</span>
                        <div class="input-actions">
                            <button class="action-btn" onclick="clearChat()" title="Clear Chat">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                            <button class="action-btn" onclick="exportChat()" title="Export Chat">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="settings-modal" id="settingsModal">
        <div class="settings-modal-content">
            <button class="modal-close" onclick="closeSettings()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title">AI Assistant Settings</h2>
            </div>

            <div class="modal-body">
                <div class="settings-section">
                    <h3 class="settings-section-title">Model Preferences</h3>
                    <div class="form-group">
                        <label class="form-label">Default Model</label>
                        <select class="form-input" id="defaultModel">
                            <option value="gpt-5.2">GPT-5.2</option>
                            <option value="gpt-5-mini">GPT-5 mini</option>
                            <option value="gpt-5-nano">GPT-5 nano</option>
                            <option value="gpt-4.1">GPT-4.1</option>
                            <option value="gpt-4.1-mini">GPT-4.1 mini</option>
                            <option value="gpt-4.1-nano">GPT-4.1 nano</option>
                            <option value="gpt-4o" selected>GPT-4o</option>
                            <option value="gpt-4o-mini">GPT-4o mini</option>
                            <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Temperature</label>
                        <input type="range" class="form-range" id="temperature" min="0" max="2" step="0.1" value="0.7">
                        <div class="range-labels">
                            <span>Focused (0)</span>
                            <span id="temperatureValue">0.7</span>
                            <span>Creative (2)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Max Tokens</label>
                        <input type="number" class="form-input" id="maxTokens" min="1" max="4096" value="1000">
                    </div>
                </div>

                <div class="settings-section">
                    <h3 class="settings-section-title">Chat Preferences</h3>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4 class="setting-name">Save Chat History</h4>
                            <p class="setting-description">Automatically save your conversations</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="saveHistory" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4 class="setting-name">Show Timestamps</h4>
                            <p class="setting-description">Display timestamps on messages</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="showTimestamps" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeSettings()">Cancel</button>
                <button class="btn-primary" onclick="saveSettings()">Save Settings</button>
            </div>
        </div>
    </div>
@endsection
