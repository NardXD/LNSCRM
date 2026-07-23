@extends('layouts.app')

@section('title', 'Quotation Builder')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Quotation Builder</h1>
        <p class="page-subtitle">Create, manage, and send professional quotations to clients</p>
    </div>

    <div class="quotation-container">
        <!-- Header Actions -->
        <div class="quotation-header">
            <div class="header-left">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" class="search-input" placeholder="Search quotations..." id="quotationSearch">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="sent">Sent</option>
                    <option value="accepted">Accepted</option>
                    <option value="paid">Paid</option>
                    <option value="rejected">Rejected</option>
                    <option value="expired">Expired</option>
                </select>
                <input type="month" class="filter-select" id="monthFilter" value="{{ date('Y-m') }}">
            </div>
            <div class="header-right">
                <button class="btn-primary" onclick="createQuotation()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Quotation
                </button>
            </div>
        </div>

        <!-- Quotation Stats -->
        <div class="quotation-stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Quotations</span>
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
                <div class="stat-value">142</div>
                <div class="stat-change positive">+8 this month</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Pending</span>
                    <div class="stat-icon orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">28</div>
                <div class="stat-change">Awaiting response</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Accepted</span>
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">89</div>
                <div class="stat-change positive">62.7% acceptance rate</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Value</span>
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">$2.4M</div>
                <div class="stat-change positive">+15.2% from last month</div>
            </div>
        </div>

        <!-- Quotations Table -->
        <div class="quotations-section">
            <div class="table-container">
                <table class="data-table" id="quotationsTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" class="table-checkbox" id="selectAllQuotations">
                            </th>
                            <th>Quotation #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Valid Until</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="quotationsTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="quotations-cards" id="quotationsCards">
                <!-- Cards will be populated by JavaScript -->
            </div>

            <!-- Pagination -->
            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="paginationInfo">Showing 1 to 10 of 142 results</span>
                </div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevBtn" disabled>Previous</button>
                    <div class="pagination-numbers" id="paginationNumbers"></div>
                    <button class="pagination-btn" id="nextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotation Builder Modal -->
    <div class="quotation-modal" id="quotationModal">
        <div class="quotation-modal-content">
            <button class="modal-close" onclick="closeQuotationModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title">Create New Quotation</h2>
                <div class="modal-actions-top">
                    <button class="btn-secondary" onclick="saveDraft()">Save Draft</button>
                    <button class="btn-secondary" onclick="previewQuotation()">Preview</button>
                </div>
            </div>

            <div class="modal-body">
                <div class="quotation-form">
                    <!-- Client & Details -->
                    <div class="form-section">
                        <h3 class="form-section-title">Client & Details</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Client *</label>
                                <select class="form-input" id="quotationClient" required>
                                    <option value="">Select a client</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Quotation #</label>
                                <input type="text" class="form-input" id="quotationNumber" value="Auto-generated" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-input" id="quotationDate" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Valid Until *</label>
                                <input type="date" class="form-input" id="quotationValidUntil" required>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="form-section">
                        <div class="section-header-inline">
                            <h3 class="form-section-title">Items</h3>
                            <button type="button" class="btn-secondary" onclick="addLineItem()">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"/>
                                    <line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Add Item
                            </button>
                        </div>
                        <div class="line-items-table">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Tax</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="lineItemsBody">
                                    <!-- Line items will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Notes & Terms -->
                    <div class="form-section">
                        <h3 class="form-section-title">Notes & Terms</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Notes (Internal)</label>
                                <textarea class="form-input" id="internalNotes" rows="3" placeholder="Internal notes (not visible to client)"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Terms & Conditions</label>
                                <textarea class="form-input" id="termsConditions" rows="4" placeholder="Payment terms, delivery conditions, etc.">Payment is due within 30 days of invoice date. Late payments may incur a 5% monthly fee.</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="quotation-summary">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal</span>
                            <span class="summary-value" id="summarySubtotal">$0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Tax</span>
                            <span class="summary-value" id="summaryTax">$0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Discount</span>
                            <div class="summary-input-group">
                                <input type="number" class="summary-input" id="discountAmount" placeholder="0" min="0" step="0.01" onchange="calculateTotal()">
                                <select class="summary-select" id="discountType" onchange="calculateTotal()">
                                    <option value="amount">$</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                        </div>
                        <div class="summary-row total">
                            <span class="summary-label">Total</span>
                            <span class="summary-value" id="summaryTotal">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeQuotationModal()">Cancel</button>
                <button class="btn-primary" onclick="saveDraft()">Save Quotation</button>
            </div>
        </div>
    </div>

    <!-- Status History Modal -->
    <div class="quotation-modal" id="statusHistoryModal">
        <div class="quotation-modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Status History</h2>
                <button class="modal-close" onclick="closeStatusHistoryModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body" id="statusHistoryBody" style="max-height: 500px; overflow-y: auto;">
                <div style="text-align: center; padding: 2rem;">
                    <div class="spinner"></div>
                    <p>Loading status history...</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .quotation-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Header */
    .quotation-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .header-left,
    .header-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .search-box {
        position: relative;
        min-width: 250px;
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

    /* Stats Grid */
    .quotation-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
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

    .stat-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .stat-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .stat-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
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

    /* Tables */
    .quotations-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1.5rem;
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
        cursor: pointer;
    }

    .table-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--accent);
    }

    /* Status Badge */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.draft {
        background: #e5e7eb;
        color: #374151;
    }

    .status-badge.sent {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.accepted {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.rejected {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-badge.paid {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.expired {
        background: #fef3c7;
        color: #d97706;
    }

    /* Status Select */
    .status-select,
    .status-select-small {
        padding: 0.375rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        min-width: 110px;
    }

    .status-select-small {
        min-width: 90px;
        font-size: 0.8125rem;
        padding: 0.25rem 0.5rem;
    }

    .status-select:hover,
    .status-select-small:hover {
        border-color: var(--primary);
    }

    .status-select:focus,
    .status-select-small:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }

    /* Status History */
    .status-history-list {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .status-history-item {
        display: flex;
        gap: 1rem;
        padding: 1rem 0;
        position: relative;
    }

    .status-history-item:not(:last-child) {
        border-bottom: 1px solid var(--border);
    }

    .status-history-item.current .status-history-dot {
        background: var(--accent);
        border-color: var(--accent);
    }

    .status-history-timeline {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
        flex-shrink: 0;
    }

    .status-history-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--bg-primary);
        border: 2px solid var(--border);
        z-index: 1;
    }

    .status-history-line {
        width: 2px;
        flex: 1;
        background: var(--border);
        margin-top: 0.25rem;
        min-height: 40px;
    }

    .status-history-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .status-history-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .status-history-date {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .status-history-change {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .status-history-user,
    .status-history-notes {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .status-history-label {
        font-weight: 500;
        color: var(--text-primary);
        margin-right: 0.5rem;
    }

    .status-history-notes {
        padding: 0.5rem;
        background: var(--bg-primary);
        border-radius: 6px;
        border-left: 3px solid var(--accent);
    }

    .spinner {
        border: 3px solid var(--border);
        border-top: 3px solid var(--accent);
        border-radius: 50%;
        width: 32px;
        height: 32px;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Actions */
    .table-actions {
        display: flex;
        gap: 0.5rem;
    }

    .icon-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .icon-btn:hover {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .icon-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .icon-btn:disabled:hover {
        background: none;
        border-color: var(--border);
        color: inherit;
    }

    .icon-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Mobile Card View */
    .quotations-cards {
        display: none;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .quotation-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .quotation-card:hover {
        border-color: var(--accent);
    }

    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .card-title {
        font-weight: 600;
        color: var(--text-primary);
    }

    .card-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .card-detail {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .card-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .card-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    /* Pagination */
    .table-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }

    .pagination-info {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .pagination-btn {
        padding: 0.625rem 1rem;
        border: 1px solid var(--border);
        background: var(--bg-card);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .pagination-btn:hover:not(:disabled) {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-numbers {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        flex-wrap: wrap;
    }

    .pagination-number {
        min-width: 36px;
        height: 36px;
        padding: 0 0.5rem;
        border: 1px solid var(--border);
        background: var(--bg-card);
        border-radius: 8px;
        font-size: 0.875rem;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        justify-content: center;
        -webkit-tap-highlight-color: transparent;
    }

    .pagination-number:hover:not(.active):not(.ellipsis) {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .pagination-number.active {
        background: var(--accent);
        border-color: var(--accent);
        color: white;
    }

    .pagination-number.ellipsis {
        border: none;
        background: none;
        cursor: default;
        min-width: auto;
        padding: 0 0.25rem;
    }

    /* Quotation Modal */
    .quotation-modal {
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

    .quotation-modal.active {
        display: flex;
        opacity: 1;
    }

    .quotation-modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 1000px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .quotation-modal.active .quotation-modal-content {
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
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-actions-top {
        display: flex;
        gap: 0.75rem;
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
    }

    /* Form Styles */
    .quotation-form {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .form-section {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1.5rem;
    }

    .form-section-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .section-header-inline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
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
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-input[readonly] {
        background: var(--bg-primary);
        cursor: not-allowed;
        color: var(--text-primary);
    }

    /* Line Items Table */
    .line-items-table {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--bg-card);
        border-radius: 8px;
        overflow: visible;
    }

    .items-table thead {
        background: var(--bg-primary);
    }

    .items-table th {
        padding: 0.75rem;
        text-align: left;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .items-table td {
        padding: 0.75rem;
        border-bottom: 1px solid var(--border);
    }

    .item-input {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .item-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(95, 97, 230, 0.1);
    }

    .item-input.small {
        width: 80px;
    }

    .item-input.medium {
        width: 120px;
    }

    /* Item Autocomplete */
    .item-autocomplete-wrapper {
        position: relative;
        width: 100%;
    }

    .item-autocomplete-suggest {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        margin-top: 2px;
        min-width: 200px;
        max-height: 200px;
        overflow-y: auto;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 2100;
        display: none;
    }

    /* Allow dropdown to overflow when open */
    .quotation-modal.item-suggest-open .modal-body,
    .quotation-modal.item-suggest-open .line-items-table {
        overflow: visible !important;
    }

    .item-autocomplete-suggest.show {
        display: block;
    }

    .item-autocomplete-suggest-item {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        cursor: pointer;
        transition: background 0.15s;
    }

    .item-autocomplete-suggest-item:hover,
    .item-autocomplete-suggest-item.active {
        background: var(--bg-primary);
    }

    .item-autocomplete-suggest-item .suggest-name {
        font-weight: 500;
    }

    .item-autocomplete-suggest-item .suggest-desc {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.125rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .item-remove-btn {
        width: 32px;
        height: 32px;
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

    .item-remove-btn:hover {
        background: #fecaca;
    }

    .item-remove-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Quotation Summary */
    .quotation-summary {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1.5rem;
        margin-top: 1rem;
    }

    .summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--border);
    }

    .summary-row:last-child {
        border-bottom: none;
    }

    .summary-row.total {
        border-top: 2px solid var(--border);
        margin-top: 0.5rem;
        padding-top: 1rem;
    }

    .summary-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .summary-row.total .summary-label {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .summary-value {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .summary-row.total .summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--accent);
    }

    .summary-input-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .summary-input {
        width: 100px;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .summary-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(95, 97, 230, 0.1);
    }

    .summary-select {
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    /* Responsive */
    @media (min-width: 769px) {
        .table-container {
            display: block;
        }
        .quotations-cards {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .table-container {
            display: none !important;
        }
        .quotations-cards {
            display: flex !important;
        }

        .quotation-header {
            flex-direction: column;
            align-items: stretch;
        }

        .header-left,
        .header-right {
            width: 100%;
        }

        .search-box {
            min-width: 100%;
        }

        .quotation-stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .table-pagination {
            flex-direction: column;
            align-items: stretch;
        }

        .pagination-controls {
            justify-content: center;
            width: 100%;
        }

        .quotation-modal-content {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 0;
        }

        .modal-header {
            flex-direction: column;
            align-items: stretch;
        }

        .modal-actions-top {
            width: 100%;
        }

        .modal-actions-top .btn-secondary {
            flex: 1;
            justify-content: center;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .items-table {
            font-size: 0.8125rem;
        }

        .items-table th,
        .items-table td {
            padding: 0.5rem;
        }

        .item-input.small {
            width: 60px;
        }

        .item-input.medium {
            width: 100px;
        }

        .modal-footer {
            flex-direction: column;
        }

        .modal-footer .btn-primary,
        .modal-footer .btn-secondary {
            width: 100%;
            justify-content: center;
        }

        .card-details {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .quotation-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // API Routes
    const API_ROUTES = {
        quotations: '{{ route("api.quotation-builder.quotations") }}',
        stats: '{{ route("api.quotation-builder.stats") }}',
        clients: '{{ route("api.quotation-builder.clients") }}',
        nextQuotationNumber: '{{ route("api.quotation-builder.next-quotation-number") }}',
        store: '{{ route("api.quotation-builder.quotations.store") }}',
        show: (id) => `{{ route("api.quotation-builder.quotations.show", ":id") }}`.replace(':id', id),
        pdf: (id) => `{{ route("api.quotation-builder.quotations.pdf", ":id") }}`.replace(':id', id),
        statusHistory: (id) => `{{ route("api.quotation-builder.quotations.status-history", ":id") }}`.replace(':id', id),
        sendEmail: (id) => `{{ route("api.quotation-builder.quotations.send-email", ":id") }}`.replace(':id', id),
        update: (id) => `{{ route("api.quotation-builder.quotations.update", ":id") }}`.replace(':id', id),
        updateStatus: (id) => `{{ route("api.quotation-builder.quotations.status.update", ":id") }}`.replace(':id', id),
        destroy: (id) => `{{ route("api.quotation-builder.quotations.destroy", ":id") }}`.replace(':id', id),
        itemTemplatesSearch: '{{ route("api.quotation-builder.item-templates.search") }}',
    };

    // Global State
    let quotationsData = [];
    let clientsData = [];
    let currentPage = 1;
    let totalPages = 1;
    const itemsPerPage = 10;

    // Line Items State
    let lineItems = [];
    let editingQuotationId = null;
    let itemTemplates = [];

    // Load Clients
    async function loadClients() {
        try {
            const response = await fetch(API_ROUTES.clients);
            const result = await response.json();
            if (result.success) {
                clientsData = result.data;
                const clientSelect = document.getElementById('quotationClient');
                clientSelect.innerHTML = '<option value="">Select a client</option>';
                clientsData.forEach(client => {
                    const option = document.createElement('option');
                    option.value = client.id;
                    option.textContent = client.name;
                    clientSelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading clients:', error);
        }
    }

    // Load Stats
    async function loadStats() {
        try {
            const response = await fetch(API_ROUTES.stats);
            const result = await response.json();
            if (result.success) {
                const stats = result.data;
                document.querySelector('.stat-card:nth-child(1) .stat-value').textContent = stats.total_quotations;
                document.querySelector('.stat-card:nth-child(1) .stat-change').textContent = `+${stats.new_this_month} this month`;
                
                document.querySelector('.stat-card:nth-child(2) .stat-value').textContent = stats.pending;
                document.querySelector('.stat-card:nth-child(3) .stat-value').textContent = stats.accepted;
                document.querySelector('.stat-card:nth-child(3) .stat-change').textContent = `${stats.acceptance_rate}% acceptance rate`;
                
                const totalValue = stats.total_value || 0;
                const valueDisplay = totalValue >= 1000000 
                    ? `$${(totalValue / 1000000).toFixed(1)}M` 
                    : `$${(totalValue / 1000).toFixed(1)}K`;
                document.querySelector('.stat-card:nth-child(4) .stat-value').textContent = valueDisplay;
                document.querySelector('.stat-card:nth-child(4) .stat-change').textContent = `${stats.value_growth_percentage > 0 ? '+' : ''}${stats.value_growth_percentage}% from last month`;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    // Load Quotations
    async function loadQuotations(page = 1, status = 'all', search = '', month = null) {
        try {
            const url = new URL(API_ROUTES.quotations);
            url.searchParams.append('page', page);
            url.searchParams.append('per_page', itemsPerPage);
            if (status !== 'all') {
                url.searchParams.append('status', status);
            }
            if (search) {
                url.searchParams.append('search', search);
            }
            if (month === null) {
                month = document.getElementById('monthFilter')?.value || new Date().toISOString().slice(0, 7);
            }
            if (month) {
                url.searchParams.append('month', month);
            }

            const response = await fetch(url);
            const result = await response.json();
            if (result.success) {
                quotationsData = result.data;
                currentPage = result.pagination.current_page;
                totalPages = result.pagination.last_page;
                updateView();
            }
        } catch (error) {
            console.error('Error loading quotations:', error);
        }
    }

    // Render Functions
    function renderTable() {
        const tbody = document.getElementById('quotationsTableBody');
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = quotationsData.slice(start, end);

        tbody.innerHTML = pageData.map(quotation => `
            <tr onclick="viewQuotation(${quotation.id})">
                <td onclick="event.stopPropagation()"><input type="checkbox" class="table-checkbox" data-id="${quotation.id}"></td>
                <td><strong>${quotation.quotation_number}</strong></td>
                <td>${quotation.client}</td>
                <td>${quotation.date}</td>
                <td>${quotation.valid_until}</td>
                <td><strong>$${quotation.amount.toLocaleString()}</strong></td>
                <td onclick="event.stopPropagation()">
                    ${quotation.status === 'paid' || quotation.status === 'rejected' ? `
                        <span class="status-badge ${quotation.status}">${quotation.status.charAt(0).toUpperCase() + quotation.status.slice(1)}</span>
                    ` : `
                        <select class="status-select" onchange="updateQuotationStatus(${quotation.id}, this.value)" onclick="event.stopPropagation()">
                            ${quotation.status !== 'accepted' ? `
                            <option value="draft" ${quotation.status === 'draft' ? 'selected' : ''}>Draft</option>
                            <option value="sent" ${quotation.status === 'sent' ? 'selected' : ''}>Sent</option>
                            ` : ''}
                            <option value="accepted" ${quotation.status === 'accepted' ? 'selected' : ''}>Accepted</option>
                            <option value="rejected" ${quotation.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                            <option value="expired" ${quotation.status === 'expired' ? 'selected' : ''}>Expired</option>
                        </select>
                    `}
                </td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="viewQuotation(${quotation.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="Status History" onclick="event.stopPropagation(); viewStatusHistory(${quotation.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="${['paid', 'accepted', 'rejected'].includes(quotation.status) ? 'Cannot send - quotation is ' + quotation.status : 'Send Email'}" ${['paid', 'accepted', 'rejected'].includes(quotation.status) ? 'disabled' : ''} onclick="event.stopPropagation(); if (this.disabled) return; sendQuotationEmail(${quotation.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="${['paid', 'accepted', 'rejected'].includes(quotation.status) ? 'Cannot edit - quotation is ' + quotation.status : 'Edit'}" ${['paid', 'accepted', 'rejected'].includes(quotation.status) ? 'disabled' : ''} onclick="if (this.disabled) return; editQuotation(${quotation.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="${['paid', 'accepted', 'rejected'].includes(quotation.status) ? 'Cannot delete - quotation is ' + quotation.status : 'Delete'}" ${['paid', 'accepted', 'rejected'].includes(quotation.status) ? 'disabled' : ''} onclick="event.stopPropagation(); if (this.disabled) return; deleteQuotation(${quotation.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderCards() {
        const container = document.getElementById('quotationsCards');
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = quotationsData.slice(start, end);

        container.innerHTML = pageData.map(quotation => `
            <div class="quotation-card" onclick="viewQuotation(${quotation.id})">
                <div class="card-header">
                    <div>
                        <div class="card-title">${quotation.quotationNumber}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${quotation.client}</div>
                    </div>
                    ${quotation.status === 'paid' || quotation.status === 'rejected' ? `
                        <span class="status-badge ${quotation.status}">${quotation.status.charAt(0).toUpperCase() + quotation.status.slice(1)}</span>
                    ` : `
                        <select class="status-select-small" onchange="updateQuotationStatus(${quotation.id}, this.value)" onclick="event.stopPropagation()">
                            ${quotation.status !== 'accepted' ? `
                            <option value="draft" ${quotation.status === 'draft' ? 'selected' : ''}>Draft</option>
                            <option value="sent" ${quotation.status === 'sent' ? 'selected' : ''}>Sent</option>
                            ` : ''}
                            <option value="accepted" ${quotation.status === 'accepted' ? 'selected' : ''}>Accepted</option>
                            <option value="rejected" ${quotation.status === 'rejected' ? 'selected' : ''}>Rejected</option>
                            <option value="expired" ${quotation.status === 'expired' ? 'selected' : ''}>Expired</option>
                        </select>
                    `}
                </div>
                <div class="card-details">
                    <div class="card-detail">
                        <span class="card-label">Date</span>
                        <span class="card-value">${quotation.date}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Valid Until</span>
                        <span class="card-value">${quotation.validUntil}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Amount</span>
                        <span class="card-value">$${quotation.amount.toLocaleString()}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderPagination() {
        const info = document.getElementById('paginationInfo');
        const numbers = document.getElementById('paginationNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        const start = quotationsData.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0;
        const end = Math.min(currentPage * itemsPerPage, quotationsData.length + (currentPage - 1) * itemsPerPage);
        const total = quotationsData.length + (currentPage - 1) * itemsPerPage; // Approximate total
        info.textContent = `Showing ${start} to ${end} of ${total} results`;

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages;

        let html = '';
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<button class="pagination-number" data-page="1">1</button>`;
            if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-number ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<span class="pagination-number ellipsis">...</span>`;
            html += `<button class="pagination-number" data-page="${totalPages}">${totalPages}</button>`;
        }

        numbers.innerHTML = html;
        numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                currentPage = parseInt(btn.dataset.page);
                updateView();
            });
        });
    }

    function updateView() {
        if (window.innerWidth <= 768) {
            renderCards();
        } else {
            renderTable();
        }
        renderPagination();
    }

    // Search and Filter
    let searchTimeout;
    document.getElementById('quotationSearch')?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const search = this.value;
        searchTimeout = setTimeout(() => {
            const status = document.getElementById('statusFilter').value;
            const month = document.getElementById('monthFilter')?.value;
            loadQuotations(1, status, search, month);
        }, 500);
    });

    document.getElementById('statusFilter')?.addEventListener('change', function() {
        const search = document.getElementById('quotationSearch').value;
        const month = document.getElementById('monthFilter')?.value;
        loadQuotations(1, this.value, search, month);
    });

    document.getElementById('monthFilter')?.addEventListener('change', function() {
        const search = document.getElementById('quotationSearch').value;
        const status = document.getElementById('statusFilter').value;
        loadQuotations(1, status, search, this.value);
    });

    // Event Listeners
    document.getElementById('prevBtn')?.addEventListener('click', () => {
        if (currentPage > 1) {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('quotationSearch').value;
            const month = document.getElementById('monthFilter')?.value;
            loadQuotations(currentPage - 1, status, search, month);
        }
    });

    document.getElementById('nextBtn')?.addEventListener('click', () => {
        if (currentPage < totalPages) {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('quotationSearch').value;
            const month = document.getElementById('monthFilter')?.value;
            loadQuotations(currentPage + 1, status, search, month);
        }
    });

    // Select All Checkbox
    document.getElementById('selectAllQuotations')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.table-checkbox:not(#selectAllQuotations)');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });

    // Render Line Items
    function renderLineItems() {
        const tbody = document.getElementById('lineItemsBody');
        if (lineItems.length === 0) {
            lineItems = [{ id: Date.now(), item: '', description: '', quantity: 1, unitPrice: 0, tax: 0, total: 0, template_id: null }];
        }
        tbody.innerHTML = lineItems.map((item, index) => `
            <tr>
                <td>
                    <div class="item-autocomplete-wrapper" data-item-id="${item.id}">
                        <input type="text" class="item-input item-input-suggest" id="itemInput-${item.id}" value="${(item.item || item.item_name || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;')}" placeholder="Type to search or enter custom item" data-item-id="${item.id}" autocomplete="off">
                        <div class="item-autocomplete-suggest" id="itemSuggest-${item.id}" role="listbox"></div>
                    </div>
                </td>
                <td>
                    <input type="text" class="item-input" value="${item.description || ''}" placeholder="Description" onchange="updateLineItem(${item.id}, 'description', this.value)">
                </td>
                <td>
                    <input type="number" class="item-input small" value="${item.quantity || 1}" min="0.01" step="0.01" onchange="updateLineItem(${item.id}, 'quantity', parseFloat(this.value))">
                </td>
                <td>
                    <input type="number" class="item-input medium" value="${item.unitPrice || item.unit_price || 0}" min="0" step="0.01" onchange="updateLineItem(${item.id}, 'unitPrice', parseFloat(this.value))">
                </td>
                <td>
                    <input type="number" class="item-input small" value="${item.tax || item.tax_percentage || 0}" min="0" step="0.1" placeholder="%" onchange="updateLineItem(${item.id}, 'tax', parseFloat(this.value))">
                </td>
                <td>
                    <strong>$${(item.total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong>
                </td>
                <td>
                    <button class="item-remove-btn" onclick="removeLineItem(${item.id})" title="Remove">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </td>
            </tr>
        `).join('');
        calculateTotal();
        setTimeout(initItemSuggest, 0);
    }

    // Item autocomplete - search templates and apply on selection
    let itemSuggestTimeouts = {};
    let itemSuggestSelectionMade = false;
    async function searchItemTemplates(q) {
        try {
            const url = new URL(API_ROUTES.itemTemplatesSearch);
            url.searchParams.append('q', q || '');
            const response = await fetch(url);
            const result = await response.json();
            return (result.success && result.data) ? result.data : [];
        } catch (e) {
            return [];
        }
    }
    function applyTemplateFromSuggestion(itemId, template) {
        itemSuggestSelectionMade = true;
        const item = lineItems.find(i => i.id === itemId);
        if (!item || !template) return;
        item.template_id = template.id;
        item.item = template.item_name;
        item.item_name = template.item_name;
        item.description = template.description || '';
        item.quantity = template.default_quantity || 1;
        item.unitPrice = template.default_unit_price || 0;
        item.unit_price = template.default_unit_price || 0;
        item.tax = template.default_tax_percentage || 0;
        item.tax_percentage = template.default_tax_percentage || 0;
        const q = item.quantity || 1, up = item.unitPrice || item.unit_price || 0, tx = item.tax || item.tax_percentage || 0;
        item.total = (q * up) * (1 + tx / 100);
        renderLineItems();
    }
    function initItemSuggest() {
        document.querySelectorAll('.item-input-suggest').forEach(input => {
            const itemId = parseInt(input.dataset.itemId, 10);
            const dropdown = document.getElementById(`itemSuggest-${itemId}`);
            if (!dropdown || dropdown.dataset.inited === '1') return;
            dropdown.dataset.inited = '1';

            const modal = document.getElementById('quotationModal');
            const hide = () => {
                dropdown.innerHTML = '';
                dropdown.classList.remove('show');
                dropdown.removeAttribute('style');
                modal?.classList.remove('item-suggest-open');
            };
            const show = (items) => {
                dropdown.innerHTML = items.map(t => `
                    <div class="item-autocomplete-suggest-item" data-template='${JSON.stringify(t).replace(/'/g, '&apos;')}'>
                        <div class="suggest-name">${(t.item_name || '').replace(/</g, '&lt;')}</div>
                        ${t.description ? `<div class="suggest-desc">${(t.description || '').substring(0, 80).replace(/</g, '&lt;')}</div>` : ''}
                    </div>
                `).join('');
                modal?.classList.add('item-suggest-open');
                dropdown.classList.add('show');
                dropdown.querySelectorAll('.item-autocomplete-suggest-item').forEach(el => {
                    el.addEventListener('click', () => {
                        const t = JSON.parse(el.dataset.template.replace(/&apos;/g, "'"));
                        clearTimeout(itemSuggestTimeouts[itemId]);
                        hide();
                        applyTemplateFromSuggestion(itemId, t);
                    });
                });
            };

            input.addEventListener('input', () => {
                clearTimeout(itemSuggestTimeouts[itemId]);
                const q = input.value.trim();
                const item = lineItems.find(i => i.id === itemId);
                if (item) { item.item = q; item.item_name = q; }
                if (q.length === 0) {
                    hide();
                    return;
                }
                const delay = q.length === 1 ? 0 : 150;
                itemSuggestTimeouts[itemId] = setTimeout(async () => {
                    const results = await searchItemTemplates(q);
                    show(results);
                }, delay);
            });
            input.addEventListener('focus', () => {
                const q = input.value.trim();
                if (q.length === 0) {
                    hide();
                    return;
                }
                if (dropdown.children.length) {
                    modal?.classList.add('item-suggest-open');
                    dropdown.classList.add('show');
                } else {
                    searchItemTemplates(q).then(show);
                }
            });
            input.addEventListener('blur', () => setTimeout(hide, 150));
        });
    }

    function addLineItem() {
        const newItem = {
            id: Date.now(),
            item: '',
            description: '',
            quantity: 1,
            unitPrice: 0,
            tax: 0,
            total: 0,
            template_id: null
        };
        lineItems.push(newItem);
        renderLineItems();
    }

    function removeLineItem(id) {
        lineItems = lineItems.filter(item => item.id !== id);
        renderLineItems();
    }

    function updateLineItem(id, field, value) {
        const item = lineItems.find(i => i.id === id);
        if (!item) return;

        // Map field names
        if (field === 'item') {
            item.item = value;
            item.item_name = value;
        } else if (field === 'unitPrice') {
            item.unitPrice = value;
            item.unit_price = value;
        } else if (field === 'tax') {
            item.tax = value;
            item.tax_percentage = value;
        } else {
            item[field] = value;
        }
        
        // Calculate total
        const quantity = item.quantity || 1;
        const unitPrice = item.unitPrice || item.unit_price || 0;
        const taxPercent = item.tax || item.tax_percentage || 0;
        const subtotal = quantity * unitPrice;
        const taxAmount = subtotal * (taxPercent / 100);
        item.total = subtotal + taxAmount;
        
        renderLineItems();
    }

    function calculateTotal() {
        const subtotal = lineItems.reduce((sum, item) => {
            const quantity = item.quantity || 1;
            const unitPrice = item.unitPrice || item.unit_price || 0;
            const itemSubtotal = quantity * unitPrice;
            return sum + itemSubtotal;
        }, 0);

        const tax = lineItems.reduce((sum, item) => {
            const quantity = item.quantity || 1;
            const unitPrice = item.unitPrice || item.unit_price || 0;
            const taxPercent = item.tax || item.tax_percentage || 0;
            const itemSubtotal = quantity * unitPrice;
            return sum + (itemSubtotal * (taxPercent / 100));
        }, 0);

        const discountInput = document.getElementById('discountAmount');
        const discountType = document.getElementById('discountType').value;
        const discountValue = parseFloat(discountInput?.value) || 0;

        let discount = 0;
        if (discountType === 'percent') {
            discount = subtotal * (discountValue / 100);
        } else {
            discount = discountValue;
        }

        const total = subtotal + tax - discount;

        const subtotalEl = document.getElementById('summarySubtotal');
        const taxEl = document.getElementById('summaryTax');
        const totalEl = document.getElementById('summaryTotal');
        
        if (subtotalEl) subtotalEl.textContent = `$${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        if (taxEl) taxEl.textContent = `$${tax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        if (totalEl) totalEl.textContent = `$${total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    // Load Item Templates
    async function loadItemTemplates() {
        try {
            const url = new URL(API_ROUTES.itemTemplatesSearch);
            url.searchParams.append('q', ''); // Empty query to get all active templates

            const response = await fetch(url);
            const result = await response.json();

            if (result.success && result.data) {
                itemTemplates = result.data;
            }
        } catch (error) {
            console.error('Error loading item templates:', error);
        }
    }

    // Handle Template Selection
    function handleTemplateSelect(itemId, templateId) {
        const item = lineItems.find(i => i.id === itemId);
        if (!item) return;

        const inputField = document.getElementById(`itemInput-${itemId}`);

        if (!templateId || templateId === '') {
            // Clear template selection, show manual input
            item.template_id = null;
            if (inputField) {
                inputField.style.display = 'block';
                inputField.value = item.item || item.item_name || '';
            }
        } else {
            // Find and apply template
            const template = itemTemplates.find(t => t.id == templateId);
            if (template) {
                item.template_id = template.id;
                item.item = template.item_name;
                item.item_name = template.item_name;
                item.description = template.description || '';
                item.quantity = template.default_quantity || 1;
                item.unitPrice = template.default_unit_price || 0;
                item.unit_price = template.default_unit_price || 0;
                item.tax = template.default_tax_percentage || 0;
                item.tax_percentage = template.default_tax_percentage || 0;

                // Calculate total
                const quantity = item.quantity || 1;
                const unitPrice = item.unitPrice || item.unit_price || 0;
                const taxPercent = item.tax || item.tax_percentage || 0;
                const subtotal = quantity * unitPrice;
                const taxAmount = subtotal * (taxPercent / 100);
                item.total = subtotal + taxAmount;

                // Hide manual input when template is selected
                if (inputField) {
                    inputField.style.display = 'none';
                    inputField.value = '';
                }
            }
        }

        renderLineItems();
    }

    // Quotation Modal
    async function createQuotation() {
        editingQuotationId = null;
        lineItems = [
            { id: Date.now(), item: '', description: '', quantity: 1, unitPrice: 0, tax: 0, total: 0, template_id: null }
        ];
        
        // Reset form
        document.getElementById('quotationClient').value = '';
        document.getElementById('quotationDate').value = new Date().toISOString().split('T')[0];
        const validUntil = new Date();
        validUntil.setDate(validUntil.getDate() + 30);
        document.getElementById('quotationValidUntil').value = validUntil.toISOString().split('T')[0];
        document.getElementById('internalNotes').value = '';
        document.getElementById('termsConditions').value = 'Payment is due within 30 days of invoice date. Late payments may incur a 5% monthly fee.';
        document.getElementById('discountAmount').value = '0';
        document.getElementById('discountType').value = 'amount';
        
        // Load next quotation number
        try {
            const response = await fetch(API_ROUTES.nextQuotationNumber);
            const result = await response.json();
            if (result.success && result.data.quotation_number) {
                document.getElementById('quotationNumber').value = result.data.quotation_number;
            } else {
                document.getElementById('quotationNumber').value = 'Auto-generated';
            }
        } catch (error) {
            console.error('Error loading quotation number:', error);
            document.getElementById('quotationNumber').value = 'Auto-generated';
        }
        
        document.querySelector('.modal-title').textContent = 'Create New Quotation';
        itemSuggestSelectionMade = false;
        renderLineItems();
        document.getElementById('quotationModal').classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.querySelector('#quotationModal .item-input-suggest')?.focus(), 50);
    }

    function closeQuotationModal() {
        document.getElementById('quotationModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('quotationModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeQuotationModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeQuotationModal();
        }
    });

    async function saveDraft() {
        await saveQuotation('draft');
    }

    function previewQuotation() {
        alert('Preview quotation (PDF view would open here)');
    }


    async function saveQuotation(status = 'draft') {
        const clientId = document.getElementById('quotationClient').value;
        const quotationDate = document.getElementById('quotationDate').value;
        const validUntil = document.getElementById('quotationValidUntil').value;
        
        if (!clientId) {
            alert('Please select a client');
            return;
        }

        if (lineItems.length === 0 || lineItems.every(item => !item.item && !item.item_name)) {
            alert('Please add at least one item');
            return;
        }

        // Prepare items data
        const items = lineItems
            .filter(item => item.item || item.item_name)
            .map(item => ({
                item_name: item.item || item.item_name,
                description: item.description || '',
                quantity: parseFloat(item.quantity) || 1,
                unit_price: parseFloat(item.unitPrice || item.unit_price) || 0,
                tax_percentage: parseFloat(item.tax || item.tax_percentage) || 0,
            }));

        const data = {
            client_id: parseInt(clientId),
            quotation_date: quotationDate,
            valid_until: validUntil,
            status: status,
            items: items,
            discount_amount: parseFloat(document.getElementById('discountAmount').value) || 0,
            discount_type: document.getElementById('discountType').value,
            internal_notes: document.getElementById('internalNotes').value,
            terms_conditions: document.getElementById('termsConditions').value,
        };

        try {
            const url = editingQuotationId ? API_ROUTES.update(editingQuotationId) : API_ROUTES.store;
            const method = editingQuotationId ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(data),
            });

            const result = await response.json();

            if (result.success) {
                // Update quotation number in the form if it was auto-generated
                if (result.data && result.data.quotation_number) {
                    document.getElementById('quotationNumber').value = result.data.quotation_number;
                }
                
                alert(`Quotation ${status === 'draft' ? 'saved as draft' : 'sent'} successfully!`);
                closeQuotationModal();
                const statusFilter = document.getElementById('statusFilter').value;
                const search = document.getElementById('quotationSearch').value;
                const month = document.getElementById('monthFilter')?.value;
                loadQuotations(currentPage, statusFilter, search, month);
                loadStats();
            } else {
                alert('Error: ' + (result.message || 'Failed to save quotation'));
            }
        } catch (error) {
            console.error('Error saving quotation:', error);
            alert('Error saving quotation. Please try again.');
        }
    }

    function viewQuotation(id) {
        // Open PDF in new tab
        window.open(API_ROUTES.pdf(id), '_blank');
    }

    // View Status History
    async function viewStatusHistory(id) {
        try {
            document.getElementById('statusHistoryModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            
            const body = document.getElementById('statusHistoryBody');
            body.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <div class="spinner"></div>
                    <p>Loading status history...</p>
                </div>
            `;

            const response = await fetch(API_ROUTES.statusHistory(id));
            const result = await response.json();

            if (result.success && result.data) {
                const history = result.data;
                
                if (history.length === 0) {
                    body.innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                            <p>No status history available.</p>
                        </div>
                    `;
                } else {
                    body.innerHTML = `
                        <div class="status-history-list">
                            ${history.map((item, index) => `
                                <div class="status-history-item ${index === 0 ? 'current' : ''}">
                                    <div class="status-history-timeline">
                                        <div class="status-history-dot"></div>
                                        ${index < history.length - 1 ? '<div class="status-history-line"></div>' : ''}
                                    </div>
                                    <div class="status-history-content">
                                        <div class="status-history-header">
                                            <span class="status-badge ${item.status}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span>
                                            <span class="status-history-date">${item.changed_at_formatted}</span>
                                        </div>
                                        ${item.previous_status ? `
                                            <div class="status-history-change">
                                                <span class="status-history-label">Changed from:</span>
                                                <span class="status-badge ${item.previous_status}" style="font-size: 0.75rem;">${item.previous_status.charAt(0).toUpperCase() + item.previous_status.slice(1)}</span>
                                                <span style="margin: 0 0.5rem;">→</span>
                                                <span class="status-badge ${item.status}" style="font-size: 0.75rem;">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span>
                                            </div>
                                        ` : ''}
                                        <div class="status-history-user">
                                            <span class="status-history-label">Changed by:</span>
                                            <span>${item.changed_by}</span>
                                        </div>
                                        ${item.notes ? `
                                            <div class="status-history-notes">
                                                <span class="status-history-label">Notes:</span>
                                                <span>${item.notes}</span>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                }
            } else {
                body.innerHTML = `
                    <div style="text-align: center; padding: 2rem; color: var(--text-danger, #dc2626);">
                        <p>Error loading status history.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading status history:', error);
            document.getElementById('statusHistoryBody').innerHTML = `
                <div style="text-align: center; padding: 2rem; color: var(--text-danger, #dc2626);">
                    <p>Error loading status history. Please try again.</p>
                </div>
            `;
        }
    }

    function closeStatusHistoryModal() {
        document.getElementById('statusHistoryModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close status history modal when clicking outside
    document.getElementById('statusHistoryModal')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeStatusHistoryModal();
        }
    });

    // Send Quotation Email
    async function sendQuotationEmail(id) {
        const quotation = quotationsData.find(q => q.id === id);
        if (!quotation) {
            alert('Quotation not found.');
            return;
        }

        if (confirm(`Send quotation ${quotation.quotation_number} to client via email?`)) {
            try {
                const response = await fetch(API_ROUTES.sendEmail(id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });

                const result = await response.json();

                // Check if response was successful
                if (response.ok && result.success) {
                    alert('Quotation sent successfully!');
                    // Update status to 'sent' and reload
                    const statusFilter = document.getElementById('statusFilter').value;
                    const search = document.getElementById('quotationSearch').value;
                    const month = document.getElementById('monthFilter')?.value;
                    loadQuotations(currentPage, statusFilter, search, month);
                } else {
                    // Handle error response - check if it's a Gmail integration error
                    if (response.status === 400 && result.message && result.message.includes('Gmail integration')) {
                        alert('Error: ' + result.message + '\n\nPlease configure Gmail integration in the Integrations section before sending quotations via email.');
                    } else {
                        alert('Error: ' + (result.message || 'Error sending quotation email. Please ensure Gmail integration is configured.'));
                    }
                }
            } catch (error) {
                console.error('Error sending quotation email:', error);
                alert('Error: Unable to send quotation email. Please check your internet connection and try again.');
            }
        }
    }

    // Update Quotation Status
    async function updateQuotationStatus(id, status) {
        try {
            const response = await fetch(API_ROUTES.updateStatus(id), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ status: status }),
            });

            const result = await response.json();

            if (result.success) {
                // Reload quotations
                const statusFilter = document.getElementById('statusFilter').value;
                const search = document.getElementById('quotationSearch').value;
                const month = document.getElementById('monthFilter')?.value;
                loadQuotations(currentPage, statusFilter, search, month);
            } else {
                alert(result.message || 'Error updating quotation status. Please try again.');
            }
        } catch (error) {
            console.error('Error updating quotation status:', error);
            alert('Error updating quotation status. Please try again.');
        }
    }

    async function editQuotation(id) {
        try {
            const response = await fetch(API_ROUTES.show(id));
            const result = await response.json();
            if (result.success) {
                const quotation = result.data;
                editingQuotationId = quotation.id;
                
                document.getElementById('quotationClient').value = quotation.client_id;
                document.getElementById('quotationNumber').value = quotation.quotation_number || 'Auto-generated';
                document.getElementById('quotationDate').value = quotation.quotation_date;
                document.getElementById('quotationValidUntil').value = quotation.valid_until;
                document.getElementById('internalNotes').value = quotation.internal_notes || '';
                document.getElementById('termsConditions').value = quotation.terms_conditions || '';
                document.getElementById('discountAmount').value = quotation.discount_amount || '0';
                document.getElementById('discountType').value = quotation.discount_type || 'amount';
                
                await loadItemTemplates();
                lineItems = quotation.items.map((item, index) => ({
                    id: Date.now() + index,
                    item: item.item_name,
                    item_name: item.item_name,
                    description: item.description,
                    quantity: item.quantity,
                    unitPrice: item.unit_price,
                    unit_price: item.unit_price,
                    tax: item.tax_percentage,
                    tax_percentage: item.tax_percentage,
                    total: item.total,
                    template_id: null, // Will be matched on render if template exists
                }));
                
                document.querySelector('.modal-title').textContent = 'Edit Quotation';
                itemSuggestSelectionMade = false;
                renderLineItems();
                document.getElementById('quotationModal').classList.add('active');
                document.body.style.overflow = 'hidden';
                setTimeout(() => document.querySelector('#quotationModal .item-input-suggest')?.focus(), 100);
            }
        } catch (error) {
            console.error('Error loading quotation:', error);
            alert('Error loading quotation');
        }
    }

    async function deleteQuotation(id) {
        if (!confirm('Are you sure you want to delete this quotation?')) {
            return;
        }

        try {
            const response = await fetch(API_ROUTES.destroy(id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const result = await response.json();

            if (result.success) {
                alert('Quotation deleted successfully!');
                const status = document.getElementById('statusFilter').value;
                const search = document.getElementById('quotationSearch').value;
                const month = document.getElementById('monthFilter')?.value;
                loadQuotations(currentPage, status, search, month);
                loadStats();
            } else {
                alert('Error: ' + (result.message || 'Failed to delete quotation'));
            }
        } catch (error) {
            console.error('Error deleting quotation:', error);
            alert('Error deleting quotation. Please try again.');
        }
    }


    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadClients();
        loadStats();
        const month = document.getElementById('monthFilter')?.value || new Date().toISOString().slice(0, 7);
        loadQuotations(1, 'all', '', month);
        
        // Set default valid until date
        const validUntil = new Date();
        validUntil.setDate(validUntil.getDate() + 30);
        const validUntilInput = document.getElementById('quotationValidUntil');
        if (validUntilInput && !validUntilInput.value) {
            validUntilInput.value = validUntil.toISOString().split('T')[0];
        }
        
        // Set default quotation date
        const quotationDateInput = document.getElementById('quotationDate');
        if (quotationDateInput && !quotationDateInput.value) {
            quotationDateInput.value = new Date().toISOString().split('T')[0];
        }
    });

    // Window Resize Handler
    window.addEventListener('resize', updateView);
</script>
@endpush

