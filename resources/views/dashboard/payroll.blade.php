@extends('layouts.app')

@section('title', 'Time Sheets & Payroll')

@push('styles')
    @vite(['resources/css/pages/payroll.css'])
@endpush

@push('scripts')
<script>
    window.__payrollConfig = {
        wiseReportBaseUrl: @json(url('api/payroll/payroll-report')),
        permissions: {
            viewTimeInOut: @json((bool) auth()->user()?->hasPermission('view_time_in_out')),
            editTimeInOut: @json((bool) auth()->user()?->hasPermission('edit_time_in_out')),
            exportTimeInOut: @json((bool) auth()->user()?->hasPermission('export_time_in_out')),
            viewPayrollReport: @json((bool) auth()->user()?->hasPermission('view_payroll_report')),
            generatePayrollReport: @json((bool) auth()->user()?->hasPermission('generate_payroll_report')),
            viewSavedForWise: @json((bool) auth()->user()?->hasPermission('view_saved_for_wise')),
            exportPayrollReport: @json((bool) auth()->user()?->hasPermission('export_payroll_report')),
        },
        reportSaveUrl: @json(route('api.payroll.report.save')),
        reportSavedUrl: @json(route('api.payroll.report.saved')),
        pdfBaseUrl: @json(url('/api/billing-invoices')),
        billingUrl: @json(route('billing')),
        integrationsUrl: @json(route('integrations')),
        reportItemSendWiseUrl: @json(route('api.payroll.report-item.send-wise', ['payrollReportItem' => '__ID__'])),
        reportSendWiseUrl: @json(route('api.payroll.report.send-wise', ['payrollReport' => '__ID__'])),
        reportDeleteUrl: @json(route('api.payroll.report.delete', ['payrollReport' => '__ID__'])),
        reportItemDeleteUrl: @json(route('api.payroll.report-item.delete', ['payrollReportItem' => '__ID__'])),
    };
</script>
    @vite(['resources/js/pages/payroll.js'])
@endpush

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
                        <tbody id="timeLogsTableBody" aria-busy="true">
                            @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 6])
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
                            <tbody id="reportTableBody" aria-busy="true">
                                @include('partials.skeleton-table-rows', ['rows' => 6, 'cols' => 6])
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
