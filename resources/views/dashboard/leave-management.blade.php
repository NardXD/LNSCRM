@extends('layouts.app')

@section('title', 'Leave Management')

@push('styles')
<style>
    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .page-subtitle {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }

    .leave-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Tabs */
    .management-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        overflow-x: auto;
    }

    .tab-btn {
        flex: 1;
        min-width: 150px;
        padding: 0.75rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
    }

    .tab-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .tab-btn.active {
        background: var(--accent);
        color: white;
    }

    .status-tab-btn {
        min-width: 100px;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
    }

    .stat-label {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
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
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .section-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Filters */
    .filter-select, .date-input {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.8125rem;
        background: var(--bg-card);
        color: var(--text-primary);
        min-width: 150px;
    }

    /* Buttons */
    .btn {
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.8125rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.15s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
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
        background: var(--bg-card);
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-success:hover {
        background: #059669;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
    }

    /* Table */
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin-bottom: 1.5rem;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--bg-card);
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

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-cancelled {
        background: #e5e7eb;
        color: #374151;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 2rem;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-body {
        margin: 1.5rem 0;
    }

    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
    }

    .modal-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.25rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }

    .modal-close:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .credits-info {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .credits-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
    }

    .credits-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .credits-value {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .credits-used {
        color: #ef4444;
    }

    .credits-available {
        color: #10b981;
    }

    /* Leave request attachment preview */
    .attachment-preview {
        margin-top: 0.5rem;
    }

    .attachment-preview img.attachment-thumb {
        max-width: 200px;
        max-height: 150px;
        object-fit: contain;
        border-radius: 8px;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: opacity 0.15s, border-color 0.15s;
    }

    .attachment-preview img.attachment-thumb:hover {
        opacity: 0.9;
        border-color: var(--accent);
    }

    /* Fullscreen image overlay */
    .attachment-fullscreen {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.9);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .attachment-fullscreen.active {
        display: flex;
    }

    .attachment-fullscreen img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .attachment-fullscreen-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 1.5rem;
        cursor: pointer;
        transition: background 0.15s;
    }

    .attachment-fullscreen-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .attachment-preview-pdf {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--accent);
        text-decoration: none;
        font-size: 0.8125rem;
        font-weight: 500;
        transition: background 0.15s, border-color 0.15s;
    }

    .attachment-preview-pdf:hover {
        background: var(--bg-card);
        border-color: var(--accent);
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

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.8125rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .form-textarea {
        min-height: 100px;
        resize: vertical;
    }

    .form-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--text-secondary);
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .data-table td .empty-state {
        padding: 2rem 1rem;
    }

    .data-table td .empty-state-icon {
        font-size: 2.5rem;
    }

    /* User Credits Group Styles */
    .user-credits-group {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .user-credits-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid var(--border);
    }

    .user-credits-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .user-credits-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .user-credits-email {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .user-credits-count {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    /* Calendar Styles */
    .calendar-container {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        overflow-x: auto;
    }

    .calendar-header {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .calendar-day-header {
        text-align: center;
        font-weight: 600;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        padding: 0.5rem;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
    }

    .calendar-day {
        min-height: 80px;
        padding: 0.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-primary);
        cursor: pointer;
        transition: all 0.15s;
        position: relative;
    }

    .calendar-day:hover {
        background: var(--bg-card);
        border-color: var(--accent);
    }

    .calendar-day.other-month {
        opacity: 0.3;
        background: var(--bg-card);
    }

    .calendar-day-number {
        font-weight: 600;
        font-size: 0.8125rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .calendar-day-count {
        font-size: 0.7rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .calendar-day.has-leaves {
        background: #fef3c7;
        border-color: #fbbf24;
    }

    .calendar-day.has-leaves:hover {
        background: #fde68a;
        border-color: #f59e0b;
    }

    .calendar-day-leave-badge {
        display: inline-block;
        background: var(--accent);
        color: white;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        margin-top: 0.25rem;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1 class="page-title">Leave Management</h1>
    <p class="page-subtitle">Manage leave requests and credits for your team</p>
</div>

<div class="leave-container">
    <!-- Stats Cards -->
    @if($permissions['view_stats'] ?? false)
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value" id="statPending">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Approved This Year</div>
            <div class="stat-value" id="statApproved">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Credits</div>
            <div class="stat-value" id="statTotalCredits">0</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Available Credits</div>
            <div class="stat-value" id="statAvailableCredits">0</div>
        </div>
    </div>
    @endif

    <!-- Tabs -->
    <div class="management-tabs" id="mainTabs">
        <button class="tab-btn main-tab-btn active" onclick="switchTab('requests')">Leave Requests</button>
        @if($permissions['view_calendar'] ?? false)
        <button class="tab-btn main-tab-btn" onclick="switchTab('calendar')">Calendar</button>
        @endif
        @if($permissions['view_credits'] ?? false || $permissions['manage_credits'] ?? false)
        <button class="tab-btn main-tab-btn" onclick="switchTab('credits')">Leave Credits</button>
        @endif
    </div>

    <!-- Leave Requests Tab -->
    <div id="requestsTab" class="tab-content active">
        <div class="section-header">
            <h2 class="section-title">Leave Requests</h2>
            @if($permissions['create_request'] ?? false)
            <div class="section-actions">
                <button class="btn btn-primary" onclick="openNewRequestModal()">New Request</button>
            </div>
            @endif
        </div>

        <div class="status-tabs management-tabs" style="margin-bottom: 1.5rem;">
            <button class="tab-btn status-tab-btn active" data-status="pending" onclick="switchStatusTab('pending')">Pending</button>
            <button class="tab-btn status-tab-btn" data-status="approved" onclick="switchStatusTab('approved')">Approved</button>
            <button class="tab-btn status-tab-btn" data-status="rejected" onclick="switchStatusTab('rejected')">Rejected</button>
            <button class="tab-btn status-tab-btn" data-status="cancelled" onclick="switchStatusTab('cancelled')">Cancelled</button>
        </div>

        <div class="table-container">
            <table class="data-table" id="requestsTable">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Leave Type</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="requestsTableBody">
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <div class="empty-state-icon">📋</div>
                                <p>No leave requests found</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" id="leaveRequestsPagination" style="display: none;">
            <div class="pagination-info">
                <span id="leaveRequestsPaginationInfo">Showing 0 results</span>
            </div>
            <div class="pagination-controls">
                <button class="pagination-btn" id="leaveRequestsPrevBtn" disabled>Previous</button>
                <div class="pagination-numbers" id="leaveRequestsPaginationNumbers"></div>
                <button class="pagination-btn" id="leaveRequestsNextBtn" disabled>Next</button>
            </div>
        </div>
    </div>

    <!-- Calendar Tab -->
    <div id="calendarTab" class="tab-content">
        <div class="section-header">
            <h2 class="section-title">Leave Calendar</h2>
            <div class="section-actions">
                <button class="btn btn-secondary" onclick="previousMonth()">← Previous</button>
                <select class="filter-select" id="calendarMonth" onchange="loadCalendar()">
                    <option value="01">January</option>
                    <option value="02">February</option>
                    <option value="03">March</option>
                    <option value="04">April</option>
                    <option value="05">May</option>
                    <option value="06">June</option>
                    <option value="07">July</option>
                    <option value="08">August</option>
                    <option value="09">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
                <select class="filter-select" id="calendarYear" onchange="loadCalendar()">
                    <option value="2026"selected>2026</option>
                    <option value="2027">2027</option>
                    <option value="2028">2028</option>
                    <option value="2029">2029</option>
                    <option value="2030">2030</option>
                    <option value="2031">2031</option>
                    <option value="2032">2032</option>
                    <option value="2033">2033</option>
                    <option value="2034">2034</option>
                    <option value="2035">2035</option>
                    <option value="2036">2036</option>
                    <option value="2037">2037</option>
                    <option value="2038">2038</option>
                    <option value="2039">2039</option>
                    <option value="2040">2040</option>
                </select>
                <button class="btn btn-secondary" onclick="nextMonth()">Next →</button>
            </div>
        </div>

        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-day-header">Sun</div>
                <div class="calendar-day-header">Mon</div>
                <div class="calendar-day-header">Tue</div>
                <div class="calendar-day-header">Wed</div>
                <div class="calendar-day-header">Thu</div>
                <div class="calendar-day-header">Fri</div>
                <div class="calendar-day-header">Sat</div>
            </div>
            <div class="calendar-grid" id="calendarGrid">
                <!-- Calendar days will be populated here -->
            </div>
        </div>
    </div>

    <!-- Leave Credits Tab -->
    <div id="creditsTab" class="tab-content">
        @if($permissions['view_credits'] ?? false || $permissions['manage_credits'] ?? false)
        <div class="section-header">
            <h2 class="section-title">Leave Credits</h2>
            <div class="section-actions">
                <input type="text" class="form-input" id="creditsSearchInput" placeholder="Search by employee name..." style="min-width: 250px; margin-right: 0.75rem;" onkeyup="filterCredits()">
                <select class="filter-select" id="yearFilter" onchange="loadLeaveCredits()">
                    <option value="2026" selected>2026</option>
                    <option value="2027">2027</option>
                    <option value="2028">2028</option>
                    <option value="2029">2029</option>
                    <option value="2030">2030</option>
                    <option value="2031">2031</option>
                    <option value="2032">2032</option>
                    <option value="2033">2033</option>
                    <option value="2034">2034</option>
                    <option value="2035">2035</option>
                    <option value="2036">2036</option>
                    <option value="2037">2037</option>
                    <option value="2038">2038</option>
                    <option value="2039">2039</option>
                    <option value="2040">2040</option>
                </select>
                @if($permissions['manage_credits'] ?? false)
                <button class="btn btn-primary" onclick="openNewCreditModal()">Add Credits</button>
                @endif
            </div>
        </div>

        <div id="creditsTableBody">
            <div class="empty-state">
                <div class="empty-state-icon">💳</div>
                <p>No leave credits found</p>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- New Leave Request Modal -->
@if($permissions['create_request'] ?? false)
<div id="newRequestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">New Leave Request</h3>
            <button class="modal-close" onclick="closeNewRequestModal()">&times;</button>
        </div>
        <form id="newRequestForm" enctype="multipart/form-data" onsubmit="submitLeaveRequest(event)">
            <div class="form-group">
                <label class="form-label">Leave Type</label>
                <select class="form-select" name="leave_type" id="leaveTypeSelect" required onchange="updateCreditsDisplay(); toggleAttachmentField();">
                    <option value="">Select type</option>
                    <option value="vacation">Vacation</option>
                    <option value="sick">Sick Leave</option>
                    <option value="personal">Personal</option>
                    <option value="emergency">Emergency</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group" id="creditsInfoGroup" style="display: none;">
                <div class="credits-info">
                    <div class="credits-item">
                        <span class="credits-label">Total Credits:</span>
                        <span class="credits-value" id="totalCredits">0</span>
                    </div>
                    <div class="credits-item">
                        <span class="credits-label">Used Credits:</span>
                        <span class="credits-value credits-used" id="usedCredits">0</span>
                    </div>
                    <div class="credits-item">
                        <span class="credits-label">Available Credits:</span>
                        <span class="credits-value credits-available" id="availableCredits">0</span>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-input" name="start_date" id="startDateInput" required min="{{ date('Y-m-d') }}" onchange="validateDates()">
                <span class="form-error" id="startDateError" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" class="form-input" name="end_date" id="endDateInput" required onchange="validateDates()">
                <span class="form-error" id="endDateError" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
            </div>
            <div class="form-group">
                <label class="form-label">Reason (Optional)</label>
                <textarea class="form-textarea" name="reason" rows="4"></textarea>
            </div>
            <div class="form-group" id="attachmentGroup" style="display: none;">
                <label class="form-label">Attachment (Required for Sick Leave)</label>
                <input type="file" class="form-input" name="attachment" id="attachmentInput" accept=".pdf,.jpg,.jpeg,.png">
                <span class="form-error" id="attachmentError" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
                <span class="form-hint" style="display: block; font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">PDF, JPG, or PNG. Max 5 MB.</span>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeNewRequestModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Attachment fullscreen overlay -->
<div id="attachmentFullscreen" class="attachment-fullscreen" onclick="closeAttachmentFullscreen(event)">
    <button type="button" class="attachment-fullscreen-close" onclick="closeAttachmentFullscreen(event)" title="Close">&times;</button>
    <img id="attachmentFullscreenImg" src="" alt="Attachment" onclick="event.stopPropagation()">
</div>

<!-- Leave Request Details Modal -->
<div id="leaveRequestDetailsModal" class="modal">
    <div class="modal-content leave-request-details-modal" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title">Leave Request Details</h3>
            <button class="modal-close" onclick="closeLeaveRequestDetailsModal()">&times;</button>
        </div>
        <div class="modal-body" id="leaveRequestDetailsBody">
            <div class="credits-info" id="leaveRequestDetailsContent">
                <!-- Populated by JS -->
            </div>
            <div class="form-actions" id="leaveRequestDetailsActions" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                <!-- Action buttons populated by JS -->
            </div>
        </div>
    </div>
</div>

<!-- Cancel Request Confirmation Modal -->
<div id="cancelConfirmModal" class="modal">
    <div class="modal-content">
        <input type="hidden" id="cancelRequestId">
        <div class="modal-header">
            <h3 class="modal-title">Cancel Leave Request</h3>
            <button class="modal-close" onclick="closeCancelConfirmModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p style="color: var(--text-primary); margin: 0;">Are you sure you want to cancel this leave request?</p>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeCancelConfirmModal()">No, Keep Request</button>
            <button type="button" class="btn btn-danger" id="cancelConfirmBtn">Yes, Cancel Request</button>
        </div>
    </div>
</div>

<!-- Approve/Reject Modal -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="approveModalTitle">Approve Leave Request</h3>
            <button class="modal-close" onclick="closeApproveModal()">&times;</button>
        </div>
        <form id="approveForm" onsubmit="submitApproval(event)">
            <input type="hidden" id="approveRequestId">
            <input type="hidden" id="approveStatus">
            <div class="form-group" id="rejectionReasonGroup" style="display: none;">
                <label class="form-label">Rejection Reason</label>
                <textarea class="form-textarea" id="rejectionReason" name="rejection_reason" rows="4"></textarea>
                <span class="form-error" id="rejectionReasonError" style="display: none; color: #ef4444; font-size: 0.75rem; margin-top: 0.25rem;"></span>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeApproveModal()">Cancel</button>
                <button type="submit" class="btn" id="approveSubmitBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- Employees on Leave Modal -->
<div id="employeesOnLeaveModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3 class="modal-title" id="employeesOnLeaveTitle">Employees on Leave</h3>
            <button class="modal-close" onclick="closeEmployeesOnLeaveModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="employeesOnLeaveList">
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <p>Loading...</p>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeEmployeesOnLeaveModal()">Close</button>
        </div>
    </div>
</div>

<!-- Add Leave Credits Modal -->
@if($permissions['manage_credits'] ?? false)
<div id="newCreditModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Leave Credits</h3>
            <button class="modal-close" onclick="closeNewCreditModal()">&times;</button>
        </div>
        <form id="newCreditForm" onsubmit="submitLeaveCredit(event)">
            <div class="form-group">
                <label class="form-label">Employee</label>
                <select class="form-select" name="user_id" id="creditUserId" required>
                    <option value="">Select employee</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Leave Type</label>
                <select class="form-select" name="leave_type" required>
                    <option value="">Select type</option>
                    <option value="vacation">Vacation</option>
                    <option value="sick">Sick Leave</option>
                    <option value="personal">Personal</option>
                    <option value="emergency">Emergency</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Credits (Days)</label>
                <input type="number" class="form-input" name="credits" min="0" max="365" step="0.5" required>
            </div>
            <div class="form-group">
                <label class="form-label">Year</label>
                <input type="number" class="form-input" name="year" value="{{ date('Y') }}" min="2020" max="2100" required>
            </div>
            <div class="form-group">
                <label class="form-label">Notes (Optional)</label>
                <textarea class="form-textarea" name="notes" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeNewCreditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Credits</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    let currentYear = new Date().getFullYear();
    let myCreditsData = {}; // Store credits data for the current user
    
    // Permissions from server
    const permissions = {
        viewStats: {{ ($permissions['view_stats'] ?? false) ? 'true' : 'false' }},
        createRequest: {{ ($permissions['create_request'] ?? false) ? 'true' : 'false' }},
        viewCredits: {{ ($permissions['view_credits'] ?? false) ? 'true' : 'false' }},
        manageCredits: {{ ($permissions['manage_credits'] ?? false) ? 'true' : 'false' }},
        viewCalendar: {{ ($permissions['view_calendar'] ?? false) ? 'true' : 'false' }}
    };

    // Calendar functions
    let currentCalendarMonth = new Date().getMonth() + 1;
    let currentCalendarYear = new Date().getFullYear();

    async function loadCalendar() {
        const month = document.getElementById('calendarMonth').value;
        const year = document.getElementById('calendarYear').value;
        
        try {
            const response = await fetch(`/api/leave-management/calendar?year=${year}&month=${month}`);
            const data = await response.json();
            
            if (data.success) {
                renderCalendar(data.data, parseInt(year), parseInt(month));
            }
        } catch (error) {
            console.error('Error loading calendar:', error);
        }
    }

    function renderCalendar(calendarData, year, month) {
        const grid = document.getElementById('calendarGrid');
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - startDate.getDay()); // Start from Sunday
        
        grid.innerHTML = '';
        
        // Generate 42 days (6 weeks)
        for (let i = 0; i < 42; i++) {
            const currentDate = new Date(startDate);
            currentDate.setDate(startDate.getDate() + i);
            
            const dateStr = currentDate.toISOString().split('T')[0];
            const dayNumber = currentDate.getDate();
            const isCurrentMonth = currentDate.getMonth() === month - 1;
            const count = calendarData[dateStr] || 0;
            
            const dayElement = document.createElement('div');
            dayElement.className = `calendar-day ${!isCurrentMonth ? 'other-month' : ''} ${count > 0 ? 'has-leaves' : ''}`;
            dayElement.onclick = () => openEmployeesOnLeaveModal(dateStr);
            
            dayElement.innerHTML = `
                <div class="calendar-day-number">${dayNumber}</div>
                ${count > 0 ? `<div class="calendar-day-leave-badge">${count} on leave</div>` : ''}
            `;
            
            grid.appendChild(dayElement);
        }
    }

    function previousMonth() {
        let month = parseInt(document.getElementById('calendarMonth').value);
        let year = parseInt(document.getElementById('calendarYear').value);
        
        month--;
        if (month < 1) {
            month = 12;
            year--;
        }
        
        document.getElementById('calendarMonth').value = String(month).padStart(2, '0');
        document.getElementById('calendarYear').value = year;
        loadCalendar();
    }

    function nextMonth() {
        let month = parseInt(document.getElementById('calendarMonth').value);
        let year = parseInt(document.getElementById('calendarYear').value);
        
        month++;
        if (month > 12) {
            month = 1;
            year++;
        }
        
        document.getElementById('calendarMonth').value = String(month).padStart(2, '0');
        document.getElementById('calendarYear').value = year;
        loadCalendar();
    }

    async function openEmployeesOnLeaveModal(date) {
        const modal = document.getElementById('employeesOnLeaveModal');
        const title = document.getElementById('employeesOnLeaveTitle');
        const list = document.getElementById('employeesOnLeaveList');
        
        const dateObj = new Date(date);
        title.textContent = `Employees on Leave - ${dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}`;
        
        list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">👥</div><p>Loading...</p></div>';
        modal.classList.add('active');
        
        try {
            const response = await fetch(`/api/leave-management/employees-on-leave?date=${date}`);
            const data = await response.json();
            
            if (data.success) {
                if (data.data.length === 0) {
                    list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">👥</div><p>No employees on leave for this date</p></div>';
                } else {
                    list.innerHTML = `
                        <div style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.8125rem;">
                            ${data.count} employee(s) on leave
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Type</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Days</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.data.map(emp => `
                                        <tr>
                                            <td>${emp.name}</td>
                                            <td>${emp.leave_type_label}</td>
                                            <td>${new Date(emp.start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                            <td>${new Date(emp.end_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                                            <td>${emp.days_requested}</td>
                                            <td>${emp.reason || '-'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
            }
        } catch (error) {
            console.error('Error loading employees on leave:', error);
            list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Error loading employees on leave</p></div>';
        }
    }

    function closeEmployeesOnLeaveModal() {
        document.getElementById('employeesOnLeaveModal').classList.remove('active');
    }

    // Leave Request Details Modal
    const leaveManagementBaseUrl = '{{ url("/api/leave-management/leave-requests") }}';

    function openLeaveRequestDetailsModal(event, requestId) {
        const request = (window.leaveRequestsData || []).find(r => r.id === requestId);
        if (!request) return;

        const content = document.getElementById('leaveRequestDetailsContent');
        const actions = document.getElementById('leaveRequestDetailsActions');

        const attachmentViewUrl = `${leaveManagementBaseUrl}/${request.id}/attachment`;
        const ext = (request.attachment_filename || '').split('.').pop()?.toLowerCase() || '';
        const isImage = ['jpg', 'jpeg', 'png'].includes(ext);
        const isPdf = ext === 'pdf';
        const attachmentHtml = request.attachment_path
            ? `<div class="credits-item"><span class="credits-label">Attachment:</span><span class="credits-value"><div class="attachment-preview">${isImage ? `<img src="${attachmentViewUrl}" alt="Attachment" class="attachment-thumb" onclick="event.stopPropagation(); openAttachmentFullscreen('${attachmentViewUrl}')">` : isPdf ? `<a href="${attachmentViewUrl}" target="_blank" rel="noopener" class="attachment-preview-pdf">View PDF</a>` : `<a href="${attachmentViewUrl}" class="btn btn-secondary btn-sm" style="text-decoration: none;">Download</a>`}</div></span></div>`
            : '';

        content.innerHTML = `
            <div class="credits-item"><span class="credits-label">Employee:</span><span class="credits-value">${request.user_name}</span></div>
            <div class="credits-item"><span class="credits-label">Email:</span><span class="credits-value">${request.user_email}</span></div>
            <div class="credits-item"><span class="credits-label">Leave Type:</span><span class="credits-value">${request.leave_type_label}</span></div>
            <div class="credits-item"><span class="credits-label">Start Date:</span><span class="credits-value">${request.start_date_formatted}</span></div>
            <div class="credits-item"><span class="credits-label">End Date:</span><span class="credits-value">${request.end_date_formatted}</span></div>
            <div class="credits-item"><span class="credits-label">Days:</span><span class="credits-value">${request.days_requested}</span></div>
            <div class="credits-item"><span class="credits-label">Status:</span><span class="credits-value"><span class="status-badge status-${request.status}">${request.status_label}</span></span></div>
            <div class="credits-item"><span class="credits-label">Reason:</span><span class="credits-value">${request.reason || '-'}</span></div>
            ${attachmentHtml}
            ${request.rejection_reason ? `<div class="credits-item"><span class="credits-label">Rejection Reason:</span><span class="credits-value credits-used">${request.rejection_reason}</span></div>` : ''}
            ${request.approver_name ? `<div class="credits-item"><span class="credits-label">Approved by:</span><span class="credits-value">${request.approver_name}</span></div>` : ''}
            ${request.approved_at ? `<div class="credits-item"><span class="credits-label">Approved at:</span><span class="credits-value">${request.approved_at}</span></div>` : ''}
            <div class="credits-item"><span class="credits-label">Submitted:</span><span class="credits-value">${request.created_at}</span></div>
        `;

        let actionsHtml = '<button type="button" class="btn btn-secondary" onclick="closeLeaveRequestDetailsModal()">Close</button>';
        if (request.status === 'pending' && request.can_approve) {
            actionsHtml += ` <button type="button" class="btn btn-success" onclick="closeLeaveRequestDetailsModal(); approveRequest(${request.id}, 'approved')">Approve</button>`;
            actionsHtml += ` <button type="button" class="btn btn-danger" onclick="closeLeaveRequestDetailsModal(); approveRequest(${request.id}, 'rejected')">Reject</button>`;
        }
        if (request.can_cancel) {
            actionsHtml += ` <button type="button" class="btn btn-danger" onclick="closeLeaveRequestDetailsModal(); openCancelConfirmModal(${request.id})">Cancel Request</button>`;
        }
        actions.innerHTML = actionsHtml;

        document.getElementById('leaveRequestDetailsModal').classList.add('active');
    }

    function closeLeaveRequestDetailsModal() {
        document.getElementById('leaveRequestDetailsModal').classList.remove('active');
    }

    function openAttachmentFullscreen(src) {
        const overlay = document.getElementById('attachmentFullscreen');
        const img = document.getElementById('attachmentFullscreenImg');
        img.src = src;
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeAttachmentFullscreen(event) {
        if (event && event.target.id !== 'attachmentFullscreen' && !event.target.classList.contains('attachment-fullscreen-close')) {
            return;
        }
        document.getElementById('attachmentFullscreen').classList.remove('active');
        document.getElementById('attachmentFullscreenImg').src = '';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('attachmentFullscreen').classList.contains('active')) {
            closeAttachmentFullscreen({ target: document.getElementById('attachmentFullscreen') });
        }
    });

    // Load initial data
    document.addEventListener('DOMContentLoaded', function() {
        // Set current month and year in calendar selectors
        const now = new Date();
        document.getElementById('calendarMonth').value = String(now.getMonth() + 1).padStart(2, '0');
        document.getElementById('calendarYear').value = now.getFullYear();
        
        if (permissions.viewStats) {
            loadStats();
        }
        loadLeaveRequests();
        if (permissions.manageCredits || permissions.viewCredits) {
            loadAvailableUsers();
        }
    });

    // Tab switching
    function switchTab(tab) {
        document.querySelectorAll('.main-tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        if (tab === 'requests') {
            const requestsBtn = document.querySelector('.main-tab-btn');
            if (requestsBtn) {
                requestsBtn.classList.add('active');
            }
            document.getElementById('requestsTab').classList.add('active');
            loadLeaveRequests(document.querySelector('.status-tab-btn.active')?.dataset.status || 'pending');
        } else if (tab === 'calendar') {
            if (!permissions.viewCalendar) {
                return;
            }
            // Find the calendar button by its onclick attribute
            const calendarBtn = Array.from(document.querySelectorAll('.main-tab-btn')).find(btn => 
                btn.getAttribute('onclick') === "switchTab('calendar')"
            );
            if (calendarBtn) {
                calendarBtn.classList.add('active');
            }
            document.getElementById('calendarTab').classList.add('active');
            loadCalendar();
        } else if (tab === 'credits') {
            if (!permissions.viewCredits && !permissions.manageCredits) {
                return;
            }
            // Find the credits button by its onclick attribute
            const creditsBtn = Array.from(document.querySelectorAll('.main-tab-btn')).find(btn => 
                btn.getAttribute('onclick') === "switchTab('credits')"
            );
            if (creditsBtn) {
                creditsBtn.classList.add('active');
            }
            document.getElementById('creditsTab').classList.add('active');
            loadLeaveCredits();
        }
    }

    // Load stats
    async function loadStats() {
        if (!permissions.viewStats) {
            return;
        }
        
        try {
            const response = await fetch('/api/leave-management/stats?year=' + currentYear);
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('statPending').textContent = data.data.pending_requests;
                document.getElementById('statApproved').textContent = data.data.approved_requests;
                document.getElementById('statTotalCredits').textContent = data.data.total_credits;
                document.getElementById('statAvailableCredits').textContent = data.data.available_credits;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    // Switch status tab and load leave requests
    function switchStatusTab(status) {
        document.querySelectorAll('.status-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.status === status);
        });
        leaveRequestsCurrentPage = 1;
        loadLeaveRequests(status, 1);
    }

    // Load leave requests
    let leaveRequestsCurrentPage = 1;

    async function loadLeaveRequests(status, page) {
        const statusParam = status !== undefined ? status : (document.querySelector('.status-tab-btn.active')?.dataset.status || 'pending');
        const pageParam = page !== undefined ? page : leaveRequestsCurrentPage;
        leaveRequestsCurrentPage = pageParam;
        try {
            const url = `/api/leave-management/leave-requests?status=${statusParam}&page=${pageParam}&per_page=10`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                renderLeaveRequests(data.data);
                if (data.pagination) {
                    updateLeaveRequestsPagination(data.pagination);
                }
            }
        } catch (error) {
            console.error('Error loading leave requests:', error);
        }
    }

    function updateLeaveRequestsPagination(pagination) {
        const container = document.getElementById('leaveRequestsPagination');
        const infoEl = document.getElementById('leaveRequestsPaginationInfo');
        const numbersEl = document.getElementById('leaveRequestsPaginationNumbers');
        const prevBtn = document.getElementById('leaveRequestsPrevBtn');
        const nextBtn = document.getElementById('leaveRequestsNextBtn');

        if (!container || !pagination) return;

        if (pagination.total === 0) {
            container.style.display = 'none';
            return;
        }
        container.style.display = 'flex';

        const { current_page, last_page, total, from, to } = pagination;
        infoEl.textContent = `Showing ${from} to ${to} of ${total} results`;

        prevBtn.disabled = current_page <= 1;
        nextBtn.disabled = current_page >= last_page || last_page === 0;

        prevBtn.onclick = () => { if (current_page > 1) loadLeaveRequests(undefined, current_page - 1); };
        nextBtn.onclick = () => { if (current_page < last_page) loadLeaveRequests(undefined, current_page + 1); };

        let html = '';
        const maxVisible = 5;
        let startPage = Math.max(1, current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(last_page, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

        if (startPage > 1) {
            html += `<button class="pagination-number" data-page="1">1</button>`;
            if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
        }
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="pagination-number ${i === current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
        if (endPage < last_page) {
            if (endPage < last_page - 1) html += `<span class="pagination-number ellipsis">...</span>`;
            html += `<button class="pagination-number" data-page="${last_page}">${last_page}</button>`;
        }
        numbersEl.innerHTML = html;

        numbersEl.querySelectorAll('.pagination-number[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.page, 10);
                if (page && page !== current_page) loadLeaveRequests(undefined, page);
            });
        });
    }

    // Render leave requests
    function renderLeaveRequests(requests) {
        const tbody = document.getElementById('requestsTableBody');
        
        if (requests.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <p>No leave requests found</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        window.leaveRequestsData = requests;
        tbody.innerHTML = requests.map(request => `
            <tr class="leave-request-row clickable" data-request-id="${request.id}" onclick="openLeaveRequestDetailsModal(event, ${request.id})">
                <td>${request.user_name}</td>
                <td>${request.leave_type_label}</td>
                <td>${request.start_date_formatted}</td>
                <td>${request.end_date_formatted}</td>
                <td>${request.days_requested}</td>
                <td><span class="status-badge status-${request.status}">${request.status_label}</span></td>
            </tr>
        `).join('');
    }

    // Load leave credits
    async function loadLeaveCredits() {
        if (!permissions.viewCredits && !permissions.manageCredits) {
            return;
        }
        
        try {
            const year = document.getElementById('yearFilter').value;
            const url = `/api/leave-management/leave-credits?year=${year}`;
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                renderLeaveCredits(data.data);
                // Clear search when loading new data
                document.getElementById('creditsSearchInput').value = '';
            }
        } catch (error) {
            console.error('Error loading leave credits:', error);
        }
    }

    // Store original credits data for filtering
    let allCreditsData = [];

    // Render leave credits grouped by user
    function renderLeaveCredits(credits) {
        const container = document.getElementById('creditsTableBody');
        allCreditsData = credits;
        
        if (credits.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">💳</div><p>No leave credits found</p></div>';
            return;
        }

        // Group credits by user
        const groupedByUser = {};
        credits.forEach(credit => {
            if (!groupedByUser[credit.user_id]) {
                groupedByUser[credit.user_id] = {
                    user_id: credit.user_id,
                    user_name: credit.user_name,
                    user_email: credit.user_email,
                    credits: []
                };
            }
            groupedByUser[credit.user_id].credits.push(credit);
        });

        // Render grouped credits
        let html = '';
        Object.values(groupedByUser).forEach(userGroup => {
            html += `
                <div class="user-credits-group" data-user-id="${userGroup.user_id}" data-user-name="${userGroup.user_name.toLowerCase()}">
                    <div class="user-credits-header">
                        <div class="user-credits-info">
                            <h3 class="user-credits-name">${userGroup.user_name}</h3>
                            <span class="user-credits-email">${userGroup.user_email}</span>
                        </div>
                        <div class="user-credits-count">${userGroup.credits.length} leave type(s)</div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Total Credits</th>
                                    <th>Available</th>
                                    <th>Year</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${userGroup.credits.map(credit => `
                                    <tr>
                                        <td>${credit.leave_type_label}</td>
                                        <td>${credit.credits}</td>
                                        <td>${credit.available_credits}</td>
                                        <td>${credit.year}</td>
                                        <td>${credit.notes || '-'}</td>
                                        <td>
                                            ${permissions.manageCredits ? `
                                                <div class="table-actions">
                                                    <button class="icon-btn" title="Edit" onclick="editCredit(${credit.id})">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            ` : '-'}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // Filter credits by search term
    function filterCredits() {
        const searchTerm = document.getElementById('creditsSearchInput').value.toLowerCase().trim();
        const userGroups = document.querySelectorAll('.user-credits-group');
        
        if (!searchTerm) {
            userGroups.forEach(group => {
                group.style.display = 'block';
            });
            return;
        }

        userGroups.forEach(group => {
            const userName = group.getAttribute('data-user-name');
            if (userName.includes(searchTerm)) {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        });
    }

    // Load available users
    async function loadAvailableUsers() {
        try {
            const response = await fetch('/api/leave-management/users');
            const data = await response.json();
            
            if (data.success) {
                const select = document.getElementById('creditUserId');
                select.innerHTML = '<option value="">Select employee</option>' + 
                    data.data.map(user => `<option value="${user.id}">${user.name}</option>`).join('');
            }
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    // Modal functions
    async function openNewRequestModal() {
        if (!permissions.createRequest) {
            alert('You do not have permission to create leave requests.');
            return;
        }
        document.getElementById('newRequestForm').reset();
        document.getElementById('creditsInfoGroup').style.display = 'none';
        toggleAttachmentField();

        // Set minimum date to today for both date inputs
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('startDateInput').min = today;
        document.getElementById('endDateInput').min = today;
        
        // Clear any error messages
        document.getElementById('startDateError').style.display = 'none';
        document.getElementById('endDateError').style.display = 'none';
        
        document.getElementById('newRequestModal').classList.add('active');
        
        // Load credits data
        await loadMyCredits();
    }

    function closeNewRequestModal() {
        document.getElementById('newRequestModal').classList.remove('active');
        document.getElementById('creditsInfoGroup').style.display = 'none';
    }

    // Load my leave credits
    async function loadMyCredits() {
        try {
            const response = await fetch('/api/leave-management/my-credits');
            const data = await response.json();
            
            if (data.success) {
                myCreditsData = data.data;
                updateCreditsDisplay();
            }
        } catch (error) {
            console.error('Error loading my credits:', error);
        }
    }

    // Show/hide attachment field based on leave type (required for sick leave)
    function toggleAttachmentField() {
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const attachmentGroup = document.getElementById('attachmentGroup');
        const attachmentInput = document.getElementById('attachmentInput');
        const attachmentError = document.getElementById('attachmentError');

        if (leaveType === 'sick') {
            attachmentGroup.style.display = 'block';
            attachmentInput.setAttribute('required', 'required');
        } else {
            attachmentGroup.style.display = 'none';
            attachmentInput.removeAttribute('required');
            attachmentInput.value = '';
            attachmentError.style.display = 'none';
            attachmentError.textContent = '';
        }
    }

    // Update credits display based on selected leave type
    function updateCreditsDisplay() {
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const creditsInfoGroup = document.getElementById('creditsInfoGroup');
        
        if (!leaveType || !myCreditsData[leaveType]) {
            creditsInfoGroup.style.display = 'none';
            return;
        }
        
        const credits = myCreditsData[leaveType];
        document.getElementById('totalCredits').textContent = credits.total.toFixed(1);
        document.getElementById('usedCredits').textContent = credits.used.toFixed(1);
        document.getElementById('availableCredits').textContent = credits.available.toFixed(1);
        
        creditsInfoGroup.style.display = 'block';
    }

    // Validate start and end dates
    function validateDates() {
        const startDateInput = document.getElementById('startDateInput');
        const endDateInput = document.getElementById('endDateInput');
        const startDateError = document.getElementById('startDateError');
        const endDateError = document.getElementById('endDateError');
        
        // Clear previous errors
        startDateError.style.display = 'none';
        startDateError.textContent = '';
        endDateError.style.display = 'none';
        endDateError.textContent = '';
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (!startDateInput.value) {
            return;
        }
        
        const startDate = new Date(startDateInput.value);
        startDate.setHours(0, 0, 0, 0);
        
        // Validate start date is not in the past
        if (startDate < today) {
            startDateError.textContent = 'Start date cannot be in the past.';
            startDateError.style.display = 'block';
            startDateInput.setCustomValidity('Start date cannot be in the past.');
            return;
        } else {
            startDateInput.setCustomValidity('');
        }
        
        // Set minimum end date to start date
        endDateInput.min = startDateInput.value;
        
        if (!endDateInput.value) {
            return;
        }
        
        const endDate = new Date(endDateInput.value);
        endDate.setHours(0, 0, 0, 0);
        
        // Validate end date is not before start date
        if (endDate < startDate) {
            endDateError.textContent = 'End date must be after or equal to start date.';
            endDateError.style.display = 'block';
            endDateInput.setCustomValidity('End date must be after or equal to start date.');
            return;
        } else {
            endDateInput.setCustomValidity('');
        }
    }

    function openNewCreditModal() {
        if (!permissions.manageCredits) {
            alert('You do not have permission to manage leave credits.');
            return;
        }
        document.getElementById('newCreditModal').classList.add('active');
        document.getElementById('newCreditForm').reset();
    }

    function closeNewCreditModal() {
        document.getElementById('newCreditModal').classList.remove('active');
    }

    function openApproveModal(requestId, status) {
        document.getElementById('approveRequestId').value = requestId;
        document.getElementById('approveStatus').value = status;
        const rejectionReasonTextarea = document.getElementById('rejectionReason');
        const rejectionReasonError = document.getElementById('rejectionReasonError');
        
        // Clear previous errors and values
        rejectionReasonTextarea.value = '';
        rejectionReasonError.style.display = 'none';
        rejectionReasonError.textContent = '';
        rejectionReasonTextarea.removeAttribute('required');
        
        if (status === 'approved') {
            document.getElementById('approveModalTitle').textContent = 'Approve Leave Request';
            document.getElementById('approveSubmitBtn').textContent = 'Approve';
            document.getElementById('approveSubmitBtn').className = 'btn btn-success';
            document.getElementById('rejectionReasonGroup').style.display = 'none';
        } else {
            document.getElementById('approveModalTitle').textContent = 'Reject Leave Request';
            document.getElementById('approveSubmitBtn').textContent = 'Reject';
            document.getElementById('approveSubmitBtn').className = 'btn btn-danger';
            document.getElementById('rejectionReasonGroup').style.display = 'block';
            rejectionReasonTextarea.setAttribute('required', 'required');
        }
        
        document.getElementById('approveModal').classList.add('active');
    }

    function closeApproveModal() {
        document.getElementById('approveModal').classList.remove('active');
        document.getElementById('rejectionReason').value = '';
        document.getElementById('rejectionReasonError').style.display = 'none';
        document.getElementById('rejectionReasonError').textContent = '';
    }

    function openCancelConfirmModal(requestId) {
        document.getElementById('cancelRequestId').value = requestId;
        document.getElementById('cancelConfirmBtn').onclick = () => {
            closeCancelConfirmModal();
            cancelRequest(requestId);
        };
        document.getElementById('cancelConfirmModal').classList.add('active');
    }

    function closeCancelConfirmModal() {
        document.getElementById('cancelConfirmModal').classList.remove('active');
    }

    // Submit leave request
    async function submitLeaveRequest(event) {
        event.preventDefault();
        
        if (!permissions.createRequest) {
            alert('You do not have permission to create leave requests.');
            return;
        }
        
        // Validate dates before submitting
        validateDates();
        
        // Check if there are any validation errors
        const startDateInput = document.getElementById('startDateInput');
        const endDateInput = document.getElementById('endDateInput');
        const startDateError = document.getElementById('startDateError');
        const endDateError = document.getElementById('endDateError');
        
        if (startDateError.style.display === 'block' || endDateError.style.display === 'block' || 
            !startDateInput.validity.valid || !endDateInput.validity.valid) {
            // Focus on the first invalid field
            if (!startDateInput.validity.valid || startDateError.style.display === 'block') {
                startDateInput.focus();
            } else if (!endDateInput.validity.valid || endDateError.style.display === 'block') {
                endDateInput.focus();
            }
            return;
        }
        
        // Validate sick leave attachment before submit
        const leaveType = document.getElementById('leaveTypeSelect').value;
        const attachmentInput = document.getElementById('attachmentInput');
        const attachmentError = document.getElementById('attachmentError');

        if (leaveType === 'sick' && !attachmentInput.files.length) {
            attachmentError.textContent = 'A file attachment is required for sick leave.';
            attachmentError.style.display = 'block';
            return;
        }
        attachmentError.style.display = 'none';
        attachmentError.textContent = '';

        const formData = new FormData(event.target);
        if (leaveType !== 'sick') {
            formData.delete('attachment');
        }

        try {
            const response = await fetch('/api/leave-management/leave-requests', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeNewRequestModal();
                loadLeaveRequests();
                if (permissions.viewStats) {
                    loadStats();
                }
                alert('Leave request submitted successfully!');
            } else {
                // Display validation errors if any
                if (result.errors) {
                    let errorMessage = 'Validation errors:\n';
                    Object.keys(result.errors).forEach(key => {
                        errorMessage += `- ${result.errors[key].join(', ')}\n`;
                    });
                    alert(errorMessage);
                } else {
                    alert(result.message || 'Error submitting leave request');
                }
            }
        } catch (error) {
            console.error('Error submitting leave request:', error);
            alert('Error submitting leave request');
        }
    }

    // Approve/Reject request
    function approveRequest(requestId, status) {
        openApproveModal(requestId, status);
    }

    async function submitApproval(event) {
        event.preventDefault();
        
        const requestId = document.getElementById('approveRequestId').value;
        const status = document.getElementById('approveStatus').value;
        const rejectionReasonTextarea = document.getElementById('rejectionReason');
        const rejectionReasonError = document.getElementById('rejectionReasonError');
        const rejectionReason = rejectionReasonTextarea.value.trim();
        
        // Clear previous errors
        rejectionReasonError.style.display = 'none';
        rejectionReasonError.textContent = '';
        
        // Validate rejection reason if status is rejected
        if (status === 'rejected' && !rejectionReason) {
            rejectionReasonError.textContent = 'Rejection reason is required when rejecting a leave request.';
            rejectionReasonError.style.display = 'block';
            rejectionReasonTextarea.focus();
            return;
        }
        
        const data = {
            status: status,
            rejection_reason: status === 'rejected' ? rejectionReason : null
        };
        
        try {
            const response = await fetch(`/api/leave-management/leave-requests/${requestId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeApproveModal();
                loadLeaveRequests();
                loadStats();
                alert('Leave request updated successfully!');
            } else {
                // Display validation errors if any
                if (result.errors) {
                    let errorMessage = 'Validation errors:\n';
                    Object.keys(result.errors).forEach(key => {
                        errorMessage += `- ${result.errors[key].join(', ')}\n`;
                    });
                    alert(errorMessage);
                } else {
                    alert(result.message || 'Error updating leave request');
                }
            }
        } catch (error) {
            console.error('Error updating leave request:', error);
            alert('Error updating leave request');
        }
    }

    // Cancel request
    async function cancelRequest(requestId) {
        try {
            const response = await fetch(`/api/leave-management/leave-requests/${requestId}/cancel`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                loadLeaveRequests();
                loadStats();
                alert('Leave request cancelled successfully!');
            } else {
                alert(result.message || 'Error cancelling leave request');
            }
        } catch (error) {
            console.error('Error cancelling leave request:', error);
            alert('Error cancelling leave request');
        }
    }

    // Submit leave credit
    async function submitLeaveCredit(event) {
        event.preventDefault();
        
        if (!permissions.manageCredits) {
            alert('You do not have permission to manage leave credits.');
            return;
        }
        
        const formData = new FormData(event.target);
        const data = Object.fromEntries(formData);
        data.credits = parseFloat(data.credits);
        data.year = parseInt(data.year);
        
        try {
            const response = await fetch('/api/leave-management/leave-credits', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                closeNewCreditModal();
                loadLeaveCredits();
                loadStats();
                alert('Leave credits added successfully!');
            } else {
                alert(result.message || 'Error adding leave credits');
            }
        } catch (error) {
            console.error('Error adding leave credits:', error);
            alert('Error adding leave credits');
        }
    }

    // Edit credit (placeholder - can be enhanced)
    function editCredit(creditId) {
        alert('Edit functionality can be added here');
    }
</script>
@endpush
