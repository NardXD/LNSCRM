@extends('layouts.app')

@section('title', 'Time Sheets & Payroll')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Time Sheets & Payroll</h1>
        <p class="page-subtitle">Track time, compute salaries, and generate payroll reports</p>
    </div>

    <div class="payroll-container">
        <!-- Tabs Navigation -->
        <div class="payroll-tabs">
            @if(auth()->user()?->hasPermission('view_time_in_out'))
            <button class="tab-btn active" data-tab="time-tracking">Time In/Out</button>
            @endif
            @if(auth()->user()?->hasPermission('view_payroll_report'))
            <button class="tab-btn {{ !auth()->user()?->hasPermission('view_time_in_out') ? 'active' : '' }}" data-tab="payroll-reports">Payroll Reports</button>
            @endif
            @if(auth()->user()?->hasPermission('view_saved_for_wise'))
            <button class="tab-btn" data-tab="saved-for-wise">Saved for Wise</button>
            @endif
            @if(auth()->user()?->hasPermission('generate_payroll_report'))
            <button class="tab-btn" data-tab="converted-invoices">Converted to Invoice</button>
            @endif
        </div>

        <!-- Time In/Out Tracking Tab -->
        <div class="tab-content {{ auth()->user()?->hasPermission('view_time_in_out') ? 'active' : '' }}" id="timeTrackingTab">
            <div class="section-header">
                <h2 class="section-title">Time In/Out Tracking</h2>
                <div class="section-actions">
                    <select class="filter-select" id="employeeFilter">
                        <option value="all">All Employees</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                    <div class="date-range-filter">
                        <input type="date" class="date-input" id="dateStartFilter" value="{{ date('Y-m-01') }}">
                        <span class="date-range-separator">to</span>
                        <input type="date" class="date-input" id="dateEndFilter" value="{{ date('Y-m-d') }}">
                    </div>
                    @if(auth()->user()?->hasPermission('export_time_in_out'))
                    <button class="btn-primary" onclick="exportTimeLogs()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Export
                    </button>
                    @endif
                </div>
            </div>

            <!-- Time Logs Table -->
            <div class="time-logs-section">
                <div class="table-container">
                    <table class="data-table" id="timeLogsTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Total Hours</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="timeLogsTableBody">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="time-logs-cards" id="timeLogsCards">
                    <!-- Cards will be populated by JavaScript -->
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    <div class="pagination-info">
                        <span id="timePaginationInfo">Showing 1 to 10 of 50 results</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="timePrevBtn" disabled>Previous</button>
                        <div class="pagination-numbers" id="timePaginationNumbers"></div>
                        <button class="pagination-btn" id="timeNextBtn">Next</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Reports Tab -->
        <div class="tab-content {{ !auth()->user()?->hasPermission('view_time_in_out') && auth()->user()?->hasPermission('view_payroll_report') ? 'active' : '' }}" id="payrollReportsTab">
            <div class="section-header">
                <h2 class="section-title">Payroll Reports</h2>
                <div class="section-actions">
                    <select class="filter-select" id="reportClientFilter">
                        <option value="all">All Clients</option>
                    </select>
                    <select class="filter-select" id="reportPaymentStatusFilter">
                        <option value="all">Client Payment Status</option>
                        <option value="paid">Paid clients only</option>
                        <option value="unpaid">Unpaid clients only</option>
                        <option value="partial">Partially paid</option>
                        <option value="not_invoiced">Not invoiced</option>
                    </select>
                    <div class="date-range-filter">
                        <input type="date" class="date-input" id="reportDateStartFilter" value="{{ date('Y-m-01') }}">
                        <span class="date-range-separator">to</span>
                        <input type="date" class="date-input" id="reportDateEndFilter" value="{{ date('Y-m-t') }}">
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="limitHoursToRequired" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="limitHoursToRequired" style="font-size: 0.875rem; color: var(--text-secondary); white-space: nowrap; cursor: pointer;">Limit hours to required hours</label>
                    </div>
                    @if(auth()->user()?->hasPermission('generate_payroll_report'))
                    <button class="btn-primary" onclick="generateReport()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                            <polyline points="17 6 23 6 23 12"/>
                        </svg>
                        Generate
                    </button>
                    <button class="btn-secondary" id="convertToInvoiceBtn" onclick="convertToInvoice()" title="Convert selected rows to invoice(s) - one per client. Limited to one conversion per date range." disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        Convert to Invoice
                    </button>
                    @endif
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; width: 100%; margin-top: 0.5rem;">
                    @if(auth()->user()?->hasPermission('export_payroll_report'))
                    <button class="btn-secondary" onclick="exportReport()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Export Excel
                    </button>
                    @endif
                    @if(auth()->user()?->hasPermission('generate_payroll_report'))
                    <button class="btn-secondary" onclick="savePayrollForWise()" title="Save payroll report for Wise bulk transfer per employee">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8"/>
                        </svg>
                        Save for Wise
                    </button>
                    @endif
                    </div>
                </div>
            </div>

            <!-- Report Preview -->
            <div class="report-preview">
                <div class="report-header">
                    <div class="report-title-section">
                        <h3 class="report-title">Payroll Summary Report</h3>
                        <p class="report-period" id="reportPeriod">Period: {{ date('M d, Y', strtotime(date('Y-m-01'))) }} - {{ date('M d, Y', strtotime(date('Y-m-t'))) }}</p>
                    </div>
                    <div class="report-meta">
                        <div class="meta-item">
                            <span class="meta-label">Generated:</span>
                            <span class="meta-value" id="reportGeneratedDate">{{ date('M d, Y') }}</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Total Employees:</span>
                            <span class="meta-value" id="reportTotalEmployees">0</span>
                        </div>
                    </div>
                </div>

                <div class="report-stats">
                    <div class="report-stat-card">
                        <span class="stat-label">Total Gross Pay</span>
                        <span class="stat-amount" id="reportTotalGrossPay">$0.00</span>
                    </div>
                    <div class="report-stat-card">
                        <span class="stat-label">Total Deductions</span>
                        <span class="stat-amount" id="reportTotalDeductions">$0.00</span>
                    </div>
                    <div class="report-stat-card highlight">
                        <span class="stat-label">Net Pay</span>
                        <span class="stat-amount" id="reportTotalNetPay">$0.00</span>
                    </div>
                    <div class="report-stat-card">
                        <span class="stat-label">Total Commission</span>
                        <span class="stat-amount" id="reportTotalCommission">$0.00</span>
                    </div>
                </div>
                @if(auth()->user()?->hasPermission('generate_payroll_report'))
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
                    <input type="number"
                           id="bulkHoursWorkedInput"
                           class="date-input"
                           step="0.1"
                           min="0"
                           placeholder="Hours worked"
                           style="max-width: 140px;">
                    <button class="btn-secondary" id="applyHoursToSelectedBtn" onclick="applyHoursWorkedToSelected()" title="Apply entered hours worked to selected employees" disabled>
                        Apply Hours
                    </button>
                </div>
                @endif

                <div class="report-table-section">
                    <div class="table-container">
                        <table class="data-table" id="payrollReportTable">
                            <colgroup>
                                <col class="col-select">
                                <col class="col-employee">
                                <col class="col-clients">
                                <col class="col-bill">
                                <col class="col-base">
                                <col class="col-hours">
                                <col class="col-required">
                                <col class="col-deductions">
                                <col class="col-commission">
                                <col class="col-net">
                                <col class="col-client-paid">
                                <col class="col-status">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="table-checkbox" id="selectAllPayrollRows" title="Select all"></th>
                                    <th>Employee</th>
                                    <th>Client(s)</th>
                                    <th>Bill Amount</th>
                                    <th>Base Salary</th>
                                    <th>Hours Worked</th>
                                    <th>Required Hours</th>
                                    <th>Deductions</th>
                                    <th>Commission</th>
                                    <th>Net Pay</th>
                                    <th>Client Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="reportTableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Saved for Wise Tab -->
        <div class="tab-content" id="savedForWiseTab">
            <div class="section-header" style="flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 class="section-title">Saved Payroll Reports for Wise</h2>
                    <p class="section-subtitle" style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">Send payroll to Wise per report or in bulk</p>
                </div>
            </div>
            <div id="wiseBalanceBar" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem;">
                <strong>Wise Balance:</strong>
                <span id="wiseBalanceContent"></span>
            </div>
            <div id="wiseStatusCounters" style="display: none; margin-bottom: 1rem; padding: 0.75rem 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem;">
                <strong>Summary:</strong>
                <span class="wise-counter wise-sent" style="margin-left: 0.75rem;">Sent: <span id="wiseCounterSent">0</span></span>
                <span class="wise-counter wise-pending" style="margin-left: 0.75rem;">Pending: <span id="wiseCounterPending">0</span></span>
                <span class="wise-counter wise-other" style="margin-left: 0.75rem;">Other: <span id="wiseCounterOther">0</span></span>
            </div>
            <!-- Same structure as Time In/Out: section > table-container + table-pagination -->
            <div class="time-logs-section">
                <div id="savedForWiseContent">
                    <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">Click the tab to load saved reports</div>
                </div>
            </div>
        </div>

        <!-- Converted to Invoice Tab -->
        <div class="tab-content" id="convertedInvoicesTab">
            <div class="section-header" style="flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 class="section-title">Converted to Invoice</h2>
                    <p class="section-subtitle" style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">List of payroll records that have been converted to client invoices</p>
                </div>
                <div class="section-actions">
                    <input type="month" class="date-input" id="convertedInvoicesMonthFilter">
                </div>
            </div>
            <div class="time-logs-section">
                <div class="table-container">
                    <table class="data-table" id="convertedInvoicesTable">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Client</th>
                                <th>Invoice #</th>
                                <th>Period</th>
                                <th>Bill Amount</th>
                                <th>Status</th>
                                <th>View</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="convertedInvoicesTableBody">
                            <tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">Click the tab to load converted invoices</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination" id="convertedInvoicesPaginationWrap">
                    <div class="pagination-info">
                        <span id="convertedInvoicesPaginationInfo">Showing 0 to 0 of 0 results</span>
                    </div>
                    <div class="pagination-controls">
                        <button class="pagination-btn" id="convertedInvoicesPrevBtn" disabled>Previous</button>
                        <div class="pagination-numbers" id="convertedInvoicesPaginationNumbers"></div>
                        <button class="pagination-btn" id="convertedInvoicesNextBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Time Tracking Modal -->
    <div class="modal-overlay" id="editTimeModal" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Edit Time Tracking</h3>
                <button class="modal-close" onclick="closeEditModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <form id="editTimeForm">
                    <input type="hidden" id="editRecordId">
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <input type="text" class="form-input" id="editEmployeeName" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="text" class="form-input" id="editDate" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time In</label>
                        <input type="time" class="form-input" id="editTimeIn" step="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time Out Date</label>
                        <input type="date" class="form-input" id="editTimeOutDate">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time Out</label>
                        <input type="time" class="form-input" id="editTimeOut" step="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Hours (Auto-calculated)</label>
                        <input type="text" class="form-input" id="editTotalHours" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason for Edit</label>
                        <textarea class="form-input" id="editReason" rows="3" placeholder="Optional: Provide a reason for this edit"></textarea>
                    </div>
                </form>
                <div class="edit-history-section" id="editHistorySection" style="margin-top: 2rem;">
                    <h4 style="font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin-bottom: 1rem;">Edit History</h4>
                    <div id="editHistoryList" style="max-height: 200px; overflow-y: auto;">
                        <div style="text-align: center; padding: 1rem; color: var(--text-secondary);">Loading history...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button class="btn-primary" onclick="saveTimeEdit()">Save Changes</button>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
    .payroll-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Tabs */
    .payroll-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .tab-btn {
        flex: 1;
        min-width: 150px;
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

    .tab-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .tab-btn.active {
        background: var(--accent);
        color: white;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Section Header */
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .section-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .section-subheader {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .subsection-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
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

    .btn.btn-primary, .btn.btn-secondary, .btn.btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-danger {
        background: #dc3545;
        color: white;
    }
    .btn-danger:hover {
        background: #c82333;
    }

    .btn-primary svg, .btn-secondary svg {
        width: 18px;
        height: 18px;
    }

    .btn.btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
    }

    .wise-status-badge {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .wise-status-badge.status-ready_for_wise { background: #e3f2fd; color: #1565c0; }
    .wise-status-badge.status-queued { background: #fff3e0; color: #e65100; }
    .wise-status-badge.status-sent { background: #e8f5e9; color: #2e7d32; }
    .wise-status-badge.status-pending { background: #f5f5f5; color: #616161; }
    .wise-status-badge.status-failed { background: #ffebee; color: #c62828; }
    .wise-error-reason { font-size: 0.7rem; color: #c62828; margin-top: 0.25rem; max-width: 320px; word-wrap: break-word; }
    .wise-status-badge.status-wise-outgoing-payment-sent { background: #e8f5e9; color: #2e7d32; }
    .wise-status-badge.status-wise-processing { background: #fff3e0; color: #e65100; }
    .wise-status-badge.status-wise-funds-converted { background: #e3f2fd; color: #1565c0; }
    .wise-status-badge.status-wise-incoming-payment-waiting { background: #f5f5f5; color: #616161; }
    .wise-status-badge.status-wise-cancelled,
    .wise-status-badge.status-wise-funds-refunded,
    .wise-status-badge.status-wise-bounced-back { background: #ffebee; color: #c62828; }

    .wise-download-dropdown { position: relative; display: inline-block; }
    .wise-download-menu {
        position: fixed;
        min-width: 140px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.18);
        z-index: 9999;
        padding: 0.25rem 0;
    }
    .wise-download-item {
        display: block;
        padding: 0.625rem 1rem;
        font-size: 0.875rem;
        color: var(--text-primary);
        text-decoration: none;
        white-space: nowrap;
    }
    .wise-download-item:hover {
        background: var(--bg-primary);
    }
    .wise-download-item:first-child { border-radius: 6px 6px 0 0; }
    .wise-download-item:last-child { border-radius: 0 0 6px 6px; }

    /* Filters */
    .filter-select, .date-input {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .filter-select:focus, .date-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .date-range-filter {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .date-range-separator {
        font-size: 0.875rem;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .filter-group {
        display: flex;
        gap: 0.75rem;
    }

    /* Tables */
    .table-container {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
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

    /* Time Logs Section */
    .time-logs-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    /* Salary Summary */
    .salary-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .summary-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .summary-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .summary-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .summary-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: var(--accent-light);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .summary-icon svg {
        width: 18px;
        height: 18px;
    }

    .summary-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .summary-value.highlight {
        color: var(--accent);
    }

    /* Payroll Reports */
    .report-preview {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 2rem;
    }

    .report-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid var(--border);
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .report-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .report-period {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .report-meta {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .meta-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .meta-value {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .report-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .report-stat-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .report-stat-card.highlight {
        background: var(--accent-light);
        border-color: var(--accent);
    }

    .stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .stat-amount {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .report-stat-card.highlight .stat-amount {
        color: var(--accent);
    }

    .report-table-section {
        margin-top: 2rem;
    }

    /* Payroll Report table: fit all columns within the container (no horizontal scroll) */
    .report-table-section .table-container {
        overflow-x: hidden;
    }

    #payrollReportTable {
        table-layout: fixed;
        width: 100%;
    }

    #payrollReportTable th,
    #payrollReportTable td {
        padding: 0.5rem 0.5rem;
        font-size: 0.8125rem;
        overflow: hidden;
        text-overflow: ellipsis;
        word-break: break-word;
    }

    #payrollReportTable th {
        white-space: normal;
    }

    #payrollReportTable .editable-bill-amount,
    #payrollReportTable .editable-base-salary,
    #payrollReportTable .editable-required-hours,
    #payrollReportTable .editable-deduction {
        padding: 0.375rem 0.375rem !important;
        font-size: 0.8125rem !important;
    }

    #payrollReportTable .client-multi-select {
        max-width: 100%;
    }

    /* Column widths tuned so 12 columns fit a typical desktop width */
    #payrollReportTable col.col-select { width: 2.5%; }
    #payrollReportTable col.col-employee { width: 11%; }
    #payrollReportTable col.col-clients { width: 12%; }
    #payrollReportTable col.col-bill { width: 8%; }
    #payrollReportTable col.col-base { width: 8%; }
    #payrollReportTable col.col-hours { width: 8%; }
    #payrollReportTable col.col-required { width: 7%; }
    #payrollReportTable col.col-deductions { width: 8%; }
    #payrollReportTable col.col-commission { width: 8%; }
    #payrollReportTable col.col-net { width: 8.5%; }
    #payrollReportTable col.col-client-paid { width: 9%; }
    #payrollReportTable col.col-status { width: 7%; }

    @media (max-width: 1024px) {
        /* On smaller screens, fall back to horizontal scroll so content stays readable */
        .report-table-section .table-container {
            overflow-x: auto;
        }

        #payrollReportTable {
            table-layout: auto;
            min-width: 900px;
        }
    }

    /* Multi-client selector in Payroll Report Client(s) column */
    .client-multi-select {
        position: relative;
        display: inline-block;
        max-width: 220px;
        width: 100%;
    }

    .client-multi-select-toggle {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        width: 100%;
        padding: 0.375rem 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: var(--text-primary);
        font-size: 0.8125rem;
        cursor: pointer;
        text-align: left;
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .client-multi-select-toggle:hover,
    .client-multi-select.open .client-multi-select-toggle {
        border-color: var(--accent);
    }

    .client-multi-select.open .client-multi-select-toggle {
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .client-multi-select-label {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .client-multi-select-caret {
        flex-shrink: 0;
        width: 12px;
        height: 12px;
        color: var(--text-secondary);
    }

    .client-multi-select-panel {
        position: fixed;
        z-index: 1000;
        min-width: 200px;
        max-width: 320px;
        max-height: 260px;
        overflow-y: auto;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
        padding: 0.25rem;
        display: none;
    }

    .client-multi-select.open .client-multi-select-panel {
        display: block;
    }

    .client-multi-select-actions {
        display: flex;
        gap: 0.25rem;
        padding: 0.25rem 0.375rem 0.5rem;
        border-bottom: 1px solid var(--border);
        margin-bottom: 0.25rem;
    }

    .client-multi-select-action {
        font-size: 0.75rem;
        color: var(--accent);
        background: none;
        border: none;
        padding: 0.125rem 0.25rem;
        cursor: pointer;
    }

    .client-multi-select-action:hover {
        text-decoration: underline;
    }

    .client-multi-select-option {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.5rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.8125rem;
        color: var(--text-primary);
    }

    .client-multi-select-option:hover {
        background: var(--bg-primary);
    }

    .client-multi-select-option input {
        cursor: pointer;
    }

    .client-multi-select-option-name {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .client-multi-select-option--disabled {
        color: var(--text-secondary);
        cursor: not-allowed;
    }

    .client-multi-select-option--disabled input {
        cursor: not-allowed;
    }

    .client-multi-select-option--disabled:hover {
        background: transparent;
    }

    .client-multi-select-badge {
        font-size: 0.625rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.125rem 0.375rem;
        border-radius: 100px;
        background: #d1fae5;
        color: #059669;
        flex-shrink: 0;
    }

    .client-multi-select-empty {
        padding: 0.5rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        text-align: center;
    }

    /* Badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.present {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.absent {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-badge.late {
        background: #fef3c7;
        color: #d97706;
    }

    .status-badge.pending {
        background: #fef3c7;
        color: #d97706;
    }

    .status-badge.approved {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.rejected {
        background: #fee2e2;
        color: #dc2626;
    }

    /* Invoice billing status (matches billing page) */
    .status-badge.draft {
        background: #e5e7eb;
        color: #374151;
    }

    .status-badge.sent {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.paid {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.overdue {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    /* Employee Cell */
    .employee-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .employee-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
    }

    .employee-name {
        font-weight: 500;
        color: var(--text-primary);
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

    .icon-btn svg {
        width: 16px;
        height: 16px;
    }

    /* Mobile Card Views */
    .time-logs-cards,
    .salary-cards {
        display: none;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .time-log-card,
    .salary-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
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
        margin-top: 1.5rem;
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

    /* Responsive */
    @media (min-width: 769px) {
        .table-container {
            display: block;
        }
        .time-logs-cards,
        .salary-cards {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .table-container {
            display: none !important;
        }
        .time-logs-cards,
        .salary-cards {
            display: flex !important;
        }

        .payroll-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-btn {
            min-width: 120px;
            font-size: 0.8125rem;
            padding: 0.625rem 1rem;
        }

        .section-header,
        .section-subheader {
            flex-direction: column;
            align-items: stretch;
        }

        .section-actions {
            width: 100%;
        }

        .filter-select,
        .date-input {
            flex: 1;
            min-width: 0;
        }

        .date-range-filter {
            width: 100%;
            flex-wrap: wrap;
        }

        .date-range-separator {
            flex-shrink: 0;
        }

        .salary-summary-grid,
        .report-stats {
            grid-template-columns: 1fr;
        }

        .report-header {
            flex-direction: column;
        }

        .table-pagination {
            flex-direction: column;
            align-items: stretch;
        }

        .pagination-controls {
            justify-content: center;
            width: 100%;
        }

        .card-details {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 480px) {
        .tab-btn {
            min-width: 100px;
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }

        .pagination-btn {
            font-size: 0.8125rem;
            padding: 0.5rem 0.75rem;
        }

        .pagination-number {
            min-width: 32px;
            height: 32px;
            font-size: 0.8125rem;
        }
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 1rem;
    }

    .modal-container {
        background: var(--bg-card);
        border-radius: 12px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        border-radius: 6px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .modal-close:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .modal-close svg {
        width: 20px;
        height: 20px;
    }

    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1.5rem;
        border-top: 1px solid var(--border);
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
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-input[readonly] {
        background: var(--bg-primary);
        cursor: not-allowed;
    }

    .edit-history-section {
        border-top: 1px solid var(--border);
        padding-top: 1.5rem;
    }

    .history-item {
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-bottom: 0.75rem;
    }

    .history-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .history-item-user {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .history-item-date {
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .history-item-changes {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-bottom: 0.25rem;
    }

    .history-item-reason {
        font-size: 0.8125rem;
        color: var(--text-primary);
        font-style: italic;
        margin-top: 0.5rem;
    }
</style>
@endpush

@push('scripts')
<script>
    // Permissions for frontend checks
    const wiseReportBaseUrl = "{{ url('api/payroll/payroll-report') }}";
    const userPermissions = {
        viewTimeInOut: {{ auth()->user()?->hasPermission('view_time_in_out') ? 'true' : 'false' }},
        editTimeInOut: {{ auth()->user()?->hasPermission('edit_time_in_out') ? 'true' : 'false' }},
        exportTimeInOut: {{ auth()->user()?->hasPermission('export_time_in_out') ? 'true' : 'false' }},
        viewPayrollReport: {{ auth()->user()?->hasPermission('view_payroll_report') ? 'true' : 'false' }},
        generatePayrollReport: {{ auth()->user()?->hasPermission('generate_payroll_report') ? 'true' : 'false' }},
        viewSavedForWise: {{ auth()->user()?->hasPermission('view_saved_for_wise') ? 'true' : 'false' }},
        exportPayrollReport: {{ auth()->user()?->hasPermission('export_payroll_report') ? 'true' : 'false' }}
    };

    // Tab Switching
    function kebabToCamel(str) {
        return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            const camelTabId = kebabToCamel(tabId);
            
            // Update buttons
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const tabContent = document.getElementById(camelTabId + 'Tab');
            if (tabContent) {
                tabContent.classList.add('active');
            }

            // Load saved reports when Saved for Wise tab is selected
            if (tabId === 'saved-for-wise') {
                fetchSavedPayrollReports();
            }
            // Load converted invoices when Converted to Invoice tab is selected
            if (tabId === 'converted-invoices') {
                loadConvertedInvoices();
            }
        });
    });

    // Time In/Out Data (will be fetched from API)
    let timeLogsData = [];
    let timeTotalRecords = 0;

    // Report Data
    let reportData = [];
    let allReportData = [];
    let originalReportData = []; // Store original data before applying hours limit
    let reportSummary = {
        total_employees: 0,
        total_gross_pay: 0,
        total_deductions: 0,
        total_net_pay: 0,
        total_commission: 0
    };

    // Helper function to convert decimal hours to hrs:mins:secs format
    function formatHoursToTime(decimalHours) {
        // Handle null, undefined, empty string, or NaN
        if (decimalHours === null || decimalHours === undefined || decimalHours === '' || isNaN(decimalHours)) {
            return '--';
        }
        
        // Convert to number if it's a string (handles "8.5 hrs" format)
        const hoursNum = typeof decimalHours === 'string' ? parseFloat(decimalHours) : decimalHours;
        
        if (isNaN(hoursNum) || hoursNum < 0) {
            return '--';
        }
        
        const hours = Math.floor(hoursNum);
        const minutesDecimal = (hoursNum - hours) * 60;
        const minutes = Math.floor(minutesDecimal);
        const secondsDecimal = (minutesDecimal - minutes) * 60;
        const seconds = Math.floor(secondsDecimal);
        
        return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    /** Wall-clock total from tracked seconds (matches time_tracking_records.hours_worked). */
    function formatSecondsToHms(totalSeconds) {
        const n = Math.max(0, Math.floor(parseInt(totalSeconds, 10) || 0));
        const h = Math.floor(n / 3600);
        const m = Math.floor((n % 3600) / 60);
        const s = n % 60;
        return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    function employeeHoursWorkedDecimal(emp) {
        if (emp && emp.hours_worked_seconds != null && emp.hours_worked_seconds !== '') {
            return (parseInt(emp.hours_worked_seconds, 10) || 0) / 3600;
        }
        return parseFloat(emp.hours_worked) || 0;
    }

    function isNetPayFromConversion(emp) {
        if (!emp || emp.net_pay_from_conversion !== true) {
            return false;
        }

        return pendingClientIdsFor(emp).length === 0;
    }

    function selectedReportClientId() {
        return document.getElementById('reportClientFilter')?.value || 'all';
    }

    function employeeClientNames(emp) {
        return String(emp?.clients || '')
            .split(',')
            .map(name => name.trim())
            .filter(name => !!name && name !== '—');
    }

    function effectiveClientIds(emp) {
        if (!emp) return [];
        if (Array.isArray(emp.selected_client_ids)) {
            return emp.selected_client_ids.map(id => String(id));
        }
        if (Array.isArray(emp.client_ids)) {
            return emp.client_ids.map(id => String(id));
        }
        return [];
    }

    function effectiveClientNames(emp) {
        if (!emp) return [];
        const names = employeeClientNames(emp);
        if (!Array.isArray(emp.client_ids) || emp.client_ids.length === 0 || names.length === 0) {
            return names;
        }
        if (!Array.isArray(emp.selected_client_ids)) {
            return names;
        }
        const selected = emp.selected_client_ids.map(id => String(id));
        const mapped = [];
        emp.client_ids.forEach((id, index) => {
            if (selected.includes(String(id)) && names[index]) {
                mapped.push(names[index]);
            }
        });
        return mapped;
    }

    function convertedClientIdsFor(emp) {
        if (!emp || !Array.isArray(emp.converted_client_ids)) return [];
        return emp.converted_client_ids.map(id => String(id));
    }

    function pendingClientIdsFor(emp) {
        const allIds = Array.isArray(emp?.client_ids) ? emp.client_ids.map(id => String(id)) : [];
        const converted = convertedClientIdsFor(emp);
        return allIds.filter(id => !converted.includes(id));
    }

    function initializeSelectedClientIds(data) {
        (data || []).forEach(emp => {
            if (!emp) return;
            const ids = Array.isArray(emp.client_ids) ? emp.client_ids.map(id => String(id)) : [];
            const converted = convertedClientIdsFor(emp);
            const pending = ids.filter(id => !converted.includes(id));
            const existing = Array.isArray(emp.selected_client_ids)
                ? emp.selected_client_ids.map(id => String(id)).filter(id => ids.includes(id))
                : null;
            // Default to clients that have not yet been invoiced so the user can
            // immediately convert the remaining ones. If everything is invoiced,
            // fall back to all so the row still displays meaningful labels.
            const fallback = pending.length > 0 ? pending : ids.slice();
            emp.selected_client_ids = existing !== null ? existing : fallback;
        });
    }

    function employeeHasClient(emp, clientId) {
        if (!emp) return false;

        const value = String(clientId || '');
        if (!value) return false;

        const selectedIds = effectiveClientIds(emp);
        if (selectedIds.includes(value)) {
            return true;
        }

        if (value.startsWith('name:')) {
            const targetName = value.slice(5).trim().toLowerCase();
            const names = effectiveClientNames(emp).map(name => name.toLowerCase());
            return names.includes(targetName);
        }

        return false;
    }

    function populateReportClientFilterFromData(data) {
        const filter = document.getElementById('reportClientFilter');
        if (!filter) return;

        const previousValue = filter.value || 'all';
        const clientsById = new Map();
        const clientsByName = new Map();

        (data || []).forEach(emp => {
            const ids = Array.isArray(emp.client_ids) ? emp.client_ids : [];
            const names = String(emp.clients || '')
                .split(',')
                .map(name => name.trim())
                .filter(name => !!name && name !== '—');

            ids.forEach((id, index) => {
                const key = String(id);
                if (!key || key === '0' || clientsById.has(key)) return;
                const name = names[index] || `Client #${key}`;
                clientsById.set(key, name);
            });

            // Fallback: include names even when client IDs are not present in data.
            names.forEach(name => {
                if (!name) return;
                const normalized = name.toLowerCase();
                if (!clientsByName.has(normalized)) {
                    clientsByName.set(normalized, name);
                }
            });
        });

        const idOptions = Array.from(clientsById.entries()).sort((a, b) => a[1].localeCompare(b[1]));
        const nameOnlyOptions = Array.from(clientsByName.entries())
            .filter(([, name]) => !Array.from(clientsById.values()).some(idName => idName.toLowerCase() === name.toLowerCase()))
            .sort((a, b) => a[1].localeCompare(b[1]));

        filter.innerHTML = '<option value="all">All Clients</option>' +
            idOptions.map(([id, name]) => `<option value="${id}">${name}</option>`).join('') +
            nameOnlyOptions.map(([normalized, name]) => `<option value="name:${normalized}">${name}</option>`).join('');

        const hasPrevious = previousValue === 'all' ||
            clientsById.has(String(previousValue)) ||
            (String(previousValue).startsWith('name:') && clientsByName.has(String(previousValue).slice(5)));
        filter.value = hasPrevious ? previousValue : 'all';
    }

    function selectedPaymentStatusFilter() {
        return document.getElementById('reportPaymentStatusFilter')?.value || 'all';
    }

    function applyReportClientFilterAndRender() {
        const clientId = selectedReportClientId();
        const paymentStatus = selectedPaymentStatusFilter();

        reportData = allReportData.filter(emp => {
            const matchesClient = clientId === 'all' || employeeHasClient(emp, clientId);
            const matchesPayment = paymentStatus === 'all' || (emp.client_payment_status || 'not_invoiced') === paymentStatus;
            return matchesClient && matchesPayment;
        });

        recalculateReportSummary();
        renderReport();
    }

    // Time Logs Rendering
    let timeCurrentPage = 1;
    const timeItemsPerPage = 10;
    let timeTotalPages = 1;
    let isLoadingTimeLogs = false;

    // Fetch time tracking records from API
    async function fetchTimeTrackingRecords() {
        if (isLoadingTimeLogs) return;
        
        isLoadingTimeLogs = true;
        const tbody = document.getElementById('timeLogsTableBody');
        const cards = document.getElementById('timeLogsCards');
        
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem;">Loading...</td></tr>';
        cards.innerHTML = '<div style="text-align: center; padding: 2rem;">Loading...</div>';

        try {
            const employeeId = document.getElementById('employeeFilter').value;
            let startDate = document.getElementById('dateStartFilter').value;
            let endDate = document.getElementById('dateEndFilter').value;

            // Default to current month if dates are not set
            if (!startDate || !endDate) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const firstDay = `${year}-${month}-01`;
                const lastDay = `${year}-${month}-${new Date(year, now.getMonth() + 1, 0).getDate()}`;
                
                if (!startDate) {
                    startDate = firstDay;
                    document.getElementById('dateStartFilter').value = firstDay;
                }
                if (!endDate) {
                    endDate = lastDay;
                    document.getElementById('dateEndFilter').value = lastDay;
                }
            }

            const params = new URLSearchParams({
                employee_id: employeeId,
                start_date: startDate,
                end_date: endDate,
                page: timeCurrentPage,
                per_page: timeItemsPerPage
            });

            const response = await fetch(`/api/payroll/time-tracking-records?${params}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Expected JSON but got:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Route may not be found.');
            }
            
            const result = await response.json();

            if (result.success) {
                timeLogsData = result.data;
                timeTotalRecords = result.pagination.total;
                timeTotalPages = result.pagination.last_page;
                renderTimeLogs();
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: red;">Error: ' + (result.message || 'Failed to load data') + '</td></tr>';
                cards.innerHTML = '<div style="text-align: center; padding: 2rem; color: red;">Error: ' + (result.message || 'Failed to load data') + '</div>';
            }
        } catch (error) {
            console.error('Error fetching time tracking records:', error);
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem; color: red;">Error loading data: ' + error.message + '. Please refresh the page or check if you are logged in.</td></tr>';
            cards.innerHTML = '<div style="text-align: center; padding: 2rem; color: red;">Error loading data: ' + error.message + '. Please refresh the page or check if you are logged in.</div>';
        } finally {
            isLoadingTimeLogs = false;
        }
    }

    function renderTimeLogs() {
        const tbody = document.getElementById('timeLogsTableBody');
        const cards = document.getElementById('timeLogsCards');

        if (timeLogsData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 2rem;">No records found</td></tr>';
            cards.innerHTML = '<div style="text-align: center; padding: 2rem;">No records found</div>';
            updateTimePagination();
            return;
        }

        if (window.innerWidth > 768) {
            tbody.innerHTML = timeLogsData.map(log => `
                <tr>
                    <td>
                        <div class="employee-cell">
                            <div class="employee-avatar">${log.employee.initials}</div>
                            <span class="employee-name">${log.employee.name}</span>
                        </div>
                    </td>
                    <td>${log.date}</td>
                    <td>${log.timeIn}</td>
                    <td>${log.timeOut}</td>
                    <td>${formatHoursToTime(log.totalHours)}</td>
                    <td>
                        <div class="table-actions">
                            ${userPermissions.editTimeInOut ? `<button class="icon-btn" title="Edit" onclick="openEditModal(${log.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>` : '<span style="color: var(--text-muted);">No access</span>'}
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            cards.innerHTML = timeLogsData.map(log => `
                <div class="time-log-card">
                    <div class="card-header">
                        <div class="employee-cell">
                            <div class="employee-avatar">${log.employee.initials}</div>
                            <div>
                                <div class="card-title">${log.employee.name}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">${log.date}</div>
                            </div>
                        </div>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Time In</span>
                            <span class="card-value">${log.timeIn}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Time Out</span>
                            <span class="card-value">${log.timeOut}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Total Hours</span>
                            <span class="card-value">${formatHoursToTime(log.totalHours)}</span>
                        </div>
                    </div>
                    ${userPermissions.editTimeInOut ? `<div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        <button class="btn-primary" onclick="openEditModal(${log.id})" style="width: 100%;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; margin-right: 0.5rem;">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit
                        </button>
                    </div>` : ''}
                </div>
            `).join('');
        }

        updateTimePagination();
    }

    function updateTimePagination() {
        const info = document.getElementById('timePaginationInfo');
        const numbers = document.getElementById('timePaginationNumbers');
        const prevBtn = document.getElementById('timePrevBtn');
        const nextBtn = document.getElementById('timeNextBtn');

        const start = timeTotalRecords > 0 ? (timeCurrentPage - 1) * timeItemsPerPage + 1 : 0;
        const end = Math.min(timeCurrentPage * timeItemsPerPage, timeTotalRecords);
        info.textContent = `Showing ${start} to ${end} of ${timeTotalRecords} results`;

        prevBtn.disabled = timeCurrentPage === 1;
        nextBtn.disabled = timeCurrentPage === timeTotalPages;

        let html = '';
        const maxVisible = 5;
        let startPage = Math.max(1, timeCurrentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(timeTotalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<button class="pagination-number" data-page="1">1</button>`;
            if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-number ${i === timeCurrentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (endPage < timeTotalPages) {
            if (endPage < timeTotalPages - 1) html += `<span class="pagination-number ellipsis">...</span>`;
            html += `<button class="pagination-number" data-page="${timeTotalPages}">${timeTotalPages}</button>`;
        }

        numbers.innerHTML = html;
        numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                timeCurrentPage = parseInt(btn.dataset.page);
                fetchTimeTrackingRecords();
            });
        });
    }

    document.getElementById('timePrevBtn').addEventListener('click', () => {
        if (timeCurrentPage > 1) {
            timeCurrentPage--;
            fetchTimeTrackingRecords();
        }
    });

    document.getElementById('timeNextBtn').addEventListener('click', () => {
        if (timeCurrentPage < timeTotalPages) {
            timeCurrentPage++;
            fetchTimeTrackingRecords();
        }
    });

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderClientsCell(emp, index) {
        const names = employeeClientNames(emp);
        const ids = Array.isArray(emp.client_ids) ? emp.client_ids.map(id => String(id)) : [];

        if (ids.length < 2) {
            return escapeHtml(emp.clients || '—');
        }

        const convertedIds = convertedClientIdsFor(emp);
        const selected = Array.isArray(emp.selected_client_ids)
            ? emp.selected_client_ids.map(id => String(id))
            : ids.slice();

        const selectedNames = ids
            .map((id, i) => (selected.includes(id) ? (names[i] || `Client #${id}`) : null))
            .filter(Boolean);

        let label;
        if (selectedNames.length === 0) {
            label = 'No clients';
        } else if (selectedNames.length === ids.length) {
            label = `All clients (${ids.length})`;
        } else if (selectedNames.length === 1) {
            label = selectedNames[0];
        } else {
            label = `${selectedNames.length} of ${ids.length} selected`;
        }

        const options = ids.map((id, i) => {
            const name = names[i] || `Client #${id}`;
            const isInvoiced = convertedIds.includes(id);
            const checked = selected.includes(id) ? 'checked' : '';
            const disabled = isInvoiced ? 'disabled' : '';
            const optionClass = isInvoiced ? 'client-multi-select-option client-multi-select-option--disabled' : 'client-multi-select-option';
            const badge = isInvoiced ? '<span class="client-multi-select-badge">Invoiced</span>' : '';
            const optionTitle = isInvoiced
                ? `${name} — already invoiced for this period`
                : name;
            return `
                <label class="${optionClass}" title="${escapeHtml(optionTitle)}">
                    <input type="checkbox" class="client-multi-option" data-index="${index}" data-client-id="${escapeHtml(id)}" ${checked} ${disabled}>
                    <span class="client-multi-select-option-name">${escapeHtml(name)}</span>
                    ${badge}
                </label>
            `;
        }).join('');

        return `
            <div class="client-multi-select" data-index="${index}" title="${escapeHtml(selectedNames.join(', ') || 'No clients selected')}">
                <button type="button" class="client-multi-select-toggle" data-action="toggle-client-select" data-index="${index}">
                    <span class="client-multi-select-label">${escapeHtml(label)}</span>
                    <svg class="client-multi-select-caret" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <div class="client-multi-select-panel">
                    <div class="client-multi-select-actions">
                        <button type="button" class="client-multi-select-action" data-action="select-all-clients" data-index="${index}">Select pending</button>
                        <button type="button" class="client-multi-select-action" data-action="clear-clients" data-index="${index}">Clear</button>
                    </div>
                    ${options || '<div class="client-multi-select-empty">No clients available</div>'}
                </div>
            </div>
        `;
    }

    function setClientSelection(index, clientIds) {
        if (index < 0 || index >= reportData.length) return;
        const emp = reportData[index];
        if (!emp) return;
        const validIds = Array.isArray(emp.client_ids) ? emp.client_ids.map(id => String(id)) : [];
        const nextSelection = clientIds.filter(id => validIds.includes(String(id))).map(id => String(id));
        emp.selected_client_ids = nextSelection;

        // Mirror selection back into allReportData / originalReportData for the same employee.
        const mirror = (collection) => {
            if (!Array.isArray(collection)) return;
            const target = collection.find(row => row && row.employee_id === emp.employee_id);
            if (target) {
                target.selected_client_ids = nextSelection.slice();
            }
        };
        mirror(allReportData);
        mirror(originalReportData);
    }

    function positionClientSelectPanel(container) {
        const toggle = container.querySelector('.client-multi-select-toggle');
        const panel = container.querySelector('.client-multi-select-panel');
        if (!toggle || !panel) return;

        const rect = toggle.getBoundingClientRect();
        const panelWidth = Math.max(rect.width, 200);
        const viewportHeight = window.innerHeight;
        const viewportWidth = window.innerWidth;

        let top = rect.bottom + 4;
        let left = rect.left;

        const estimatedPanelHeight = Math.min(panel.scrollHeight || 240, 260);
        if (top + estimatedPanelHeight > viewportHeight - 8) {
            top = Math.max(8, rect.top - estimatedPanelHeight - 4);
        }
        if (left + panelWidth > viewportWidth - 8) {
            left = Math.max(8, viewportWidth - panelWidth - 8);
        }

        panel.style.top = `${top}px`;
        panel.style.left = `${left}px`;
        panel.style.minWidth = `${panelWidth}px`;
    }

    function repositionOpenClientSelectPanels() {
        document.querySelectorAll('.client-multi-select.open').forEach(positionClientSelectPanel);
    }

    function toggleClientSelectPanel(index, forceOpen) {
        document.querySelectorAll('.client-multi-select').forEach(el => {
            const elIndex = el.dataset.index;
            if (String(elIndex) === String(index)) {
                let shouldOpen;
                if (forceOpen === true) {
                    shouldOpen = true;
                } else if (forceOpen === false) {
                    shouldOpen = false;
                } else {
                    shouldOpen = !el.classList.contains('open');
                }
                el.classList.toggle('open', shouldOpen);
                if (shouldOpen) {
                    positionClientSelectPanel(el);
                }
            } else {
                el.classList.remove('open');
            }
        });
    }

    window.addEventListener('scroll', repositionOpenClientSelectPanels, true);
    window.addEventListener('resize', repositionOpenClientSelectPanels);

    function refreshClientsCell(index) {
        const row = document.querySelector(`tr[data-employee-index="${index}"]`);
        if (!row) return;
        const cell = row.children[2];
        if (!cell) return;
        const wasOpen = cell.querySelector('.client-multi-select.open') !== null;
        cell.innerHTML = renderClientsCell(reportData[index], index);
        if (wasOpen) {
            toggleClientSelectPanel(index, true);
        }
    }

    function updateTopFilterAfterClientChange(emp) {
        const topFilter = selectedReportClientId();
        if (topFilter === 'all') {
            return false;
        }
        return !employeeHasClient(emp, topFilter);
    }

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-action="toggle-client-select"]');
        if (toggle) {
            event.preventDefault();
            event.stopPropagation();
            toggleClientSelectPanel(toggle.dataset.index);
            return;
        }

        const selectAll = event.target.closest('[data-action="select-all-clients"]');
        if (selectAll) {
            event.preventDefault();
            event.stopPropagation();
            const index = parseInt(selectAll.dataset.index, 10);
            if (!isNaN(index) && reportData[index]) {
                // Default to clients that have not yet been invoiced; fall back to
                // all clients if every assigned client is already invoiced.
                const pending = pendingClientIdsFor(reportData[index]);
                const allIds = Array.isArray(reportData[index].client_ids) ? reportData[index].client_ids.map(id => String(id)) : [];
                setClientSelection(index, pending.length > 0 ? pending : allIds);
                refreshClientsCell(index);
                if (updateTopFilterAfterClientChange(reportData[index])) {
                    applyReportClientFilterAndRender();
                }
            }
            return;
        }

        const clear = event.target.closest('[data-action="clear-clients"]');
        if (clear) {
            event.preventDefault();
            event.stopPropagation();
            const index = parseInt(clear.dataset.index, 10);
            if (!isNaN(index) && reportData[index]) {
                setClientSelection(index, []);
                refreshClientsCell(index);
                if (updateTopFilterAfterClientChange(reportData[index])) {
                    applyReportClientFilterAndRender();
                }
            }
            return;
        }

        if (!event.target.closest('.client-multi-select')) {
            document.querySelectorAll('.client-multi-select.open').forEach(el => el.classList.remove('open'));
        }
    });

    document.addEventListener('change', (event) => {
        const checkbox = event.target.closest('.client-multi-option');
        if (!checkbox) return;

        const index = parseInt(checkbox.dataset.index, 10);
        if (isNaN(index) || !reportData[index]) return;

        const cell = checkbox.closest('.client-multi-select');
        if (!cell) return;

        const selected = Array.from(cell.querySelectorAll('.client-multi-option:checked'))
            .map(input => input.dataset.clientId);

        setClientSelection(index, selected);
        refreshClientsCell(index);
        if (updateTopFilterAfterClientChange(reportData[index])) {
            applyReportClientFilterAndRender();
        }
    });

    // Report Rendering
    function clientPaymentStatusBadge(emp) {
        const status = emp.client_payment_status || 'not_invoiced';
        const map = {
            paid:         { label: 'Paid',        cls: 'paid' },
            unpaid:       { label: 'Unpaid',       cls: 'overdue' },
            partial:      { label: 'Partial',      cls: 'late' },
            not_invoiced: { label: 'Not Invoiced', cls: 'pending' },
        };
        const { label, cls } = map[status] || map.not_invoiced;
        return `<span class="status-badge ${cls}">${label}</span>`;
    }

    function renderReport() {
        const tbody = document.getElementById('reportTableBody');

        if (reportData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="12" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No data available. Generate a report to view data.</td></tr>';
            updateConvertToInvoiceButtonState();
            updateApplyHoursButtonState();
            return;
        }

        const convertedIds = (window.payrollInvoiceStatus && window.payrollInvoiceStatus.converted_employee_ids) || [];

        tbody.innerHTML = reportData.map((emp, index) => {
            const isAlreadyConverted = convertedIds.includes(emp.employee_id);
            const checkbox = isAlreadyConverted
                ? ''
                : `<input type="checkbox" class="table-checkbox payroll-row-checkbox" data-index="${index}" data-employee-id="${emp.employee_id}">`;
            return `
            <tr data-employee-index="${index}">
                <td>${checkbox}</td>
                <td>${emp.employee_name}</td>
                <td>${renderClientsCell(emp, index)}</td>
                <td>
                    <input type="number"
                           class="editable-bill-amount"
                           data-index="${index}"
                           data-employee-id="${emp.employee_id}"
                           value="${parseFloat(emp.client_invoice_amount || 0).toFixed(2)}"
                           step="0.01"
                           min="0"
                           style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem; text-align: right;"
                           onchange="updateBillAmount(${index}, this.value)"
                           onblur="updateBillAmount(${index}, this.value)">
                </td>
                <td>
                    <input type="number"
                           class="editable-base-salary"
                           data-index="${index}"
                           data-employee-id="${emp.employee_id}"
                           value="${parseFloat(emp.base_salary || 0).toFixed(2)}"
                           step="0.01"
                           min="0"
                           style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem; text-align: right;"
                           onchange="updateBaseSalary(${index}, this.value)"
                           onblur="updateBaseSalary(${index}, this.value)">
                </td>
                <td>${formatSecondsToHms(emp.hours_worked_seconds ?? 0)}</td>
                <td>
                    <input type="number" 
                           class="editable-required-hours" 
                           data-index="${index}"
                           data-employee-id="${emp.employee_id}"
                           value="${parseFloat(emp.required_hours || 0).toFixed(1)}" 
                           step="0.1" 
                           min="0"
                           max="999"
                           style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem; text-align: right;"
                           onchange="updateRequiredHours(${index}, this.value)"
                           onblur="updateRequiredHours(${index}, this.value)">
                </td>
                <td>
                    <input type="number" 
                           class="editable-deduction" 
                           data-index="${index}"
                           value="${parseFloat(emp.deductions || 0).toFixed(2)}" 
                           step="0.01" 
                           min="0"
                           style="width: 100%; padding: 0.375rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.875rem; text-align: right;"
                           onchange="updateDeduction(${index}, this.value)"
                           onblur="updateDeduction(${index}, this.value)">
                </td>
                <td><span class="commission-cell" data-index="${index}">$${parseFloat(emp.pnl_commission || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span></td>
                <td><strong class="net-pay-cell" data-index="${index}">$${parseFloat(emp.net_pay).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></td>
                <td>${clientPaymentStatusBadge(emp)}</td>
                <td>${(() => {
                    const invStatus = window.payrollInvoiceStatus;
                    const convIds = (invStatus && invStatus.converted_employee_ids) || [];
                    const isConverted = convIds.includes(emp.employee_id);
                    if (!isConverted || !invStatus) return '—';
                    const empStatuses = invStatus.employee_statuses || {};
                    const display = empStatuses[emp.employee_id] || empStatuses[String(emp.employee_id)] || '—';
                    if (!display || display === '—') return '—';
                    const badgeClass = (display || '').toLowerCase();
                    return '<span class="status-badge ' + badgeClass + '">' + display + '</span>';
                })()}</td>
            </tr>
        `;
        }).join('');

        // Setup select-all and Convert button state
        setupPayrollRowCheckboxes();
        updateConvertToInvoiceButtonState();

        // Update summary stats
        updateReportSummary();
    }

    function updateReportSummary() {
        // Total Gross Pay represents the total Bill Amount across employees.
        const totalBillAmount = reportData.reduce((sum, emp) => sum + parseFloat(emp.client_invoice_amount || 0), 0);
        const totalDeductions = reportData.reduce((sum, emp) => sum + parseFloat(emp.deductions || 0), 0);
        const totalCommission = reportData.reduce((sum, emp) => sum + parseFloat(emp.pnl_commission || 0), 0);
        const totalNetPay = reportData.reduce((sum, emp) => sum + parseFloat(emp.net_pay || 0), 0);

        document.getElementById('reportTotalEmployees').textContent = reportData.length || 0;
        document.getElementById('reportTotalGrossPay').textContent = '$' + totalBillAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('reportTotalDeductions').textContent = '$' + totalDeductions.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('reportTotalNetPay').textContent = '$' + totalNetPay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('reportTotalCommission').textContent = '$' + totalCommission.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        reportSummary = {
            total_employees: reportData.length,
            total_gross_pay: totalBillAmount,
            total_deductions: totalDeductions,
            total_net_pay: totalNetPay,
            total_commission: totalCommission
        };
    }

    function updateDeduction(index, newDeductionValue) {
        if (index < 0 || index >= reportData.length) return;

        const deductionValue = parseFloat(newDeductionValue) || 0;
        
        // Update the deduction in reportData
        reportData[index].deductions = deductionValue;

        if (isNetPayFromConversion(reportData[index])) {
            updateReportSummary();
            return;
        }
        
        // Recalculate net pay
        const grossPay = parseFloat(reportData[index].gross_pay || 0);
        const netPay = grossPay - deductionValue;
        reportData[index].net_pay = netPay;

        // Update the net pay cell in the table
        const netPayCell = document.querySelector(`.net-pay-cell[data-index="${index}"]`);
        if (netPayCell) {
            netPayCell.textContent = '$' + netPay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Update summary stats
        updateReportSummary();
    }

    function updateBillAmount(index, newValue) {
        if (index < 0 || index >= reportData.length) return;

        const billAmount = parseFloat(newValue);
        if (isNaN(billAmount) || billAmount < 0) return;

        const emp = reportData[index];
        const roundedBillAmount = Math.round(billAmount * 100) / 100;
        emp.client_invoice_amount = roundedBillAmount;

        let reportCommission = 0;
        const commissionType = emp.sales_rep_commission_type;
        const commissionValue = emp.sales_rep_commission_value !== null && emp.sales_rep_commission_value !== undefined
            ? parseFloat(emp.sales_rep_commission_value)
            : null;
        if (commissionType && commissionValue !== null && !isNaN(commissionValue)) {
            if (commissionType === 'percent') {
                reportCommission = Math.round(roundedBillAmount * (commissionValue / 100) * 100) / 100;
            } else if (commissionType === 'usd') {
                reportCommission = Math.round(commissionValue * 100) / 100;
            }
        }
        emp.pnl_commission = reportCommission;

        const row = document.querySelector(`tr[data-employee-index="${index}"]`);
        if (row) {
            const billAmountInput = row.querySelector('.editable-bill-amount');
            if (billAmountInput && document.activeElement !== billAmountInput) {
                billAmountInput.value = roundedBillAmount.toFixed(2);
            }
            const commissionCell = row.querySelector('.commission-cell');
            if (commissionCell) {
                commissionCell.textContent = '$' + reportCommission.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }

        updateReportSummary();
        updateConvertToInvoiceButtonState();
    }

    function updateBaseSalary(index, newValue) {
        if (index < 0 || index >= reportData.length) return;

        const baseSalary = parseFloat(newValue);
        if (isNaN(baseSalary) || baseSalary < 0) return;

        const emp = reportData[index];
        const roundedBaseSalary = Math.round(baseSalary * 100) / 100;
        emp.base_salary = roundedBaseSalary;

        const requiredHours = parseFloat(emp.required_hours) || 0;
        const safeRequiredHours = requiredHours > 0 ? requiredHours : 1;
        const hoursWorked = employeeHoursWorkedDecimal(emp);
        const allowances = parseFloat(emp.allowances || 0);
        const deductions = parseFloat(emp.deductions || 0);

        const proportionalBase = (hoursWorked > 0 && requiredHours > 0)
            ? roundedBaseSalary * (hoursWorked / safeRequiredHours)
            : 0;
        const overtimeHours = Math.max(0, hoursWorked - safeRequiredHours);
        const hourlyRate = (roundedBaseSalary > 0 && safeRequiredHours > 0) ? (roundedBaseSalary / safeRequiredHours) : 0;
        const overtimePay = overtimeHours * hourlyRate * 1.5;
        const grossPay = proportionalBase + overtimePay + allowances;
        const netPay = grossPay - deductions;

        emp.overtime_hours = Math.round(overtimeHours * 10) / 10;
        emp.gross_pay = Math.round(grossPay * 100) / 100;
        if (!isNetPayFromConversion(emp)) {
            emp.net_pay = Math.round(netPay * 100) / 100;
        }

        const row = document.querySelector(`tr[data-employee-index="${index}"]`);
        if (row) {
            const baseSalaryInput = row.querySelector('.editable-base-salary');
            if (baseSalaryInput && document.activeElement !== baseSalaryInput) {
                baseSalaryInput.value = roundedBaseSalary.toFixed(2);
            }
            const netPayCell = row.querySelector('.net-pay-cell');
            if (netPayCell && !isNetPayFromConversion(emp)) {
                netPayCell.textContent = '$' + emp.net_pay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }

        updateReportSummary();
    }

    async function updateRequiredHours(index, newValue) {
        if (index < 0 || index >= reportData.length) return;

        const requiredHours = parseFloat(newValue) || 0;
        if (requiredHours < 0) return;

        const emp = reportData[index];
        const fullBaseSalary = parseFloat(emp.base_salary || 0);
        const hoursWorked = employeeHoursWorkedDecimal(emp);
        const allowances = parseFloat(emp.allowances || 0);
        const deductions = parseFloat(emp.deductions || 0);

        // Calculate proportional base salary and overtime
        const effectiveRequiredHours = requiredHours > 0 ? requiredHours : 1;
        const proportionalBase = fullBaseSalary * (hoursWorked / effectiveRequiredHours);
        const overtimeHours = Math.max(0, hoursWorked - effectiveRequiredHours);
        const hourlyRate = (fullBaseSalary > 0 && effectiveRequiredHours > 0) ? (fullBaseSalary / effectiveRequiredHours) : 0;
        const overtimePay = overtimeHours * hourlyRate * 1.5;
        const grossPay = proportionalBase + overtimePay + allowances;
        const netPay = grossPay - deductions;

        // Update reportData
        emp.required_hours = Math.round(effectiveRequiredHours * 10) / 10;
        emp.overtime_hours = Math.round(overtimeHours * 10) / 10;
        emp.gross_pay = Math.round(grossPay * 100) / 100;
        if (!isNetPayFromConversion(emp)) {
            emp.net_pay = Math.round(netPay * 100) / 100;
        }

        // Update UI: sync Required Hours input to rounded value, update Net Pay cell
        const row = document.querySelector(`tr[data-employee-index="${index}"]`);
        if (row) {
            const requiredHoursInput = row.querySelector('.editable-required-hours');
            if (requiredHoursInput) requiredHoursInput.value = emp.required_hours.toFixed(1);
            const netPayCell = row.querySelector('.net-pay-cell');
            if (netPayCell && !isNetPayFromConversion(emp)) {
                netPayCell.textContent = '$' + emp.net_pay.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        }

        updateReportSummary();

        // Save to database
        const employeeId = emp.employee_id;
        if (employeeId) {
            try {
                const response = await fetch(`/api/payroll/employees/${employeeId}/required-work-hours`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({ required_work_hours: emp.required_hours })
                });
                if (!response.ok) {
                    const result = await response.json();
                    console.error('Failed to save required hours:', result.message || 'Unknown error');
                }
            } catch (error) {
                console.error('Error saving required hours:', error);
            }
        }
    }

    function updateHoursWorked(index, newValue) {
        if (index < 0 || index >= reportData.length) return;

        const hoursWorked = parseFloat(newValue);
        if (isNaN(hoursWorked) || hoursWorked < 0) return;

        const emp = reportData[index];
        const requiredHours = parseFloat(emp.required_hours) || 1;
        const fullBaseSalary = parseFloat(emp.base_salary || 0);
        const allowances = parseFloat(emp.allowances || 0);
        const deductions = parseFloat(emp.deductions || 0);

        const normalizedHours = Math.round(hoursWorked * 10) / 10;
        const safeRequiredHours = requiredHours > 0 ? requiredHours : 1;
        const proportionalBase = fullBaseSalary * (normalizedHours / safeRequiredHours);
        const overtimeHours = Math.max(0, normalizedHours - safeRequiredHours);
        const hourlyRate = fullBaseSalary > 0 ? (fullBaseSalary / safeRequiredHours) : 0;
        const overtimePay = overtimeHours * hourlyRate * 1.5;
        const grossPay = proportionalBase + overtimePay + allowances;
        const netPay = grossPay - deductions;

        emp.hours_worked = normalizedHours;
        emp.hours_worked_seconds = Math.round(normalizedHours * 3600);
        emp.overtime_hours = Math.round(overtimeHours * 10) / 10;
        emp.gross_pay = Math.round(grossPay * 100) / 100;
        if (!isNetPayFromConversion(emp)) {
            emp.net_pay = Math.round(netPay * 100) / 100;
        }
    }

    // Load employees for filter dropdown
    async function loadEmployees() {
        try {
            const response = await fetch('/api/payroll/employees');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Expected JSON but got:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response. Route may not be found.');
            }
            
            const result = await response.json();

            if (result.success) {
                const employeeFilter = document.getElementById('employeeFilter');
                // Clear existing options except "All Employees"
                employeeFilter.innerHTML = '<option value="all">All Employees</option>';
                
                // Add employees
                result.data.forEach(employee => {
                    const option = document.createElement('option');
                    option.value = employee.id;
                    option.textContent = employee.name;
                    employeeFilter.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading employees:', error);
            // Show error in dropdown
            const employeeFilter = document.getElementById('employeeFilter');
            employeeFilter.innerHTML = '<option value="all">All Employees</option><option disabled>Error loading employees</option>';
        }
    }

    async function loadReportClients() {
        const filter = document.getElementById('reportClientFilter');
        if (!filter) return;

        const setClientOptions = (clients, previousValue = 'all') => {
            const options = (clients || [])
                .filter(client => client && client.id && client.name)
                .sort((a, b) => String(a.name).localeCompare(String(b.name)))
                .map(client => `<option value="${client.id}">${client.name}</option>`)
                .join('');

            filter.innerHTML = '<option value="all">All Clients</option>' + options;
            const hasPrevious = previousValue === 'all' || (clients || []).some(client => String(client.id) === String(previousValue));
            filter.value = hasPrevious ? previousValue : 'all';
        };

        const fetchClientsFromPayrollEndpoint = async () => {
            const response = await fetch('/api/payroll/clients');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) throw new Error('Server returned non-JSON response.');
            const result = await response.json();
            if (!result.success || !Array.isArray(result.data)) throw new Error(result.message || 'Failed to load clients.');
            return result.data;
        };

        const fetchClientsFromClientManagement = async () => {
            const response = await fetch('/api/client-management/clients?per_page=1000');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) throw new Error('Server returned non-JSON response.');
            const result = await response.json();
            if (!result.success || !Array.isArray(result.data)) throw new Error(result.message || 'Failed to load clients.');
            return result.data.map(client => ({ id: client.id, name: client.name }));
        };

        const fetchClientsFromPayrollReport = async () => {
            const startDate = document.getElementById('reportDateStartFilter')?.value;
            const endDate = document.getElementById('reportDateEndFilter')?.value;
            if (!startDate || !endDate) return [];

            const params = new URLSearchParams({ start_date: startDate, end_date: endDate });
            const response = await fetch(`/api/payroll/payroll-report?${params}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) throw new Error('Server returned non-JSON response.');
            const result = await response.json();
            if (!result.success || !Array.isArray(result.data)) throw new Error(result.message || 'Failed to load report clients.');

            const clients = new Map();
            result.data.forEach(emp => {
                const ids = Array.isArray(emp.client_ids) ? emp.client_ids : [];
                const names = String(emp.clients || '')
                    .split(',')
                    .map(name => name.trim())
                    .filter(name => !!name && name !== '—');
                ids.forEach((id, index) => {
                    const key = String(id);
                    if (!key || key === '0') return;
                    if (!clients.has(key)) {
                        clients.set(key, names[index] || `Client #${key}`);
                    }
                });
            });

            return Array.from(clients.entries()).map(([id, name]) => ({ id, name }));
        };

        try {
            const previousValue = filter.value || 'all';
            let clients = [];

            try {
                clients = await fetchClientsFromPayrollEndpoint();
            } catch (primaryError) {
                console.warn('Primary payroll clients endpoint unavailable, trying fallback...', primaryError);
            }

            if (!clients.length) {
                try {
                    clients = await fetchClientsFromClientManagement();
                } catch (fallbackError) {
                    console.warn('Client-management clients fallback unavailable, trying report fallback...', fallbackError);
                }
            }

            if (!clients.length) {
                try {
                    clients = await fetchClientsFromPayrollReport();
                } catch (reportFallbackError) {
                    console.warn('Payroll report fallback unavailable.', reportFallbackError);
                }
            }

            setClientOptions(clients, previousValue);
        } catch (error) {
            console.error('Error loading payroll clients:', error);
            filter.innerHTML = '<option value="all">All Clients</option>';
        }
    }

    // Functions
    function exportTimeLogs() {
        alert('Exporting time logs...');
    }

    async function generateReport() {
        const startDate = document.getElementById('reportDateStartFilter').value;
        const endDate = document.getElementById('reportDateEndFilter').value;
        const limitHours = document.getElementById('limitHoursToRequired').checked;

        if (!startDate || !endDate) {
            alert('Please select both start and end dates for the report.');
            return;
        }

        // Show loading state
        const tbody = document.getElementById('reportTableBody');
        tbody.innerHTML = '<tr><td colspan="12" style="text-align: center; padding: 2rem;">Loading...</td></tr>';

        // Update report period display
        const startDateObj = new Date(startDate);
        const endDateObj = new Date(endDate);
        const startFormatted = startDateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        const endFormatted = endDateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        document.getElementById('reportPeriod').textContent = `Period: ${startFormatted} - ${endFormatted}`;

        // Update generated date
        const generatedDateEl = document.getElementById('reportGeneratedDate');
        if (generatedDateEl) {
            generatedDateEl.textContent = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        // Update report title
        const reportTitle = document.querySelector('.report-title');
        if (reportTitle) {
            reportTitle.textContent = 'Payroll Report';
        }

        try {
            const params = new URLSearchParams({
                start_date: startDate,
                end_date: endDate
            });

            const response = await fetch(`/api/payroll/payroll-report?${params}`);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Expected JSON but got:', text.substring(0, 200));
                throw new Error('Server returned non-JSON response.');
            }

            const result = await response.json();

            if (result.success) {
                originalReportData = JSON.parse(JSON.stringify(result.data || []));
                allReportData = JSON.parse(JSON.stringify(result.data || []));
                window.payrollInvoiceStatus = result.invoice_status || { generated: false };

                initializeSelectedClientIds(originalReportData);
                initializeSelectedClientIds(allReportData);

                if (limitHours) {
                    applyHoursLimit(allReportData);
                }

                populateReportClientFilterFromData(allReportData);
                applyReportClientFilterAndRender();
            } else {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align: center; padding: 2rem; color: red;">Error: ' + (result.message || 'Failed to generate report') + '</td></tr>';
            }
        } catch (error) {
            console.error('Error generating report:', error);
            tbody.innerHTML = '<tr><td colspan="12" style="text-align: center; padding: 2rem; color: red;">Error loading report: ' + error.message + '</td></tr>';
        }
    }

    function setupPayrollRowCheckboxes() {
        const selectAll = document.getElementById('selectAllPayrollRows');
        if (!selectAll) return;
        const hasConvertibleRows = document.querySelectorAll('.payroll-row-checkbox:not(:disabled)').length > 0;
        const th = selectAll.closest('th');
        if (!hasConvertibleRows) {
            selectAll.style.display = 'none';
            if (th) th.style.visibility = 'hidden';
        } else {
            selectAll.style.display = '';
            if (th) th.style.visibility = '';
            selectAll.checked = false;
            selectAll.onchange = function() {
                document.querySelectorAll('.payroll-row-checkbox:not(:disabled)').forEach(cb => cb.checked = this.checked);
                updateConvertToInvoiceButtonState();
                updateApplyHoursButtonState();
            };
            document.querySelectorAll('.payroll-row-checkbox').forEach(cb => {
                cb.onchange = () => {
                    updateConvertToInvoiceButtonState();
                    updateApplyHoursButtonState();
                };
            });
        }
        updateApplyHoursButtonState();
    }

    function updateConvertToInvoiceButtonState() {
        const btn = document.getElementById('convertToInvoiceBtn');
        if (!btn) return;
        const checked = document.querySelectorAll('.payroll-row-checkbox:checked');
        const hasConvertibleRows = document.querySelectorAll('.payroll-row-checkbox:not(:disabled)').length > 0;
        btn.disabled = checked.length === 0;
        btn.title = !hasConvertibleRows ? 'All employees have been converted.' 
            : checked.length === 0 ? 'Select at least one employee to convert to invoice.' 
            : 'Convert selected rows to invoice(s) - one per client. You can convert remaining employees in batches.';
    }

    function updateApplyHoursButtonState() {
        const btn = document.getElementById('applyHoursToSelectedBtn');
        if (!btn) return;
        const selectedCount = document.querySelectorAll('.payroll-row-checkbox:checked').length;
        btn.disabled = selectedCount === 0;
        btn.title = selectedCount === 0
            ? 'Select at least one employee row first.'
            : 'Apply entered hours to selected employees.';
    }

    function applyHoursWorkedToSelected() {
        if (!reportData || reportData.length === 0) {
            alert('Please generate a report first.');
            return;
        }

        const input = document.getElementById('bulkHoursWorkedInput');
        const selectedRows = Array.from(document.querySelectorAll('.payroll-row-checkbox:checked'));
        const selectedEmployeeIds = new Set(
            selectedRows
                .map(cb => String(cb.dataset.employeeId || '').trim())
                .filter(Boolean)
        );
        if (selectedRows.length === 0) {
            alert('Please select at least one employee.');
            return;
        }

        const hoursWorked = parseFloat(input?.value ?? '');
        if (isNaN(hoursWorked) || hoursWorked < 0) {
            alert('Please enter a valid non-negative value for hours worked.');
            if (input) input.focus();
            return;
        }

        selectedRows.forEach(cb => {
            const index = parseInt(cb.dataset.index, 10);
            if (!isNaN(index)) {
                updateHoursWorked(index, hoursWorked);
            }
        });

        renderReport();
        document.querySelectorAll('.payroll-row-checkbox:not(:disabled)').forEach(cb => {
            cb.checked = selectedEmployeeIds.has(String(cb.dataset.employeeId || '').trim());
        });

        const selectableRows = document.querySelectorAll('.payroll-row-checkbox:not(:disabled)');
        const checkedRows = document.querySelectorAll('.payroll-row-checkbox:not(:disabled):checked');
        const selectAll = document.getElementById('selectAllPayrollRows');
        if (selectAll && selectableRows.length > 0) {
            selectAll.checked = checkedRows.length === selectableRows.length;
        }
        updateConvertToInvoiceButtonState();
        updateApplyHoursButtonState();

        if (input) input.value = '';
        alert(`Hours worked updated for ${selectedRows.length} employee(s).`);
    }

    async function convertToInvoice() {
        const startDate = document.getElementById('reportDateStartFilter').value;
        const endDate = document.getElementById('reportDateEndFilter').value;
        if (!startDate || !endDate) {
            alert('Please select both start and end dates.');
            return;
        }
        if (!reportData || reportData.length === 0) {
            alert('Please generate a report first.');
            return;
        }
        const checked = document.querySelectorAll('.payroll-row-checkbox:checked');
        const selectedIds = Array.from(checked).map(cb => parseInt(cb.dataset.employeeId));
        if (selectedIds.length === 0) {
            alert('Please select at least one employee to include in the invoice.');
            return;
        }

        const rowsMissingClient = reportData
            .filter(e => selectedIds.includes(e.employee_id))
            .filter(e => {
                const selected = effectiveClientIds(e);
                const converted = convertedClientIdsFor(e);
                const pendingSelected = selected.filter(id => !converted.includes(id));
                return pendingSelected.length === 0;
            })
            .map(e => e.employee_name);
        if (rowsMissingClient.length > 0) {
            alert(`These employees have no un-invoiced clients selected and cannot be converted to invoice:\n- ${rowsMissingClient.join('\n- ')}\nUse the Client(s) dropdown to select at least one client that hasn't been invoiced yet.`);
            return;
        }

        if (!confirm(`Create invoice(s) for ${selectedIds.length} selected employee(s)? One invoice will be created per selected client.`)) return;

        const btn = document.getElementById('convertToInvoiceBtn');
        if (btn) btn.disabled = true;

        try {
            const response = await fetch('/api/payroll/payroll-report/convert-to-invoice', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate,
                    selected_employee_ids: selectedIds,
                    employee_details: reportData
                        .filter(e => selectedIds.includes(e.employee_id))
                        .map(e => {
                            const converted = convertedClientIdsFor(e);
                            const pendingSelected = effectiveClientIds(e)
                                .filter(id => !converted.includes(id))
                                .map(id => parseInt(id, 10))
                                .filter(id => !isNaN(id));

                            const empIndex = reportData.indexOf(e);
                            const billAmountEl = document.querySelector(`.editable-bill-amount[data-employee-id="${e.employee_id}"]`);
                            const baseSalaryEl = document.querySelector(`.editable-base-salary[data-employee-id="${e.employee_id}"]`);
                            const netPayEl = document.querySelector(`.net-pay-cell[data-index="${empIndex}"]`);

                            const billAmount = billAmountEl ? parseFloat(billAmountEl.value) || 0 : parseFloat(e.client_invoice_amount) || 0;
                            const baseSalary = baseSalaryEl ? parseFloat(baseSalaryEl.value) || 0 : parseFloat(e.base_salary) || 0;
                            const netPayRaw = netPayEl ? netPayEl.textContent.replace(/[$,]/g, '') : null;
                            const netPay = netPayRaw !== null ? (parseFloat(netPayRaw) || 0) : (parseFloat(e.net_pay) || 0);
                            const commission = parseFloat(e.pnl_commission || 0) || 0;

                            return {
                                employee_id: e.employee_id,
                                hours_worked: parseFloat(e.hours_worked) || 0,
                                net_pay: netPay,
                                bill_amount: billAmount,
                                base_salary: baseSalary,
                                commission: commission,
                                selected_client_ids: pendingSelected
                            };
                        })
                })
            });
            const result = await response.json();
            if (result.success) {
                alert(result.message);
                await generateReport();
            } else {
                let errMsg = result.message || 'Failed to create invoices.';
                if (result.errors && typeof result.errors === 'object') {
                    const errParts = [];
                    for (const [k, v] of Object.entries(result.errors)) {
                        if (Array.isArray(v)) errParts.push(v.join(' '));
                        else errParts.push(String(v));
                    }
                    if (errParts.length) errMsg = errParts.join('\n');
                }
                alert(errMsg);
                if (btn) updateConvertToInvoiceButtonState();
            }
        } catch (error) {
            console.error('Convert to invoice error:', error);
            alert('Failed to create invoices. Please try again.');
            if (btn) updateConvertToInvoiceButtonState();
        }
    }

    function applyHoursLimit(data = reportData) {
        data.forEach(emp => {
            const hoursWorked = employeeHoursWorkedDecimal(emp);
            const requiredHours = parseFloat(emp.required_hours) || 1; // Prevent division by zero
            const fullBaseSalary = parseFloat(emp.base_salary) || 0;
            const allowances = parseFloat(emp.allowances || 0);
            const deductions = parseFloat(emp.deductions || 0);
            
            // Cap hours worked if it exceeds required hours
            let effectiveHoursWorked = hoursWorked > requiredHours ? requiredHours : hoursWorked;
            
            // Calculate proportional base salary (round to 2 decimals to avoid precision issues)
            let proportionalBaseSalary = 0;
            if (effectiveHoursWorked > 0 && requiredHours > 0) {
                proportionalBaseSalary = Math.round((fullBaseSalary * effectiveHoursWorked / requiredHours) * 100) / 100;
            }
            
            // Update employee data
            emp.hours_worked = Math.round(effectiveHoursWorked * 10) / 10;
            emp.hours_worked_seconds = Math.round(effectiveHoursWorked * 3600);
            
            // Gross pay = proportional base salary + allowances (round to 2 decimals)
            emp.gross_pay = Math.round((proportionalBaseSalary + allowances) * 100) / 100;
            
            if (!isNetPayFromConversion(emp)) {
                // Net pay = gross pay - deductions (round to 2 decimals)
                emp.net_pay = Math.round((emp.gross_pay - deductions) * 100) / 100;
            }
        });
    }

    function recalculateReportSummary() {
        // Total Gross Pay represents the total Bill Amount across employees.
        const totalBillAmount = reportData.reduce((sum, emp) => sum + parseFloat(emp.client_invoice_amount || 0), 0);
        const totalDeductions = reportData.reduce((sum, emp) => sum + parseFloat(emp.deductions || 0), 0);
        const totalCommission = reportData.reduce((sum, emp) => sum + parseFloat(emp.pnl_commission || 0), 0);
        const totalNetPay = reportData.reduce((sum, emp) => sum + parseFloat(emp.net_pay || 0), 0);

        reportSummary = {
            total_employees: reportData.length,
            total_gross_pay: totalBillAmount,
            total_deductions: totalDeductions,
            total_net_pay: totalNetPay,
            total_commission: totalCommission
        };
    }

    // Payroll Reports Date Range Filters
    document.getElementById('reportDateStartFilter').addEventListener('change', () => {
        if (document.getElementById('reportDateEndFilter').value) {
            // Auto-generate report on date change if both dates are set
            // generateReport();
        }
    });

    document.getElementById('reportDateEndFilter').addEventListener('change', () => {
        if (document.getElementById('reportDateStartFilter').value) {
            // Auto-generate report on date change if both dates are set
            // generateReport();
        }
    });

    // Hours limit checkbox - re-apply limit when toggled
    document.getElementById('limitHoursToRequired').addEventListener('change', () => {
        if (originalReportData && originalReportData.length > 0) {
            // Restore original data
            allReportData = JSON.parse(JSON.stringify(originalReportData));
            
            // Apply hours limit if checkbox is checked
            if (document.getElementById('limitHoursToRequired').checked) {
                applyHoursLimit(allReportData);
            }

            applyReportClientFilterAndRender();
        }
    });

    document.getElementById('reportClientFilter').addEventListener('change', () => {
        if (!allReportData || allReportData.length === 0) {
            renderReport();
            return;
        }
        applyReportClientFilterAndRender();
    });

    document.getElementById('reportPaymentStatusFilter').addEventListener('change', () => {
        if (!allReportData || allReportData.length === 0) {
            renderReport();
            return;
        }
        applyReportClientFilterAndRender();
    });

    document.getElementById('convertedInvoicesMonthFilter').addEventListener('change', () => {
        convertedInvoicesCurrentPage = 1;
        loadConvertedInvoices();
    });

    document.getElementById('convertedInvoicesPrevBtn').addEventListener('click', () => {
        if (convertedInvoicesCurrentPage > 1) {
            convertedInvoicesCurrentPage--;
            loadConvertedInvoices();
        }
    });

    document.getElementById('convertedInvoicesNextBtn').addEventListener('click', () => {
        if (convertedInvoicesCurrentPage < convertedInvoicesTotalPages) {
            convertedInvoicesCurrentPage++;
            loadConvertedInvoices();
        }
    });

    async function exportReport() {
        const startDate = document.getElementById('reportDateStartFilter').value;
        const endDate = document.getElementById('reportDateEndFilter').value;
        const limitHours = document.getElementById('limitHoursToRequired').checked;

        if (!startDate || !endDate) {
            alert('Please select both start and end dates for the report.');
            return;
        }

        // Check if report data exists (with edited deductions)
        if (!reportData || reportData.length === 0) {
            alert('Please generate a report first before exporting.');
            return;
        }

        if (!confirm('Export this payroll report to Excel?')) return;

        // Apply hours limit if checkbox is checked (create a copy for export)
        let exportData = JSON.parse(JSON.stringify(reportData));
        exportData.forEach(emp => {
            const names = effectiveClientNames(emp);
            if (names.length > 0) {
                emp.clients = names.join(', ');
            }
        });
        if (limitHours) {
            exportData.forEach(emp => {
                const hoursWorked = employeeHoursWorkedDecimal(emp);
                const requiredHours = parseFloat(emp.required_hours) || 1; // Prevent division by zero
                const fullBaseSalary = parseFloat(emp.base_salary) || 0;
                const allowances = parseFloat(emp.allowances || 0);
                const deductions = parseFloat(emp.deductions || 0);
                
                // Cap hours worked if it exceeds required hours
                let effectiveHoursWorked = hoursWorked > requiredHours ? requiredHours : hoursWorked;
                
                // Calculate proportional base salary (round to 2 decimals to avoid precision issues)
                let proportionalBaseSalary = 0;
                if (effectiveHoursWorked > 0 && requiredHours > 0) {
                    proportionalBaseSalary = Math.round((fullBaseSalary * effectiveHoursWorked / requiredHours) * 100) / 100;
                }
                
                // Update employee data
                emp.hours_worked = Math.round(effectiveHoursWorked * 10) / 10;
                emp.hours_worked_seconds = Math.round(effectiveHoursWorked * 3600);
                
                // Gross pay = proportional base salary + allowances (round to 2 decimals)
                emp.gross_pay = Math.round((proportionalBaseSalary + allowances) * 100) / 100;
                
                if (!isNetPayFromConversion(emp)) {
                    // Net pay = gross pay - deductions (round to 2 decimals)
                    emp.net_pay = Math.round((emp.gross_pay - deductions) * 100) / 100;
                }
            });
        }

        try {
            // Send the current reportData (with edited deductions and hours limit applied) to the backend
            const response = await fetch('/api/payroll/payroll-report/export', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate,
                    report_data: exportData  // Include edited data with deductions, required hours, and hours limit
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Get the blob and create download link
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `payroll-report-${startDate}-to-${endDate}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Error exporting report:', error);
            alert('Error exporting report. Please try again.');
        }
    }

    async function savePayrollForWise() {
        const startDate = document.getElementById('reportDateStartFilter').value;
        const endDate = document.getElementById('reportDateEndFilter').value;

        if (!startDate || !endDate) {
            alert('Please select both start and end dates for the report.');
            return;
        }

        if (!reportData || reportData.length === 0) {
            alert('Please generate a report first before saving for Wise.');
            return;
        }

        // Warn if any employees have unpaid or partially-paid client invoices
        const unpaidEmployees = reportData.filter(emp => {
            const s = emp.client_payment_status || 'not_invoiced';
            return s === 'unpaid' || s === 'partial';
        });
        if (unpaidEmployees.length > 0) {
            const names = unpaidEmployees.map(e => `• ${e.employee_name} (${e.client_payment_status === 'partial' ? 'Partially paid' : 'Unpaid'})`).join('\n');
            const proceed = confirm(
                `⚠️ Some clients have not paid yet:\n\n${names}\n\nIt is recommended to only pay employees after the client has paid.\n\nDo you still want to save for Wise?`
            );
            if (!proceed) return;
        } else if (!confirm('Save this payroll report for Wise? You can send it later from the Saved for Wise tab.')) {
            return;
        }

        // Ensure report_data has employee_id for each row
        const reportDataToSave = reportData.map(emp => ({
            employee_id: emp.employee_id,
            employee_name: emp.employee_name,
            net_pay: parseFloat(emp.net_pay) || 0,
            gross_pay: parseFloat(emp.gross_pay) || 0,
            base_salary: parseFloat(emp.base_salary) || 0,
            hours_worked: parseFloat(emp.hours_worked) || 0,
            required_hours: parseFloat(emp.required_hours) || 0,
            overtime_hours: parseFloat(emp.overtime_hours) || 0,
            allowances: parseFloat(emp.allowances) || 0,
            deductions: parseFloat(emp.deductions) || 0
        }));

        try {
            const response = await fetch('{{ route("api.payroll.report.save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    start_date: startDate,
                    end_date: endDate,
                    report_data: reportDataToSave
                })
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message || 'Payroll report saved successfully. Ready for Wise payroll sending.');
            } else {
                const errors = result.errors ? Object.values(result.errors).flat().join('\n') : (result.message || 'Failed to save payroll report.');
                alert(errors);
            }
        } catch (error) {
            console.error('Error saving payroll for Wise:', error);
            alert('Error saving payroll report. Please try again.');
        }
    }

    // Saved for Wise - Fetch and render saved payroll reports
    async function fetchSavedPayrollReports() {
        const container = document.getElementById('savedForWiseContent');
        if (!container) return;

        container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--text-secondary);">Loading...</div>';

        try {
            const response = await fetch('{{ route("api.payroll.report.saved") }}', {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            const result = await response.json();

            if (result.success) {
                wiseCurrentPage = 1;
                renderSavedForWise(result.data, result.wise_configured, result.wise_balances || []);
            } else {
                container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--danger, #dc3545);">' + (result.message || 'Failed to load saved reports.') + '</div>';
            }
        } catch (error) {
            console.error('Error fetching saved payroll reports:', error);
            container.innerHTML = '<div style="text-align: center; padding: 2rem; color: var(--danger, #dc3545);">Error loading saved reports. Please try again.</div>';
        }
    }

    async function loadConvertedInvoices() {
        const tbody = document.getElementById('convertedInvoicesTableBody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem;">Loading...</td></tr>';

        const pdfBaseUrl = "{{ url('/api/billing-invoices') }}";
        const billingUrl = "{{ route('billing') }}";
        const monthFilter = document.getElementById('convertedInvoicesMonthFilter');
        const selectedMonth = monthFilter?.value || getCurrentMonthString();

        try {
            const params = new URLSearchParams({
                month: selectedMonth,
                page: convertedInvoicesCurrentPage,
                per_page: convertedInvoicesPageSize
            });
            const response = await fetch(`/api/payroll/payroll-report/converted-invoices?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (result.success && Array.isArray(result.data)) {
                const data = result.data;
                const pagination = result.pagination || {
                    current_page: 1,
                    last_page: 1,
                    total: data.length,
                    per_page: convertedInvoicesPageSize
                };
                convertedInvoicesCurrentPage = parseInt(pagination.current_page || 1, 10);
                convertedInvoicesTotalPages = parseInt(pagination.last_page || 1, 10);
                convertedInvoicesTotalRecords = parseInt(pagination.total || 0, 10);
                updateConvertedInvoicesPagination();
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No converted invoices yet. Convert payroll to invoice from the Payroll Reports tab.</td></tr>';
                } else {
                    tbody.innerHTML = data.map(row => {
                        const billAmt = row.bill_amount != null ? '$' + parseFloat(row.bill_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
                        const statusClass = (row.status || '').toLowerCase();
                        const pdfUrl = row.invoice_id ? pdfBaseUrl + '/' + row.invoice_id + '/pdf' : null;
                        const viewLink = pdfUrl ? `<a href="${pdfUrl}" target="_blank" rel="noopener noreferrer" style="color: var(--accent, #3b82f6);">View PDF</a>` : '—';
                        const canDelete = statusClass === 'draft';
                        const deleteBtn = canDelete && row.invoice_item_id
                            ? `<button type="button" class="btn-secondary btn-sm" onclick="deleteConvertedInvoice(${row.invoice_item_id})" title="Delete">Delete</button>`
                            : '—';
                        const invNum = (row.invoice_number || '—').replace(/</g, '&lt;');
                        const billingLink = row.invoice_id
                            ? `<a href="${billingUrl}?invoice=${row.invoice_id}" style="color: var(--accent, #3b82f6); font-weight: 500;">${invNum}</a>`
                            : invNum;
                        return `<tr>
                            <td>${(row.employee || '—').replace(/</g, '&lt;')}</td>
                            <td>${(row.client || '—').replace(/</g, '&lt;')}</td>
                            <td>${billingLink}</td>
                            <td>${(row.period || '—').replace(/</g, '&lt;')}</td>
                            <td>${billAmt}</td>
                            <td><span class="status-badge ${statusClass}">${(row.status || '—').replace(/</g, '&lt;')}</span></td>
                            <td>${viewLink}</td>
                            <td>${deleteBtn}</td>
                        </tr>`;
                    }).join('');
                }
            } else {
                convertedInvoicesTotalRecords = 0;
                convertedInvoicesTotalPages = 1;
                updateConvertedInvoicesPagination();
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--danger, #dc3545);">' + (result.message || 'Failed to load.') + '</td></tr>';
            }
        } catch (error) {
            console.error('Error loading converted invoices:', error);
            convertedInvoicesTotalRecords = 0;
            convertedInvoicesTotalPages = 1;
            updateConvertedInvoicesPagination();
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 2rem; color: var(--danger, #dc3545);">Error loading. Please try again.</td></tr>';
        }
    }

    async function deleteConvertedInvoice(invoiceItemId) {
        if (!confirm('Delete this converted invoice? It will be removed from Billing as well.')) return;
        try {
            const response = await fetch('/api/payroll/payroll-report/converted-invoice', {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ invoice_item_id: invoiceItemId })
            });
            const result = await response.json();
            if (result.success) {
                loadConvertedInvoices();
            } else {
                alert(result.message || 'Failed to delete.');
            }
        } catch (error) {
            console.error('Error deleting converted invoice:', error);
            alert('Error deleting. Please try again.');
        }
    }

    let savedWiseReports = [];
    let savedWiseConfigured = true;
    let lastWiseBalances = [];
    let wiseCurrentPage = 1;
    const wisePageSize = 10;
    let convertedInvoicesCurrentPage = 1;
    let convertedInvoicesTotalPages = 1;
    let convertedInvoicesTotalRecords = 0;
    const convertedInvoicesPageSize = 10;

    function getCurrentMonthString() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    }

    function updateConvertedInvoicesPagination() {
        const infoEl = document.getElementById('convertedInvoicesPaginationInfo');
        const prevBtn = document.getElementById('convertedInvoicesPrevBtn');
        const nextBtn = document.getElementById('convertedInvoicesNextBtn');
        const numbersEl = document.getElementById('convertedInvoicesPaginationNumbers');

        const start = convertedInvoicesTotalRecords > 0 ? (convertedInvoicesCurrentPage - 1) * convertedInvoicesPageSize + 1 : 0;
        const end = Math.min(convertedInvoicesCurrentPage * convertedInvoicesPageSize, convertedInvoicesTotalRecords);

        if (infoEl) infoEl.textContent = `Showing ${start} to ${end} of ${convertedInvoicesTotalRecords} results`;
        if (prevBtn) prevBtn.disabled = convertedInvoicesCurrentPage <= 1;
        if (nextBtn) nextBtn.disabled = convertedInvoicesCurrentPage >= convertedInvoicesTotalPages;
        if (!numbersEl) return;

        let html = '';
        if (convertedInvoicesTotalPages > 1) {
            const maxVisible = 5;
            let startPage = Math.max(1, convertedInvoicesCurrentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(convertedInvoicesTotalPages, startPage + maxVisible - 1);
            if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

            if (startPage > 1) {
                html += `<button type="button" class="pagination-number" data-page="1">1</button>`;
                if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
            }
            for (let i = startPage; i <= endPage; i++) {
                html += `<button type="button" class="pagination-number ${i === convertedInvoicesCurrentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
            if (endPage < convertedInvoicesTotalPages) {
                if (endPage < convertedInvoicesTotalPages - 1) html += `<span class="pagination-number ellipsis">...</span>`;
                html += `<button type="button" class="pagination-number" data-page="${convertedInvoicesTotalPages}">${convertedInvoicesTotalPages}</button>`;
            }
        }

        numbersEl.innerHTML = html;
        numbersEl.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                convertedInvoicesCurrentPage = parseInt(btn.dataset.page, 10);
                loadConvertedInvoices();
            });
        });
    }

    function bindWiseSetupGuide() {
        const btn = document.querySelector('.wise-setup-guide-btn');
        const guide = document.getElementById('wiseSetupGuide');
        if (btn && guide) {
            btn.onclick = function() {
                guide.style.display = guide.style.display === 'none' ? 'block' : 'none';
                btn.textContent = guide.style.display === 'none' ? 'How to set up' : 'Hide guide';
            };
        }
    }

    function renderSavedForWise(reports, wiseConfigured, wiseBalances) {
        const container = document.getElementById('savedForWiseContent');
        if (!container) return;

        savedWiseReports = reports || [];
        savedWiseConfigured = wiseConfigured === true;
        lastWiseBalances = wiseBalances || [];
        const wiseReady = savedWiseConfigured;
        const balances = wiseBalances || [];

        // Setup notice when Wise is not configured
        const setupNoticeHtml = !wiseReady ? `
            <div class="wise-setup-notice" style="background: #fff8e1; border: 1px solid #ffc107; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                <strong style="color: #e65100;">Wise API not configured.</strong> Set up your API key in Integrations to send payroll to Wise.
                <button type="button" class="wise-setup-guide-btn" style="margin-left: 0.5rem; background: none; border: none; color: var(--accent); cursor: pointer; text-decoration: underline; font-size: 0.875rem;">How to set up</button>
                <div id="wiseSetupGuide" style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ffecb3; font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;">
                    <strong style="color: var(--text-primary);">Setup guide:</strong>
                    <ol style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
                        <li>Go to <a href="{{ route('integrations') }}" style="color: var(--accent);">Integrations</a> → Wise → Connect</li>
                        <li>Log in to your <a href="https://wise.com/business" target="_blank" rel="noopener" style="color: var(--accent);">Wise Business</a> account</li>
                        <li>Create an API token: Settings → API tokens → Create new token (needs "Transfer" permission)</li>
                        <li>Find your Profile ID: Settings → Account details (or via API). It's a numeric ID for your business profile</li>
                        <li>Enter the API token and Profile ID in the Integrations form and click Connect</li>
                        <li>For testing, enable "Use Sandbox" and use <a href="https://api.sandbox.transferwise.tech" target="_blank" rel="noopener" style="color: var(--accent);">Wise Sandbox</a> credentials</li>
                    </ol>
                </div>
            </div>
        ` : '';

        // Show/hide Wise balance bar
        const balanceBar = document.getElementById('wiseBalanceBar');
        const balanceContent = document.getElementById('wiseBalanceContent');
        if (balanceBar && balanceContent) {
            if (wiseReady && balances.length > 0) {
                balanceContent.innerHTML = balances.map(b => `<span style="margin-left: 0.75rem;">${b.currency} ${parseFloat(b.amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`).join('');
                balanceBar.style.display = 'block';
            } else {
                balanceBar.style.display = 'none';
            }
        }

        if (!reports || reports.length === 0) {
            container.innerHTML = setupNoticeHtml + '<div style="text-align: center; padding: 3rem; color: var(--text-secondary);">No saved payroll reports yet. Generate a payroll report and click "Save for Wise" to add one.</div>';
            document.getElementById('wiseStatusCounters')?.style.setProperty('display', 'none');
            bindWiseSetupGuide();
            return;
        }

        const totalReports = reports.length;
        const wiseTotalPages = Math.max(1, Math.ceil(totalReports / wisePageSize));
        const startIdx = (wiseCurrentPage - 1) * wisePageSize;
        const reportsToShow = reports.slice(startIdx, startIdx + wisePageSize);

        let html = setupNoticeHtml + `<div class="table-container">
            <table class="data-table" id="wiseReportsTable">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Total Amount</th>
                        <th>Employees</th>
                        <th>Saved By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>`;

        let totalSent = 0, totalPending = 0, totalOther = 0;

        reportsToShow.forEach(report => {
            const items = report.items || [];
            const sentCount = items.filter(i => (i.wise_status || 'pending') === 'sent').length;
            const pendingCount = items.filter(i => (i.wise_status || 'pending') === 'pending').length;
            const otherCount = items.filter(i => !['sent','pending'].includes(i.wise_status || 'pending')).length;
            totalSent += sentCount;
            totalPending += pendingCount;
            totalOther += Math.max(0, otherCount);

            const periodStart = report.period_start_date ? new Date(report.period_start_date).toLocaleDateString() : '--';
            const periodEnd = report.period_end_date ? new Date(report.period_end_date).toLocaleDateString() : '--';
            const totalAmount = parseFloat(report.total_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
            const currency = report.currency || 'USD';
            const createdBy = (report.created_by && report.created_by.name) ? report.created_by.name : '--';
            const created = report.created_at ? new Date(report.created_at).toLocaleDateString() : '--';
            const itemCount = items.length;
            const status = report.status || 'ready_for_wise';
            const statusLabel = status === 'sent' ? 'Sent' : status === 'queued' ? 'Queued' : 'Ready';
            const hasActuallySentItems = items.some(i => (i.wise_status || '') === 'sent' && parseFloat(i.net_pay || 0) > 0);
            const canDeleteReport = !hasActuallySentItems;
            const allSent = itemCount > 0 && sentCount === itemCount;

            html += `<tr class="wise-report-row" data-report-id="${report.id}">
                <td>${periodStart} – ${periodEnd}</td>
                <td>${currency} ${totalAmount}</td>
                <td>${itemCount}</td>
                <td>${escapeHtml(createdBy)}<br><small style="color: var(--text-secondary);">${created}</small></td>
                <td>
                    <span class="wise-status-badge status-${status}">${statusLabel}</span>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        Sent: ${sentCount} | Pending: ${pendingCount}${otherCount > 0 ? ' | Other: ' + otherCount : ''}
                    </div>
                </td>
                <td>
                    ${allSent ? '<button type="button" class="btn btn-sm btn-secondary" disabled title="All employees already sent">All Sent</button>' : (wiseReady ? '<button type="button" class="btn btn-sm btn-primary send-wise-btn" data-report-id="' + report.id + '" title="Send this report to Wise">Send to Wise</button>' : '<button type="button" class="btn btn-sm btn-secondary" disabled title="Set up Wise API first">Send to Wise</button>')}
                    <button type="button" class="btn btn-sm btn-secondary toggle-details-btn" data-report-id="${report.id}" title="View employees">Details</button>
                    <div class="wise-download-dropdown">
                        <button type="button" class="btn btn-sm btn-secondary wise-download-btn" data-report-id="${report.id}" title="Download report">Download &#9662;</button>
                        <div class="wise-download-menu" id="wise-download-menu-${report.id}" style="display: none;">
                            <a href="${wiseReportBaseUrl}/${report.id}/export/excel" class="wise-download-item">Download Excel</a>
                            <a href="${wiseReportBaseUrl}/${report.id}/export/pdf" class="wise-download-item">Download PDF</a>
                        </div>
                    </div>
                    ${canDeleteReport ? `<button type="button" class="btn btn-sm btn-danger delete-report-wise-btn" data-report-id="${report.id}" title="Delete this report">Delete</button>` : '<span style="color: var(--text-secondary); font-size: 0.75rem;">Cannot delete (sent)</span>'}
                </td>
            </tr>`;

            // Expandable detail row (items table)
            html += `<tr class="wise-detail-row" data-report-id="${report.id}" style="display: none;">
                <td colspan="6" style="padding: 0; background: var(--bg-secondary, #f8f9fa); border-top: none;">
                    <div class="wise-detail-content" style="padding: 1rem 1.5rem;">
                        <table class="data-table wise-items-table" style="font-size: 0.8125rem;">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Hours</th>
                                    <th>Net Pay</th>
                                    <th>Wise Account</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>`;
            if (items.length > 0) {
                items.forEach(item => {
                    const netPayVal = item.net_pay_display !== undefined ? item.net_pay_display : item.net_pay;
                    const netPayCur = item.currency_display || currency;
                    const netPay = parseFloat(netPayVal || 0).toLocaleString('en-US', { minimumFractionDigits: 2 });
                    const feeVal = parseFloat(item.fee_amount || 0);
                    const feeCur = item.fee_currency || currency;
                    const feeStr = feeVal > 0 ? ` (Fee: ${feeCur} ${feeVal.toLocaleString('en-US', { minimumFractionDigits: 2 })}` + ')' : '';
                    const itemStatus = item.wise_status || 'pending';
                    const itemStatusLabel = item.wise_status_label || (itemStatus === 'sent' ? 'Sent' : itemStatus === 'queued' ? 'Queued' : itemStatus === 'failed' ? 'Failed' : 'Pending');
                    const wiseApiStatus = (item.wise_api_status || '').replace(/_/g, '-');
                    const statusClass = wiseApiStatus ? 'status-wise-' + wiseApiStatus : 'status-' + itemStatus;
                    const canSend = itemStatus !== 'sent';
                    const canDeleteItem = canSend || (itemStatus === 'sent' && parseFloat(item.net_pay || 0) === 0);
                    const errMsg = (item.wise_error || '').trim();
                    const statusTitle = item.wise_transfer_id ? 'Wise ID: ' + item.wise_transfer_id : '';
                    const statusCell = itemStatus === 'failed' && errMsg
                        ? `<span class="wise-status-badge ${statusClass}" title="${escapeHtml(errMsg)}">${escapeHtml(itemStatusLabel)}</span><div class="wise-error-reason" title="${escapeHtml(errMsg)}">${escapeHtml(errMsg)}</div>`
                        : `<span class="wise-status-badge ${statusClass}" title="${statusTitle || (errMsg ? escapeHtml(errMsg) : '')}">${escapeHtml(itemStatusLabel)}</span>`;
                    html += `<tr class="wise-item-row" data-item-id="${item.id}">
                        <td>${escapeHtml(item.employee_name || '--')}</td>
                        <td>${item.hours_worked_seconds != null ? formatSecondsToHms(item.hours_worked_seconds) : escapeHtml(String(item.hours_worked ?? '--'))}</td>
                        <td>${netPayCur} ${netPay}${feeStr}</td>
                        <td>${escapeHtml(item.wise_account || '--')}</td>
                        <td>${statusCell}</td>
                        <td>
                            ${canSend ? (wiseReady ? `<button type="button" class="btn btn-sm btn-primary send-item-wise-btn" data-item-id="${item.id}" title="Send this employee to Wise">Send</button>` : '<button type="button" class="btn btn-sm btn-secondary" disabled title="Set up Wise API first">Send</button>') : '<span style="color: var(--text-secondary); font-size: 0.75rem;">Sent</span>'}
                            ${canDeleteItem ? `<button type="button" class="btn btn-sm btn-danger delete-item-wise-btn" data-item-id="${item.id}" data-report-id="${report.id}" title="Remove this employee from report">Delete</button>` : ''}
                        </td>
                    </tr>`;
                });
            } else {
                html += '<tr><td colspan="6" style="text-align: center; color: var(--text-secondary);">No items</td></tr>';
            }
            html += '</tbody></table></div></td></tr>';
        });

        html += '</tbody></table></div>';

        const paginationStart = totalReports > 0 ? startIdx + 1 : 0;
        const paginationEnd = Math.min(startIdx + wisePageSize, totalReports);
        html += `<div class="table-pagination" id="wisePaginationWrap">
            <div class="pagination-info"><span id="wisePaginationInfo">Showing ${paginationStart} to ${paginationEnd} of ${totalReports} results</span></div>
            <div class="pagination-controls">
                <button type="button" class="pagination-btn" id="wisePrevBtn" ${wiseCurrentPage <= 1 ? 'disabled' : ''}>Previous</button>
                <div class="pagination-numbers" id="wisePaginationNumbers"></div>
                <button type="button" class="pagination-btn" id="wiseNextBtn" ${wiseCurrentPage >= wiseTotalPages ? 'disabled' : ''}>Next</button>
            </div>
        </div>`;

        container.innerHTML = html;
        bindWiseSetupGuide();
        updateWisePagination(totalReports, wiseTotalPages);
        bindWisePaginationClicks();
        bindWiseDownloadDropdowns();

        // Update summary counters
        const countersBar = document.getElementById('wiseStatusCounters');
        if (countersBar) {
            document.getElementById('wiseCounterSent').textContent = totalSent;
            document.getElementById('wiseCounterPending').textContent = totalPending;
            document.getElementById('wiseCounterOther').textContent = totalOther;
            countersBar.style.display = 'block';
        }

        // Event listeners
        document.querySelectorAll('.send-wise-btn').forEach(btn => {
            btn.addEventListener('click', () => sendPayrollToWise(parseInt(btn.dataset.reportId)));
        });

        document.querySelectorAll('.toggle-details-btn').forEach(btn => {
            btn.addEventListener('click', () => toggleWiseReportDetails(parseInt(btn.dataset.reportId)));
        });

        document.querySelectorAll('.send-item-wise-btn').forEach(btn => {
            btn.addEventListener('click', () => sendPayrollItemToWise(parseInt(btn.dataset.itemId)));
        });

        document.querySelectorAll('.delete-report-wise-btn').forEach(btn => {
            btn.addEventListener('click', () => deletePayrollReport(parseInt(btn.dataset.reportId)));
        });

        document.querySelectorAll('.delete-item-wise-btn').forEach(btn => {
            btn.addEventListener('click', () => deletePayrollReportItem(parseInt(btn.dataset.itemId), parseInt(btn.dataset.reportId)));
        });
    }

    function updateWisePagination(totalRecords, totalPages) {
        const start = totalRecords > 0 ? (wiseCurrentPage - 1) * wisePageSize + 1 : 0;
        const end = Math.min(wiseCurrentPage * wisePageSize, totalRecords);
        const infoEl = document.getElementById('wisePaginationInfo');
        const prevBtn = document.getElementById('wisePrevBtn');
        const nextBtn = document.getElementById('wiseNextBtn');
        const numbersEl = document.getElementById('wisePaginationNumbers');
        if (infoEl) infoEl.textContent = `Showing ${start} to ${end} of ${totalRecords} results`;
        if (prevBtn) prevBtn.disabled = wiseCurrentPage <= 1;
        if (nextBtn) nextBtn.disabled = wiseCurrentPage >= totalPages;
        if (numbersEl) {
            let html = '';
            if (totalPages > 1) {
                const maxVisible = 5;
                let startPage = Math.max(1, wiseCurrentPage - Math.floor(maxVisible / 2));
                let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);
                if (startPage > 1) {
                    html += `<button type="button" class="pagination-number" data-page="1">1</button>`;
                    if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
                }
                for (let i = startPage; i <= endPage; i++) {
                    html += `<button type="button" class="pagination-number ${i === wiseCurrentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
                }
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) html += `<span class="pagination-number ellipsis">...</span>`;
                    html += `<button type="button" class="pagination-number" data-page="${totalPages}">${totalPages}</button>`;
                }
            }
            numbersEl.innerHTML = html;
        }
    }

    function bindWisePaginationClicks() {
        const prevBtn = document.getElementById('wisePrevBtn');
        const nextBtn = document.getElementById('wiseNextBtn');
        const numbersEl = document.getElementById('wisePaginationNumbers');
        if (prevBtn) prevBtn.addEventListener('click', () => {
            if (wiseCurrentPage > 1) { wiseCurrentPage--; renderSavedForWise(savedWiseReports, savedWiseConfigured, lastWiseBalances); }
        });
        if (nextBtn) nextBtn.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(savedWiseReports.length / wisePageSize));
            if (wiseCurrentPage < totalPages) { wiseCurrentPage++; renderSavedForWise(savedWiseReports, savedWiseConfigured, lastWiseBalances); }
        });
        if (numbersEl) numbersEl.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
            btn.addEventListener('click', () => {
                wiseCurrentPage = parseInt(btn.dataset.page);
                renderSavedForWise(savedWiseReports, savedWiseConfigured, lastWiseBalances);
            });
        });
    }

    function bindWiseDownloadDropdowns() {
        document.querySelectorAll('.wise-download-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const menuId = 'wise-download-menu-' + btn.dataset.reportId;
                const menu = document.getElementById(menuId);
                const isOpen = menu && menu.style.display === 'block';
                document.querySelectorAll('.wise-download-menu').forEach(m => { m.style.display = 'none'; });
                if (menu && !isOpen) {
                    const rect = btn.getBoundingClientRect();
                    menu.style.display = 'block';
                    menu.style.top = (rect.bottom + 4) + 'px';
                    menu.style.left = rect.left + 'px';
                }
            });
        });
        document.addEventListener('click', function() {
            document.querySelectorAll('.wise-download-menu').forEach(m => { m.style.display = 'none'; });
        });
    }

    function toggleWiseReportDetails(reportId) {
        const detailRow = document.querySelector(`.wise-detail-row[data-report-id="${reportId}"]`);
        const btn = document.querySelector(`.toggle-details-btn[data-report-id="${reportId}"]`);
        if (!detailRow || !btn) return;
        const isShown = detailRow.style.display !== 'none';
        detailRow.style.display = isShown ? 'none' : 'table-row';
        btn.textContent = isShown ? 'Details' : 'Hide';
    }

    async function sendPayrollItemToWise(itemId) {
        if (!confirm('Send this employee\'s payroll to Wise?')) return;
        const btn = document.querySelector(`.send-item-wise-btn[data-item-id="${itemId}"]`);
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Sending...';
        }
        try {
            const url = '{{ route("api.payroll.report-item.send-wise", ["payrollReportItem" => "__ID__"]) }}'.replace('__ID__', itemId);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            const result = await response.json();
            if (result.success) {
                alert(result.message || 'Employee payroll sent to Wise successfully.');
                fetchSavedPayrollReports();
            } else {
                alert(result.message || 'Failed to send to Wise.');
                if (btn) { btn.disabled = false; btn.textContent = 'Send to Wise'; }
            }
        } catch (error) {
            console.error('Error sending item to Wise:', error);
            alert('Error sending payroll to Wise. Please try again.');
            if (btn) { btn.disabled = false; btn.textContent = 'Send to Wise'; }
        }
    }

    async function sendPayrollToWise(reportId) {
        if (!confirm('Send this entire payroll report to Wise?')) return;
        const btn = document.querySelector(`.send-wise-btn[data-report-id="${reportId}"]`);
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Sending...';
        }
        try {
            const url = '{{ route("api.payroll.report.send-wise", ["payrollReport" => "__ID__"]) }}'.replace('__ID__', reportId);
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            const result = await response.json();
            if (result.success) {
                alert(result.message || 'Payroll sent to Wise successfully.');
                fetchSavedPayrollReports();
            } else {
                alert(result.message || 'Failed to send to Wise.');
                if (btn) { btn.disabled = false; btn.textContent = 'Send to Wise'; }
            }
        } catch (error) {
            console.error('Error sending to Wise:', error);
            alert('Error sending payroll to Wise. Please try again.');
            if (btn) { btn.disabled = false; btn.textContent = 'Send to Wise'; }
        }
    }

    async function deletePayrollReport(reportId) {
        if (!confirm('Delete this payroll report? This cannot be undone.')) return;
        try {
            const url = '{{ route("api.payroll.report.delete", ["payrollReport" => "__ID__"]) }}'.replace('__ID__', reportId);
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            const result = await response.json();
            if (result.success) {
                fetchSavedPayrollReports();
            } else {
                alert(result.message || 'Failed to delete report.');
            }
        } catch (error) {
            console.error('Error deleting report:', error);
            alert('Error deleting report. Please try again.');
        }
    }

    async function deletePayrollReportItem(itemId, reportId) {
        if (!confirm('Remove this employee from the report?')) return;
        try {
            const url = '{{ route("api.payroll.report-item.delete", ["payrollReportItem" => "__ID__"]) }}'.replace('__ID__', itemId);
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            });
            const result = await response.json();
            if (result.success) {
                fetchSavedPayrollReports();
            } else {
                alert(result.message || 'Failed to remove employee.');
            }
        } catch (error) {
            console.error('Error removing employee:', error);
            alert('Error removing employee. Please try again.');
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Filter event listeners
    document.getElementById('employeeFilter').addEventListener('change', () => {
        timeCurrentPage = 1;
        fetchTimeTrackingRecords();
    });

    document.getElementById('dateStartFilter').addEventListener('change', () => {
        timeCurrentPage = 1;
        fetchTimeTrackingRecords();
    });

    document.getElementById('dateEndFilter').addEventListener('change', () => {
        timeCurrentPage = 1;
        fetchTimeTrackingRecords();
    });

    // Window Resize Handler
    window.addEventListener('resize', () => {
        renderTimeLogs();
    });

    // Set default date range to current month on page load
    function setDefaultDateRange() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const firstDay = `${year}-${month}-01`;
        const lastDay = `${year}-${month}-${new Date(year, now.getMonth() + 1, 0).getDate()}`;
        
        const startDateInput = document.getElementById('dateStartFilter');
        const endDateInput = document.getElementById('dateEndFilter');
        
        // Only set if not already set
        if (!startDateInput.value) {
            startDateInput.value = firstDay;
        }
        if (!endDateInput.value) {
            endDateInput.value = lastDay;
        }
    }

    // Edit Modal Functions
    let currentEditRecord = null;

    function openEditModal(recordId) {
        currentEditRecord = recordId;
        const record = timeLogsData.find(r => r.id === recordId);
        
        if (!record) {
            alert('Record not found');
            return;
        }

        // Populate form
        document.getElementById('editRecordId').value = recordId;
        document.getElementById('editEmployeeName').value = record.employee.name;
        document.getElementById('editDate').value = record.date;
        
        // Convert time format from "09:00 AM" to "09:00:00"
        const timeIn24 = convertTo24Hour(record.timeIn);
        const timeOut24 = convertTo24Hour(record.timeOut);
        
        // Parse date format "M d, Y" (e.g., "Jan 09, 2026") to YYYY-MM-DD for date input
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dateParts = record.date.match(/(\w+) (\d+), (\d+)/);
        let timeOutDate = '';
        if (dateParts) {
            const monthName = dateParts[1];
            const day = dateParts[2];
            const year = dateParts[3];
            const monthIndex = monthNames.indexOf(monthName);
            if (monthIndex !== -1) {
                const baseDate = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                
                // If time out is earlier than time in, assume it's the next day (cross-day scenario)
                if (timeIn24 && timeOut24) {
                    const timeIn24Full = timeIn24.length === 5 ? timeIn24 + ':00' : timeIn24;
                    const timeOut24Full = timeOut24.length === 5 ? timeOut24 + ':00' : timeOut24;
                    const startDateTime = new Date(`${baseDate}T${timeIn24Full}`);
                    const endDateTime = new Date(`${baseDate}T${timeOut24Full}`);
                    
                    if (endDateTime < startDateTime) {
                        // Time out is on next day
                        const nextDay = new Date(startDateTime);
                        nextDay.setDate(nextDay.getDate() + 1);
                        timeOutDate = nextDay.toISOString().split('T')[0];
                    } else {
                        timeOutDate = baseDate;
                    }
                } else {
                    timeOutDate = baseDate;
                }
            }
        }
        
        document.getElementById('editTimeIn').value = timeIn24;
        document.getElementById('editTimeOut').value = timeOut24;
        document.getElementById('editTimeOutDate').value = timeOutDate || '';
        document.getElementById('editTotalHours').value = formatHoursToTime(record.totalHours);
        document.getElementById('editReason').value = '';

        // Show modal
        document.getElementById('editTimeModal').style.display = 'flex';
        
        // Load history
        loadEditHistory(recordId);

        // Remove existing event listeners and add new ones for auto-calculation
        const timeInInput = document.getElementById('editTimeIn');
        const timeOutInput = document.getElementById('editTimeOut');
        const timeOutDateInput = document.getElementById('editTimeOutDate');
        
        // Clone and replace to remove old listeners
        const newTimeIn = timeInInput.cloneNode(true);
        const newTimeOut = timeOutInput.cloneNode(true);
        const newTimeOutDate = timeOutDateInput.cloneNode(true);
        timeInInput.parentNode.replaceChild(newTimeIn, timeInInput);
        timeOutInput.parentNode.replaceChild(newTimeOut, timeOutInput);
        timeOutDateInput.parentNode.replaceChild(newTimeOutDate, timeOutDateInput);
        
        // Add event listeners for auto-calculation
        document.getElementById('editTimeIn').addEventListener('input', calculateHours);
        document.getElementById('editTimeOut').addEventListener('input', calculateHours);
        document.getElementById('editTimeOutDate').addEventListener('change', calculateHours);
    }

    function closeEditModal() {
        document.getElementById('editTimeModal').style.display = 'none';
        currentEditRecord = null;
    }

    function convertTo24Hour(time12h) {
        if (!time12h || time12h === '--') return '';
        
        const [time, modifier] = time12h.split(' ');
        let [hours, minutes] = time.split(':');
        
        if (hours === '12') {
            hours = '00';
        }
        
        if (modifier === 'PM') {
            hours = parseInt(hours, 10) + 12;
        }
        
        return `${hours.toString().padStart(2, '0')}:${minutes}:00`;
    }

    function calculateHours() {
        const timeIn = document.getElementById('editTimeIn').value;
        const timeOut = document.getElementById('editTimeOut').value;
        const date = document.getElementById('editDate').value;
        const timeOutDate = document.getElementById('editTimeOutDate').value;
        
        if (timeIn && timeOut && date) {
            // Parse date format "M d, Y" (e.g., "Jan 09, 2026")
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dateParts = date.match(/(\w+) (\d+), (\d+)/);
            
            if (dateParts) {
                const monthName = dateParts[1];
                const day = dateParts[2];
                const year = dateParts[3];
                const monthIndex = monthNames.indexOf(monthName);
                
                if (monthIndex !== -1) {
                    const dateYMD = `${year}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    
                    // Use time_out_date if provided, otherwise use the record's date
                    const endDateYMD = timeOutDate || dateYMD;
                    
                    // Ensure time has seconds
                    const timeIn24 = timeIn.length === 5 ? timeIn + ':00' : timeIn;
                    const timeOut24 = timeOut.length === 5 ? timeOut + ':00' : timeOut;
                    
                    const startDateTime = new Date(`${dateYMD}T${timeIn24}`);
                    let endDateTime = new Date(`${endDateYMD}T${timeOut24}`);
                    
                    // Handle next day scenario (fallback if dates are the same but times suggest next day)
                    if (endDateYMD === dateYMD && endDateTime < startDateTime) {
                        endDateTime.setDate(endDateTime.getDate() + 1);
                    }
                    
                    const diffMs = endDateTime - startDateTime;
                    const diffHours = Math.max(0, diffMs / (1000 * 60 * 60));
                    
                    document.getElementById('editTotalHours').value = formatHoursToTime(diffHours);
                }
            }
        }
    }

    async function loadEditHistory(recordId) {
        const historyList = document.getElementById('editHistoryList');
        historyList.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--text-secondary);">Loading history...</div>';
        
        try {
            const response = await fetch(`/api/payroll/time-tracking-records/${recordId}/history`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                if (result.data.length === 0) {
                    historyList.innerHTML = '<div style="text-align: center; padding: 1rem; color: var(--text-secondary);">No edit history</div>';
                } else {
                    historyList.innerHTML = result.data.map(item => `
                        <div class="history-item">
                            <div class="history-item-header">
                                <span class="history-item-user">${item.edited_by}</span>
                                <span class="history-item-date">${item.edited_at}</span>
                            </div>
                            ${item.old_time_in !== item.new_time_in ? `<div class="history-item-changes">Time In: ${item.old_time_in} → ${item.new_time_in}</div>` : ''}
                            ${item.old_time_out !== item.new_time_out ? `<div class="history-item-changes">Time Out: ${item.old_time_out} → ${item.new_time_out}</div>` : ''}
                            ${item.old_hours !== item.new_hours ? `<div class="history-item-changes">Total Hours: ${formatHoursToTime(item.old_hours)} → ${formatHoursToTime(item.new_hours)}</div>` : ''}
                            ${item.reason ? `<div class="history-item-reason">Reason: ${item.reason}</div>` : ''}
                        </div>
                    `).join('');
                }
            }
        } catch (error) {
            console.error('Error loading edit history:', error);
            historyList.innerHTML = '<div style="text-align: center; padding: 1rem; color: red;">Error loading history</div>';
        }
    }

    async function saveTimeEdit() {
        const recordId = document.getElementById('editRecordId').value;
        const timeIn = document.getElementById('editTimeIn').value;
        const timeOut = document.getElementById('editTimeOut').value;
        const timeOutDate = document.getElementById('editTimeOutDate').value;
        const reason = document.getElementById('editReason').value;

        if (!timeIn || !timeOut) {
            alert('Please provide both time in and time out');
            return;
        }

        if (!confirm('Save changes to this time tracking record?')) return;

        // Convert time format to H:i:s
        const timeIn24 = timeIn.length === 5 ? timeIn + ':00' : timeIn;
        const timeOut24 = timeOut.length === 5 ? timeOut + ':00' : timeOut;

        try {
            const requestBody = {
                time_in: timeIn24,
                time_out: timeOut24,
                reason: reason
            };
            
            // Include time_out_date only if it's different from the record's date
            if (timeOutDate) {
                requestBody.time_out_date = timeOutDate;
            }

            const response = await fetch(`/api/payroll/time-tracking-records/${recordId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                alert('Time tracking record updated successfully');
                closeEditModal();
                // Refresh the data
                fetchTimeTrackingRecords();
            } else {
                alert('Error: ' + (result.message || 'Failed to update record'));
            }
        } catch (error) {
            console.error('Error updating record:', error);
            alert('Error updating record. Please try again.');
        }
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('editTimeModal');
        if (event.target === modal) {
            closeEditModal();
        }
        const salaryModal = document.getElementById('editSalaryModal');
        if (event.target === salaryModal) {
            closeEditSalaryModal();
        }
    });

    // Salary Edit Modal Functions
    // Initialize
    setDefaultDateRange();
    const convertedInvoicesMonthFilter = document.getElementById('convertedInvoicesMonthFilter');
    if (convertedInvoicesMonthFilter && !convertedInvoicesMonthFilter.value) {
        convertedInvoicesMonthFilter.value = getCurrentMonthString();
    }
    Promise.all([loadEmployees(), loadReportClients()]).then(() => {
        fetchTimeTrackingRecords();
    });
    // Initialize report with empty state
    renderReport();
</script>
@endpush

