@extends('layouts.app')

@section('title', 'Email Tracking & Sequences')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Email Tracking & Sequences</h1>
        <p class="page-subtitle">Manage email templates, sequences, and track engagement</p>
    </div>

    <div class="email-tracking-container">
        <!-- Tabs -->
        <div class="email-tabs">
            <button class="email-tab active" data-tab="templates" onclick="switchTab('templates')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                </svg>
                Email Templates
            </button>
            <button class="email-tab" data-tab="sequences" onclick="switchTab('sequences')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
                Sequences
            </button>
            <button class="email-tab" data-tab="tracking" onclick="switchTab('tracking')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
                Email Tracking
            </button>
            <button class="email-tab" data-tab="analytics" onclick="switchTab('analytics')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
                Analytics
            </button>
            <button class="email-tab" data-tab="accounts" onclick="switchTab('accounts')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Email Accounts
            </button>
        </div>

        <!-- Email Templates Tab -->
        <div class="tab-content active" id="templatesTab">
            <div class="tab-header">
                <div class="tab-header-left">
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" class="search-input" placeholder="Search templates..." id="templateSearch">
                    </div>
                    <select class="filter-select" id="templateCategory">
                        <option value="all">All Categories</option>
                        <option value="sales">Sales</option>
                        <option value="follow-up">Follow-up</option>
                        <option value="welcome">Welcome</option>
                        <option value="nurture">Nurture</option>
                    </select>
                </div>
                <button class="btn-primary" onclick="openTemplateModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Template
                </button>
            </div>

            <div class="templates-grid" id="templatesGrid">
                <!-- Templates will be populated by JavaScript -->
            </div>
        </div>

        <!-- Sequences Tab -->
        <div class="tab-content" id="sequencesTab">
            <div class="tab-header">
                <div class="tab-header-left">
                    <div class="search-box">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" class="search-input" placeholder="Search sequences..." id="sequenceSearch">
                    </div>
                </div>
                <button class="btn-primary" onclick="openSequenceModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Sequence
                </button>
            </div>

            <div class="sequences-list" id="sequencesList">
                <!-- Sequences will be populated by JavaScript -->
            </div>
        </div>

        <!-- Email Tracking Tab -->
        <div class="tab-content" id="trackingTab">
            <div class="tracking-stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Total Sent</span>
                        <div class="stat-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">2,458</div>
                    <div class="stat-change">This month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Open Rate</span>
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">68.5%</div>
                    <div class="stat-change positive">+5.2% from last month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Reply Rate</span>
                        <div class="stat-icon purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">24.3%</div>
                    <div class="stat-change positive">+2.1% from last month</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Click Rate</span>
                        <div class="stat-icon orange">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value">12.8%</div>
                    <div class="stat-change positive">+1.5% from last month</div>
                </div>
            </div>

            <div class="tracking-filters">
                <select class="filter-select" id="trackingFilter">
                    <option value="all">All Emails</option>
                    <option value="templates">Templates</option>
                    <option value="sequences">Sequences</option>
                    <option value="manual">Manual</option>
                </select>
                <input type="date" class="filter-select" id="trackingDateFrom" value="{{ date('Y-m-d', strtotime('-30 days')) }}">
                <input type="date" class="filter-select" id="trackingDateTo" value="{{ date('Y-m-d') }}">
            </div>

            <div class="tracking-table-container">
                <table class="data-table" id="trackingTable">
                    <thead>
                        <tr>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Sent</th>
                            <th>Opened</th>
                            <th>Replied</th>
                            <th>Clicked</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="trackingTableBody">
                        <!-- Tracking data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="tracking-cards" id="trackingCards">
                <!-- Cards will be populated by JavaScript -->
            </div>
        </div>

        <!-- Analytics Tab -->
        <div class="tab-content" id="analyticsTab">
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3 class="analytics-title">Email Performance</h3>
                    <div class="analytics-chart" id="performanceChart">
                        <!-- Chart placeholder -->
                        <div class="chart-placeholder">Email performance chart would be displayed here</div>
                    </div>
                </div>

                <div class="analytics-card">
                    <h3 class="analytics-title">Open Rate by Day</h3>
                    <div class="analytics-chart" id="openRateChart">
                        <!-- Chart placeholder -->
                        <div class="chart-placeholder">Open rate chart would be displayed here</div>
                    </div>
                </div>

                <div class="analytics-card">
                    <h3 class="analytics-title">Top Performing Templates</h3>
                    <div class="top-templates-list" id="topTemplatesList">
                        <!-- Top templates will be populated by JavaScript -->
                    </div>
                </div>

                <div class="analytics-card">
                    <h3 class="analytics-title">Sequence Performance</h3>
                    <div class="sequence-performance-list" id="sequencePerformanceList">
                        <!-- Sequence performance will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Accounts Tab -->
        <div class="tab-content" id="accountsTab">
            <div class="accounts-header">
                <div>
                    <h2 class="accounts-title">Email Account Integration</h2>
                    <p class="accounts-subtitle">Connect your email accounts to enable tracking and send emails directly from the platform</p>
                </div>
            </div>

            <!-- Connected Accounts -->
            <div class="connected-accounts-section">
                <h3 class="section-title">Connected Accounts</h3>
                <div class="accounts-grid" id="connectedAccountsGrid">
                    <!-- Connected accounts will be populated by JavaScript -->
                </div>
            </div>

            <!-- Available Integrations -->
            <div class="available-integrations-section">
                <h3 class="section-title">Available Integrations</h3>
                <div class="integrations-grid">
                    <div class="integration-card">
                        <div class="integration-icon google">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                        </div>
                        <div class="integration-info">
                            <h4 class="integration-name">Google (Gmail)</h4>
                            <p class="integration-description">Connect your Gmail account to send emails and track opens, clicks, and replies</p>
                            <div class="integration-features">
                                <span class="feature-badge">Send Emails</span>
                                <span class="feature-badge">Track Opens</span>
                                <span class="feature-badge">Track Replies</span>
                                <span class="feature-badge">Track Clicks</span>
                            </div>
                        </div>
                        <button class="btn-primary integration-connect-btn" id="connectGoogleBtn" onclick="connectGoogleAccount()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            </svg>
                            Connect Google
                        </button>
                    </div>

                    <div class="integration-card">
                        <div class="integration-icon outlook">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M7.5 7.5h9v9h-9z" fill="#0078D4"/>
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="#0078D4"/>
                            </svg>
                        </div>
                        <div class="integration-info">
                            <h4 class="integration-name">Microsoft Outlook</h4>
                            <p class="integration-description">Connect your Outlook account to send emails and track engagement metrics</p>
                            <div class="integration-features">
                                <span class="feature-badge">Send Emails</span>
                                <span class="feature-badge">Track Opens</span>
                                <span class="feature-badge">Track Replies</span>
                                <span class="feature-badge">Track Clicks</span>
                            </div>
                        </div>
                        <button class="btn-primary integration-connect-btn" id="connectOutlookBtn" onclick="connectOutlookAccount()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            </svg>
                            Connect Outlook
                        </button>
                    </div>
                </div>
            </div>

            <!-- Integration Settings -->
            <div class="integration-settings-section">
                <h3 class="section-title">Integration Settings</h3>
                <div class="settings-card">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4 class="setting-name">Auto-track Opens</h4>
                            <p class="setting-description">Automatically track when recipients open your emails</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="autoTrackOpens" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4 class="setting-name">Auto-track Clicks</h4>
                            <p class="setting-description">Automatically track when recipients click links in your emails</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="autoTrackClicks" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4 class="setting-name">Auto-track Replies</h4>
                            <p class="setting-description">Automatically track when recipients reply to your emails</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="autoTrackReplies" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4 class="setting-name">Email Notifications</h4>
                            <p class="setting-description">Receive notifications when emails are opened, clicked, or replied to</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="emailNotifications" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Template Modal -->
    <div class="email-modal" id="templateModal">
        <div class="email-modal-content">
            <button class="modal-close" onclick="closeTemplateModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title" id="templateModalTitle">New Email Template</h2>
            </div>

            <div class="modal-body">
                <form id="templateForm" onsubmit="saveTemplate(event)">
                    <div class="form-group">
                        <label class="form-label">Template Name *</label>
                        <input type="text" class="form-input" id="templateName" required placeholder="e.g., Sales Introduction">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category *</label>
                            <select class="form-input" id="templateCategorySelect" required>
                                <option value="sales">Sales</option>
                                <option value="follow-up">Follow-up</option>
                                <option value="welcome">Welcome</option>
                                <option value="nurture">Nurture</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subject *</label>
                            <input type="text" class="form-input" id="templateSubject" required placeholder="Email subject line">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Body *</label>
                        <div class="email-editor-toolbar">
                            <button type="button" class="editor-btn" data-variable="first_name" title="First Name">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </button>
                            <button type="button" class="editor-btn" data-variable="company" title="Company">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    <polyline points="9 22 9 12 15 12 15 22"/>
                                </svg>
                            </button>
                            <button type="button" class="editor-btn" data-variable="product" title="Product">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="9" y1="3" x2="9" y2="21"/>
                                    <line x1="3" y1="9" x2="21" y2="9"/>
                                </svg>
                            </button>
                        </div>
                        <textarea class="form-input email-body" id="templateBody" rows="12" required placeholder="Write your email template here..."></textarea>
                        <div class="form-hint">Use variables like @{{first_name}}, @{{company}}, @{{product}} for personalization</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="templateTrackOpens">
                            Track email opens
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <input type="checkbox" id="templateTrackClicks">
                            Track link clicks
                        </label>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeTemplateModal()">Cancel</button>
                <button class="btn-secondary" onclick="previewTemplate()">Preview</button>
                <button class="btn-primary" onclick="document.getElementById('templateForm').requestSubmit()">Save Template</button>
            </div>
        </div>
    </div>

    <!-- Sequence Modal -->
    <div class="email-modal" id="sequenceModal">
        <div class="email-modal-content large">
            <button class="modal-close" onclick="closeSequenceModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title" id="sequenceModalTitle">New Email Sequence</h2>
            </div>

            <div class="modal-body">
                <form id="sequenceForm" onsubmit="saveSequence(event)">
                    <div class="form-group">
                        <label class="form-label">Sequence Name *</label>
                        <input type="text" class="form-input" id="sequenceName" required placeholder="e.g., Sales Follow-up Sequence">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-input" id="sequenceDescription" rows="2" placeholder="Describe this sequence"></textarea>
                    </div>

                    <div class="sequence-steps" id="sequenceSteps">
                        <!-- Sequence steps will be populated by JavaScript -->
                    </div>

                    <button type="button" class="btn-secondary" onclick="addSequenceStep()" style="width: 100%; margin-top: 1rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add Step
                    </button>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeSequenceModal()">Cancel</button>
                <button class="btn-primary" onclick="document.getElementById('sequenceForm').requestSubmit()">Save Sequence</button>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .email-tracking-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Tabs */
    .email-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .email-tab {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.25rem;
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

    .email-tab:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .email-tab.active {
        background: var(--accent);
        color: white;
    }

    .email-tab svg {
        width: 18px;
        height: 18px;
    }

    /* Tab Content */
    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .tab-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .tab-header-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
        min-width: 0;
    }

    .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
    }

    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        width: 18px;
        height: 18px;
        pointer-events: none;
    }

    .search-input {
        width: 100%;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .filter-select {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
        min-width: 150px;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
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

    /* Templates Grid */
    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .template-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .template-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .template-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .template-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .template-category {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 100px;
        background: var(--bg-primary);
        color: var(--text-secondary);
    }

    .template-subject {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 0.75rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .template-preview {
        font-size: 0.8125rem;
        color: var(--text-muted);
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .template-actions {
        display: flex;
        gap: 0.5rem;
    }

    .template-action-btn {
        flex: 1;
        padding: 0.5rem;
        border: 1px solid var(--border);
        background: var(--bg-primary);
        border-radius: 6px;
        font-size: 0.8125rem;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .template-action-btn:hover {
        background: var(--border);
    }

    /* Sequences List */
    .sequences-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .sequence-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .sequence-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .sequence-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .sequence-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .sequence-stats {
        display: flex;
        gap: 1.5rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .sequence-stat {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .sequence-steps-preview {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .sequence-step-preview {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .step-number {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--accent);
        color: white;
        border-radius: 50%;
        font-size: 0.875rem;
        font-weight: 600;
        flex-shrink: 0;
    }

    .step-info {
        flex: 1;
        min-width: 0;
    }

    .step-delay {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Tracking Stats */
    .tracking-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .stat-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .stat-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .stat-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .stat-icon svg {
        width: 20px;
        height: 20px;
    }

    .stat-value {
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .stat-change {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .stat-change.positive {
        color: #059669;
    }

    /* Tracking Filters */
    .tracking-filters {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    /* Tracking Table */
    .tracking-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table thead {
        background: var(--bg-primary);
    }

    .data-table th {
        padding: 0.875rem 1rem;
        text-align: left;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }

    .data-table td {
        padding: 1rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border);
    }

    .data-table tbody tr:hover {
        background: var(--bg-primary);
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.opened {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.replied {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.clicked {
        background: #ede9fe;
        color: #7c3aed;
    }

    .status-badge.pending {
        background: #fef3c7;
        color: #d97706;
    }

    /* Tracking Cards (Mobile) */
    .tracking-cards {
        display: none;
        flex-direction: column;
        gap: 1rem;
    }

    .tracking-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .tracking-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .tracking-card-title {
        font-weight: 600;
        color: var(--text-primary);
    }

    .tracking-card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .tracking-card-detail {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .tracking-card-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .tracking-card-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    /* Analytics */
    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
    }

    .analytics-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .analytics-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .analytics-chart {
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chart-placeholder {
        color: var(--text-muted);
        font-size: 0.875rem;
    }

    .top-templates-list,
    .sequence-performance-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .top-template-item,
    .sequence-performance-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .top-template-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .top-template-stats {
        display: flex;
        gap: 1rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    /* Email Modal */
    .email-modal {
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

    .email-modal.active {
        display: flex;
        opacity: 1;
    }

    .email-modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 700px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .email-modal-content.large {
        max-width: 900px;
    }

    .email-modal.active .email-modal-content {
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

    /* Form Styles */
    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
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

    .form-input[type="checkbox"] {
        width: auto;
        margin-right: 0.5rem;
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
    }

    .email-body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        resize: vertical;
    }

    /* Email Editor Toolbar */
    .email-editor-toolbar {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        padding: 0.5rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .editor-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .editor-btn:hover {
        background: var(--border);
        color: var(--text-primary);
    }

    .editor-btn svg {
        width: 18px;
        height: 18px;
    }

    /* Sequence Steps */
    .sequence-steps {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .sequence-step {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .step-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .step-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .step-remove {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fee2e2;
        border: none;
        border-radius: 6px;
        color: #dc2626;
        cursor: pointer;
        transition: all 0.15s;
    }

    .step-remove:hover {
        background: #fecaca;
    }

    .step-remove svg {
        width: 16px;
        height: 16px;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    /* Email Accounts */
    .accounts-header {
        margin-bottom: 2rem;
    }

    .accounts-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .accounts-subtitle {
        font-size: 0.9375rem;
        color: var(--text-secondary);
    }

    .connected-accounts-section,
    .available-integrations-section,
    .integration-settings-section {
        margin-bottom: 2rem;
    }

    .section-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .accounts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .account-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .account-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .account-icon.google {
        background: #fef3c7;
    }

    .account-icon.outlook {
        background: #dbeafe;
    }

    .account-icon svg {
        width: 28px;
        height: 28px;
    }

    .account-info {
        flex: 1;
        min-width: 0;
    }

    .account-email {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .account-status {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--success);
    }

    .account-actions {
        display: flex;
        gap: 0.5rem;
    }

    .account-action-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .account-action-btn:hover {
        background: var(--border);
        color: var(--text-primary);
    }

    .account-action-btn.danger:hover {
        background: #fee2e2;
        color: #dc2626;
        border-color: #fee2e2;
    }

    .account-action-btn svg {
        width: 18px;
        height: 18px;
    }

    .integrations-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 1.5rem;
    }

    .integration-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .integration-icon {
        width: 64px;
        height: 64px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .integration-icon.google {
        background: #fef3c7;
    }

    .integration-icon.outlook {
        background: #dbeafe;
    }

    .integration-icon svg {
        width: 40px;
        height: 40px;
    }

    .integration-info {
        flex: 1;
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
        margin-bottom: 1rem;
        line-height: 1.5;
    }

    .integration-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .feature-badge {
        padding: 0.25rem 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 100px;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .integration-connect-btn {
        width: 100%;
        justify-content: center;
    }

    .integration-connect-btn.connected {
        background: var(--success);
    }

    .integration-connect-btn.connected:hover {
        background: #059669;
    }

    .settings-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
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

    /* Responsive */
    @media (min-width: 769px) {
        .tracking-table-container {
            display: block;
        }
        .tracking-cards {
            display: none !important;
        }
    }

    @media (max-width: 1024px) {
        .analytics-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .email-tabs {
            flex-wrap: nowrap;
        }

        .tab-header {
            flex-direction: column;
            align-items: stretch;
        }

        .tab-header-left {
            width: 100%;
        }

        .templates-grid {
            grid-template-columns: 1fr;
        }

        .tracking-table-container {
            display: none !important;
        }
        .tracking-cards {
            display: flex !important;
        }

        .tracking-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .email-modal-content {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 0;
        }

        .integrations-grid {
            grid-template-columns: 1fr;
        }

        .accounts-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .tracking-stats-grid {
            grid-template-columns: 1fr;
        }

        .tracking-card-details {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
@verbatim
<script>
    // Data
    let templates = [
        { id: 1, name: 'Sales Introduction', category: 'sales', subject: 'Introduction to Our Services', body: 'Hi {{first_name}},\n\nI hope this email finds you well. I wanted to introduce you to our company and the services we offer...', trackOpens: true, trackClicks: true },
        { id: 2, name: 'Follow-up Email', category: 'follow-up', subject: 'Following up on our conversation', body: 'Hi {{first_name}},\n\nI wanted to follow up on our recent conversation about {{product}}...', trackOpens: true, trackClicks: true },
        { id: 3, name: 'Welcome Email', category: 'welcome', subject: 'Welcome to {{company}}!', body: 'Hi {{first_name}},\n\nWelcome to {{company}}! We\'re excited to have you on board...', trackOpens: true, trackClicks: false },
        { id: 4, name: 'Product Nurture', category: 'nurture', subject: 'Learn more about {{product}}', body: 'Hi {{first_name}},\n\nI thought you might be interested in learning more about {{product}}...', trackOpens: true, trackClicks: true }
    ];

    let sequences = [
        { id: 1, name: 'Sales Follow-up Sequence', description: '5-step follow-up sequence for sales', steps: [
            { templateId: 1, delay: 0, delayUnit: 'days' },
            { templateId: 2, delay: 3, delayUnit: 'days' },
            { templateId: 2, delay: 7, delayUnit: 'days' },
            { templateId: 4, delay: 14, delayUnit: 'days' }
        ], active: true, sent: 245, opened: 168, replied: 42 }
    ];

    let trackingData = [
        { id: 1, recipient: 'john@example.com', subject: 'Introduction to Our Services', type: 'Template', sent: '2025-01-15 10:00', opened: '2025-01-15 14:30', replied: '2025-01-16 09:00', clicked: true, status: 'replied' },
        { id: 2, recipient: 'sarah@example.com', subject: 'Following up on our conversation', type: 'Sequence', sent: '2025-01-15 11:00', opened: '2025-01-15 15:20', replied: null, clicked: true, status: 'opened' },
        { id: 3, recipient: 'mike@example.com', subject: 'Welcome to Our Company!', type: 'Template', sent: '2025-01-16 09:00', opened: null, replied: null, clicked: false, status: 'pending' },
        { id: 4, recipient: 'lisa@example.com', subject: 'Learn more about our product', type: 'Sequence', sent: '2025-01-16 10:30', opened: '2025-01-16 11:15', replied: '2025-01-16 14:00', clicked: true, status: 'replied' }
    ];

    let currentEditingTemplate = null;
    let currentEditingSequence = null;
    let sequenceStepCounter = 0;

    // Tab Switching
    function switchTab(tab) {
        document.querySelectorAll('.email-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById(`${tab}Tab`).classList.add('active');

        if (tab === 'templates') {
            renderTemplates();
        } else if (tab === 'sequences') {
            renderSequences();
        } else if (tab === 'tracking') {
            renderTracking();
        } else if (tab === 'analytics') {
            renderAnalytics();
        } else if (tab === 'accounts') {
            renderConnectedAccounts();
            updateConnectButtons();
        }
    }

    // Templates
    function renderTemplates() {
        const grid = document.getElementById('templatesGrid');
        grid.innerHTML = templates.map(template => `
            <div class="template-card" onclick="editTemplate(${template.id})">
                <div class="template-header">
                    <div>
                        <div class="template-title">${template.name}</div>
                        <span class="template-category">${template.category}</span>
                    </div>
                </div>
                <div class="template-subject">${template.subject}</div>
                <div class="template-preview">${template.body.substring(0, 150)}...</div>
                <div class="template-actions" onclick="event.stopPropagation()">
                    <button class="template-action-btn" onclick="useTemplate(${template.id})">Use</button>
                    <button class="template-action-btn" onclick="editTemplate(${template.id})">Edit</button>
                    <button class="template-action-btn" onclick="deleteTemplate(${template.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    function openTemplateModal() {
        currentEditingTemplate = null;
        document.getElementById('templateModalTitle').textContent = 'New Email Template';
        document.getElementById('templateForm').reset();
        document.getElementById('templateModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeTemplateModal() {
        document.getElementById('templateModal').classList.remove('active');
        document.body.style.overflow = '';
        currentEditingTemplate = null;
    }

    function editTemplate(id) {
        const template = templates.find(t => t.id === id);
        if (!template) return;

        currentEditingTemplate = template;
        document.getElementById('templateModalTitle').textContent = 'Edit Email Template';
        document.getElementById('templateName').value = template.name;
        document.getElementById('templateCategorySelect').value = template.category;
        document.getElementById('templateSubject').value = template.subject;
        document.getElementById('templateBody').value = template.body;
        document.getElementById('templateTrackOpens').checked = template.trackOpens;
        document.getElementById('templateTrackClicks').checked = template.trackClicks;

        document.getElementById('templateModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function saveTemplate(e) {
        e.preventDefault();

        const template = {
            name: document.getElementById('templateName').value,
            category: document.getElementById('templateCategorySelect').value,
            subject: document.getElementById('templateSubject').value,
            body: document.getElementById('templateBody').value,
            trackOpens: document.getElementById('templateTrackOpens').checked,
            trackClicks: document.getElementById('templateTrackClicks').checked
        };

        if (currentEditingTemplate) {
            const index = templates.findIndex(t => t.id === currentEditingTemplate.id);
            templates[index] = { ...currentEditingTemplate, ...template };
        } else {
            template.id = Date.now();
            templates.push(template);
        }

        closeTemplateModal();
        renderTemplates();
    }

    function deleteTemplate(id) {
        if (confirm('Are you sure you want to delete this template?')) {
            templates = templates.filter(t => t.id !== id);
            renderTemplates();
        }
    }

    function useTemplate(id) {
        alert(`Template ${id} would be used to send an email`);
    }

    function insertVariable(variable) {
        const textarea = document.getElementById('templateBody');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const variableText = '{{' + variable + '}}';
        textarea.value = text.substring(0, start) + variableText + text.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + variableText.length, start + variableText.length);
    }

    // Handle variable button clicks
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.editor-btn[data-variable]').forEach(btn => {
            btn.addEventListener('click', function() {
                const variable = this.getAttribute('data-variable');
                insertVariable(variable);
            });
        });
    });

    function previewTemplate() {
        alert('Template preview would be displayed here');
    }

    // Sequences
    function renderSequences() {
        const list = document.getElementById('sequencesList');
        list.innerHTML = sequences.map(sequence => {
            const stepsHtml = sequence.steps.map((step, index) => {
                const template = templates.find(t => t.id === step.templateId);
                return `
                    <div class="sequence-step-preview">
                        <div class="step-number">${index + 1}</div>
                        <div class="step-info">
                            <div class="step-title">${template ? template.name : 'Template'}</div>
                            <div class="step-delay">Send after ${step.delay} ${step.delayUnit}</div>
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="sequence-card" onclick="editSequence(${sequence.id})">
                    <div class="sequence-header">
                        <div class="sequence-title">${sequence.name}</div>
                        <div class="sequence-stats">
                            <div class="sequence-stat">
                                <span>Sent: ${sequence.sent}</span>
                            </div>
                            <div class="sequence-stat">
                                <span>Opened: ${sequence.opened}</span>
                            </div>
                            <div class="sequence-stat">
                                <span>Replied: ${sequence.replied}</span>
                            </div>
                        </div>
                    </div>
                    <div class="sequence-steps-preview">
                        ${stepsHtml}
                    </div>
                </div>
            `;
        }).join('');
    }

    function openSequenceModal() {
        currentEditingSequence = null;
        sequenceStepCounter = 0;
        document.getElementById('sequenceModalTitle').textContent = 'New Email Sequence';
        document.getElementById('sequenceForm').reset();
        document.getElementById('sequenceSteps').innerHTML = '';
        document.getElementById('sequenceModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSequenceModal() {
        document.getElementById('sequenceModal').classList.remove('active');
        document.body.style.overflow = '';
        currentEditingSequence = null;
    }

    function addSequenceStep() {
        sequenceStepCounter++;
        const stepsContainer = document.getElementById('sequenceSteps');
        const stepHtml = `
            <div class="sequence-step" data-step-id="${sequenceStepCounter}">
                <div class="step-header">
                    <div class="step-title">Step ${sequenceStepCounter}</div>
                    <button type="button" class="step-remove" onclick="removeSequenceStep(${sequenceStepCounter})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="form-group">
                    <label class="form-label">Template *</label>
                    <select class="form-input step-template" required>
                        <option value="">Select a template</option>
                        ${templates.map(t => `<option value="${t.id}">${t.name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Delay *</label>
                        <input type="number" class="form-input step-delay" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delay Unit *</label>
                        <select class="form-input step-delay-unit" required>
                            <option value="hours">Hours</option>
                            <option value="days" selected>Days</option>
                            <option value="weeks">Weeks</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
        stepsContainer.insertAdjacentHTML('beforeend', stepHtml);
    }

    function removeSequenceStep(stepId) {
        const step = document.querySelector(`[data-step-id="${stepId}"]`);
        if (step) step.remove();
    }

    function editSequence(id) {
        const sequence = sequences.find(s => s.id === id);
        if (!sequence) return;

        currentEditingSequence = sequence;
        document.getElementById('sequenceModalTitle').textContent = 'Edit Email Sequence';
        document.getElementById('sequenceName').value = sequence.name;
        document.getElementById('sequenceDescription').value = sequence.description || '';

        const stepsContainer = document.getElementById('sequenceSteps');
        stepsContainer.innerHTML = '';
        sequence.steps.forEach((step, index) => {
            sequenceStepCounter = index + 1;
            const stepHtml = `
                <div class="sequence-step" data-step-id="${sequenceStepCounter}">
                    <div class="step-header">
                        <div class="step-title">Step ${sequenceStepCounter}</div>
                        <button type="button" class="step-remove" onclick="removeSequenceStep(${sequenceStepCounter})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Template *</label>
                        <select class="form-input step-template" required>
                            <option value="">Select a template</option>
                            ${templates.map(t => `<option value="${t.id}" ${t.id === step.templateId ? 'selected' : ''}>${t.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Delay *</label>
                            <input type="number" class="form-input step-delay" min="0" value="${step.delay}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Delay Unit *</label>
                            <select class="form-input step-delay-unit" required>
                                <option value="hours" ${step.delayUnit === 'hours' ? 'selected' : ''}>Hours</option>
                                <option value="days" ${step.delayUnit === 'days' ? 'selected' : ''}>Days</option>
                                <option value="weeks" ${step.delayUnit === 'weeks' ? 'selected' : ''}>Weeks</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            stepsContainer.insertAdjacentHTML('beforeend', stepHtml);
        });

        document.getElementById('sequenceModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function saveSequence(e) {
        e.preventDefault();

        const steps = Array.from(document.querySelectorAll('.sequence-step')).map(step => ({
            templateId: parseInt(step.querySelector('.step-template').value),
            delay: parseInt(step.querySelector('.step-delay').value),
            delayUnit: step.querySelector('.step-delay-unit').value
        }));

        const sequence = {
            name: document.getElementById('sequenceName').value,
            description: document.getElementById('sequenceDescription').value,
            steps: steps
        };

        if (currentEditingSequence) {
            const index = sequences.findIndex(s => s.id === currentEditingSequence.id);
            sequences[index] = { ...currentEditingSequence, ...sequence };
        } else {
            sequence.id = Date.now();
            sequence.active = true;
            sequence.sent = 0;
            sequence.opened = 0;
            sequence.replied = 0;
            sequences.push(sequence);
        }

        closeSequenceModal();
        renderSequences();
    }

    // Tracking
    function renderTracking() {
        const tbody = document.getElementById('trackingTableBody');
        tbody.innerHTML = trackingData.map(item => `
            <tr>
                <td>${item.recipient}</td>
                <td>${item.subject}</td>
                <td>${item.type}</td>
                <td>${item.sent}</td>
                <td>${item.opened || '-'}</td>
                <td>${item.replied || '-'}</td>
                <td>${item.clicked ? 'Yes' : 'No'}</td>
                <td><span class="status-badge ${item.status}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span></td>
            </tr>
        `).join('');

        // Mobile cards
        const cards = document.getElementById('trackingCards');
        cards.innerHTML = trackingData.map(item => `
            <div class="tracking-card">
                <div class="tracking-card-header">
                    <div class="tracking-card-title">${item.subject}</div>
                    <span class="status-badge ${item.status}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span>
                </div>
                <div class="tracking-card-details">
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Recipient</span>
                        <span class="tracking-card-value">${item.recipient}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Type</span>
                        <span class="tracking-card-value">${item.type}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Sent</span>
                        <span class="tracking-card-value">${item.sent}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Opened</span>
                        <span class="tracking-card-value">${item.opened || '-'}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Replied</span>
                        <span class="tracking-card-value">${item.replied || '-'}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Clicked</span>
                        <span class="tracking-card-value">${item.clicked ? 'Yes' : 'No'}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Analytics
    function renderAnalytics() {
        // Top templates
        const topTemplates = templates.slice(0, 5);
        const topTemplatesList = document.getElementById('topTemplatesList');
        topTemplatesList.innerHTML = topTemplates.map((template, index) => `
            <div class="top-template-item">
                <div class="top-template-name">${template.name}</div>
                <div class="top-template-stats">
                    <span>Opens: 68%</span>
                    <span>Replies: 24%</span>
                </div>
            </div>
        `).join('');

        // Sequence performance
        const sequencePerformanceList = document.getElementById('sequencePerformanceList');
        sequencePerformanceList.innerHTML = sequences.map(sequence => `
            <div class="sequence-performance-item">
                <div class="top-template-name">${sequence.name}</div>
                <div class="top-template-stats">
                    <span>Sent: ${sequence.sent}</span>
                    <span>Opened: ${sequence.opened}</span>
                    <span>Replied: ${sequence.replied}</span>
                </div>
            </div>
        `).join('');
    }

    // Close modals on outside click
    document.getElementById('templateModal').addEventListener('click', function(e) {
        if (e.target === this) closeTemplateModal();
    });

    document.getElementById('sequenceModal').addEventListener('click', function(e) {
        if (e.target === this) closeSequenceModal();
    });

    // Email Accounts
    let connectedAccounts = [];

    function renderConnectedAccounts() {
        const grid = document.getElementById('connectedAccountsGrid');
        if (connectedAccounts.length === 0) {
            grid.innerHTML = '<div style="color: var(--text-muted); font-size: 0.875rem; grid-column: 1 / -1;">No email accounts connected yet. Connect an account below to start tracking emails.</div>';
            return;
        }

        grid.innerHTML = connectedAccounts.map(account => `
            <div class="account-card">
                <div class="account-icon ${account.type}">
                    ${account.type === 'google' ? `
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    ` : `
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.5 7.5h9v9h-9z" fill="#0078D4"/>
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="#0078D4"/>
                        </svg>
                    `}
                </div>
                <div class="account-info">
                    <div class="account-email">${account.email}</div>
                    <div class="account-status">
                        <span class="status-dot"></span>
                        <span>Connected • ${account.type === 'google' ? 'Gmail' : 'Outlook'}</span>
                    </div>
                </div>
                <div class="account-actions">
                    <button class="account-action-btn" onclick="testConnection('${account.id}')" title="Test Connection">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </button>
                    <button class="account-action-btn danger" onclick="disconnectAccount('${account.id}')" title="Disconnect">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');
    }

    function connectGoogleAccount() {
        // In a real application, this would redirect to Google OAuth
        if (confirm('This will redirect you to Google to authorize email access. Continue?')) {
            // Simulate connection
            const account = {
                id: 'google_' + Date.now(),
                type: 'google',
                email: 'user@gmail.com',
                connectedAt: new Date().toISOString()
            };
            connectedAccounts.push(account);
            renderConnectedAccounts();
            updateConnectButtons();
            alert('Google account connected successfully!');
        }
    }

    function connectOutlookAccount() {
        // In a real application, this would redirect to Microsoft OAuth
        if (confirm('This will redirect you to Microsoft to authorize email access. Continue?')) {
            // Simulate connection
            const account = {
                id: 'outlook_' + Date.now(),
                type: 'outlook',
                email: 'user@outlook.com',
                connectedAt: new Date().toISOString()
            };
            connectedAccounts.push(account);
            renderConnectedAccounts();
            updateConnectButtons();
            alert('Outlook account connected successfully!');
        }
    }

    function disconnectAccount(accountId) {
        if (confirm('Are you sure you want to disconnect this email account? Email tracking will stop for this account.')) {
            connectedAccounts = connectedAccounts.filter(acc => acc.id !== accountId);
            renderConnectedAccounts();
            updateConnectButtons();
            alert('Account disconnected successfully.');
        }
    }

    function testConnection(accountId) {
        const account = connectedAccounts.find(acc => acc.id === accountId);
        if (account) {
            alert(`Testing connection to ${account.email}...\n\nConnection successful!`);
        }
    }

    function updateConnectButtons() {
        const hasGoogle = connectedAccounts.some(acc => acc.type === 'google');
        const hasOutlook = connectedAccounts.some(acc => acc.type === 'outlook');

        const googleBtn = document.getElementById('connectGoogleBtn');
        const outlookBtn = document.getElementById('connectOutlookBtn');

        if (hasGoogle) {
            googleBtn.classList.add('connected');
            googleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Connected';
            googleBtn.onclick = () => alert('Google account is already connected');
        } else {
            googleBtn.classList.remove('connected');
            googleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>Connect Google';
            googleBtn.onclick = connectGoogleAccount;
        }

        if (hasOutlook) {
            outlookBtn.classList.add('connected');
            outlookBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Connected';
            outlookBtn.onclick = () => alert('Outlook account is already connected');
        } else {
            outlookBtn.classList.remove('connected');
            outlookBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>Connect Outlook';
            outlookBtn.onclick = connectOutlookAccount;
        }
    }

    // Initialize
    renderTemplates();
    renderConnectedAccounts();
    updateConnectButtons();
</script>
@endverbatim
@endpush

