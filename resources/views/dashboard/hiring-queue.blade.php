@extends('layouts.app')

@section('title', 'Hiring Queue')

@push('styles')
    @vite(['resources/css/pages/hiring-queue.css'])
@endpush

@push('scripts')
<script>
    window.__hiringQueueConfig = {
        indexUrl: @json(route('api.hiring-queue.index')),
        baseUrl: @json(url('/api/hiring-queue')),
    };
</script>
    @vite(['resources/js/pages/hiring-queue.js'])
@endpush

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
                    <tbody id="hiringQueueBody" aria-busy="true">
                        @include('partials.skeleton-table-rows', ['rows' => 6, 'cols' => 7])
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
@endsection

