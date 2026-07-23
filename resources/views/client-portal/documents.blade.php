@extends('layouts.client-portal')

@section('title', 'Documents')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Signed Documents</h1>
        <p class="page-subtitle">Download your fully executed contracts</p>
    </div>

    <div class="documents-container">
        <div class="documents-list" id="documentsList">
            <div class="loading-state">
                <div class="spinner"></div>
                <span>Loading documents...</span>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .documents-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .documents-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .document-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .document-icon {
        width: 48px;
        height: 48px;
        background: #d1fae5;
        color: #059669;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .document-icon svg {
        width: 22px;
        height: 22px;
    }

    .document-info {
        flex: 1;
        min-width: 0;
    }

    .document-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .document-meta {
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .document-right {
        flex-shrink: 0;
    }

    .btn-download {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1rem;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        transition: opacity 0.15s;
    }

    .btn-download:hover {
        opacity: 0.9;
        color: #fff;
    }

    .btn-download svg {
        width: 18px;
        height: 18px;
    }

    .loading-state,
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        padding: 3rem 1.5rem;
        text-align: center;
        color: var(--text-muted);
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .empty-state svg {
        width: 48px;
        height: 48px;
        opacity: 0.5;
    }

    .spinner {
        width: 32px;
        height: 32px;
        border: 3px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 640px) {
        .document-card {
            flex-wrap: wrap;
        }

        .document-right {
            width: 100%;
        }

        .btn-download {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    const contractsApiUrl = @json(route('client.portal.api.contracts'));

    document.addEventListener('DOMContentLoaded', loadDocuments);

    async function loadDocuments() {
        const list = document.getElementById('documentsList');

        try {
            const response = await fetch(contractsApiUrl);
            const result = await response.json();

            if (!result.success) {
                list.innerHTML = '<div class="empty-state"><p>Unable to load documents. Please try again.</p></div>';
                return;
            }

            renderDocuments(result.contracts || []);
        } catch (error) {
            console.error('Documents error', error);
            list.innerHTML = '<div class="empty-state"><p>Error loading documents. Please try again.</p></div>';
        }
    }

    function renderDocuments(documents) {
        const list = document.getElementById('documentsList');

        if (!documents.length) {
            list.innerHTML = `
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <p>No signed documents yet.</p>
                </div>`;
            return;
        }

        list.innerHTML = documents.map(doc => `
            <div class="document-card">
                <div class="document-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <polyline points="9 15 11 17 15 13"/>
                    </svg>
                </div>
                <div class="document-info">
                    <div class="document-title">${escapeHtml(doc.title || doc.contract_number)}</div>
                    <div class="document-meta">
                        ${escapeHtml(doc.contract_number)}
                        ${doc.signed_at ? ` · Signed ${escapeHtml(doc.signed_at)}` : ''}
                        ${doc.effective_date ? ` · Effective ${escapeHtml(doc.effective_date)}` : ''}
                    </div>
                </div>
                <div class="document-right">
                    <a href="${doc.pdf_url}" class="btn-download" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Download PDF
                    </a>
                </div>
            </div>
        `).join('');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }
</script>
@endpush
