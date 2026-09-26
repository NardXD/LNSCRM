@extends('layouts.app')

@section('title', 'Leave Management')

@push('styles')
    @vite(['resources/css/pages/leave-management.css'])
@endpush

@push('scripts')
<script>
    window.__leaveManagementConfig = {
        permissions: {
            viewStats: @json((bool) ($permissions['view_stats'] ?? false)),
            createRequest: @json((bool) ($permissions['create_request'] ?? false)),
            viewCredits: @json((bool) ($permissions['view_credits'] ?? false)),
            manageCredits: @json((bool) ($permissions['manage_credits'] ?? false)),
            viewCalendar: @json((bool) ($permissions['view_calendar'] ?? false)),
        },
        leaveRequestsUrl: @json(url('/api/leave-management/leave-requests')),
    };
</script>
    @vite(['resources/js/pages/leave-management.js'])
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
                <tbody id="requestsTableBody" aria-busy="true">
                    @include('partials.skeleton-table-rows', ['rows' => 6, 'cols' => 6])
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
