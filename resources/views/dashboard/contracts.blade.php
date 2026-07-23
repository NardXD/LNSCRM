@extends('layouts.app')

@section('title', 'Contracts & E-Sign')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Contracts & E-Sign</h1>
        <p class="page-subtitle">Create contracts, send them to clients, and collect electronic signatures</p>
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
                            <th>Client</th>
                            <th>Status</th>
                            <th>Signatures</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contractsTableBody">
                        <tr><td colspan="7" class="empty-cell">Loading contracts...</td></tr>
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
                                <label class="form-label">Client *</label>
                                <select class="form-input" id="clientId" required></select>
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
                        <span class="view-label">Client</span>
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

@push('styles')
<style>
    .quotation-container { display: flex; flex-direction: column; gap: 1.5rem; }
    .quotation-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; }
    .header-left, .header-right { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
    .search-box { position: relative; min-width: 250px; }
    .search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); width: 18px; height: 18px; pointer-events: none; }
    .search-input { width: 100%; padding: 0.625rem 0.75rem 0.625rem 2.5rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--bg-card); color: var(--text-primary); transition: all 0.15s; }
    .search-input:focus, .filter-select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1); }
    .filter-select { padding: 0.625rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--bg-card); color: var(--text-primary); cursor: pointer; }
    .btn-primary, .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 8px; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all 0.15s; border: none; }
    .btn-primary { background: var(--accent); color: white; }
    .btn-primary:hover { background: var(--accent-hover); }
    .btn-secondary { background: var(--bg-primary); color: var(--text-primary); border: 1px solid var(--border); }
    .btn-secondary:hover { background: var(--border); }
    .btn-danger { color: #dc2626; border-color: #fecaca; }
    .btn-danger:hover { background: #fee2e2; border-color: #fca5a5; }
    .btn-primary svg, .btn-secondary svg { width: 18px; height: 18px; }
    .quotation-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; }
    .stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; }
    .stat-label { font-size: 0.875rem; color: var(--text-secondary); }
    .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .stat-icon.blue { background: #dbeafe; color: #2563eb; }
    .stat-icon.orange { background: #fed7aa; color: #ea580c; }
    .stat-icon.green { background: #d1fae5; color: #059669; }
    .stat-icon.purple { background: #ede9fe; color: #7c3aed; }
    .stat-icon svg { width: 20px; height: 20px; }
    .stat-value { font-size: 1.875rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
    .stat-change { font-size: 0.8125rem; color: var(--text-secondary); }
    .stat-change.positive { color: #059669; }
    .quotations-section { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; }
    .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 1.5rem; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table thead { background: var(--bg-primary); }
    .data-table th { padding: 0.875rem 1rem; text-align: left; font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid var(--border); white-space: nowrap; }
    .data-table td { padding: 1rem; font-size: 0.875rem; color: var(--text-primary); border-bottom: 1px solid var(--border); }
    .data-table tbody tr:hover { background: var(--bg-primary); cursor: pointer; }
    .empty-cell { text-align: center; color: var(--text-muted); padding: 2rem !important; cursor: default !important; }
    .status-badge { padding: 0.25rem 0.75rem; border-radius: 100px; font-size: 0.75rem; font-weight: 500; display: inline-block; }
    .status-badge.draft { background: #e5e7eb; color: #374151; }
    .status-badge.sent { background: #dbeafe; color: #2563eb; }
    .status-badge.partial { background: #fef3c7; color: #d97706; }
    .status-badge.accepted { background: #d1fae5; color: #059669; }
    .status-badge.rejected { background: #fee2e2; color: #dc2626; }
    .status-badge.expired { background: #fef3c7; color: #d97706; }
    .table-actions { display: flex; gap: 0.5rem; }
    .icon-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: none; border: 1px solid var(--border); border-radius: 6px; color: var(--text-secondary); cursor: pointer; transition: all 0.15s; }
    .icon-btn:hover { background: var(--bg-primary); border-color: var(--accent); color: var(--accent); }
    .icon-btn.icon-btn-danger:hover { border-color: #ef4444; color: #ef4444; }
    .icon-btn svg { width: 16px; height: 16px; }
    .contracts-cards { display: none; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem; }
    .contract-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; cursor: pointer; transition: all 0.15s; }
    .contract-card:hover { border-color: var(--accent); }
    .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border); }
    .card-title { font-weight: 600; color: var(--text-primary); }
    .card-details { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .card-detail { display: flex; flex-direction: column; gap: 0.25rem; }
    .card-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .card-value { font-size: 0.875rem; color: var(--text-primary); font-weight: 500; }
    .table-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border); }
    .pagination-info { font-size: 0.875rem; color: var(--text-secondary); }
    .pagination-controls { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .pagination-btn { padding: 0.625rem 1rem; border: 1px solid var(--border); background: var(--bg-card); border-radius: 8px; font-size: 0.875rem; font-weight: 500; color: var(--text-primary); cursor: pointer; }
    .pagination-btn:hover:not(:disabled) { background: var(--bg-primary); border-color: var(--accent); color: var(--accent); }
    .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .pagination-numbers { display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; }
    .pagination-number { min-width: 36px; height: 36px; padding: 0 0.5rem; border: 1px solid var(--border); background: var(--bg-card); border-radius: 8px; font-size: 0.875rem; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .pagination-number.active { background: var(--accent); border-color: var(--accent); color: white; }
    .quotation-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; opacity: 0; transition: opacity 0.2s; }
    .quotation-modal.active { display: flex; opacity: 1; }
    .quotation-modal-content { background: var(--bg-card); border-radius: 16px; max-width: 900px; width: 100%; max-height: 90vh; display: flex; flex-direction: column; position: relative; overflow: hidden; }
    .quotation-modal .modal-close { position: absolute; top: 1rem; right: 1rem; width: 40px; height: 40px; background: rgba(0,0,0,0.5); border: none; border-radius: 50%; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; }
    .quotation-modal .modal-close svg { width: 20px; height: 20px; }
    .quotation-modal .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
    .modal-title { font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin: 0; }
    .quotation-modal .modal-body { flex: 1; overflow-y: auto; padding: 1.5rem; }
    .contract-modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.75rem; }
    .quotation-form { display: flex; flex-direction: column; gap: 1.5rem; }
    .form-section { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; }
    .form-section-title { font-size: 1rem; font-weight: 600; margin: 0 0 1rem; color: var(--text-primary); }
    .section-header-inline { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
    .section-header-inline .form-section-title { margin: 0; }
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.375rem; }
    .form-label { font-size: 0.875rem; font-weight: 500; color: var(--text-primary); }
    .form-input { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-primary); font-family: inherit; font-size: 0.875rem; }
    .signers-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .signer-row { display: grid; grid-template-columns: 1.2fr 1.2fr 0.8fr 0.5fr auto; gap: 0.5rem; align-items: center; }
    .rich-editor { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: var(--bg-card); }
    .rich-editor-toolbar { display: flex; align-items: center; gap: 0.25rem; padding: 0.5rem; border-bottom: 1px solid var(--border); background: var(--bg-primary); flex-wrap: wrap; }
    .rich-editor-btn { padding: 0.375rem 0.625rem; border: none; border-radius: 6px; background: transparent; color: var(--text-primary); font-size: 0.875rem; cursor: pointer; }
    .rich-editor-btn:hover { background: var(--border); }
    .rich-editor-sep { width: 1px; height: 1.25rem; background: var(--border); margin: 0 0.25rem; }
    .rich-editor-select { padding: 0.35rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.8125rem; background: var(--bg-card); color: var(--text-primary); min-width: 7rem; }
    .rich-editor-content { min-height: 120px; max-height: 240px; overflow-y: auto; padding: 0.75rem; font-size: 0.875rem; line-height: 1.6; outline: none; }
    .rich-editor-content:empty::before { content: attr(data-placeholder); color: var(--text-muted); }
    .rich-editor-content-tall { min-height: 220px; max-height: 360px; }
    .rich-editor-content ul, .rich-editor-content ol { margin: 0.5rem 0; padding-left: 1.5rem; }
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; }
    .modal-container { background: var(--bg-card); border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
    .modal-container.modal-lg { max-width: 820px; }
    .modal-overlay .modal-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .modal-close-inline { width: 36px; height: 36px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .modal-close-inline svg { width: 18px; height: 18px; }
    .modal-overlay .modal-body { padding: 1.5rem; overflow-y: auto; flex: 1; }
    .view-contract-status { margin-bottom: 1rem; }
    .view-invoice-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem 1.5rem; margin-bottom: 1.5rem; padding: 1rem; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; }
    .view-invoice-row { display: flex; flex-direction: column; gap: 0.25rem; }
    .view-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
    .view-value { font-size: 0.9375rem; color: var(--text-primary); font-weight: 500; }
    .view-invoice-section { margin-bottom: 1.5rem; }
    .view-section-title { font-size: 0.9375rem; font-weight: 600; margin: 0 0 0.75rem; color: var(--text-primary); }
    .view-contract-content { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; max-height: 320px; overflow-y: auto; font-size: 0.875rem; line-height: 1.6; }
    .view-contract-content ul, .view-contract-content ol { margin: 0.5rem 0; padding-left: 1.5rem; }
    .view-signers-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 12px; }
    .view-signers-table { margin: 0; }
    .view-signers-table tbody tr:hover { background: transparent; cursor: default; }
    .view-invoice-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .view-invoice-footer-left, .view-invoice-footer-right { display: flex; gap: 0.75rem; flex-wrap: wrap; }
    .status-history-list { display: flex; flex-direction: column; }
    .status-history-item { display: flex; gap: 1rem; padding: 1rem 0; position: relative; }
    .status-history-item:not(:last-child) { border-bottom: 1px solid var(--border); }
    .status-history-item.current .status-history-dot { background: var(--accent); border-color: var(--accent); }
    .status-history-timeline { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
    .status-history-dot { width: 12px; height: 12px; border-radius: 50%; background: var(--bg-primary); border: 2px solid var(--border); z-index: 1; }
    .status-history-line { width: 2px; flex: 1; background: var(--border); margin-top: 0.25rem; min-height: 40px; }
    .status-history-content { flex: 1; display: flex; flex-direction: column; gap: 0.5rem; }
    .status-history-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .status-history-date { font-size: 0.8125rem; color: var(--text-muted); }
    .status-history-change { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
    .status-history-user, .status-history-notes { font-size: 0.875rem; color: var(--text-secondary); }
    .status-history-label { font-weight: 600; color: var(--text-primary); margin-right: 0.35rem; }
    .status-history-notes { padding: 0.5rem; background: var(--bg-primary); border-radius: 6px; border-left: 3px solid var(--accent); }
    .spinner { border: 3px solid var(--border); border-top: 3px solid var(--accent); border-radius: 50%; width: 32px; height: 32px; animation: contract-spin 1s linear infinite; margin: 0 auto 1rem; }
    @keyframes contract-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    @media (max-width: 900px) {
        .contracts-cards { display: flex; }
        .table-container { display: none; }
        .form-grid, .view-invoice-grid { grid-template-columns: 1fr; }
        .signer-row { grid-template-columns: 1fr; }
    }
</style>
@endpush

@push('scripts')
<script>
    const permissions = @json($userPermissions ?? []);
    const canCreate = permissions.includes('create_contracts');
    const canSend = permissions.includes('send_contracts');
    const canDelete = permissions.includes('delete_contracts');
    const CONTRACT_API = "{{ url('/api/contracts') }}";
    const CONTRACT_HISTORY_API = (id) => `{{ route('api.contracts.status-history', ':id') }}`.replace(':id', id);

    let clients = [];
    let contractsData = [];
    let currentPage = 1;
    let totalPages = 1;
    let totalItems = 0;
    let searchTimeout = null;
    window.viewingContractId = null;

    const statusLabels = {
        draft: 'Draft',
        pending_signatures: 'Pending',
        partially_signed: 'Partially Signed',
        signed: 'Signed',
        cancelled: 'Cancelled',
        expired: 'Expired',
    };

    function statusBadgeClass(status) {
        const map = {
            draft: 'draft',
            pending_signatures: 'sent',
            partially_signed: 'partial',
            signed: 'accepted',
            cancelled: 'rejected',
            expired: 'expired',
        };
        return map[status] || 'draft';
    }

    function formatStatusLabel(status) {
        return statusLabels[status] || (status ? status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '');
    }

    function renderHistoryHtml(history) {
        if (!history.length) {
            return '<div style="text-align:center;padding:2rem;color:var(--text-muted);">No history recorded yet.</div>';
        }
        return `
            <div class="status-history-list">
                ${history.map((item, index) => `
                    <div class="status-history-item ${index === 0 ? 'current' : ''}">
                        <div class="status-history-timeline">
                            <div class="status-history-dot"></div>
                            ${index < history.length - 1 ? '<div class="status-history-line"></div>' : ''}
                        </div>
                        <div class="status-history-content">
                            <div class="status-history-header">
                                <span class="status-badge ${statusBadgeClass(item.status)}">${formatStatusLabel(item.status)}</span>
                                <span class="status-history-date">${item.changed_at_formatted}</span>
                            </div>
                            ${item.previous_status ? `
                                <div class="status-history-change">
                                    <span class="status-history-label">Changed from:</span>
                                    <span class="status-badge ${statusBadgeClass(item.previous_status)}">${formatStatusLabel(item.previous_status)}</span>
                                    <span>→</span>
                                    <span class="status-badge ${statusBadgeClass(item.status)}">${formatStatusLabel(item.status)}</span>
                                </div>
                            ` : ''}
                            <div class="status-history-user">
                                <span class="status-history-label">By:</span>
                                <span>${escapeHtml(item.changed_by)}</span>
                            </div>
                            ${item.notes ? `
                                <div class="status-history-notes">
                                    <span class="status-history-label">Notes:</span>
                                    <span>${escapeHtml(item.notes)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    async function api(url, options = {}) {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                ...(options.headers || {}),
            },
            ...options,
        });
        return res.json();
    }

    async function loadStats() {
        const data = await api(CONTRACT_API + '/stats');
        if (data.success) {
            document.getElementById('statTotal').textContent = data.data.total;
            document.getElementById('statPending').textContent = data.data.pending;
            document.getElementById('statSigned').textContent = data.data.signed;
            document.getElementById('statDraft').textContent = data.data.draft;
        }
    }

    async function loadContracts(page = 1) {
        currentPage = page;
        const search = document.getElementById('contractSearch').value;
        const status = document.getElementById('statusFilter').value;
        const params = new URLSearchParams({ page, per_page: 10 });
        if (search) params.set('search', search);
        if (status !== 'all') params.set('status', status);

        const data = await api(`${CONTRACT_API}?${params}`);
        if (!data.success) return;

        contractsData = data.data;
        totalPages = data.pagination.last_page;
        totalItems = data.pagination.total;
        renderTable();
        renderCards();
        renderPagination();
    }

    function renderTable() {
        const tbody = document.getElementById('contractsTableBody');
        if (!contractsData.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty-cell">No contracts found.</td></tr>';
            return;
        }

        tbody.innerHTML = contractsData.map(c => `
            <tr onclick="openViewContractModal(${c.id})">
                <td><strong>${escapeHtml(c.contract_number)}</strong></td>
                <td>${escapeHtml(c.title)}</td>
                <td>${escapeHtml(c.client)}</td>
                <td><span class="status-badge ${statusBadgeClass(c.status)}">${statusLabels[c.status] || c.status}</span></td>
                <td>${c.signers_progress}</td>
                <td>${c.created_at}</td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="openViewContractModal(${c.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="icon-btn" title="Download PDF" onclick="downloadContractPdf(${c.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </button>
                        <button class="icon-btn" title="History" onclick="event.stopPropagation(); viewContractHistory(${c.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </button>
                        ${canCreate && c.status === 'draft' ? `
                        <button class="icon-btn" title="Edit" onclick="editContract(${c.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>` : ''}
                        ${canSend && ['draft','pending_signatures','partially_signed'].includes(c.status) ? `
                        <button class="icon-btn" title="Send for Signature" onclick="sendContract(${c.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </button>` : ''}
                        ${canDelete && ['draft','cancelled'].includes(c.status) ? `
                        <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteContract(${c.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function renderCards() {
        const container = document.getElementById('contractsCards');
        if (!contractsData.length) {
            container.innerHTML = '<div class="empty-cell">No contracts found.</div>';
            return;
        }
        container.innerHTML = contractsData.map(c => `
            <div class="contract-card" onclick="openViewContractModal(${c.id})">
                <div class="card-header">
                    <div>
                        <div class="card-title">${escapeHtml(c.contract_number)}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">${escapeHtml(c.title)}</div>
                    </div>
                    <span class="status-badge ${statusBadgeClass(c.status)}">${statusLabels[c.status] || c.status}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail"><span class="card-label">Client</span><span class="card-value">${escapeHtml(c.client)}</span></div>
                    <div class="card-detail"><span class="card-label">Signatures</span><span class="card-value">${c.signers_progress}</span></div>
                    <div class="card-detail"><span class="card-label">Created</span><span class="card-value">${c.created_at}</span></div>
                </div>
            </div>
        `).join('');
    }

    function renderPagination() {
        const info = document.getElementById('paginationInfo');
        const numbers = document.getElementById('paginationNumbers');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const perPage = 10;
        const start = totalItems ? (currentPage - 1) * perPage + 1 : 0;
        const end = Math.min(currentPage * perPage, totalItems);
        info.textContent = totalItems ? `Showing ${start} to ${end} of ${totalItems} results` : 'Showing 0 results';
        prevBtn.disabled = currentPage <= 1;
        nextBtn.disabled = currentPage >= totalPages;

        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            html += `<button class="pagination-number ${i === currentPage ? 'active' : ''}" onclick="loadContracts(${i})">${i}</button>`;
        }
        numbers.innerHTML = html;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').trim();
    }

    function syncContractContentEditor() {
        const editor = document.getElementById('contractContentEditor');
        const hidden = document.getElementById('contractContent');
        if (editor && hidden) hidden.value = editor.innerHTML;
    }

    function setContractContentEditor(html) {
        const editor = document.getElementById('contractContentEditor');
        const hidden = document.getElementById('contractContent');
        const value = html || '';
        if (editor) editor.innerHTML = value;
        if (hidden) hidden.value = value;
    }

    function clearContractContentEditor() { setContractContentEditor(''); }

    document.getElementById('contractContentEditor')?.addEventListener('input', syncContractContentEditor);
    document.getElementById('contractContentEditor')?.addEventListener('paste', () => setTimeout(syncContractContentEditor, 0));
    document.querySelector('.rich-editor-toolbar[data-editor="contractContentEditor"]')?.addEventListener('click', function(e) {
        const btn = e.target.closest('.rich-editor-btn');
        if (!btn) return;
        e.preventDefault();
        const editor = document.getElementById('contractContentEditor');
        if (!editor) return;
        editor.focus();
        const cmd = btn.dataset.cmd;
        const value = btn.dataset.value;
        if (cmd === 'createLink') {
            const url = prompt('Enter URL:', 'https://');
            if (url) { document.execCommand('createLink', false, url); syncContractContentEditor(); }
        } else if (cmd === 'formatBlock' && value) {
            document.execCommand('formatBlock', false, value);
            syncContractContentEditor();
        } else {
            document.execCommand(cmd, false, null);
            syncContractContentEditor();
        }
    });
    document.querySelector('.rich-editor-select[data-editor="contractContentEditor"]')?.addEventListener('change', function() {
        const editor = document.getElementById('contractContentEditor');
        if (!editor) return;
        editor.focus();
        document.execCommand('formatBlock', false, this.value);
        syncContractContentEditor();
    });

    async function loadClients() {
        const data = await api(CONTRACT_API + '/clients');
        if (data.success) clients = data.data;
        document.getElementById('clientId').innerHTML = '<option value="">Select client...</option>' +
            clients.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
    }

    function addSignerRow(signer = {}) {
        const row = document.createElement('div');
        row.className = 'signer-row';
        row.innerHTML = `
            <input type="text" class="form-input signer-name" placeholder="Name" value="${escapeHtml(signer.name || '')}" required>
            <input type="email" class="form-input signer-email" placeholder="Email" value="${escapeHtml(signer.email || '')}" required>
            <select class="form-input signer-role">
                <option value="client" ${signer.role === 'client' ? 'selected' : ''}>Client</option>
                <option value="company" ${signer.role === 'company' ? 'selected' : ''}>Company</option>
                <option value="witness" ${signer.role === 'witness' ? 'selected' : ''}>Witness</option>
            </select>
            <input type="number" class="form-input signer-order" min="1" value="${signer.signing_order || 1}" title="Order">
            <button type="button" class="icon-btn icon-btn-danger" onclick="this.parentElement.remove()" title="Remove">&times;</button>
        `;
        document.getElementById('signersList').appendChild(row);
    }

    function getSignersFromForm() {
        return [...document.querySelectorAll('.signer-row')].map((row, i) => ({
            name: row.querySelector('.signer-name').value.trim(),
            email: row.querySelector('.signer-email').value.trim(),
            role: row.querySelector('.signer-role').value,
            signing_order: parseInt(row.querySelector('.signer-order').value) || (i + 1),
        }));
    }

    function openContractModal() {
        document.getElementById('contractModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeContractModal() {
        document.getElementById('contractModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    async function openNewContract() {
        document.getElementById('contractModalTitle').textContent = 'New Contract';
        document.getElementById('contractId').value = '';
        document.getElementById('contractForm').reset();
        document.getElementById('signersList').innerHTML = '';
        clearContractContentEditor();
        const numData = await api(CONTRACT_API + '/next-number');
        if (numData.success) document.getElementById('contractNumber').value = numData.data.contract_number;
        addSignerRow();
        openContractModal();
    }

    async function editContract(id) {
        const data = await api(`${CONTRACT_API}/${id}`);
        if (!data.success) return alert(data.message);
        const c = data.data;
        document.getElementById('contractModalTitle').textContent = 'Edit Contract';
        document.getElementById('contractId').value = c.id;
        document.getElementById('clientId').value = c.client_id;
        document.getElementById('contractNumber').value = c.contract_number;
        document.getElementById('contractTitle').value = c.title;
        document.getElementById('effectiveDate').value = c.effective_date || '';
        document.getElementById('expiryDate').value = c.expiry_date || '';
        setContractContentEditor(c.content || '');
        document.getElementById('signersList').innerHTML = '';
        c.signers.forEach(s => addSignerRow(s));
        closeViewContractModal();
        openContractModal();
    }

    async function saveContract() {
        const id = document.getElementById('contractId').value;
        const signers = getSignersFromForm();
        if (!signers.length) return alert('Add at least one signer.');
        syncContractContentEditor();
        const content = document.getElementById('contractContent').value;
        if (!stripHtml(content)) return alert('Contract content is required.');

        const payload = {
            client_id: document.getElementById('clientId').value,
            title: document.getElementById('contractTitle').value,
            content,
            effective_date: document.getElementById('effectiveDate').value || null,
            expiry_date: document.getElementById('expiryDate').value || null,
            signers,
        };

        const data = await api(id ? `${CONTRACT_API}/${id}` : CONTRACT_API, {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });

        if (data.success) {
            closeContractModal();
            loadContracts(currentPage);
            loadStats();
        } else {
            alert(data.message || 'Failed to save contract.');
        }
    }

    async function openViewContractModal(id) {
        const data = await api(`${CONTRACT_API}/${id}`);
        if (!data.success) return alert(data.message);
        const c = data.data;
        window.viewingContractId = c.id;

        document.getElementById('viewContractNumber').textContent = c.contract_number;
        document.getElementById('viewContractStatus').innerHTML = `<span class="status-badge ${statusBadgeClass(c.status)}">${statusLabels[c.status] || c.status}</span>`;
        document.getElementById('viewContractTitle').textContent = c.title || '-';
        document.getElementById('viewContractClient').textContent = c.client?.name || '-';
        document.getElementById('viewContractEffective').textContent = c.effective_date || '-';
        document.getElementById('viewContractExpiry').textContent = c.expiry_date || '-';
        const signed = c.signers.filter(s => s.status === 'signed').length;
        document.getElementById('viewContractProgress').textContent = `${signed}/${c.signers.length} signed`;
        document.getElementById('viewContractCreator').textContent = c.created_by || '-';
        document.getElementById('viewContractContent').innerHTML = c.content || '<p style="color:var(--text-muted)">No content</p>';
        document.getElementById('viewContractSigners').innerHTML = c.signers.map(s => `
            <tr>
                <td>${escapeHtml(s.name)}</td>
                <td>${escapeHtml(s.email)}</td>
                <td>${escapeHtml(s.role)}</td>
                <td><span class="status-badge ${s.status === 'signed' ? 'accepted' : 'sent'}">${s.status}</span></td>
                <td>${s.signed_at ? new Date(s.signed_at).toLocaleString() : '-'}</td>
            </tr>
        `).join('');

        document.getElementById('viewSendBtn').style.display = canSend && ['draft','pending_signatures','partially_signed'].includes(c.status) ? 'inline-flex' : 'none';
        document.getElementById('viewEditBtn').style.display = canCreate && c.status === 'draft' ? 'inline-flex' : 'none';
        document.getElementById('viewDeleteBtn').style.display = canDelete && ['draft','cancelled'].includes(c.status) ? 'inline-flex' : 'none';
        document.getElementById('viewCancelBtn').style.display = c.status !== 'signed' && c.status !== 'cancelled' ? 'inline-flex' : 'none';

        document.getElementById('viewContractModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeViewContractModal() {
        document.getElementById('viewContractModal').style.display = 'none';
        document.body.style.overflow = '';
        window.viewingContractId = null;
    }

    function downloadContractPdf(contractId) {
        const id = contractId ?? window.viewingContractId;
        if (!id) return alert('No contract selected.');
        const c = contractsData.find(x => x.id === id);
        const filename = (c?.contract_number || 'contract') + '.pdf';
        const a = document.createElement('a');
        a.href = `${CONTRACT_API}/${id}/pdf`;
        a.download = filename;
        a.rel = 'noopener noreferrer';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function openContractHistoryModal() {
        document.getElementById('contractHistoryModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeContractHistoryModal() {
        document.getElementById('contractHistoryModal').classList.remove('active');
        if (document.getElementById('viewContractModal').style.display !== 'flex') {
            document.body.style.overflow = '';
        }
    }

    async function viewContractHistory(id) {
        const contractId = id ?? window.viewingContractId;
        if (!contractId) return;

        openContractHistoryModal();
        const body = document.getElementById('contractHistoryBody');
        body.innerHTML = '<div style="text-align:center;padding:2rem;"><div class="spinner"></div><p>Loading history...</p></div>';

        try {
            const response = await fetch(CONTRACT_HISTORY_API(contractId), {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });
            const result = await response.json();
            if (result.success && result.data) {
                body.innerHTML = renderHistoryHtml(result.data);
            } else {
                body.innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626;">Failed to load history.</div>';
            }
        } catch (e) {
            body.innerHTML = '<div style="text-align:center;padding:2rem;color:#dc2626;">Failed to load history.</div>';
        }
    }

    async function sendContract(id) {
        const contractId = id ?? window.viewingContractId;
        if (!contractId) return;
        if (!confirm('Send this contract to all pending signers via email?')) return;
        const data = await api(`${CONTRACT_API}/${contractId}/send`, { method: 'POST' });
        alert(data.message);
        if (data.success) {
            closeViewContractModal();
            loadContracts(currentPage);
            loadStats();
        }
    }

    async function cancelContract(id) {
        const contractId = id ?? window.viewingContractId;
        if (!contractId || !confirm('Cancel this contract?')) return;
        const data = await api(`${CONTRACT_API}/${contractId}/cancel`, { method: 'POST' });
        if (data.success) {
            closeViewContractModal();
            loadContracts(currentPage);
            loadStats();
        } else alert(data.message);
    }

    async function deleteContract(id) {
        const contractId = id ?? window.viewingContractId;
        if (!contractId || !confirm('Delete this contract permanently?')) return;
        const data = await api(`${CONTRACT_API}/${contractId}`, { method: 'DELETE' });
        if (data.success) {
            closeViewContractModal();
            loadContracts(currentPage);
            loadStats();
        } else alert(data.message);
    }

    document.getElementById('newContractBtn')?.addEventListener('click', openNewContract);
    document.getElementById('addSignerBtn').addEventListener('click', () => addSignerRow());
    document.getElementById('saveContractBtn').addEventListener('click', saveContract);
    document.getElementById('closeContractModal').addEventListener('click', closeContractModal);
    document.getElementById('cancelContractBtn').addEventListener('click', closeContractModal);
    document.getElementById('closeViewModal').addEventListener('click', closeViewContractModal);
    document.getElementById('closeViewModalBtn').addEventListener('click', closeViewContractModal);
    document.getElementById('viewSendBtn').addEventListener('click', () => sendContract());
    document.getElementById('viewEditBtn').addEventListener('click', () => editContract(window.viewingContractId));
    document.getElementById('viewDeleteBtn').addEventListener('click', () => deleteContract());
    document.getElementById('viewCancelBtn').addEventListener('click', () => cancelContract());
    document.getElementById('viewHistoryBtn').addEventListener('click', () => viewContractHistory());
    document.getElementById('closeContractHistoryModal').addEventListener('click', closeContractHistoryModal);
    document.getElementById('contractHistoryModal').addEventListener('click', e => { if (e.target.id === 'contractHistoryModal') closeContractHistoryModal(); });
    document.getElementById('contractModal').addEventListener('click', e => { if (e.target.id === 'contractModal') closeContractModal(); });
    document.getElementById('viewContractModal').addEventListener('click', e => { if (e.target.id === 'viewContractModal') closeViewContractModal(); });
    document.getElementById('contractSearch').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadContracts(1), 300);
    });
    document.getElementById('statusFilter').addEventListener('change', () => loadContracts(1));
    document.getElementById('prevBtn').addEventListener('click', () => { if (currentPage > 1) loadContracts(currentPage - 1); });
    document.getElementById('nextBtn').addEventListener('click', () => { if (currentPage < totalPages) loadContracts(currentPage + 1); });

    document.getElementById('clientId').addEventListener('change', function() {
        const client = clients.find(c => c.id == this.value);
        if (!client || document.getElementById('contractId').value) return;
        const list = document.getElementById('signersList');
        if (list.children.length === 1 && !list.querySelector('.signer-name').value) {
            list.innerHTML = '';
            addSignerRow({ name: client.contact_person || client.name, email: client.email || '', role: 'client', signing_order: 1 });
            client.contacts?.forEach((contact, i) => addSignerRow({ name: contact.name, email: contact.email, role: 'client', signing_order: i + 2 }));
        }
    });

    loadClients();
    loadStats();
    loadContracts();
</script>
@endpush
