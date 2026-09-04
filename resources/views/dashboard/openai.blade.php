@extends('layouts.app')

@section('title', 'AI Assistant')

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

@push('styles')
<style>
    .openai-container {
        height: calc(100vh - 120px);
        display: flex;
        flex-direction: column;
    }

    .openai-layout {
        display: grid;
        grid-template-columns: 280px 1fr;
        gap: 1.5rem;
        flex: 1;
        min-height: 0;
    }

    /* Sidebar */
    .openai-sidebar {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        overflow-y: auto;
    }

    .sidebar-section {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .sidebar-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0;
    }

    .new-chat-btn {
        width: 100%;
        justify-content: center;
    }

    /* Chat History */
    .chat-history {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .chat-history-item {
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        text-align: left;
        border: none;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .chat-history-item:hover {
        background: var(--border);
    }

    .chat-history-item.active {
        background: var(--accent-light);
        border: 1px solid var(--accent);
    }

    .chat-history-title {
        font-weight: 500;
        margin-bottom: 0.25rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .chat-history-date {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Quick Actions */
    .quick-actions-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .quick-action-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        text-align: left;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .quick-action-btn:hover {
        background: var(--border);
        border-color: var(--accent);
    }

    .quick-action-btn svg {
        width: 18px;
        height: 18px;
        color: var(--text-secondary);
    }

    .settings-btn {
        width: 100%;
        justify-content: center;
        margin-top: auto;
    }

    /* Main Chat Area */
    .openai-main {
        display: flex;
        flex-direction: column;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    /* Model Selector */
    .model-selector {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .model-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
    }

    .model-select:focus {
        outline: none;
        border-color: var(--accent);
    }

    .model-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .model-badge {
        padding: 0.25rem 0.75rem;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .model-desc {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    /* Chat Messages */
    .chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .welcome-message {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 3rem 2rem;
        flex: 1;
    }

    .welcome-icon {
        width: 64px;
        height: 64px;
        background: var(--accent-light);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
    }

    .welcome-icon svg {
        width: 32px;
        height: 32px;
        color: var(--accent);
    }

    .welcome-message h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .welcome-message p {
        font-size: 0.9375rem;
        color: var(--text-secondary);
        margin-bottom: 2rem;
    }

    .suggestions-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        max-width: 600px;
        width: 100%;
    }

    .suggestion-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
        padding: 1.5rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.15s;
        text-align: center;
    }

    .suggestion-card:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .suggestion-card svg {
        width: 32px;
        height: 32px;
        color: var(--accent);
    }

    .suggestion-card span {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    /* Message Bubbles */
    .message {
        display: flex;
        gap: 1rem;
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message.user {
        flex-direction: row-reverse;
    }

    .message-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .message.user .message-avatar {
        background: var(--accent);
        color: white;
    }

    .message.assistant .message-avatar {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .message-content {
        flex: 1;
        min-width: 0;
    }

    .message-bubble {
        padding: 1rem 1.25rem;
        border-radius: 12px;
        line-height: 1.6;
        font-size: 0.9375rem;
        white-space: pre-wrap;
        word-wrap: break-word;
    }

    .message.user .message-bubble {
        background: var(--accent);
        color: white;
        border-bottom-right-radius: 4px;
    }

    .message.assistant .message-bubble {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
        border-bottom-left-radius: 4px;
    }

    .message-time {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.5rem;
    }

    .message-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .message-action-btn {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .message-action-btn:hover {
        background: var(--border);
    }

    .message-action-btn svg {
        width: 14px;
        height: 14px;
        color: var(--text-secondary);
    }

    .typing-indicator {
        display: flex;
        gap: 0.5rem;
        padding: 1rem 1.25rem;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        background: var(--text-muted);
        border-radius: 50%;
        animation: typing 1.4s infinite;
    }

    .typing-dot:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-dot:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typing {
        0%, 60%, 100% {
            transform: translateY(0);
        }
        30% {
            transform: translateY(-10px);
        }
    }

    /* Input Area */
    .chat-input-area {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        background: var(--bg-primary);
    }

    .input-wrapper {
        display: flex;
        align-items: flex-end;
        gap: 0.75rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.75rem;
    }

    .chat-input {
        flex: 1;
        border: none;
        background: transparent;
        color: var(--text-primary);
        font-size: 0.9375rem;
        resize: none;
        max-height: 200px;
        font-family: inherit;
        line-height: 1.5;
    }

    .chat-input:focus {
        outline: none;
    }

    .chat-input::placeholder {
        color: var(--text-muted);
    }

    .send-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--accent);
        border: none;
        border-radius: 8px;
        color: white;
        cursor: pointer;
        transition: all 0.15s;
        flex-shrink: 0;
    }

    .send-btn:hover:not(:disabled) {
        background: var(--accent-hover);
    }

    .send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .send-btn svg {
        width: 18px;
        height: 18px;
    }

    .input-footer {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
        margin-top: 0.75rem;
    }

    .data-source-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .data-source-label {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .data-source-options {
        display: flex;
        gap: 0.25rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 2px;
    }

    .data-source-btn {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.6rem;
        font-size: 0.75rem;
        background: transparent;
        border: none;
        border-radius: 6px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .data-source-btn:hover {
        color: var(--text-primary);
        background: var(--border);
    }

    .data-source-btn.active {
        background: var(--accent);
        color: white;
    }

    .input-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .input-actions {
        display: flex;
        gap: 0.5rem;
    }

    .action-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .action-btn:hover {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .action-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Settings Modal */
    .settings-modal {
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

    .settings-modal.active {
        display: flex;
        opacity: 1;
    }

    .settings-modal-content {
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

    .settings-modal.active .settings-modal-content {
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
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
    }

    .settings-section {
        margin-bottom: 2rem;
    }

    .settings-section:last-child {
        margin-bottom: 0;
    }

    .settings-section-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
    }

    .form-range {
        width: 100%;
        margin: 0.5rem 0;
    }

    .range-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .setting-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border);
    }

    .setting-item:last-child {
        border-bottom: none;
    }

    .setting-info {
        flex: 1;
        min-width: 0;
    }

    .setting-name {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .setting-description {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--border);
        transition: 0.3s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--accent);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(24px);
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

    .btn-primary svg, .btn-secondary svg {
        width: 18px;
        height: 18px;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .openai-layout {
            grid-template-columns: 240px 1fr;
        }
    }

    @media (max-width: 768px) {
        .openai-layout {
            grid-template-columns: 1fr;
        }

        .openai-sidebar {
            display: none;
        }

        .suggestions-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    let chatHistory = [];
    let currentChatId = null;
    let isWaitingForResponse = false;
    let conversationMessages = [];
    let pendingContextType = null;
    let dataSource = 'database';

    function setDataSource(source) {
        dataSource = source;
        document.querySelectorAll('.data-source-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.source === source);
        });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadChatHistory();
        updateModelInfo();
        
        document.getElementById('modelSelect').addEventListener('change', updateModelInfo);
        document.getElementById('temperature').addEventListener('input', function() {
            document.getElementById('temperatureValue').textContent = this.value;
        });

        // Auto-resize textarea
        const chatInput = document.getElementById('chatInput');
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 200) + 'px';
        });
    });

    function updateModelInfo() {
        const model = document.getElementById('modelSelect').value;
        const modelNames = {
            'gpt-5.2': 'GPT-5.2',
            'gpt-5-mini': 'GPT-5 mini',
            'gpt-5-nano': 'GPT-5 nano',
            'gpt-4.1': 'GPT-4.1',
            'gpt-4.1-mini': 'GPT-4.1 mini',
            'gpt-4.1-nano': 'GPT-4.1 nano',
            'gpt-4o': 'GPT-4o',
            'gpt-4o-mini': 'GPT-4o mini',
            'gpt-3.5-turbo': 'GPT-3.5 Turbo'
        };
        const modelDescs = {
            'gpt-5.2': 'Best for coding and agentic tasks',
            'gpt-5-mini': 'Faster, cost-efficient for well-defined tasks',
            'gpt-5-nano': 'Fastest, most cost-efficient',
            'gpt-4.1': 'Smartest non-reasoning model',
            'gpt-4.1-mini': 'Smaller, faster version of GPT-4.1',
            'gpt-4.1-nano': 'Fastest GPT-4.1 tier',
            'gpt-4o': 'Fast, intelligent, flexible',
            'gpt-4o-mini': 'Fast, affordable for focused tasks',
            'gpt-3.5-turbo': 'Legacy, budget-friendly'
        };
        
        document.getElementById('modelBadge').textContent = modelNames[model] || model;
        document.querySelector('.model-desc').textContent = modelDescs[model] || '';
    }

    function handleInputKeydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }

    async function sendMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();

        if (!message || isWaitingForResponse) return;

        // Hide welcome message
        const welcomeEl = document.querySelector('.welcome-message');
        if (welcomeEl) welcomeEl.style.display = 'none';

        // Add user message
        addMessage('user', message);
        conversationMessages.push({ role: 'user', content: message });
        input.value = '';
        input.style.height = 'auto';

        // Show typing indicator
        showTypingIndicator();

        isWaitingForResponse = true;
        document.getElementById('sendBtn').disabled = true;

        const contextTypeToSend = pendingContextType;
        pendingContextType = null;

        try {
            const model = document.getElementById('modelSelect').value;
            const response = await fetch('{{ route("api.openai.chat") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    messages: conversationMessages,
                    model: model,
                    context_type: contextTypeToSend,
                    data_source: dataSource
                })
            });

            const data = await response.json();
            hideTypingIndicator();

            if (response.ok) {
                const content = data.content || '';
                addMessage('assistant', content);
                conversationMessages.push({ role: 'assistant', content });
            } else {
                const errorMsg = data.error || 'Unable to get a response. Please try again.';
                const isQuotaError = /quota|billing|exceeded|rate limit/i.test(errorMsg);
                const isConfigError = /not configured|API key|Invalid API key/i.test(errorMsg);
                const isModelError = /model/i.test(errorMsg) && /(not found|does not exist|invalid|access|unsupported)/i.test(errorMsg);
                let help = '';
                if (isQuotaError) {
                    help = '\n\nCheck your usage and billing at https://platform.openai.com/account/billing';
                } else if (isConfigError) {
                    help = '\n\nPlease ensure your OpenAI API key is configured correctly in Integrations.';
                } else if (isModelError) {
                    help = '\n\nThat model isn\'t available on this account. Switching back to GPT-4o — please try sending your message again.';
                    const modelSelect = document.getElementById('modelSelect');
                    modelSelect.value = 'gpt-4o';
                    updateModelInfo();
                }
                addMessage('assistant', 'Sorry, I encountered an error: ' + errorMsg + help);
                conversationMessages.push({ role: 'assistant', content: errorMsg });
            }
        } catch (error) {
            hideTypingIndicator();
            addMessage('assistant', 'Sorry, I could not connect to the server. Please check your connection and that the OpenAI API key is configured in Integrations.');
            conversationMessages.push({ role: 'assistant', content: 'Connection error.' });
        } finally {
            isWaitingForResponse = false;
            document.getElementById('sendBtn').disabled = false;
        }
    }

    function addMessage(role, content) {
        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        
        const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        const showTimestamps = document.getElementById('showTimestamps')?.checked !== false;
        
        messageDiv.innerHTML = `
            <div class="message-avatar">${role === 'user' ? 'U' : 'AI'}</div>
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(content)}</div>
                ${showTimestamps ? `<div class="message-time">${time}</div>` : ''}
                <div class="message-actions">
                    <button class="message-action-btn" onclick="copyMessage(this)" title="Copy">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function showTypingIndicator() {
        const messagesContainer = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message assistant';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = `
            <div class="message-avatar">AI</div>
            <div class="message-content">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;
        messagesContainer.appendChild(typingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.remove();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function copyMessage(btn) {
        const messageBubble = btn.closest('.message-content').querySelector('.message-bubble');
        const text = messageBubble.textContent;
        navigator.clipboard.writeText(text).then(() => {
            btn.style.background = 'var(--success)';
            btn.style.borderColor = 'var(--success)';
            setTimeout(() => {
                btn.style.background = '';
                btn.style.borderColor = '';
            }, 1000);
        });
    }

    function startNewChat() {
        if (confirm('Start a new chat? Current conversation will be saved.')) {
            const container = document.getElementById('chatMessages');
            container.querySelectorAll('.message').forEach(el => el.remove());
            const welcomeEl = document.querySelector('.welcome-message');
            if (welcomeEl) welcomeEl.style.display = 'flex';
            currentChatId = null;
            conversationMessages = [];
        }
    }

    function clearChat() {
        if (confirm('Clear all messages in this chat?')) {
            const container = document.getElementById('chatMessages');
            container.querySelectorAll('.message').forEach(el => el.remove());
            const welcomeEl = document.querySelector('.welcome-message');
            if (welcomeEl) welcomeEl.style.display = 'flex';
            conversationMessages = [];
        }
    }

    function exportChat() {
        const messages = document.querySelectorAll('.message-bubble');
        let text = 'Chat Export\n' + '='.repeat(50) + '\n\n';
        messages.forEach((msg, index) => {
            const role = msg.closest('.message').classList.contains('user') ? 'User' : 'Assistant';
            text += `${role}: ${msg.textContent}\n\n`;
        });
        
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `chat-export-${Date.now()}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function useSuggestion(text) {
        document.getElementById('chatInput').value = text;
        document.getElementById('chatInput').focus();
    }

    function useQuickAction(action) {
        const prompts = {
            'email': 'Write a professional email to a client about a project update',
            'summary': 'Summarize the following text:',
            'translate': 'Translate the following text to Spanish:',
            'reply': 'Draft a friendly, professional reply to this customer message:'
        };
        useSuggestion(prompts[action]);
    }

    function useSummaryAction(type) {
        pendingContextType = type;
        const prompts = {
            'leads': 'Generate a summary of my leads: status breakdown, top sources, unassigned leads, and recent activity.',
            'shared-inbox': 'Generate a summary of my shared inboxes: unread conversations, status breakdown, and recent activity.',
            'viber': 'Generate a summary of my Viber conversations: unread messages and recent activity.',
            'whatsapp': 'Generate a summary of my WhatsApp conversations: unread messages, messaging window status, and recent activity.',
            'facebook': 'Generate a summary of my Facebook and Instagram conversations: unread messages and recent activity by channel.',
            'sms': 'Generate a summary of my SMS conversations: unread messages and recent activity.',
            'broadcast': 'Generate a summary of my broadcast campaigns: status, delivery rates, and recent campaigns.',
            'knowledge-base': 'Generate a summary of my knowledge base: articles, FAQs, and guides by category.'
        };
        const prompt = prompts[type] || 'Generate a summary.';
        useSuggestion(prompt);
    }

    function loadChatHistory() {
        // In production, load from localStorage or API
        const history = JSON.parse(localStorage.getItem('chatHistory') || '[]');
        chatHistory = history;
        renderChatHistory();
    }

    function renderChatHistory() {
        const container = document.getElementById('chatHistory');
        if (chatHistory.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted); font-size: 0.875rem; text-align: center; padding: 1rem;">No chat history</div>';
            return;
        }
        
        container.innerHTML = chatHistory.map((chat, index) => `
            <button class="chat-history-item ${chat.id === currentChatId ? 'active' : ''}" onclick="loadChat('${chat.id}')">
                <div class="chat-history-title">${chat.title}</div>
                <div class="chat-history-date">${new Date(chat.date).toLocaleDateString()}</div>
            </button>
        `).join('');
    }

    function loadChat(chatId) {
        const chat = chatHistory.find(c => c.id === chatId);
        if (chat) {
            currentChatId = chatId;
            // Load chat messages
            renderChatHistory();
        }
    }

    // Settings
    function openSettings() {
        document.getElementById('settingsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSettings() {
        document.getElementById('settingsModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function saveSettings() {
        const settings = {
            defaultModel: document.getElementById('defaultModel').value,
            temperature: document.getElementById('temperature').value,
            maxTokens: document.getElementById('maxTokens').value,
            saveHistory: document.getElementById('saveHistory').checked,
            showTimestamps: document.getElementById('showTimestamps').checked
        };
        
        localStorage.setItem('openaiSettings', JSON.stringify(settings));
        alert('Settings saved successfully!');
        closeSettings();
    }

    document.getElementById('settingsModal').addEventListener('click', function(e) {
        if (e.target === this) closeSettings();
    });

    // Load saved settings
    const savedSettings = JSON.parse(localStorage.getItem('openaiSettings') || '{}');
    if (savedSettings.defaultModel) {
        document.getElementById('modelSelect').value = savedSettings.defaultModel;
        updateModelInfo();
    }
    if (savedSettings.temperature) {
        document.getElementById('temperature').value = savedSettings.temperature;
        document.getElementById('temperatureValue').textContent = savedSettings.temperature;
    }
</script>
@endpush

