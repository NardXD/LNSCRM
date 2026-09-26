@extends('layouts.app')

@section('title', 'Email Tracking & Sequences')

@push('styles')
    @vite(['resources/css/pages/email-tracking.css'])
@endpush

@push('scripts')
    @vite(['resources/js/pages/email-tracking.js'])
@endpush

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
                    <tbody id="trackingTableBody" aria-busy="true">
                        @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 6])
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
