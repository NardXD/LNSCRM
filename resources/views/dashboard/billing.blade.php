@extends('layouts.app')

@section('title', 'Billing & Payments')

@push('styles')
    @vite(['resources/css/pages/billing.css'])
@endpush

@push('scripts')
<script>
    window.__billingConfig = {
        invoiceApi: @json(url('/api/billing-invoices')),
        paymentTrackingUrl: @json(route('api.billing-invoices.payment-tracking')),
        stripeConnected: @json($stripeConnected ?? false),
        userPermissions: @json(auth()->user()?->getPermissionSlugs() ?? []),
        wiseDefaultLink: @json($wiseDefaultLink ?? ''),
    };
</script>
    @vite(['resources/js/pages/billing.js'])
@endpush

@section('content')
    <div class="page-header">
        <h1 class="page-title">Billing & Payments</h1>
        <p class="page-subtitle">Manage invoices, track payments, and handle subscriptions</p>
    </div>

    <div class="billing-container">
        <!-- Tabs Navigation -->
        <div class="billing-tabs">
            <button class="tab-btn active" data-tab="invoices">Invoices</button>
            <button class="tab-btn" data-tab="payment-tracking">Payment Tracking</button>
            <button class="tab-btn" data-tab="subscriptions">Subscriptions</button>
            <button class="tab-btn" data-tab="dashboard">Payment Dashboard</button>
        </div>

        <!-- Invoices Tab -->
        <div class="tab-content active" id="invoicesTab">
            <div class="section-header">
                <h2 class="section-title">Client Invoicing</h2>
                <div class="section-actions">
                    <input type="month" class="date-input" id="invoiceMonthFilter" value="{{ date('Y-m') }}">
                    <select class="filter-select" id="invoiceStatusFilter">
                        <option value="all">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                    <button class="btn-primary" onclick="createInvoice()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Invoice
                    </button>
                </div>
            </div>

            <!-- Invoice Stats -->
            <div class="invoice-stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Total Invoices</span>
                        <div class="stat-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statTotalInvoices">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Pending Payment</span>
                        <div class="stat-icon orange">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statPendingAmount">$0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Paid This Month</span>
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statPaidThisMonth">$0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Overdue</span>
                        <div class="stat-icon red">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="statOverdueAmount">$0</div>
                </div>
            </div>

            <!-- Wise Default Payment Link Setting -->
            <div class="wise-settings-bar">
                <div class="wise-settings-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                    </svg>
                    <span>Default Wise payment link</span>
                </div>
                <input type="url" class="form-input" id="wiseDefaultLinkInput" placeholder="https://wise.com/pay/... (pre-fills new invoices)" value="{{ $wiseDefaultLink ?? '' }}">
                <button type="button" class="btn-secondary" onclick="saveWiseDefaultLink()" id="saveWiseDefaultLinkBtn">Save</button>

                <div class="wise-reconcile" id="wiseReconcileControls" style="display: none;">
                    <span class="wise-reconcile-status" id="wiseReconcileStatus" title="Auto-mark invoices Paid when a matching Wise payment arrives (matched by invoice number reference)">
                        <span class="wise-reconcile-dot" id="wiseReconcileDot"></span>
                        <span id="wiseReconcileLabel">Auto-reconciliation: checking…</span>
                    </span>
                    <button type="button" class="btn-secondary btn-sm" id="wiseReconcileToggleBtn" onclick="toggleWiseReconciliation()" style="display: none;"></button>
                    <button type="button" class="btn-secondary btn-sm" onclick="openWiseIncomingModal()">Incoming Payments</button>
                </div>
            </div>

            <!-- Bulk Actions Toolbar -->
            <div class="bulk-actions-bar" id="invoiceBulkBar" style="display: none;">
                <span class="bulk-actions-count" id="invoiceBulkCount">0 selected</span>
                <div class="bulk-actions-buttons">
                    <button class="btn-secondary" id="bulkSendEmailBtn" onclick="openSendInvoiceEmailModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        Send Email
                    </button>
                    <button class="btn-secondary" id="bulkStripeLinkBtn" onclick="bulkGenerateStripeLinks()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                        </svg>
                        Generate Stripe Link
                    </button>
                    <button class="bulk-actions-clear" onclick="clearInvoiceSelection()" title="Clear selection">Clear</button>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="billing-table-section">
                <div class="table-container">
                    <table class="data-table" id="invoicesTable">
                        <thead>
                            <tr>
                                <th class="checkbox-col"><input type="checkbox" id="invoiceSelectAll" onchange="toggleSelectAllInvoices(this)" title="Select all"></th>
                                <th>Invoice #</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody" aria-busy="true">
                            @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 8])
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="billing-cards" id="invoicesCards">
                    <!-- Cards will be populated by JavaScript -->
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    <div class="pagination-info">
                        <span id="invoicesPaginationInfo">Showing 1 to 6 of 6 results</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="invoicesPrevBtn" disabled>Previous</button>
                        <div class="pagination-numbers" id="invoicesPaginationNumbers"></div>
                        <button class="pagination-btn" id="invoicesNextBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Tracking Tab -->
        <div class="tab-content" id="paymentTrackingTab">
            <div class="section-header">
                <h2 class="section-title">Payment Tracking</h2>
                <div class="section-actions">
                    <input type="month" class="date-input" id="paymentDateFilter" value="{{ date('Y-m') }}">
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="payment-summary-grid">
                <div class="summary-card">
                    <div class="summary-header">
                        <span class="summary-label">Total Received</span>
                        <div class="summary-icon green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="summary-value" id="paymentTotalReceived">$0</div>
                    <div class="summary-change" id="paymentTotalReceivedSub">Paid invoices (selected month)</div>
                </div>

                <div class="summary-card">
                    <div class="summary-header">
                        <span class="summary-label">Pending Payments</span>
                        <div class="summary-icon orange">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="summary-value" id="paymentPendingAmount">$0</div>
                    <div class="summary-change" id="paymentPendingCount">0 invoices</div>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="billing-table-section">
                <div class="table-container">
                    <table class="data-table" id="paymentsTable">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Invoice #</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="paymentsTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="billing-cards" id="paymentsCards">
                    <!-- Cards will be populated by JavaScript -->
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    <div class="pagination-info">
                        <span id="paymentsPaginationInfo">Loading...</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="paymentsPrevBtn" disabled>Previous</button>
                        <div class="pagination-numbers" id="paymentsPaginationNumbers"></div>
                        <button class="pagination-btn" id="paymentsNextBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Subscriptions Tab -->
        <div class="tab-content" id="subscriptionsTab">
            <div class="section-header">
                <h2 class="section-title">Subscription Billing</h2>
                <button class="btn-primary" onclick="createSubscription()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Subscription
                </button>
            </div>

            <!-- Subscription Stats -->
            <div class="subscription-stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Active Subscriptions</span>
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 13a10 10 0 0 1 14-8M5 13a10 10 0 0 0 7 7M5 13l4-4m10 0a10 10 0 0 1-14 8m14-8l-4-4"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="subscriptionStatActive">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Monthly Recurring Revenue</span>
                        <div class="stat-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="subscriptionStatMRR">$0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Trial Periods</span>
                        <div class="stat-icon orange">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="subscriptionStatTrials">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <span class="stat-label">Cancelled This Month</span>
                        <div class="stat-icon red">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" id="subscriptionStatCancelled">0</div>
                </div>
            </div>

            <!-- Subscription Status Tabs -->
            <div class="subscription-status-tabs">
                <button class="sub-tab-btn active" data-subscription-status="all">All</button>
                <button class="sub-tab-btn" data-subscription-status="active">Active</button>
                <button class="sub-tab-btn" data-subscription-status="canceled">Canceled</button>
            </div>

            <!-- Subscriptions Table -->
            <div class="billing-table-section">
                <div class="table-container">
                    <table class="data-table" id="subscriptionsTable">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Plan</th>
                                <th>Billing Cycle</th>
                                <th>Amount</th>
                                <th>Start Date</th>
                                <th>Next Billing</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subscriptionsTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="billing-cards" id="subscriptionsCards">
                    <!-- Cards will be populated by JavaScript -->
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    <div class="pagination-info">
                        <span id="subscriptionsPaginationInfo">Showing 1 to 5 of 5 results</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="subscriptionsPrevBtn" disabled>Previous</button>
                        <div class="pagination-numbers" id="subscriptionsPaginationNumbers"></div>
                        <button class="pagination-btn" id="subscriptionsNextBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Dashboard Tab -->
        <div class="tab-content" id="dashboardTab">
            <div class="section-header">
                <h2 class="section-title">Payment Status Dashboard</h2>
                <div class="section-actions">
                    <select class="filter-select" id="dashboardPeriodFilter">
                        <option value="this-month">This Month</option>
                        <option value="last-month">Last Month</option>
                        <option value="this-quarter">This Quarter</option>
                        <option value="this-year">This Year</option>
                    </select>
                </div>
            </div>

            <div class="dashboard-source-tabs">
                <button type="button" class="dashboard-source-btn active" data-source="stripe" onclick="switchDashboardSource('stripe')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    Stripe
                </button>
                <button type="button" class="dashboard-source-btn" data-source="wise" onclick="switchDashboardSource('wise')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    Wise
                </button>
            </div>

            <!-- Dashboard Overview -->
            <div class="dashboard-overview-grid">
                <div class="overview-card large">
                    <div class="overview-header">
                        <h3 class="overview-title">Revenue Overview</h3>
                        <span class="overview-period" id="dashboardPeriodLabel">—</span>
                    </div>
                    <div class="overview-value" id="dashboardRevenueTotal">$0</div>
                    <div class="overview-chart">
                        <div class="chart-bars" id="dashboardChartBars">
                            <!-- Chart bars populated by JS -->
                        </div>
                    </div>
                </div>

                <div class="overview-card">
                    <div class="overview-header">
                        <h3 class="overview-title">Payment Methods</h3>
                    </div>
                    <div class="payment-methods-list" id="dashboardPaymentMethods">
                        <div class="payment-method-item empty-state" id="dashboardPaymentMethodsEmpty">No payment data for this period</div>
                    </div>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="status-breakdown-grid">
                <div class="breakdown-card">
                    <div class="breakdown-header">
                        <span class="breakdown-label">Paid</span>
                        <span class="breakdown-value" id="dashboardPaidValue">$0</span>
                    </div>
                    <div class="breakdown-progress">
                        <div class="breakdown-bar">
                            <div class="breakdown-fill green" id="dashboardPaidBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="breakdown-count" id="dashboardPaidCount">0 Subscription</div>
                </div>

                <div class="breakdown-card">
                    <div class="breakdown-header">
                        <span class="breakdown-label">Pending</span>
                        <span class="breakdown-value" id="dashboardPendingValue">$0</span>
                    </div>
                    <div class="breakdown-progress">
                        <div class="breakdown-bar">
                            <div class="breakdown-fill orange" id="dashboardPendingBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="breakdown-count" id="dashboardPendingCount">0 Subscription</div>
                </div>

                <div class="breakdown-card">
                    <div class="breakdown-header">
                        <span class="breakdown-label">Overdue</span>
                        <span class="breakdown-value" id="dashboardOverdueValue">$0</span>
                    </div>
                    <div class="breakdown-progress">
                        <div class="breakdown-bar">
                            <div class="breakdown-fill red" id="dashboardOverdueBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="breakdown-count" id="dashboardOverdueCount">0 Subscription</div>
                </div>
            </div>

            <!-- Payment Links -->
            <div class="status-breakdown-grid" style="margin-top: 1rem;">
                <div class="breakdown-card">
                    <div class="breakdown-header">
                        <span class="breakdown-label">Pending</span>
                        <span class="breakdown-value" id="dashboardPendingPaymentLinksValue">$0</span>
                    </div>
                    <div class="breakdown-progress">
                        <div class="breakdown-bar">
                            <div class="breakdown-fill orange" id="dashboardPendingPaymentLinksBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="breakdown-count" id="dashboardPendingPaymentLinksLabel">0 payment links</div>
                </div>
                <div class="breakdown-card">
                    <div class="breakdown-header">
                        <span class="breakdown-label">Paid</span>
                        <span class="breakdown-value" id="dashboardPaidPaymentLinksValue">$0</span>
                    </div>
                    <div class="breakdown-progress">
                        <div class="breakdown-bar">
                            <div class="breakdown-fill green" id="dashboardPaidPaymentLinksBar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="breakdown-count" id="dashboardPaidPaymentLinksLabel">0 payment links</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity-section">
                <h3 class="subsection-title">Recent Payment Activity</h3>
                <div class="activity-list" id="dashboardActivityList">
                    <div class="empty-state">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Invoice Modal -->
    <div class="modal-overlay" id="newInvoiceModal" style="display: none;">
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">New Invoice</h3>
                <button class="modal-close" onclick="closeNewInvoiceModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="newInvoiceForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="invoiceClient">Client</label>
                            <select class="form-input" id="invoiceClient" required>
                                <option value="">Select client...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="invoiceNumber">Invoice #</label>
                            <input type="text" class="form-input" id="invoiceNumber" readonly placeholder="Auto-generated">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="invoiceDate">Invoice Date</label>
                            <input type="date" class="form-input" id="invoiceDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="invoiceDueDate">Due Date</label>
                            <input type="date" class="form-input" id="invoiceDueDate" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Line Items</label>
                        <span class="form-help" style="display:block;margin-bottom:0.5rem;">Pick an employee name in Description to attribute the line in P&amp;L; set Net Pay for payroll cost in P&amp;L. Other description text shows as &ldquo;invoice&rdquo; under By client.</span>
                        <datalist id="invoiceLineEmployeeOptions"></datalist>
                        <div class="line-items-wrap" id="newInvoiceLineItemsWrap">
                            <div class="line-item-header line-item-grid new-invoice-line-grid">
                                <span class="line-item-col-desc">Description</span>
                                <span class="line-item-col-hours">Hours</span>
                                <span class="line-item-col-net-pay">Net Pay</span>
                                <span class="line-item-col-rate">Rate</span>
                                <span class="line-item-col-amount">Amount</span>
                                <span class="line-item-col-action" aria-hidden="true"></span>
                            </div>
                            <div class="invoice-line-items" id="invoiceLineItems">
                            <div class="line-item-row line-item-grid new-invoice-line-grid">
                                <input type="text" class="form-input invoice-line-desc" placeholder="Description (employee name)" name="line_desc[]" list="invoiceLineEmployeeOptions" autocomplete="off">
                                <input type="number" class="form-input form-input-narrow" placeholder="Hours" name="line_hours[]" min="0" step="0.01" title="Hours worked">
                                <input type="number" class="form-input form-input-narrow" placeholder="Net Pay ($)" name="line_net_pay[]" min="0" step="0.01" title="Net pay for P&amp;L">
                                <input type="number" class="form-input form-input-narrow" placeholder="Rate ($)" name="line_rate[]" min="0" step="0.01" oninput="updateInvoiceTotals()">
                                <span class="line-amount">$0.00</span>
                                <button type="button" class="icon-btn icon-btn-danger" onclick="removeLineItem(this)" title="Remove">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                            </div>
                        </div>
                        <button type="button" class="btn-secondary btn-sm" onclick="addLineItem()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Add Line Item
                        </button>
                    </div>

                    <div class="invoice-totals">
                        <div class="total-row">
                            <span class="total-label">Subtotal</span>
                            <span class="total-value" id="invoiceSubtotal">$0.00</span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">Tax (%)</span>
                            <input type="number" class="form-input form-input-inline" id="invoiceTaxRate" value="0" min="0" max="100" step="0.01" oninput="updateInvoiceTotals()">
                            <span class="total-value" id="invoiceTaxAmount">$0.00</span>
                        </div>
                        <div class="total-row total-row-final">
                            <span class="total-label">Total</span>
                            <span class="total-value" id="invoiceTotal">$0.00</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="invoiceNotes">Notes</label>
                        <textarea class="form-input" id="invoiceNotes" rows="2" placeholder="Optional notes or payment terms"></textarea>
                    </div>

                    @if($stripeConnected ?? false)
                        <div class="form-group">
                            <span class="form-help">A Stripe payment link will be generated automatically when this invoice is created.</span>
                        </div>
                    @endif

                    <div class="form-group">
                        <label class="form-label" for="invoiceWisePaymentUrl">Wise Payment Link</label>
                        <input type="url" class="form-input" id="invoiceWisePaymentUrl" placeholder="https://wise.com/pay/... (paste link created in Wise)">
                        <span class="form-help">Create a payment link in your <a href="https://wise.com" target="_blank" rel="noopener">Wise</a> account and paste it here. It will be included in the invoice email.</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeNewInvoiceModal()">Cancel</button>
                <button class="btn-primary" onclick="saveNewInvoice()">Create Invoice</button>
            </div>
        </div>
    </div>

    <!-- View Invoice Modal -->
    <div class="modal-overlay" id="viewInvoiceModal" style="display: none;">
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Invoice <span id="viewInvoiceNumber"></span></h3>
                <button class="modal-close" onclick="closeViewInvoiceModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="view-invoice-status" id="viewInvoiceStatus"></div>
                <div class="view-invoice-grid">
                    <div class="view-invoice-row">
                        <span class="view-label">Client</span>
                        <span class="view-value" id="viewInvoiceClient"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Invoice Date</span>
                        <span class="view-value" id="viewInvoiceDate"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Due Date</span>
                        <span class="view-value" id="viewInvoiceDueDate"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Amount</span>
                        <span class="view-value view-amount" id="viewInvoiceAmount"></span>
                    </div>
                </div>
                <div class="view-invoice-section" id="viewStripePaymentSection">
                    <h4 class="view-section-title">Stripe Payment Link</h4>
                    <div class="view-stripe-link-row">
                        <input type="text" class="form-input" id="viewStripePaymentUrl" readonly placeholder="No payment link">
                        <button type="button" class="btn-secondary" id="viewGenerateLinkBtn" onclick="generateViewStripePaymentLink()" title="Generate with invoice ID for webhook">Generate Link</button>
                        <button type="button" class="btn-secondary" onclick="copyViewStripePaymentLink()" title="Copy to clipboard">Copy</button>
                    </div>
                </div>
                <div class="view-invoice-section" id="viewWisePaymentSection">
                    <h4 class="view-section-title">Wise Payment Link</h4>
                    <div class="view-stripe-link-row">
                        <input type="url" class="form-input" id="viewWisePaymentUrl" placeholder="Paste a Wise payment link">
                        <button type="button" class="btn-secondary" id="viewSaveWiseLinkBtn" onclick="saveViewWisePaymentLink()" title="Save link to this invoice">Save</button>
                        <button type="button" class="btn-secondary" onclick="copyViewWisePaymentLink()" title="Copy to clipboard">Copy</button>
                    </div>
                </div>
                <div class="view-invoice-section">
                    <h4 class="view-section-title">Line Items</h4>
                    <div class="view-line-items-table" id="viewInvoiceItemsHeader" style="display: none;">
                        <div class="line-item-header line-item-grid view-line-item-grid">
                            <span class="line-item-col-desc">Description</span>
                            <span class="line-item-col-hours">Hours</span>
                            <span class="line-item-col-net-pay">Net Pay</span>
                            <span class="line-item-col-rate">Rate</span>
                            <span class="line-item-col-amount">Amount</span>
                        </div>
                    </div>
                    <div class="view-invoice-items" id="viewInvoiceItems">
                        <!-- Line items populated by JavaScript -->
                    </div>
                </div>
                <div class="view-invoice-actions">
                    <button type="button" class="btn-secondary" onclick="downloadInvoice()">Download PDF</button>
                    <button type="button" class="btn-secondary" id="viewSendEmailBtn" onclick="openSendInvoiceEmailModal()">Send Email</button>
                </div>
            </div>
            <div class="modal-footer view-invoice-footer">
                <div class="view-invoice-footer-left">
                    <button type="button" class="btn-secondary btn-danger" id="viewDeleteBtn" onclick="deleteViewInvoice()">Delete</button>
                </div>
                <div class="view-invoice-footer-right">
                    <button type="button" class="btn-secondary" id="viewUpdateBtn" onclick="updateViewInvoice()">Update</button>
                    <button class="btn-primary" onclick="closeViewInvoiceModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Subscription Modal -->
    <div class="modal-overlay" id="editSubscriptionModal" style="display: none;">
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Edit Subscription</h3>
                <button class="modal-close" onclick="closeEditSubscriptionModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="editSubscriptionForm">
                    <input type="hidden" id="editSubscriptionId" value="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Client</label>
                            <input type="text" class="form-input" id="editSubscriptionClient" readonly disabled style="background: var(--bg-primary); cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editSubscriptionPlan">Product / Plan</label>
                            <input type="text" class="form-input" id="editSubscriptionPlan" placeholder="e.g. Professional, Enterprise">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="editSubscriptionCycle">Billing Cycle</label>
                            <select class="form-input" id="editSubscriptionCycle">
                                <option value="month_1">Monthly</option>
                                <option value="month_3">Quarterly</option>
                                <option value="month_6">Semi-Annual</option>
                                <option value="year_1">Annual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editSubscriptionAmount">Amount</label>
                            <input type="number" class="form-input" id="editSubscriptionAmount" placeholder="0.00" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editSubscriptionCurrency">Currency</label>
                            <select class="form-input" id="editSubscriptionCurrency">
                                <option value="usd">USD</option>
                                <option value="eur">EUR</option>
                                <option value="gbp">GBP</option>
                                <option value="cad">CAD</option>
                                <option value="aud">AUD</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="editSubscriptionStartDate">Current Period Start</label>
                            <input type="date" class="form-input" id="editSubscriptionStartDate">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editSubscriptionStatus">Status</label>
                            <select class="form-input" id="editSubscriptionStatus">
                                <option value="active">Active</option>
                                <option value="trialing">Trialing</option>
                                <option value="past_due">Past Due</option>
                                <option value="canceled">Canceled</option>
                                <option value="unpaid">Unpaid</option>
                                <option value="paused">Paused</option>
                                <option value="incomplete">Incomplete</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editSubscriptionTrialDays">Trial Days (optional)</label>
                            <input type="number" class="form-input" id="editSubscriptionTrialDays" placeholder="0" min="0" max="365" step="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editSubscriptionNotes">Notes</label>
                        <textarea class="form-input" id="editSubscriptionNotes" rows="2" placeholder="Optional notes"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeEditSubscriptionModal()">Cancel</button>
                <button class="btn-primary" onclick="saveUpdatedSubscription()">Update Subscription</button>
            </div>
        </div>
    </div>

    <!-- New Subscription Modal -->
    <div class="modal-overlay" id="newSubscriptionModal" style="display: none;">
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">New Subscription</h3>
                <button class="modal-close" onclick="closeNewSubscriptionModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="newSubscriptionForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="subscriptionClient">Client</label>
                            <select class="form-input" id="subscriptionClient" required>
                                <option value="">Select client...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subscriptionPlan">Product / Plan</label>
                            <input type="text" class="form-input" id="subscriptionPlan" placeholder="e.g. Professional, Enterprise" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="subscriptionCycle">Billing Cycle</label>
                            <select class="form-input" id="subscriptionCycle" required>
                                <option value="">Select cycle...</option>
                                <option value="month_1">Monthly</option>
                                <option value="month_3">Quarterly</option>
                                <option value="month_6">Semi-Annual</option>
                                <option value="year_1">Annual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subscriptionAmount">Amount</label>
                            <input type="number" class="form-input" id="subscriptionAmount" placeholder="0.00" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subscriptionCurrency">Currency</label>
                            <select class="form-input" id="subscriptionCurrency">
                                <option value="usd">USD</option>
                                <option value="eur">EUR</option>
                                <option value="gbp">GBP</option>
                                <option value="cad">CAD</option>
                                <option value="aud">AUD</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="subscriptionStartDate">Current Period Start</label>
                            <input type="date" class="form-input" id="subscriptionStartDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subscriptionStatus">Status</label>
                            <select class="form-input" id="subscriptionStatus">
                                <option value="active">Active</option>
                                <option value="trialing">Trialing</option>
                                <option value="past_due">Past Due</option>
                                <option value="canceled">Canceled</option>
                                <option value="unpaid">Unpaid</option>
                                <option value="paused">Paused</option>
                                <option value="incomplete">Incomplete</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="subscriptionTrialDays">Trial Days (optional)</label>
                            <input type="number" class="form-input" id="subscriptionTrialDays" placeholder="0" min="0" max="365" step="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="subscriptionNotes">Notes</label>
                        <textarea class="form-input" id="subscriptionNotes" rows="2" placeholder="Optional notes (for API integration, metadata)"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeNewSubscriptionModal()">Cancel</button>
                <button class="btn-primary" onclick="saveNewSubscription()">Create Subscription</button>
            </div>
        </div>
    </div>

    <!-- Send Invoice Email Modal -->
    <div class="modal-overlay" id="sendInvoiceEmailModal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title" id="sendInvoiceEmailModalTitle">Send Invoice Email</h3>
                <button class="modal-close" type="button" onclick="closeSendInvoiceEmailModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="form-help" id="sendInvoiceEmailModalHint" style="margin-bottom: 1rem;"></p>
                <div class="form-group">
                    <label class="form-label" for="sendInvoiceEmailCutoff">Cutoff Period</label>
                    <input type="text" class="form-input" id="sendInvoiceEmailCutoff" placeholder="e.g. June 22-29" autocomplete="off">
                </div>
                <div class="form-group" id="sendInvoiceEmailSubjectGroup">
                    <label class="form-label" for="sendInvoiceEmailSubject">Email Subject</label>
                    <input type="text" class="form-input" id="sendInvoiceEmailSubject" maxlength="255" autocomplete="off">
                </div>
                <p class="form-help" id="sendInvoiceEmailBulkNote" style="display: none; margin-top: 0.5rem;">
                    Each selected invoice will use its own subject based on client name, invoice number, and amount.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeSendInvoiceEmailModal()">Cancel</button>
                <button type="button" class="btn-primary" id="sendInvoiceEmailConfirmBtn" onclick="confirmSendInvoiceEmail()">Send Email</button>
            </div>
        </div>
    </div>

    <!-- Send Email Loading Overlay -->
    <div class="send-email-overlay" id="sendEmailOverlay" style="display: none;">
        <div class="send-email-overlay-content">
            <span class="send-email-spinner send-email-overlay-spinner"></span>
            <p>Sending invoice...</p>
            <p class="send-email-overlay-sub">Please wait</p>
        </div>
    </div>

    <!-- Edit Invoice Modal -->
    <div class="modal-overlay" id="editInvoiceModal" style="display: none;">
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Edit Invoice</h3>
                <button class="modal-close" onclick="closeEditInvoiceModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="editInvoiceForm">
                    <input type="hidden" id="editInvoiceId">
                    <input type="hidden" id="editInvoiceOriginalStatus">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="editInvoiceClient">Client</label>
                            <select class="form-input" id="editInvoiceClient" required>
                                <option value="">Select client...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editInvoiceNumber">Invoice #</label>
                            <input type="text" class="form-input" id="editInvoiceNumber" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="editInvoiceDate">Invoice Date</label>
                            <input type="date" class="form-input" id="editInvoiceDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="editInvoiceDueDate">Due Date</label>
                            <input type="date" class="form-input" id="editInvoiceDueDate" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="editInvoiceStatus">Status</label>
                            <select class="form-input" id="editInvoiceStatus">
                                <option value="draft">Draft</option>
                                <option value="sent">Sent</option>
                                <option value="paid">Paid</option>
                                <option value="overdue">Overdue</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Line Items</label>
                        <span class="form-help" style="display:block;margin-bottom:0.5rem;">Pick an employee name in Description to attribute the line in P&amp;L; set Net Pay for payroll cost in P&amp;L.</span>
                        <div class="line-items-wrap" id="editLineItemsWrap">
                            <div class="line-item-header line-item-grid new-invoice-line-grid" id="editLineItemHeader">
                                <span class="line-item-col-desc">Description</span>
                                <span class="line-item-col-hours">Hours</span>
                                <span class="line-item-col-net-pay">Net Pay</span>
                                <span class="line-item-col-rate">Rate</span>
                                <span class="line-item-col-amount">Amount</span>
                                <span class="line-item-col-action" aria-hidden="true"></span>
                            </div>
                            <div class="invoice-line-items" id="editInvoiceLineItems" style="margin-bottom: 0.75rem;"></div>
                        </div>
                        <button type="button" class="btn-secondary btn-sm" onclick="addEditLineItem()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Add Line Item
                        </button>
                    </div>
                    <div class="invoice-totals">
                        <div class="total-row">
                            <span class="total-label">Subtotal</span>
                            <span class="total-value" id="editInvoiceSubtotal">$0.00</span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">Tax (%)</span>
                            <input type="number" class="form-input form-input-inline" id="editInvoiceTaxRate" value="0" min="0" max="100" step="0.01" oninput="updateEditInvoiceTotals()">
                            <span class="total-value" id="editInvoiceTaxAmount">$0.00</span>
                        </div>
                        <div class="total-row total-row-final">
                            <span class="total-label">Total</span>
                            <span class="total-value" id="editInvoiceTotal">$0.00</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editInvoiceNotes">Notes</label>
                        <textarea class="form-input" id="editInvoiceNotes" rows="2" placeholder="Optional notes or payment terms"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="editInvoiceWisePaymentUrl">Wise Payment Link</label>
                        <input type="url" class="form-input" id="editInvoiceWisePaymentUrl" placeholder="https://wise.com/pay/...">
                        <span class="form-help">Paste a payment link created in Wise. Included in the invoice email.</span>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeEditInvoiceModal()">Cancel</button>
                <button class="btn-primary" onclick="saveEditInvoice()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Incoming Wise Payments Modal -->
    <div class="modal-overlay" id="wiseIncomingModal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Incoming Wise Payments</h3>
                <button class="modal-close" onclick="closeWiseIncomingModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="form-help" style="margin-bottom: 0.75rem;">Recent credits received in your Wise balance. Match a payment to an unpaid invoice to mark it Paid.</p>
                <div id="wiseIncomingContent">
                    <div class="wise-incoming-empty">Loading…</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="loadWiseIncomingPayments()">Refresh</button>
                <button class="btn-primary" onclick="closeWiseIncomingModal()">Close</button>
            </div>
        </div>
    </div>
@endsection
