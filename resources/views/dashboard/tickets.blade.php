@extends('layouts.app')

@section('title', 'Tickets & Helpdesk')

@push('styles')
    @include('partials.leads-page-base-styles')
    @vite(['resources/css/pages/tickets.css'])
@endpush

@push('scripts')
<script>
    window.__ticketsConfig = {
        apiBase: @json(url('/api/tickets')),
    };
</script>
    @vite(['resources/js/pages/tickets.js'])
@endpush

@section('content')
    <div class="ld-page-wrapper">
    <div class="ld-page">
    <div class="ld-top">
        <div class="ld-top-main">
            <h1 class="ld-title">Tickets & Helpdesk</h1>
            <p class="ld-subtitle">Manage support tickets, track SLAs, and prioritize issues.</p>
        </div>
        <div class="ld-top-actions">
            <button type="button" class="btn btn-primary btn-sm" onclick="createTicket()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New ticket
            </button>
        </div>
    </div>

    <div class="leads-toolbar">
        <input type="search" id="ticketSearch" class="leads-search" placeholder="Search tickets…">
        <div class="leads-toolbar-filters">
            <select id="priorityFilter" class="leads-source-filter" aria-label="Filter by priority">
                <option value="all">All priority</option>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
        </div>
    </div>

    <div class="leads-tabs" role="tablist" id="viewSubmenu">
        <button type="button" class="leads-tab view-submenu-tab active" data-view="all">All tickets</button>
        <button type="button" class="leads-tab view-submenu-tab" data-view="assigned-to-me">Assigned to me</button>
    </div>

    <div class="leads-tabs" role="tablist" id="statusTabs">
        <button type="button" class="leads-tab status-tab active" data-status="open">Open <span id="tabCountOpen">0</span></button>
        <button type="button" class="leads-tab status-tab" data-status="in-progress">In progress <span id="tabCountInProgress">0</span></button>
        <button type="button" class="leads-tab status-tab" data-status="pending">Pending <span id="tabCountPending">0</span></button>
        <button type="button" class="leads-tab status-tab" data-status="resolved">Resolved <span id="tabCountResolved">0</span></button>
        <button type="button" class="leads-tab status-tab" data-status="closed">Closed <span id="tabCountClosed">0</span></button>
    </div>

    <div class="leads-card" id="ticketsCard">
        <div class="table-container">
            <table class="data-table leads-table" id="ticketsTable">
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
                        <th></th>
                    </tr>
                </thead>
                <tbody id="ticketsTableBody" aria-busy="true">
                    @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 9])
                </tbody>
            </table>
        </div>

        <div class="tickets-cards" id="ticketsCards"></div>

        <div class="leads-pagination">
            <span id="paginationInfo">Showing 0 of 0</span>
            <div>
                <button type="button" class="btn btn-secondary btn-sm" id="prevBtn" disabled>Previous</button>
                <div class="pagination-numbers" id="paginationNumbers"></div>
                <button type="button" class="btn btn-secondary btn-sm" id="nextBtn" disabled>Next</button>
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
                                <button type="button" class="btn btn-primary btn-sm" id="addCommentBtn" onclick="addComment()">Add comment</button>
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
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeNewTicketModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Create ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>
    </div>
@endsection
