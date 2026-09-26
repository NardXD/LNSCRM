@extends('layouts.app')

@section('title', 'Contracts & E-Sign')

@push('styles')
    @vite(['resources/css/pages/contracts.css'])
@endpush

@push('scripts')
<script>
    window.__contractsConfig = {
        permissions: @json($userPermissions ?? []),
        contractApi: @json(url('/api/contracts')),
        statusHistoryUrlTemplate: @json(route('api.contracts.status-history', ':id')),
    };
</script>
    @vite(['resources/js/pages/contracts.js'])
@endpush

@section('content')
    <div class="page-header">
        <h1 class="page-title">Contracts & E-Sign</h1>
        <p class="page-subtitle">Create contracts, send them to leads, and collect electronic signatures</p>
    </div>

    <div class="quotation-container">
        <div class="quotation-header">
            <div class="header-left">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" class="search-input" id="contractSearch" placeholder="Search contracts...">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="all">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="pending_signatures">Pending Signatures</option>
                    <option value="partially_signed">Partially Signed</option>
                    <option value="signed">Signed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="header-right">
                @if(in_array('create_contracts', $userPermissions ?? []))
                <button class="btn-primary" id="newContractBtn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Contract
                </button>
                @endif
            </div>
        </div>

        <div class="quotation-stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Contracts</span>
                    <div class="stat-icon blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value" id="statTotal">0</div>
                <div class="stat-change">All contracts</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Pending</span>
                    <div class="stat-icon orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value" id="statPending">0</div>
                <div class="stat-change">Awaiting signatures</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Signed</span>
                    <div class="stat-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value" id="statSigned">0</div>
                <div class="stat-change positive">Fully executed</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Drafts</span>
                    <div class="stat-icon purple">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                            <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value" id="statDraft">0</div>
                <div class="stat-change">Not yet sent</div>
            </div>
        </div>

        <div class="quotations-section">
            <div class="table-container">
                <table class="data-table" id="contractsTable">
                    <thead>
                        <tr>
                            <th>Contract #</th>
                            <th>Title</th>
                            <th>Lead</th>
                            <th>Status</th>
                            <th>Signatures</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contractsTableBody" aria-busy="true">
                        @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 7])
                    </tbody>
                </table>
            </div>

            <div class="contracts-cards" id="contractsCards"></div>

            <div class="table-pagination">
                <div class="pagination-info">
                    <span id="paginationInfo">Showing 0 results</span>
                </div>
                <div class="pagination-controls">
                    <button class="pagination-btn" id="prevBtn" disabled>Previous</button>
                    <div class="pagination-numbers" id="paginationNumbers"></div>
                    <button class="pagination-btn" id="nextBtn" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Create / Edit Modal --}}
    <div class="quotation-modal" id="contractModal">
        <div class="quotation-modal-content">
            <button type="button" class="modal-close" id="closeContractModal" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h2 class="modal-title" id="contractModalTitle">New Contract</h2>
            </div>
            <div class="modal-body">
                <form id="contractForm" class="quotation-form">
                    <input type="hidden" id="contractId">
                    <div class="form-section">
                        <h3 class="form-section-title">Contract Details</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Lead *</label>
                                <select class="form-input" id="leadId" required></select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contract #</label>
                                <input type="text" class="form-input" id="contractNumber" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-input" id="contractTitle" required placeholder="e.g. Service Agreement">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Effective Date</label>
                                <input type="date" class="form-input" id="effectiveDate">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" class="form-input" id="expiryDate">
                            </div>
                        </div>
                    </div>
                    <div class="form-section">
                        <h3 class="form-section-title">Contract Content</h3>
                        <div class="rich-editor">
                            <div class="rich-editor-toolbar" data-editor="contractContentEditor">
                                <button type="button" class="rich-editor-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                                <button type="button" class="rich-editor-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                                <button type="button" class="rich-editor-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                                <span class="rich-editor-sep"></span>
                                <select class="rich-editor-select" data-editor="contractContentEditor" title="Text size">
                                    <option value="p">Paragraph</option>
                                    <option value="h2">Heading 2</option>
                                    <option value="h3">Heading 3</option>
                                </select>
                                <span class="rich-editor-sep"></span>
                                <button type="button" class="rich-editor-btn" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                                <button type="button" class="rich-editor-btn" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                                <button type="button" class="rich-editor-btn" data-cmd="createLink" title="Insert link">Link</button>
                                <button type="button" class="rich-editor-btn" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                            </div>
                            <div id="contractContentEditor" class="rich-editor-content rich-editor-content-tall" contenteditable="true" data-hidden="contractContent" role="textbox" aria-label="Contract content" data-placeholder="Enter the full contract text..."></div>
                            <input type="hidden" id="contractContent" name="content" value="">
                        </div>
                    </div>
                    <div class="form-section">
                        <div class="section-header-inline">
                            <h3 class="form-section-title">Signers</h3>
                            <button type="button" class="btn-secondary" id="addSignerBtn">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                                </svg>
                                Add Signer
                            </button>
                        </div>
                        <div id="signersList" class="signers-list"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer contract-modal-footer">
                <button type="button" class="btn-secondary" id="cancelContractBtn">Cancel</button>
                <button type="button" class="btn-primary" id="saveContractBtn">Save Contract</button>
            </div>
        </div>
    </div>

    {{-- View Modal --}}
    <div class="modal-overlay" id="viewContractModal" style="display: none;">
        <div class="modal-container modal-lg">
            <div class="modal-header">
                <h3 class="modal-title">Contract <span id="viewContractNumber"></span></h3>
                <button type="button" class="modal-close-inline" id="closeViewModal" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="view-contract-status" id="viewContractStatus"></div>
                <div class="view-invoice-grid">
                    <div class="view-invoice-row">
                        <span class="view-label">Title</span>
                        <span class="view-value" id="viewContractTitle"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Lead</span>
                        <span class="view-value" id="viewContractClient"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Effective Date</span>
                        <span class="view-value" id="viewContractEffective"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Expiry Date</span>
                        <span class="view-value" id="viewContractExpiry"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Signatures</span>
                        <span class="view-value" id="viewContractProgress"></span>
                    </div>
                    <div class="view-invoice-row">
                        <span class="view-label">Created By</span>
                        <span class="view-value" id="viewContractCreator"></span>
                    </div>
                </div>
                <div class="view-invoice-section">
                    <h4 class="view-section-title">Contract Content</h4>
                    <div class="view-contract-content" id="viewContractContent"></div>
                </div>
                <div class="view-invoice-section">
                    <h4 class="view-section-title">Signers</h4>
                    <div class="view-signers-table-wrap">
                        <table class="data-table view-signers-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Signed At</th>
                                </tr>
                            </thead>
                            <tbody id="viewContractSigners"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer view-invoice-footer">
                <div class="view-invoice-footer-left">
                    <button type="button" class="btn-secondary btn-danger" id="viewDeleteBtn" style="display: none;">Delete</button>
                    <button type="button" class="btn-secondary" id="viewCancelBtn" style="display: none;">Cancel Contract</button>
                </div>
                <div class="view-invoice-footer-right">
                    <button type="button" class="btn-secondary" id="viewHistoryBtn">View History</button>
                    <button type="button" class="btn-secondary" onclick="downloadContractPdf()">Download PDF</button>
                    <button type="button" class="btn-secondary" id="viewSendBtn" style="display: none;">Send for Signature</button>
                    <button type="button" class="btn-secondary" id="viewEditBtn" style="display: none;">Edit</button>
                    <button type="button" class="btn-primary" id="closeViewModalBtn">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Status History Modal --}}
    <div class="quotation-modal" id="contractHistoryModal">
        <div class="quotation-modal-content" style="max-width: 640px;">
            <button type="button" class="modal-close" id="closeContractHistoryModal" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">Contract History</h2>
            </div>
            <div class="modal-body" id="contractHistoryBody" style="max-height: 520px; overflow-y: auto;">
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">Loading history...</div>
            </div>
        </div>
    </div>
@endsection
