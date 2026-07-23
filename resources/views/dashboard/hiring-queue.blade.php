@extends('layouts.app')

@section('title', 'Hiring Queue')

@section('content')
    <div class="page-header">
        <h1 class="page-title">
            <svg style="width:24px;height:24px;vertical-align:middle;margin-right:8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
            </svg>
            Hiring Queue
        </h1>
        <p class="page-subtitle">Manage open positions and candidates</p>
    </div>

    <div class="hiring-queue-page">
        <div class="hiring-queue-section">
            <div class="hiring-queue-section-header">
                <h2>Open Positions <span class="hiring-queue-count">(<span id="queueCount">0</span>)</span></h2>
            </div>
            <div class="table-container">
                <table class="data-table hiring-queue-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Created</th>
                            <th>Comments</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hiringQueueBody">
                        <tr><td colspan="7" class="empty-state">No positions in hiring queue yet.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="queuePaginationInfo">Showing 0 to 0 of 0 results</span>
                </div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="queuePrevBtn" disabled>Previous</button>
                    <div class="pagination-numbers" id="queuePaginationNumbers"></div>
                    <button class="pagination-btn" id="queueNextBtn" disabled>Next</button>
                </div>
            </div>
        </div>

    </div>

    <!-- Add Candidate Modal -->
    <div class="modal-overlay" id="addCandidateModal">
        <div class="modal-content hiring-modal">
            <div class="modal-header">
                <h3>Add Candidate</h3>
                <button type="button" class="modal-close-btn" onclick="closeAddCandidateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-job-title" id="addCandidateJobTitle">—</p>
                <form id="addCandidateForm">
                    <input type="hidden" id="addCandidateItemId">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" id="candidateName" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" id="candidateEmail" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" id="candidatePhone">
                    </div>
                    <div class="form-group">
                        <label>Interview Date</label>
                        <input type="date" id="candidateInterviewDate">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="candidateNotes" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="candidateStatus">
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeAddCandidateModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Candidate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Job Description Modal -->
    <div class="modal-overlay" id="viewJobModal">
        <div class="modal-content hiring-modal view-modal">
            <div class="modal-header">
                <h3 id="viewJobTitle">Job Description</h3>
                <div class="modal-header-actions">
                    <a href="#" id="viewJobPdfLink" class="btn-download-pdf" target="_blank" rel="noopener" title="Download PDF">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Download PDF
                    </a>
                    <button type="button" class="modal-close-btn" onclick="closeViewJobModal()">&times;</button>
                </div>
            </div>
            <div class="modal-body">
                <pre id="viewJobDescription" class="job-description-pre"></pre>
            </div>
        </div>
    </div>

    <!-- Comments Modal -->
    <div class="modal-overlay" id="commentsModal">
        <div class="modal-content hiring-modal comments-thread-modal">
            <div class="modal-header">
                <div>
                    <h3>Comments</h3>
                    <p class="comments-modal-job" id="commentsModalJobTitle">—</p>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeCommentsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="comments-list" id="queueCommentsList">
                    <p class="comments-empty">No comments yet.</p>
                </div>
                <div class="comment-input">
                    <textarea class="comment-textarea" id="queueCommentTextarea" rows="3" placeholder="Write a comment…"></textarea>
                    <div class="comment-input-actions">
                        <button type="button" class="btn btn-primary" id="addQueueCommentBtn">Add Comment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Candidates Modal -->
    <div class="modal-overlay" id="candidatesModal">
        <div class="modal-content hiring-modal candidates-modal">
            <div class="modal-header">
                <h3>Candidates <span class="hiring-queue-count">(<span id="candidateCount">0</span>)</span></h3>
                <div class="modal-header-actions">
                    <span class="selected-queue-label" id="selectedQueueLabel">Select a hiring queue item to view candidates.</span>
                    <button type="button" class="btn btn-primary btn-add-candidate-list" id="addCandidateFromListBtn" disabled>Add Candidate</button>
                    <button type="button" class="modal-close-btn" onclick="closeCandidatesModal()">&times;</button>
                </div>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Interview Date</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="candidateListBody">
                            <tr><td colspan="7" class="empty-state">Select a queue item to load candidates.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Candidate Modal -->
    <div class="modal-overlay" id="editCandidateModal">
        <div class="modal-content hiring-modal">
            <div class="modal-header">
                <h3>Edit Candidate</h3>
                <button type="button" class="modal-close-btn" onclick="closeEditCandidateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-job-title" id="editCandidateJobTitle">—</p>
                <form id="editCandidateForm">
                    <input type="hidden" id="editCandidateItemId">
                    <input type="hidden" id="editCandidateId">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" id="editCandidateName" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" id="editCandidateEmail" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" id="editCandidatePhone">
                    </div>
                    <div class="form-group">
                        <label>Interview Date</label>
                        <input type="date" id="editCandidateInterviewDate">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="editCandidateNotes" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="editCandidateStatus">
                            <option value="pending">Pending</option>
                            <option value="accepted">Accepted</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditCandidateModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Queue Item Modal -->
    <div class="modal-overlay" id="editQueueModal">
        <div class="modal-content hiring-modal view-modal">
            <div class="modal-header">
                <h3>Edit Queue Item</h3>
                <button type="button" class="modal-close-btn" onclick="closeEditQueueModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editQueueForm">
                    <input type="hidden" id="editQueueItemId">
                    <div class="form-group">
                        <label>Job Title *</label>
                        <input type="text" id="editQueueJobTitle" required>
                    </div>
                    <div class="form-group">
                        <label>Client Email</label>
                        <input type="email" id="editQueueClientEmail">
                    </div>
                    <div class="form-group">
                        <label>Job Description *</label>
                        <textarea id="editQueueDescription" rows="12" required style="resize:vertical;"></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEditQueueModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="editQueueSubmitBtn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        .hiring-queue-page { display: flex; flex-direction: column; gap: 1rem; }
        .hiring-queue-section { background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border); padding: 1.25rem; }
        .hiring-queue-section-header { margin-bottom: 1rem; }
        .hiring-queue-section-header h2 { font-size: 1rem; font-weight: 600; color: var(--text-primary); }
        .hiring-queue-count { font-weight: 500; color: var(--text-secondary); font-size: 0.875rem; }
        .hiring-queue-page .table-container { border: 1px solid var(--border); border-radius: 10px; overflow-x: auto; background: var(--bg-card); -webkit-overflow-scrolling: touch; }
        .hiring-queue-page .data-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 0.8125rem; min-width: 900px; }
        .hiring-queue-page .data-table thead { background: var(--bg-primary); }
        .hiring-queue-page .data-table th { padding: 0.55rem 0.65rem; text-align: left; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); border-bottom: 1px solid var(--border); white-space: nowrap; }
        .hiring-queue-page .data-table td { padding: 0.55rem 0.65rem; text-align: left; border-bottom: 1px solid var(--border); color: var(--text-primary); vertical-align: middle; }
        .hiring-queue-page .data-table tbody tr:hover { background: var(--bg-primary); }
        .hiring-queue-table th:nth-child(1), .hiring-queue-table td:nth-child(1) { width: 22%; }
        .hiring-queue-table th:nth-child(2), .hiring-queue-table td:nth-child(2) { width: 14%; }
        .hiring-queue-table th:nth-child(3), .hiring-queue-table td:nth-child(3) { width: 10%; }
        .hiring-queue-table th:nth-child(4), .hiring-queue-table td:nth-child(4) { width: 12%; }
        .hiring-queue-table th:nth-child(5), .hiring-queue-table td:nth-child(5) { width: 10%; }
        .hiring-queue-table th:nth-child(6), .hiring-queue-table td:nth-child(6) { width: 11%; }
        .hiring-queue-table th:nth-child(7), .hiring-queue-table td:nth-child(7) { width: 21%; }
        .hiring-queue-page .data-table .job-title-cell { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
        .comments-cell { text-align: center; }
        .comments-open-btn { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.55rem; font-size: 0.6875rem; font-weight: 500; border: 1px solid var(--border); background: #fff; color: var(--text-secondary); border-radius: 6px; cursor: pointer; white-space: nowrap; }
        .comments-open-btn:hover { border-color: var(--accent); color: var(--accent); }
        .comments-count-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 1.15rem; height: 1.15rem; padding: 0 0.25rem; border-radius: 999px; background: var(--bg-primary); font-size: 0.625rem; font-weight: 600; color: var(--text-secondary); }
        .comments-thread-modal { max-width: 520px; width: 95%; }
        .comments-modal-job { margin: 0.25rem 0 0; font-size: 0.8125rem; color: var(--text-secondary); font-weight: 500; }
        .comments-list { display: flex; flex-direction: column; gap: 0.75rem; max-height: 320px; overflow-y: auto; margin-bottom: 1rem; padding-right: 0.25rem; }
        .comments-empty { text-align: center; color: var(--text-muted); font-size: 0.8125rem; padding: 1.5rem 0; margin: 0; }
        .comment-item { display: flex; gap: 0.65rem; padding: 0.75rem; background: var(--bg-primary); border-radius: 8px; }
        .comment-avatar { width: 32px; height: 32px; border-radius: 50%; background: var(--accent); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.75rem; flex-shrink: 0; }
        .comment-content { flex: 1; min-width: 0; }
        .comment-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem; flex-wrap: wrap; }
        .comment-header-main { display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 0; }
        .comment-delete-btn { margin-left: auto; padding: 0.15rem 0.45rem; font-size: 0.6875rem; font-weight: 500; border: 1px solid transparent; background: transparent; color: var(--text-muted); border-radius: 6px; cursor: pointer; }
        .comment-delete-btn:hover { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
        .comment-author { font-weight: 600; color: var(--text-primary); font-size: 0.8125rem; }
        .comment-time { font-size: 0.6875rem; color: var(--text-muted); }
        .comment-text { font-size: 0.8125rem; color: var(--text-primary); line-height: 1.5; white-space: pre-wrap; word-break: break-word; }
        .comment-input { display: flex; flex-direction: column; gap: 0.65rem; border-top: 1px solid var(--border); padding-top: 1rem; }
        .comment-textarea { width: 100%; padding: 0.65rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.8125rem; font-family: inherit; resize: vertical; min-height: 72px; background: #fff; color: var(--text-primary); }
        .comment-textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15); }
        .comment-input-actions { display: flex; justify-content: flex-end; }
        .comment-input-actions .btn { padding: 0.45rem 0.85rem; font-size: 0.8125rem; font-weight: 500; border-radius: 8px; cursor: pointer; border: 1px solid var(--accent); background: var(--accent); color: #fff; }
        .comment-input-actions .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .hiring-queue-page .data-table .actions-cell { white-space: nowrap; }
        .candidate-header-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .selected-queue-label { color: var(--text-secondary); font-size: 0.8125rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .btn-add-candidate-list { padding: 0.4rem 0.85rem; border: 1px solid var(--accent); border-radius: 8px; background: var(--accent); color: #fff; font-size: 0.8125rem; cursor: pointer; }
        .btn-add-candidate-list[disabled] { opacity: 0.6; cursor: not-allowed; }
        .source-badge { padding: 0.15rem 0.45rem; border-radius: 6px; font-size: 0.6875rem; font-weight: 500; white-space: nowrap; }
        .source-badge.client { background: #e5e7eb; color: #374151; }
        .source-badge.sales_rep { background: #dbeafe; color: #1d4ed8; }
        .source-badge.generic { background: #eef2ff; color: #3730a3; }
        .status-select { width: 100%; max-width: 96px; border: 1px solid var(--border); background: #fff; border-radius: 6px; padding: 0.25rem 0.4rem; font-size: 0.75rem; }
        .candidate-status-select { max-width: 110px; border: 1px solid var(--border); background: #fff; border-radius: 6px; padding: 0.25rem 0.4rem; font-size: 0.75rem; }
        .candidate-edit-btn { display: inline-flex; align-items: center; gap: 0.35rem; border: 1px solid var(--border); border-radius: 6px; padding: 0.25rem 0.5rem; font-size: 0.6875rem; background: #fff; color: var(--text-secondary); cursor: pointer; }
        .candidate-edit-btn:hover { border-color: var(--accent); color: var(--accent); }
        .action-btns { display: flex; gap: 0.15rem; flex-wrap: nowrap; }
        .action-btns button, .action-btns .action-btn-pdf { padding: 0.25rem; background: transparent; border: none; cursor: pointer; color: var(--text-secondary); display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border-radius: 4px; }
        .action-btns button:hover, .action-btns .action-btn-pdf:hover { color: var(--accent); background: var(--bg-primary); }
        .action-btns svg { width: 15px; height: 15px; }
        .empty-state { text-align: center; color: var(--text-muted); padding: 1.5rem !important; font-size: 0.8125rem; }
        .hiring-queue-page .table-pagination { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0 0; margin-top: 0.75rem; border-top: 1px solid var(--border); flex-wrap: wrap; gap: 0.75rem; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .hiring-modal { background: white; border-radius: 12px; max-width: 480px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .view-modal { max-width: 640px; }
        .candidates-modal { max-width: 960px; width: 95%; }
        .candidates-modal .table-container { border: 1px solid var(--border); border-radius: 10px; overflow-x: auto; }
        .candidates-modal .data-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; min-width: 720px; }
        .candidates-modal .data-table th { padding: 0.55rem 0.65rem; text-align: left; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); border-bottom: 1px solid var(--border); background: var(--bg-primary); white-space: nowrap; }
        .candidates-modal .data-table td { padding: 0.55rem 0.65rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .candidates-modal .data-table td:nth-child(6) { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 0.75rem; color: var(--text-secondary); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
        .modal-header h3 { font-size: 1.125rem; }
        .modal-header-actions { display: flex; align-items: center; gap: 0.5rem; }
        .btn-download-pdf { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; background: var(--accent); color: white; border-radius: 8px; font-size: 0.8125rem; font-weight: 500; text-decoration: none; transition: background 0.15s; }
        .btn-download-pdf:hover { background: #1d4ed8; color: white; }
        .btn-download-pdf svg { flex-shrink: 0; }
        .modal-close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        .modal-body { padding: 1.25rem; }
        .modal-job-title { font-weight: 600; margin-bottom: 1rem; color: var(--accent); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.8125rem; font-weight: 500; margin-bottom: 0.375rem; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; }
        .modal-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; flex-wrap: wrap; }
        .modal-actions .btn { padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; border-radius: 8px; cursor: pointer; transition: all 0.15s; border: 1px solid transparent; font-family: inherit; }
        .modal-actions .btn-primary { background: var(--accent); color: white; border-color: var(--accent); }
        .modal-actions .btn-primary:hover { background: var(--accent-hover, #4f51d6); }
        .modal-actions .btn-secondary { background: var(--bg-primary); color: var(--text-secondary); border-color: var(--border); }
        .modal-actions .btn-secondary:hover { background: var(--border); color: var(--text-primary); }
        .job-description-pre { white-space: pre-wrap; font-family: inherit; font-size: 0.875rem; line-height: 1.6; }
        .pagination-info { color: var(--text-secondary); font-size: 0.8125rem; }
        .pagination-controls { display: flex; align-items: center; gap: 0.35rem; }
        .pagination-btn { border: 1px solid var(--border); background: #fff; color: var(--text-primary); border-radius: 8px; padding: 0.45rem 0.75rem; font-size: 0.8125rem; cursor: pointer; }
        .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
        .pagination-numbers { display: flex; align-items: center; gap: 0.25rem; }
        .pagination-number { border: 1px solid var(--border); background: #fff; color: var(--text-primary); border-radius: 7px; min-width: 34px; height: 34px; padding: 0 0.45rem; font-size: 0.8125rem; cursor: pointer; }
        .pagination-number.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .pagination-number.ellipsis { border: none; background: transparent; cursor: default; min-width: auto; }
    </style>
    @endpush

    @push('scripts')
    <script>
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const queueStatuses = ['open', 'cancel', 'pending', 'close'];
        const candidateStatuses = ['pending', 'accepted', 'rejected'];
        let queueItems = [];
        let queueCurrentPage = 1;
        let queueItemsPerPage = 10;
        let queueTotalItems = 0;
        let queueTotalPages = 1;
        let selectedQueueItem = null;
        let candidateItems = [];
        let reopenCandidatesAfterAddModal = false;
        let activeCommentsItemId = null;

        async function loadQueue(page = 1) {
            try {
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: String(queueItemsPerPage)
                });
                const r = await fetch(`{{ route("api.hiring-queue.index") }}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                queueItems = data.items || [];
                queueTotalItems = Number(data.pagination?.total ?? queueItems.length);
                queueTotalPages = Number(data.pagination?.last_page ?? 1);
                queueCurrentPage = Number(data.pagination?.current_page ?? page);
                renderTable();
                renderQueuePagination();
                if (selectedQueueItem && isCandidatesModalOpen()) {
                    loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                }
            } catch (e) {
                console.error(e);
            }
        }

        function renderTable() {
            const tbody = document.getElementById('hiringQueueBody');
            document.getElementById('queueCount').textContent = queueTotalItems;
            if (!queueItems.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No positions in hiring queue yet.</td></tr>';
                return;
            }
            tbody.innerHTML = queueItems.map(item => {
                const title = escapeHtml(item.job_title);
                const creator = escapeHtml(item.created_by || 'Unknown');
                const statusOptions = queueStatuses.map(status => `<option value="${status}" ${item.status === status ? 'selected' : ''}>${formatStatusLabel(status)}</option>`).join('');
                const sourceInfo = getSourceBadgeData(item.source);
                const commentCount = Number(item.comments_count || 0);
                return `
                <tr data-id="${item.id}" data-title="${title}" class="queue-row">
                    <td class="job-title-cell">${title}</td>
                    <td><span class="source-badge ${sourceInfo.className}">${sourceInfo.label}</span></td>
                    <td>
                        <select class="status-select queue-status-select" data-id="${item.id}">
                            ${statusOptions}
                        </select>
                    </td>
                    <td>${creator}</td>
                    <td>${item.created_at}</td>
                    <td class="comments-cell">
                        <button type="button" class="comments-open-btn" data-id="${item.id}" title="View comments">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Comments
                            <span class="comments-count-badge">${commentCount}</span>
                        </button>
                    </td>
                    <td class="actions-cell">
                        <div class="action-btns">
                            <button type="button" title="Add candidate" class="add-candidate-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                            </button>
                            <button type="button" title="View" class="view-job-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button type="button" title="View candidates" class="view-candidates-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </button>
                            <button type="button" title="Edit" class="edit-queue-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <a href="{{ url('/api/hiring-queue') }}/${item.id}/pdf" class="action-btn-pdf" title="Download PDF" target="_blank" rel="noopener">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                        </div>
                    </td>
                </tr>
            `}).join('');
            tbody.querySelectorAll('.queue-row').forEach(tr => {
                const id = parseInt(tr.dataset.id);
                const title = tr.dataset.title;
                tr.querySelector('.add-candidate-btn').addEventListener('click', (e) => { e.stopPropagation(); openAddCandidateModal(id, title); });
                tr.querySelector('.edit-queue-btn').addEventListener('click', (e) => { e.stopPropagation(); openEditQueueModal(id); });
                tr.querySelector('.view-job-btn').addEventListener('click', (e) => { e.stopPropagation(); viewJob(id); });
                tr.querySelector('.view-candidates-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openCandidatesModal(id, title);
                });
                tr.querySelector('.queue-status-select').addEventListener('click', (e) => e.stopPropagation());
                tr.querySelector('.queue-status-select').addEventListener('change', async (e) => {
                    await updateQueueStatus(id, e.target.value);
                });
                tr.querySelector('.comments-open-btn').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openCommentsModal(id, title);
                });
                const pdfLink = tr.querySelector('.action-btn-pdf');
                if (pdfLink) pdfLink.addEventListener('click', (e) => e.stopPropagation());
            });
        }

        function renderCommentItem(comment) {
            const deleteBtn = comment.can_delete
                ? `<button type="button" class="comment-delete-btn" data-comment-id="${comment.id}" title="Delete comment">Delete</button>`
                : '';
            return `
                <div class="comment-item" data-comment-id="${comment.id}">
                    <div class="comment-avatar">${escapeHtml(comment.initials || '—')}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <div class="comment-header-main">
                                <span class="comment-author">${escapeHtml(comment.author || 'Unknown')}</span>
                                <span class="comment-time">${escapeHtml(comment.created_at || '')}</span>
                            </div>
                            ${deleteBtn}
                        </div>
                        <div class="comment-text">${escapeHtml(comment.content || '')}</div>
                    </div>
                </div>
            `;
        }

        function renderCommentsList(comments) {
            const list = document.getElementById('queueCommentsList');
            if (!comments.length) {
                list.innerHTML = '<p class="comments-empty">No comments yet. Be the first to add one.</p>';
                return;
            }
            list.innerHTML = comments.map(renderCommentItem).join('');
            list.scrollTop = list.scrollHeight;
        }

        function updateCommentsCount(itemId, count) {
            const item = queueItems.find(i => i.id === itemId);
            if (item) {
                item.comments_count = count;
            }
            const badge = document.querySelector(`.comments-open-btn[data-id="${itemId}"] .comments-count-badge`);
            if (badge) {
                badge.textContent = count;
            }
        }

        async function openCommentsModal(itemId, jobTitle) {
            activeCommentsItemId = itemId;
            document.getElementById('commentsModalJobTitle').textContent = jobTitle;
            document.getElementById('queueCommentTextarea').value = '';
            document.getElementById('commentsModal').classList.add('open');
            await loadQueueComments(itemId);
        }

        function closeCommentsModal() {
            document.getElementById('commentsModal').classList.remove('open');
            activeCommentsItemId = null;
        }

        async function loadQueueComments(itemId) {
            const list = document.getElementById('queueCommentsList');
            list.innerHTML = '<p class="comments-empty">Loading comments…</p>';
            try {
                const r = await fetch(`{{ url('/api/hiring-queue') }}/${itemId}/comments`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                const comments = data.comments || [];
                renderCommentsList(comments);
                updateCommentsCount(itemId, comments.length);
            } catch (e) {
                console.error(e);
                list.innerHTML = '<p class="comments-empty">Could not load comments.</p>';
            }
        }

        async function addQueueComment() {
            if (!activeCommentsItemId) return;
            const textarea = document.getElementById('queueCommentTextarea');
            const button = document.getElementById('addQueueCommentBtn');
            const content = textarea.value.trim();
            if (!content) return;

            button.disabled = true;
            button.textContent = 'Posting…';
            try {
                const r = await fetch(`{{ url('/api/hiring-queue') }}/${activeCommentsItemId}/comments`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ content })
                });
                if (r.ok) {
                    const data = await r.json();
                    const list = document.getElementById('queueCommentsList');
                    const empty = list.querySelector('.comments-empty');
                    if (empty) {
                        list.innerHTML = '';
                    }
                    list.insertAdjacentHTML('beforeend', renderCommentItem(data.comment));
                    list.scrollTop = list.scrollHeight;
                    textarea.value = '';
                    const item = queueItems.find(i => i.id === activeCommentsItemId);
                    const newCount = (item?.comments_count || 0) + 1;
                    updateCommentsCount(activeCommentsItemId, newCount);
                } else {
                    const data = await r.json().catch(() => ({}));
                    alert(data.message || 'Could not add comment. Please try again.');
                }
            } catch (e) {
                console.error(e);
                alert('Could not add comment. Please try again.');
            }
            button.disabled = false;
            button.textContent = 'Add Comment';
        }

        async function deleteQueueComment(itemId, commentId) {
            if (!confirm('Delete this comment?')) {
                return;
            }

            try {
                const r = await fetch(`{{ url('/api/hiring-queue') }}/${itemId}/comments/${commentId}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                });
                if (r.ok) {
                    const list = document.getElementById('queueCommentsList');
                    const item = list.querySelector(`.comment-item[data-comment-id="${commentId}"]`);
                    if (item) {
                        item.remove();
                    }
                    if (!list.querySelector('.comment-item')) {
                        list.innerHTML = '<p class="comments-empty">No comments yet. Be the first to add one.</p>';
                    }
                    const queueItem = queueItems.find(i => i.id === itemId);
                    const newCount = Math.max(0, (queueItem?.comments_count || 1) - 1);
                    updateCommentsCount(itemId, newCount);
                } else {
                    const data = await r.json().catch(() => ({}));
                    alert(data.message || 'Could not delete comment. Please try again.');
                }
            } catch (e) {
                console.error(e);
                alert('Could not delete comment. Please try again.');
            }
        }

        async function updateQueueStatus(itemId, status) {
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/status`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ status })
                });
                if (!r.ok) {
                    await loadQueue(queueCurrentPage);
                }
            } catch (e) {
                console.error(e);
                await loadQueue(queueCurrentPage);
            }
        }

        function renderQueuePagination() {
            const info = document.getElementById('queuePaginationInfo');
            const numbers = document.getElementById('queuePaginationNumbers');
            const prevBtn = document.getElementById('queuePrevBtn');
            const nextBtn = document.getElementById('queueNextBtn');

            const start = queueTotalItems > 0 ? (queueCurrentPage - 1) * queueItemsPerPage + 1 : 0;
            const end = Math.min(queueCurrentPage * queueItemsPerPage, queueTotalItems);
            info.textContent = `Showing ${start} to ${end} of ${queueTotalItems} results`;

            prevBtn.disabled = queueCurrentPage <= 1;
            nextBtn.disabled = queueCurrentPage >= queueTotalPages;

            const maxVisible = 5;
            let startPage = Math.max(1, queueCurrentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(queueTotalPages, startPage + maxVisible - 1);
            startPage = Math.max(1, endPage - maxVisible + 1);

            let html = '';
            if (startPage > 1) {
                html += `<button class="pagination-number" data-page="1">1</button>`;
                if (startPage > 2) html += `<span class="pagination-number ellipsis">...</span>`;
            }

            for (let i = startPage; i <= endPage; i += 1) {
                html += `<button class="pagination-number ${i === queueCurrentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }

            if (endPage < queueTotalPages) {
                if (endPage < queueTotalPages - 1) html += `<span class="pagination-number ellipsis">...</span>`;
                html += `<button class="pagination-number" data-page="${queueTotalPages}">${queueTotalPages}</button>`;
            }

            numbers.innerHTML = html;
            numbers.querySelectorAll('.pagination-number:not(.ellipsis)').forEach(btn => {
                btn.addEventListener('click', () => {
                    const page = parseInt(btn.dataset.page, 10);
                    if (!Number.isNaN(page)) loadQueue(page);
                });
            });
        }

        function openCandidatesModal(itemId, jobTitle) {
            document.getElementById('candidatesModal').classList.add('open');
            loadCandidates(itemId, jobTitle);
        }

        function closeCandidatesModal(preserveSelection = false) {
            document.getElementById('candidatesModal').classList.remove('open');
            if (!preserveSelection) {
                resetCandidatesPane();
            }
        }

        function isCandidatesModalOpen() {
            return document.getElementById('candidatesModal').classList.contains('open');
        }

        async function loadCandidates(itemId, jobTitle) {
            selectedQueueItem = { id: itemId, job_title: jobTitle };
            document.getElementById('selectedQueueLabel').textContent = `For: ${jobTitle}`;
            document.getElementById('addCandidateFromListBtn').disabled = false;

            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                candidateItems = data.candidates || [];
                renderCandidateList();
            } catch (e) {
                console.error(e);
            }
        }

        function resetCandidatesPane() {
            selectedQueueItem = null;
            candidateItems = [];
            document.getElementById('selectedQueueLabel').textContent = 'Select a hiring queue item to view candidates.';
            document.getElementById('addCandidateFromListBtn').disabled = true;
            renderCandidateList();
        }

        function renderCandidateList() {
            const tbody = document.getElementById('candidateListBody');
            document.getElementById('candidateCount').textContent = candidateItems.length;

            if (!selectedQueueItem) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Select a queue item to load candidates.</td></tr>';
                return;
            }

            if (!candidateItems.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No candidates added yet for this job.</td></tr>';
                return;
            }

            tbody.innerHTML = candidateItems.map(candidate => {
                const options = candidateStatuses.map(status => `<option value="${status}" ${candidate.status === status ? 'selected' : ''}>${formatStatusLabel(status)}</option>`).join('');
                const interviewDate = candidate.interview_date || '—';
                const notes = candidate.notes ? escapeHtml(candidate.notes) : '—';
                return `
                    <tr>
                        <td>${escapeHtml(candidate.name)}</td>
                        <td>${escapeHtml(candidate.email)}</td>
                        <td>${candidate.phone ? escapeHtml(candidate.phone) : '—'}</td>
                        <td>${interviewDate}</td>
                        <td>
                            <select class="candidate-status-select" data-candidate-id="${candidate.id}">
                                ${options}
                            </select>
                        </td>
                        <td>${notes}</td>
                        <td>
                            <button type="button" class="candidate-edit-btn" data-candidate-id="${candidate.id}">
                                Edit
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            tbody.querySelectorAll('.candidate-status-select').forEach(select => {
                select.addEventListener('change', async (e) => {
                    if (!selectedQueueItem) return;
                    const candidateId = parseInt(e.target.dataset.candidateId);
                    await updateCandidateStatus(selectedQueueItem.id, candidateId, e.target.value);
                });
            });
            tbody.querySelectorAll('.candidate-edit-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const candidateId = parseInt(e.currentTarget.dataset.candidateId);
                    openEditCandidateModal(candidateId);
                });
            });
        }

        async function updateCandidateStatus(itemId, candidateId, status) {
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates/${candidateId}/status`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ status })
                });
                if (!r.ok && selectedQueueItem) {
                    await loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                }
            } catch (e) {
                console.error(e);
                if (selectedQueueItem) {
                    await loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                }
            }
        }

        function escapeHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        }

        function formatStatusLabel(status) {
            return status.charAt(0).toUpperCase() + status.slice(1);
        }

        function getSourceBadgeData(source) {
            if (source === 'sales_rep') {
                return { className: 'sales_rep', label: 'Sales Rep' };
            }
            if (source === 'client') {
                return { className: 'client', label: 'Client' };
            }
            return { className: 'generic', label: escapeHtml(source || 'Client') };
        }

        function openAddCandidateModal(itemId, jobTitle, options = {}) {
            const fromCandidatesModal = Boolean(options.fromCandidatesModal);

            if (fromCandidatesModal && isCandidatesModalOpen()) {
                reopenCandidatesAfterAddModal = true;
                closeCandidatesModal(true);
            } else {
                reopenCandidatesAfterAddModal = false;
            }

            document.getElementById('addCandidateItemId').value = itemId;
            document.getElementById('addCandidateJobTitle').textContent = jobTitle;
            document.getElementById('addCandidateForm').reset();
            document.getElementById('addCandidateItemId').value = itemId;
            document.getElementById('candidateStatus').value = 'pending';
            document.getElementById('addCandidateModal').classList.add('open');
        }

        function openEditCandidateModal(candidateId) {
            if (!selectedQueueItem) return;
            const candidate = candidateItems.find(item => item.id === candidateId);
            if (!candidate) return;

            document.getElementById('editCandidateItemId').value = selectedQueueItem.id;
            document.getElementById('editCandidateId').value = candidate.id;
            document.getElementById('editCandidateJobTitle').textContent = selectedQueueItem.job_title || '—';
            document.getElementById('editCandidateName').value = candidate.name || '';
            document.getElementById('editCandidateEmail').value = candidate.email || '';
            document.getElementById('editCandidatePhone').value = candidate.phone || '';
            document.getElementById('editCandidateInterviewDate').value = candidate.interview_date || '';
            document.getElementById('editCandidateNotes').value = candidate.notes || '';
            document.getElementById('editCandidateStatus').value = candidate.status || 'pending';
            document.getElementById('editCandidateModal').classList.add('open');
        }

        function closeAddCandidateModal() {
            document.getElementById('addCandidateModal').classList.remove('open');
            if (reopenCandidatesAfterAddModal && selectedQueueItem) {
                const { id, job_title: jobTitle } = selectedQueueItem;
                reopenCandidatesAfterAddModal = false;
                openCandidatesModal(id, jobTitle);
                return;
            }
            reopenCandidatesAfterAddModal = false;
        }

        function closeEditCandidateModal() {
            document.getElementById('editCandidateModal').classList.remove('open');
        }

        function closeViewJobModal() {
            document.getElementById('viewJobModal').classList.remove('open');
        }

        async function viewJob(id) {
            try {
                const r = await fetch(`/api/hiring-queue/${id}`, { headers: { 'Accept': 'application/json' } });
                const data = await r.json();
                document.getElementById('viewJobTitle').textContent = data.item.job_title;
                document.getElementById('viewJobDescription').textContent = data.item.full_description;
                document.getElementById('viewJobPdfLink').href = `{{ url('/api/hiring-queue') }}/${id}/pdf`;
                document.getElementById('viewJobModal').classList.add('open');
            } catch (e) {
                console.error(e);
            }
        }

        document.getElementById('addCandidateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = document.getElementById('addCandidateItemId').value;
            const payload = {
                name: document.getElementById('candidateName').value,
                email: document.getElementById('candidateEmail').value,
                phone: document.getElementById('candidatePhone').value || null,
                interview_date: document.getElementById('candidateInterviewDate').value || null,
                notes: document.getElementById('candidateNotes').value || null,
                status: document.getElementById('candidateStatus').value,
            };
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                });
                if (r.ok) {
                    closeAddCandidateModal();
                    await loadQueue(queueCurrentPage);
                }
            } catch (e) {
                console.error(e);
            }
        });
        document.getElementById('editCandidateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = document.getElementById('editCandidateItemId').value;
            const candidateId = document.getElementById('editCandidateId').value;
            const payload = {
                name: document.getElementById('editCandidateName').value,
                email: document.getElementById('editCandidateEmail').value,
                phone: document.getElementById('editCandidatePhone').value || null,
                interview_date: document.getElementById('editCandidateInterviewDate').value || null,
                notes: document.getElementById('editCandidateNotes').value || null,
                status: document.getElementById('editCandidateStatus').value,
            };
            try {
                const r = await fetch(`/api/hiring-queue/${itemId}/candidates/${candidateId}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                });
                if (r.ok && selectedQueueItem) {
                    closeEditCandidateModal();
                    await loadCandidates(selectedQueueItem.id, selectedQueueItem.job_title);
                    await loadQueue(queueCurrentPage);
                }
            } catch (e) {
                console.error(e);
            }
        });

        function openEditQueueModal(itemId) {
            const item = queueItems.find(i => i.id === itemId);
            if (!item) return;
            document.getElementById('editQueueItemId').value = item.id;
            document.getElementById('editQueueJobTitle').value = item.job_title || '';
            document.getElementById('editQueueClientEmail').value = item.client_email || '';
            document.getElementById('editQueueDescription').value = item.full_description || '';
            document.getElementById('editQueueModal').classList.add('open');
        }

        function closeEditQueueModal() {
            document.getElementById('editQueueModal').classList.remove('open');
        }

        document.getElementById('editQueueForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = document.getElementById('editQueueItemId').value;
            const submitBtn = document.getElementById('editQueueSubmitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
            const payload = {
                job_title: document.getElementById('editQueueJobTitle').value,
                client_email: document.getElementById('editQueueClientEmail').value || null,
                full_description: document.getElementById('editQueueDescription').value,
            };
            try {
                const r = await fetch(`{{ url('/api/hiring-queue') }}/${itemId}`, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                });
                if (r.ok) {
                    closeEditQueueModal();
                    await loadQueue(queueCurrentPage);
                } else {
                    const data = await r.json().catch(() => ({}));
                    alert(data.message || 'Could not save changes. Please try again.');
                }
            } catch (err) {
                console.error(err);
            }
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        });

        document.getElementById('editQueueModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeEditQueueModal();
        });

        document.getElementById('addCandidateModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeAddCandidateModal();
        });
        document.getElementById('editCandidateModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeEditCandidateModal();
        });
        document.getElementById('commentsModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeCommentsModal();
        });
        document.getElementById('addQueueCommentBtn').addEventListener('click', addQueueComment);
        document.getElementById('queueCommentsList').addEventListener('click', async (e) => {
            const btn = e.target.closest('.comment-delete-btn');
            if (!btn || !activeCommentsItemId) return;
            const commentId = parseInt(btn.dataset.commentId, 10);
            if (Number.isNaN(commentId)) return;
            await deleteQueueComment(activeCommentsItemId, commentId);
        });
        document.getElementById('queueCommentTextarea').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                addQueueComment();
            }
        });
        document.getElementById('viewJobModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeViewJobModal();
        });
        document.getElementById('candidatesModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeCandidatesModal();
        });
        document.getElementById('addCandidateFromListBtn').addEventListener('click', () => {
            if (!selectedQueueItem) return;
            openAddCandidateModal(selectedQueueItem.id, selectedQueueItem.job_title, { fromCandidatesModal: true });
        });
        document.getElementById('queuePrevBtn').addEventListener('click', () => {
            if (queueCurrentPage > 1) loadQueue(queueCurrentPage - 1);
        });
        document.getElementById('queueNextBtn').addEventListener('click', () => {
            if (queueCurrentPage < queueTotalPages) loadQueue(queueCurrentPage + 1);
        });

        loadQueue();
    </script>
    @endpush
@endsection
