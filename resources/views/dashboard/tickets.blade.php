@extends('layouts.app')

@section('title', 'Tickets & Helpdesk')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Tickets & Helpdesk</h1>
        <p class="page-subtitle">Manage support tickets, track SLAs, and prioritize issues</p>
    </div>

    <div class="tickets-container">
        <!-- Header Actions -->
        <div class="tickets-header">
            <div class="header-left">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" class="search-input" placeholder="Search tickets..." id="ticketSearch">
                </div>
                <select class="filter-select" id="priorityFilter">
                    <option value="all">All Priority</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="header-right">
                <button class="btn-primary" onclick="createTicket()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Ticket
                </button>
            </div>
        </div>

        <!-- Ticket Stats -->
        <div class="ticket-stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Open Tickets</span>
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value" id="statOpen">0</div>
                <div class="stat-change" id="statOpenChange">—</div>
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
                <div class="stat-value" id="statPending">0</div>
                <div class="stat-change">Waiting for response</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Resolved</span>
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value" id="statResolved">0</div>
                <div class="stat-change positive" id="statResolvedChange">—</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Closed</span>
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value" id="statClosed">0</div>
                <div class="stat-change positive" id="statClosedChange">—</div>
            </div>
        </div>

        <!-- Tickets Table -->
        <div class="tickets-section">
            <div class="view-submenu" id="viewSubmenu">
                <button type="button" class="view-submenu-tab active" data-view="all">All Tickets</button>
                <button type="button" class="view-submenu-tab" data-view="assigned-to-me">Assigned to me</button>
            </div>
            <div class="status-tabs" id="statusTabs">
                <button type="button" class="status-tab active" data-status="open">Open</button>
                <button type="button" class="status-tab" data-status="in-progress">In Progress</button>
                <button type="button" class="status-tab" data-status="pending">Pending</button>
                <button type="button" class="status-tab" data-status="resolved">Resolved</button>
                <button type="button" class="status-tab" data-status="closed">Closed</button>
            </div>
            <div class="table-container">
                <table class="data-table" id="ticketsTable">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Subject</th>
                            <th>Client</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>SLA</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ticketsTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="tickets-cards" id="ticketsCards">
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

    <!-- Ticket Detail Modal -->
    <div class="ticket-modal" id="ticketModal">
        <div class="ticket-modal-content">
            <button class="modal-close" onclick="closeTicketModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <div class="ticket-header-info">
                    <div class="ticket-id-badge" id="modalTicketId">#TKT-2025-001</div>
                    <h2 class="ticket-subject" id="modalTicketSubject">Unable to login to dashboard</h2>
                    <div class="ticket-meta">
                        <span class="ticket-client" id="modalTicketClient">Client: Acme Corporation</span>
                        <span class="ticket-date" id="modalTicketDate">Created: Dec 31, 2025 at 10:30 AM</span>
                    </div>
                </div>
                <div class="ticket-header-actions">
                    <select class="status-select" id="modalStatus">
                        <option value="open">Open</option>
                        <option value="in-progress">In Progress</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>

            <div class="modal-body">
                <div class="ticket-details-grid">
                    <div class="ticket-main">
                        <div class="ticket-description">
                            <h3 class="section-title">Description</h3>
                            <p id="modalDescription">I'm unable to login to the dashboard. I've tried resetting my password but still can't access my account. This is urgent as I need to access important data.</p>
                        </div>

                        <div class="ticket-attachment" id="ticketAttachmentSection" style="display: none;">
                            <h3 class="section-title">Attached Image</h3>
                            <div class="ticket-attachment-clickable" id="ticketAttachmentClickable" onclick="openImagePopup(this)" role="button" tabindex="0">
                                <img id="ticketAttachmentImg" src="" alt="Ticket attachment" class="ticket-attachment-img">
                            </div>
                        </div>

                        <div class="ticket-comments">
                            <h3 class="section-title">Comments</h3>
                            <div class="comments-list" id="commentsList">
                                <!-- Comments will be populated by JavaScript -->
                            </div>
                            <div class="comment-input" id="commentInputSection">
                                <textarea class="comment-textarea" id="commentTextarea" placeholder="Add a comment..."></textarea>
                                <button class="btn-primary" id="addCommentBtn" onclick="addComment()">Add Comment</button>
                            </div>
                        </div>
                    </div>

                    <div class="ticket-sidebar">
                        <div class="sidebar-section">
                            <h3 class="sidebar-title">Ticket Details</h3>
                            <div class="detail-item">
                                <span class="detail-label">Assigned To</span>
                                <div class="detail-value" id="sidebarAssignedTo">
                                    <div class="employee-cell">
                                        <div class="employee-avatar">JD</div>
                                        <span>John Doe</span>
                                    </div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Priority</span>
                                <span class="detail-value"><span class="priority-badge high" id="sidebarPriority">High</span></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Status</span>
                                <span class="detail-value"><span class="status-badge open" id="sidebarStatus">Open</span></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Category</span>
                                <span class="detail-value" id="sidebarCategory">—</span>
                            </div>
                        </div>

                        <div class="sidebar-section">
                            <h3 class="sidebar-title">SLA Tracking</h3>
                            <div class="sla-item" id="slaResponseItem">
                                <div class="sla-header">
                                    <span class="sla-label">Response Time</span>
                                    <span class="sla-status compliant" id="slaResponseStatus">Compliant</span>
                                </div>
                                <div class="sla-progress">
                                    <div class="sla-bar">
                                        <div class="sla-fill compliant" id="slaResponseFill" style="width: 100%"></div>
                                    </div>
                                    <div class="sla-time" id="slaResponseText">Responded in 2h 15m (Target: 4h)</div>
                                </div>
                            </div>
                            <div class="sla-item" id="slaResolutionItem">
                                <div class="sla-header">
                                    <span class="sla-label">Resolution Time</span>
                                    <span class="sla-status warning" id="slaResolutionStatus">At Risk</span>
                                </div>
                                <div class="sla-progress">
                                    <div class="sla-bar">
                                        <div class="sla-fill warning" id="slaResolutionFill" style="width: 65%"></div>
                                    </div>
                                    <div class="sla-time" id="slaResolutionText">18h 30m remaining (Target: 24h)</div>
                                </div>
                            </div>
                        </div>

                        <div class="sidebar-section">
                            <h3 class="sidebar-title">Activity</h3>
                            <div class="activity-list" id="activityList">
                                <!-- Activity will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Popup Overlay -->
    <div class="image-popup-overlay" id="imagePopupOverlay" onclick="closeImagePopup()">
        <button type="button" class="image-popup-close" onclick="closeImagePopup()" aria-label="Close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
        <img id="imagePopupImg" src="" alt="Full size" class="image-popup-img" onclick="event.stopPropagation()">
    </div>

    <!-- New Ticket Modal -->
    <div class="ticket-modal" id="newTicketModal">
        <div class="ticket-modal-content new-ticket-modal-content">
            <button type="button" class="modal-close" onclick="closeNewTicketModal()" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 class="modal-title">New Ticket</h2>
            </div>

            <form id="newTicketForm" class="new-ticket-form" enctype="multipart/form-data" onsubmit="submitNewTicket(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newTicketSubject" class="form-label">Subject <span class="required">*</span></label>
                        <input type="text" id="newTicketSubject" name="subject" class="form-input" required placeholder="Brief description of the issue">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="newTicketClient" class="form-label">Client <span class="required">*</span></label>
                            <select id="newTicketClient" name="client_id" class="form-input" required>
                                <option value="">Select client</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="newTicketAssignedTo" class="form-label">Assigned To</label>
                            <select id="newTicketAssignedTo" name="assigned_to" class="form-input">
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="newTicketPriority" class="form-label">Priority <span class="required">*</span></label>
                            <select id="newTicketPriority" name="priority" class="form-input" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="newTicketCategory" class="form-label">Category</label>
                            <select id="newTicketCategory" name="category" class="form-input">
                                <option value="">Select category</option>
                                <option value="technical">Technical Issue</option>
                                <option value="billing">Billing</option>
                                <option value="feature">Feature Request</option>
                                <option value="general">General</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="newTicketDescription" class="form-label">Description <span class="required">*</span></label>
                        <textarea id="newTicketDescription" name="description" class="form-input" rows="4" required placeholder="Provide detailed information about the issue"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Attach Image</label>
                        <div class="image-upload-area" id="imageUploadArea" onclick="document.getElementById('newTicketImage').click()" ondragover="handleImageDragOver(event)" ondragleave="handleImageDragLeave(event)" ondrop="handleImageDrop(event)">
                            <input type="file" id="newTicketImage" name="image" class="image-input" accept="image/*" onchange="previewTicketImage(this)">
                            <div class="image-upload-placeholder" id="imageUploadPlaceholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <span>Click to add image or drag and drop</span>
                                <span class="image-upload-hint">PNG, JPG, GIF up to 5MB</span>
                            </div>
                            <div class="image-preview" id="imagePreview" style="display: none;">
                                <img id="imagePreviewImg" src="" alt="Preview">
                                <button type="button" class="image-remove" onclick="event.stopPropagation(); removeTicketImage()" aria-label="Remove image">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                                        <line x1="18" y1="6" x2="6" y2="18"/>
                                        <line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-form-actions">
                    <button type="button" class="btn-secondary" onclick="closeNewTicketModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Create Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .tickets-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Header */
    .tickets-header {
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
    .btn-primary {
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
        background: var(--accent);
        color: white;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-primary svg {
        width: 18px;
        height: 18px;
    }

    /* Stats Grid */
    .ticket-stats-grid {
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
    .tickets-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .view-submenu {
        display: flex;
        gap: 0.25rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .view-submenu-tab {
        padding: 0.5rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        background: var(--bg-primary);
        color: var(--text-secondary);
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .view-submenu-tab:hover {
        background: var(--bg-secondary);
        color: var(--text-primary);
    }

    .view-submenu-tab.active {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }

    .status-tabs {
        display: flex;
        gap: 0.25rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
    }

    .status-tab {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        background: transparent;
        color: var(--text-secondary);
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .status-tab:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .status-tab.active {
        background: var(--accent-light);
        color: var(--accent);
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

    /* Priority Badge */
    .priority-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .priority-badge.low {
        background: #d1fae5;
        color: #059669;
    }

    .priority-badge.medium {
        background: #fef3c7;
        color: #d97706;
    }

    .priority-badge.high {
        background: #fed7aa;
        color: #ea580c;
    }

    .priority-badge.urgent {
        background: #fee2e2;
        color: #dc2626;
    }

    /* Status Badge */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.open {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.in-progress {
        background: #fef3c7;
        color: #d97706;
    }

    .status-badge.pending {
        background: #e5e7eb;
        color: #374151;
    }

    .status-badge.resolved {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.closed {
        background: #e5e7eb;
        color: #6b7280;
    }

    /* SLA Badge */
    .sla-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .sla-badge.compliant {
        background: #d1fae5;
        color: #059669;
    }

    .sla-badge.warning {
        background: #fef3c7;
        color: #d97706;
    }

    .sla-badge.breached {
        background: #fee2e2;
        color: #dc2626;
    }

    /* Employee Cell */
    .employee-cell {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .employee-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.75rem;
        flex-shrink: 0;
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

    /* Mobile Card View */
    .tickets-cards {
        display: none;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .ticket-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .ticket-card:hover {
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

    /* Ticket Modal */
    .ticket-modal {
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

    .ticket-modal.active {
        display: flex;
        opacity: 1;
    }

    .ticket-modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 1200px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .ticket-modal.active .ticket-modal-content {
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
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .ticket-header-info {
        flex: 1;
    }

    .ticket-id-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .ticket-subject {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }

    .ticket-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .ticket-header-actions {
        display: flex;
        gap: 0.75rem;
    }

    .status-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
    }

    .ticket-details-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
    }

    .ticket-main {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .ticket-description {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1.25rem;
    }

    .ticket-description p {
        color: var(--text-primary);
        line-height: 1.6;
        margin: 0;
    }

    .ticket-attachment {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1.25rem;
        margin-top: 1rem;
    }

    .ticket-attachment-img {
        max-width: 100%;
        max-height: 300px;
        object-fit: contain;
        border-radius: 8px;
        border: 1px solid var(--border);
    }

    .ticket-attachment-clickable {
        display: inline-block;
        cursor: pointer;
    }

    .image-popup-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(0, 0, 0, 0.85);
        align-items: center;
        justify-content: center;
        padding: 2rem;
        animation: fadeIn 0.2s ease;
    }

    .image-popup-overlay.visible {
        display: flex;
    }

    .image-popup-close {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        background: rgba(255, 255, 255, 0.15);
        border: none;
        border-radius: 8px;
        color: white;
        padding: 0.5rem;
        cursor: pointer;
        transition: background 0.15s;
    }

    .image-popup-close:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .image-popup-img {
        max-width: 90vw;
        max-height: 90vh;
        object-fit: contain;
        border-radius: 8px;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .comments-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .comment-item {
        display: flex;
        gap: 0.75rem;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }

    .comment-avatar {
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

    .comment-content {
        flex: 1;
    }

    .comment-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .comment-author {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .comment-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .comment-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        line-height: 1.5;
    }

    .comment-input {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .comment-textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .comment-textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .comment-input.ticket-readonly {
        opacity: 0.7;
        pointer-events: none;
    }

    .status-select:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .ticket-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .sidebar-section {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1.25rem;
    }

    .sidebar-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .detail-item:last-child {
        margin-bottom: 0;
    }

    .detail-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .detail-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .sla-item {
        margin-bottom: 1.5rem;
    }

    .sla-item:last-child {
        margin-bottom: 0;
    }

    .sla-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .sla-label {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .sla-status {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .sla-status.compliant {
        background: #d1fae5;
        color: #059669;
    }

    .sla-status.warning {
        background: #fef3c7;
        color: #d97706;
    }

    .sla-status.breached {
        background: #fee2e2;
        color: #dc2626;
    }

    .sla-progress {
        margin-top: 0.5rem;
    }

    .sla-bar {
        width: 100%;
        height: 8px;
        background: var(--bg-card);
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }

    .sla-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.3s;
    }

    .sla-fill.compliant {
        background: #10b981;
    }

    .sla-fill.warning {
        background: #f59e0b;
    }

    .sla-fill.breached {
        background: #ef4444;
    }

    .sla-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .activity-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        gap: 0.75rem;
    }

    .activity-item {
        display: flex;
        gap: 0.75rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .activity-icon {
        width: 16px;
        height: 16px;
        color: var(--text-muted);
        flex-shrink: 0;
    }

    .activity-text {
        flex: 1;
    }

    .activity-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* New Ticket Form Modal */
    .new-ticket-modal-content {
        max-width: 560px;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .form-group {
        margin-bottom: 1rem;
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

    .form-label .required {
        color: #dc2626;
    }

    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-family: inherit;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-input::placeholder {
        color: var(--text-muted);
    }

    textarea.form-input {
        resize: vertical;
        min-height: 100px;
    }

    select.form-input {
        cursor: pointer;
        appearance: auto;
    }

    .modal-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border);
        background: var(--bg-primary);
    }

    .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        background: var(--bg-card);
        color: var(--text-primary);
        -webkit-tap-highlight-color: transparent;
    }

    .btn-secondary:hover {
        background: var(--bg-primary);
        border-color: var(--text-muted);
    }

    .modal-form-actions .btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Image Upload */
    .image-input {
        position: absolute;
        width: 0;
        height: 0;
        opacity: 0;
        overflow: hidden;
    }

    .image-upload-area {
        position: relative;
        border: 2px dashed var(--border);
        border-radius: 8px;
        padding: 1.5rem;
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
        min-height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-primary);
    }

    .image-upload-area:hover,
    .image-upload-area.image-upload-dragover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .image-upload-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
    }

    .image-upload-placeholder svg {
        color: var(--text-muted);
    }

    .image-upload-placeholder span {
        font-size: 0.875rem;
    }

    .image-upload-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .image-preview {
        position: relative;
        width: 100%;
        max-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .image-preview img {
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
        border-radius: 8px;
    }

    .image-remove {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        width: 28px;
        height: 28px;
        border: none;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.6);
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }

    .image-remove:hover {
        background: rgba(220, 38, 38, 0.9);
    }

    /* Responsive */
    @media (min-width: 769px) {
        .table-container {
            display: block;
        }
        .tickets-cards {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .table-container {
            display: none !important;
        }
        .tickets-cards {
            display: flex !important;
        }

        .tickets-header {
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

        .ticket-stats-grid {
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

        .ticket-modal-content {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 0;
        }

        .ticket-details-grid {
            grid-template-columns: 1fr;
        }

        .modal-header {
            flex-direction: column;
        }

        .ticket-header-actions {
            width: 100%;
        }

        .status-select {
            flex: 1;
        }

        .card-details {
            grid-template-columns: 1fr;
        }

        .new-ticket-modal-content .form-row {
            grid-template-columns: 1fr;
        }

        .status-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 1rem;
        }

        .status-tab {
            flex-shrink: 0;
        }
    }

    @media (max-width: 480px) {
        .ticket-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const API_BASE = '{{ url("/api/tickets") }}';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let ticketsData = [];
    let currentPage = 1;
    let pagination = { last_page: 1, total: 0 };
    const itemsPerPage = 10;
    let activeStatusTab = 'open';
    let activeViewFilter = 'all';
    let searchQuery = '';
    let priorityFilter = 'all';
    let currentTicketId = null;
    let searchTimeout = null;

    function getFilteredTickets() {
        return ticketsData;
    }

    async function fetchTickets() {
        try {
            const params = new URLSearchParams({
                page: currentPage,
                per_page: itemsPerPage,
                status: activeStatusTab,
                priority: priorityFilter,
            });
            if (activeViewFilter === 'assigned-to-me') params.set('assigned_to_me', '1');
            if (searchQuery) params.set('search', searchQuery);
            const res = await fetch(`${API_BASE}?${params}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load tickets');
            ticketsData = json.data;
            pagination = json.pagination;
            if (json.stats) updateStats(json.stats);
            updateView();
        } catch (err) {
            console.error(err);
            ticketsData = [];
            updateView();
        }
    }

    function updateStats(stats) {
        const el = id => document.getElementById(id);
        if (el('statOpen')) el('statOpen').textContent = stats.open ?? 0;
        if (el('statOpenChange')) el('statOpenChange').textContent = `${stats.in_progress ?? 0} in progress`;
        if (el('statPending')) el('statPending').textContent = stats.pending ?? 0;
        if (el('statResolved')) el('statResolved').textContent = stats.resolved ?? 0;
        if (el('statClosed')) el('statClosed').textContent = stats.closed ?? 0;
    }

    // Render Functions
    function renderTable() {
        const filtered = getFilteredTickets();
        const totalPages = Math.max(1, Math.ceil(filtered.length / itemsPerPage));
        const tbody = document.getElementById('ticketsTableBody');
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = filtered.slice(start, end);

        tbody.innerHTML = pageData.map(ticket => `
            <tr onclick="openTicketModal(${ticket.id})">
                <td><strong>${ticket.ticketId}</strong></td>
                <td>${ticket.subject}</td>
                <td>${ticket.client}</td>
                <td>
                    <div class="employee-cell">
                        <div class="employee-avatar">${ticket.assignedTo.initials}</div>
                        <span>${ticket.assignedTo.name}</span>
                    </div>
                </td>
                <td><span class="priority-badge ${ticket.priority}">${ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}</span></td>
                <td><span class="status-badge ${ticket.status}">${ticket.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span></td>
                <td><span class="sla-badge ${ticket.sla}">${ticket.sla.charAt(0).toUpperCase() + ticket.sla.slice(1)}</span></td>
                <td>${ticket.created}</td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="openTicketModal(${ticket.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderCards() {
        const filtered = getFilteredTickets();
        const container = document.getElementById('ticketsCards');
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const pageData = filtered.slice(start, end);

        container.innerHTML = pageData.map(ticket => `
            <div class="ticket-card" onclick="openTicketModal(${ticket.id})">
                <div class="card-header">
                    <div>
                        <div class="card-title">${ticket.ticketId}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${ticket.subject}</div>
                    </div>
                    <span class="status-badge ${ticket.status}">${ticket.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail">
                        <span class="card-label">Client</span>
                        <span class="card-value">${ticket.client}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Assigned To</span>
                        <span class="card-value">${ticket.assignedTo.name}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Priority</span>
                        <span class="card-value"><span class="priority-badge ${ticket.priority}">${ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}</span></span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">SLA</span>
                        <span class="card-value"><span class="sla-badge ${ticket.sla}">${ticket.sla.charAt(0).toUpperCase() + ticket.sla.slice(1)}</span></span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderPagination() {
        const totalPages = pagination.last_page || 1;
        const total = pagination.total || 0;

        const info = document.getElementById('paginationInfo');
        const numbers = document.getElementById('paginationNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        const start = total ? (currentPage - 1) * itemsPerPage + 1 : 0;
        const end = Math.min(currentPage * itemsPerPage, total);
        info.textContent = total ? `Showing ${start} to ${end} of ${total} results` : 'No tickets to show';

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage >= totalPages;

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
                fetchTickets();
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

    // Event Listeners
    document.getElementById('nextBtn').addEventListener('click', () => {
        if (currentPage < pagination.last_page) {
            currentPage++;
            fetchTickets();
        }
    });

    document.getElementById('prevBtn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchTickets();
        }
    });

    document.getElementById('ticketSearch').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchQuery = document.getElementById('ticketSearch').value.trim();
            currentPage = 1;
            fetchTickets();
        }, 300);
    });

    document.getElementById('priorityFilter').addEventListener('change', function() {
        priorityFilter = this.value;
        currentPage = 1;
        fetchTickets();
    });

    document.querySelectorAll('.view-submenu-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.view-submenu-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeViewFilter = tab.dataset.view;
            currentPage = 1;
            fetchTickets();
        });
    });

    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeStatusTab = tab.dataset.status;
            currentPage = 1;
            fetchTickets();
        });
    });

    // Ticket Modal
    async function openTicketModal(ticketId) {
        currentTicketId = ticketId;
        const ticket = ticketsData.find(t => t.id === ticketId);
        if (ticket) {
            populateTicketModal(ticket);
        }
        document.getElementById('ticketModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            const res = await fetch(`${API_BASE}/${ticketId}`);
            const json = await res.json();
            if (json.success && json.data) {
                populateTicketModal(json.data);
            }
        } catch (err) {
            console.error(err);
        }
    }

    function populateTicketModal(ticket) {
        const fmt = s => (s || '').replace('-', ' ').split(' ').map(w => (w || '').charAt(0).toUpperCase() + (w || '').slice(1)).join(' ');
        const el = id => document.getElementById(id);
        const set = (id, fn) => { const e = el(id); if (e) fn(e); };

        set('modalTicketId', e => e.textContent = '#' + (ticket.ticketId || ticket.ticket_number || ''));
        set('modalTicketSubject', e => e.textContent = ticket.subject || '');
        set('modalTicketClient', e => e.textContent = 'Client: ' + (ticket.client || ''));
        set('modalTicketDate', e => e.textContent = 'Created: ' + (ticket.created_at || ticket.created || ''));
        set('modalDescription', e => e.textContent = ticket.description || '');
        set('modalStatus', e => e.value = ticket.status || 'open');
        set('sidebarPriority', e => { e.textContent = fmt(ticket.priority); e.className = `priority-badge ${ticket.priority || 'medium'}`; });
        set('sidebarStatus', e => { e.textContent = fmt(ticket.status); e.className = `status-badge ${ticket.status || 'open'}`; });
        set('sidebarCategory', e => e.textContent = ticket.category ? fmt(ticket.category) : '—');
        const attSection = el('ticketAttachmentSection');
        const attImg = el('ticketAttachmentImg');
        if (ticket.image_url && attSection && attImg) {
            attSection.style.display = 'block';
            attImg.src = ticket.image_url;
        } else if (attSection) {
            attSection.style.display = 'none';
        }
        const assigned = ticket.assignedTo || { name: 'Unassigned', initials: '—' };
        set('sidebarAssignedTo', e => e.innerHTML = `<div class="employee-cell"><div class="employee-avatar">${(assigned.initials || '—')}</div><span>${(assigned.name || 'Unassigned')}</span></div>`);
        const commentsEl = el('commentsList');
        if (commentsEl) {
            if (ticket.comments) {
                commentsEl.innerHTML = ticket.comments.map(c => `
                <div class="comment-item">
                    <div class="comment-avatar">${c.initials || c.author?.slice(0,2).toUpperCase() || '—'}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <span class="comment-author">${c.author || 'User'}</span>
                            <span class="comment-time">${c.time || ''}</span>
                        </div>
                        <div class="comment-text">${c.text || c.content || ''}</div>
                    </div>
                </div>
            `).join('');
            } else {
                commentsEl.innerHTML = '';
            }
        }

        const sla = ticket.sla_tracking || {};
        const resp = sla.response || { status: ticket.sla || 'compliant', text: '—' };
        const res = sla.resolution || { status: ticket.sla || 'compliant', text: '—' };
        const pct = status => (status === 'compliant' ? 100 : status === 'warning' ? 65 : 30);
        const statusLabel = s => (s === 'warning' ? 'At Risk' : (s || 'compliant').charAt(0).toUpperCase() + (s || '').slice(1));
        set('slaResponseStatus', e => { e.textContent = statusLabel(resp.status); e.className = `sla-status ${resp.status || 'compliant'}`; });
        set('slaResponseText', e => e.textContent = resp.text || '—');
        set('slaResponseFill', e => { e.style.width = pct(resp.status) + '%'; e.className = `sla-fill ${resp.status || 'compliant'}`; });
        set('slaResolutionStatus', e => { e.textContent = statusLabel(res.status); e.className = `sla-status ${res.status || 'compliant'}`; });
        set('slaResolutionText', e => e.textContent = res.text || '—');
        set('slaResolutionFill', e => { e.style.width = pct(res.status) + '%'; e.className = `sla-fill ${res.status || 'compliant'}`; });

        const activitiesEl = el('activityList');
        if (activitiesEl) {
            const activities = ticket.activities || [];
            activitiesEl.innerHTML = activities.length ? activities.map(a => `
                <div class="activity-item">
                    <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                    <div class="activity-text">${a.text || ''}</div>
                    <div class="activity-time">${a.time || ''}</div>
                </div>
            `).join('') : '<div class="activity-empty">No activity yet</div>';
        }

        const isReadOnly = ['resolved', 'closed'].includes(ticket.status || '');
        const statusSelect = el('modalStatus');
        const commentTextarea = el('commentTextarea');
        const addCommentBtn = el('addCommentBtn');
        const commentInputSection = el('commentInputSection');
        if (statusSelect) statusSelect.disabled = isReadOnly;
        if (commentTextarea) commentTextarea.disabled = isReadOnly;
        if (addCommentBtn) addCommentBtn.disabled = isReadOnly;
        if (commentInputSection) commentInputSection.classList.toggle('ticket-readonly', isReadOnly);
    }

    function closeTicketModal() {
        document.getElementById('ticketModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function openImagePopup(clickable) {
        const img = clickable?.querySelector('img') || document.getElementById('ticketAttachmentImg');
        const overlay = document.getElementById('imagePopupOverlay');
        const popupImg = document.getElementById('imagePopupImg');
        if (img?.src && overlay && popupImg) {
            popupImg.src = img.src;
            overlay.classList.add('visible');
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', handleImagePopupEscape);
        }
    }

    function closeImagePopup() {
        const overlay = document.getElementById('imagePopupOverlay');
        if (overlay) {
            overlay.classList.remove('visible');
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleImagePopupEscape);
        }
    }

    function handleImagePopupEscape(e) {
        if (e.key === 'Escape') closeImagePopup();
    }

    document.getElementById('ticketAttachmentClickable')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            openImagePopup(this);
        }
    });

    document.getElementById('ticketModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeTicketModal();
        }
    });

    document.getElementById('modalStatus').addEventListener('change', async function() {
        if (!currentTicketId || this.disabled) return;
        try {
            const res = await fetch(`${API_BASE}/${currentTicketId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
                body: JSON.stringify({ status: this.value })
            });
            const json = await res.json();
            if (json.success) {
                const t = ticketsData.find(x => x.id === currentTicketId);
                if (t) t.status = this.value;
                document.getElementById('sidebarStatus').textContent = this.value.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
                document.getElementById('sidebarStatus').className = `status-badge ${this.value}`;
            }
        } catch (err) { console.error(err); }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTicketModal();
            closeNewTicketModal();
        }
    });

    // New Ticket Modal
    function closeNewTicketModal() {
        document.getElementById('newTicketModal').classList.remove('active');
        document.body.style.overflow = document.getElementById('ticketModal').classList.contains('active') ? 'hidden' : '';
    }

    document.getElementById('newTicketModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeNewTicketModal();
        }
    });

    async function addComment() {
        const textarea = document.getElementById('commentTextarea');
        const text = textarea.value.trim();
        if (!text || !currentTicketId) return;

        try {
            const res = await fetch(`${API_BASE}/${currentTicketId}/comments`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
                body: JSON.stringify({ content: text })
            });
            const json = await res.json();
            if (json.success && json.data) {
                const c = json.data;
                const commentsList = document.getElementById('commentsList');
                const div = document.createElement('div');
                div.className = 'comment-item';
                div.innerHTML = `
                    <div class="comment-avatar">${c.initials || 'ME'}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <span class="comment-author">${c.author || 'You'}</span>
                            <span class="comment-time">${c.created_at || 'Just now'}</span>
                        </div>
                        <div class="comment-text">${c.content}</div>
                    </div>
                `;
                commentsList.appendChild(div);
                textarea.value = '';
                const activityList = document.getElementById('activityList');
                if (activityList && !activityList.querySelector('.activity-empty')) {
                    const item = document.createElement('div');
                    item.className = 'activity-item';
                    item.innerHTML = `
                        <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        <div class="activity-text">${c.author || 'You'} commented</div>
                        <div class="activity-time">${c.created_at || 'Just now'}</div>
                    `;
                    activityList.insertBefore(item, activityList.firstChild);
                } else if (activityList) {
                    activityList.innerHTML = `
                        <div class="activity-item">
                            <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                            </svg>
                            <div class="activity-text">${c.author || 'You'} commented</div>
                            <div class="activity-time">${c.created_at || 'Just now'}</div>
                        </div>
                    `;
                }
            }
        } catch (err) { console.error(err); }
    }

    // Functions
    async function createTicket() {
        document.getElementById('newTicketModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        const clientSelect = document.getElementById('newTicketClient');
        const assignedSelect = document.getElementById('newTicketAssignedTo');

        clientSelect.innerHTML = '<option value="">Select client</option>';
        assignedSelect.innerHTML = '<option value="">Unassigned</option>';

        try {
            const res = await fetch(`${API_BASE}/form-data`);
            const json = await res.json();
            if (json.success && json.data) {
                const { clients, employees } = json.data;
                if (clients && clients.length > 0) {
                    clients.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.id;
                        opt.textContent = c.name;
                        clientSelect.appendChild(opt);
                    });
                } else {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No clients yet';
                    opt.disabled = true;
                    clientSelect.appendChild(opt);
                }
                if (employees && employees.length > 0) {
                    employees.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id;
                        opt.textContent = u.name;
                        assignedSelect.appendChild(opt);
                    });
                } else {
                    const opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No employees yet';
                    opt.disabled = true;
                    assignedSelect.appendChild(opt);
                }
            }
        } catch (err) {
            console.error(err);
        }

        document.getElementById('newTicketSubject').value = '';
        document.getElementById('newTicketDescription').value = '';
        clientSelect.value = '';
        assignedSelect.value = '';
        document.getElementById('newTicketPriority').value = 'medium';
        document.getElementById('newTicketCategory').value = '';
        removeTicketImage();
    }

    function previewTicketImage(input) {
        const placeholder = document.getElementById('imageUploadPlaceholder');
        const preview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('imagePreviewImg');
        const file = input.files && input.files[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file (PNG, JPG, GIF).');
                input.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Image must be under 5MB.');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                placeholder.style.display = 'none';
                preview.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        } else {
            removeTicketImage();
        }
    }

    function removeTicketImage() {
        const input = document.getElementById('newTicketImage');
        const placeholder = document.getElementById('imageUploadPlaceholder');
        const preview = document.getElementById('imagePreview');
        input.value = '';
        placeholder.style.display = 'flex';
        preview.style.display = 'none';
        const img = document.getElementById('imagePreviewImg');
        if (img) img.src = '';
    }

    function handleImageDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.add('image-upload-dragover');
    }

    function handleImageDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('image-upload-dragover');
    }

    function handleImageDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('image-upload-dragover');
        const files = e.dataTransfer.files;
        if (files.length && files[0].type.startsWith('image/')) {
            const input = document.getElementById('newTicketImage');
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            input.files = dt.files;
            previewTicketImage(input);
        }
    }

    async function submitNewTicket(event) {
        event.preventDefault();
        const form = document.getElementById('newTicketForm');
        const formData = new FormData(form);

        try {
            const res = await fetch(API_BASE, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: formData
            });
            const json = await res.json();
            if (json.success) {
                closeNewTicketModal();
                currentPage = 1;
                fetchTickets();
            } else {
                alert(json.message || 'Failed to create ticket.');
            }
        } catch (err) {
            console.error(err);
            alert('Failed to create ticket.');
        }
    }

    // Window Resize Handler
    window.addEventListener('resize', updateView);

    // Initialize
    fetchTickets();
</script>
@endpush

