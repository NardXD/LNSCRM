@extends('layouts.app')

@section('title', 'Quotation Item Templates')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Quotation Item Templates</h1>
        <p class="page-subtitle">Manage reusable items and descriptions for quotations</p>
    </div>

    <div class="template-container">
        <!-- Header Actions -->
        <div class="template-header">
            <div class="header-left">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" class="search-input" placeholder="Search templates..." id="templateSearch">
                </div>
                <select class="filter-select" id="activeFilter">
                    <option value="all">All Status</option>
                    <option value="true">Active</option>
                    <option value="false">Inactive</option>
                </select>
            </div>
            <div class="header-right">
                <button class="btn-primary" onclick="createTemplate()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Template
                </button>
            </div>
        </div>

        <!-- Templates Table -->
        <div class="templates-section">
            <div class="table-container">
                <table class="data-table" id="templatesTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Default Quantity</th>
                            <th>Default Unit Price</th>
                            <th>Default Tax %</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="templatesTableBody">
                        <!-- Data will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Template Modal -->
    <div class="template-modal" id="templateModal">
        <div class="template-modal-content">
            <button class="modal-close" onclick="closeTemplateModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <h2 id="modalTitle">New Item Template</h2>
            </div>

            <form id="templateForm" onsubmit="saveTemplate(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modalItemName">Item Name <span class="required">*</span></label>
                        <input type="text" id="modalItemName" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="modalDescription">Description</label>
                        <textarea id="modalDescription" class="form-input" rows="3"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="modalDefaultQuantity">Default Quantity</label>
                            <input type="number" id="modalDefaultQuantity" class="form-input" min="0.01" step="0.01" value="1">
                        </div>

                        <div class="form-group">
                            <label for="modalDefaultUnitPrice">Default Unit Price</label>
                            <input type="number" id="modalDefaultUnitPrice" class="form-input" min="0" step="0.01" value="0">
                        </div>

                        <div class="form-group">
                            <label for="modalDefaultTaxPercentage">Default Tax %</label>
                            <input type="number" id="modalDefaultTaxPercentage" class="form-input" min="0" max="100" step="0.1" value="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="modalSortOrder">Sort Order</label>
                        <input type="number" id="modalSortOrder" class="form-input" min="0" value="0">
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="modalIsActive" checked>
                            <span>Active</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeTemplateModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save Template</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
<style>
.template-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Header */
.template-header {
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
.btn-primary, .btn-secondary {
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
    -webkit-tap-highlight-color: transparent;
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
    background: var(--border);
}

.btn-primary svg, .btn-secondary svg {
    width: 18px;
    height: 18px;
}

/* Templates Section */
.templates-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.5rem;
}

.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
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
}

/* Template Modal */
.template-modal {
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

.template-modal.active {
    display: flex;
    opacity: 1;
}

.template-modal-content {
    background: var(--bg-card);
    border-radius: 16px;
    max-width: 600px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    position: relative;
    transform: scale(0.95);
    transition: transform 0.2s;
    overflow: hidden;
}

.template-modal.active .template-modal-content {
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
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
}

.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 0.875rem;
}

.required {
    color: #ef4444;
}

.form-input {
    padding: 0.625rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.875rem;
    background: var(--bg-card);
    color: var(--text-primary);
    transition: all 0.15s;
    font-family: inherit;
    width: 100%;
}

.form-input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent);
}

/* Status Badge */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 100px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
    text-transform: capitalize;
}

.status-badge.active {
    background: #d1fae5;
    color: #059669;
}

.status-badge.inactive {
    background: #fee2e2;
    color: #dc2626;
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

/* Responsive */
@media (max-width: 768px) {
    .template-header {
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

    .template-modal-content {
        max-width: 100%;
        max-height: 100vh;
        border-radius: 0;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .modal-footer {
        flex-direction: column;
    }

    .modal-footer .btn-primary,
    .modal-footer .btn-secondary {
        width: 100%;
        justify-content: center;
    }
}
</style>
@endpush

@push('scripts')
<script>
const API_ROUTES = {
    templates: '{{ route("api.quotation-item-templates.templates") }}',
    store: '{{ route("api.quotation-item-templates.templates.store") }}',
    update: (id) => `{{ route("api.quotation-item-templates.templates.update", ":id") }}`.replace(':id', id),
    destroy: (id) => `{{ route("api.quotation-item-templates.templates.destroy", ":id") }}`.replace(':id', id),
};

let templatesData = [];
let editingTemplateId = null;
let searchTimeout;

// Load Templates
async function loadTemplates() {
    try {
        const search = document.getElementById('templateSearch').value;
        const isActive = document.getElementById('activeFilter').value;
        
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (isActive !== 'all') params.append('is_active', isActive);

        const response = await fetch(`${API_ROUTES.templates}?${params}`);
        const result = await response.json();

        if (result.success) {
            templatesData = result.data;
            renderTemplates();
        }
    } catch (error) {
        console.error('Error loading templates:', error);
        alert('Error loading templates. Please try again.');
    }
}

// Render Templates
function renderTemplates() {
    const tbody = document.getElementById('templatesTableBody');
    
    if (templatesData.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    No templates found. Create your first template to get started.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = templatesData.map(template => `
        <tr>
            <td><strong>${template.item_name}</strong></td>
            <td>${template.description || '-'}</td>
            <td>${template.default_quantity || 0}</td>
            <td>$${template.default_unit_price.toFixed(2)}</td>
            <td>${template.default_tax_percentage}%</td>
            <td>
                <span class="status-badge ${template.is_active ? 'active' : 'inactive'}">
                    ${template.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>
                <div class="table-actions">
                    <button class="icon-btn" title="Edit" onclick="editTemplate(${template.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="icon-btn" title="Delete" onclick="deleteTemplate(${template.id})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Create Template
function createTemplate() {
    editingTemplateId = null;
    document.getElementById('modalTitle').textContent = 'New Item Template';
    document.getElementById('templateForm').reset();
    document.getElementById('modalDefaultQuantity').value = 1;
    document.getElementById('modalDefaultUnitPrice').value = 0;
    document.getElementById('modalDefaultTaxPercentage').value = 0;
    document.getElementById('modalSortOrder').value = 0;
    document.getElementById('modalIsActive').checked = true;
    document.getElementById('templateModal').classList.add('active');
}

// Edit Template
async function editTemplate(id) {
    try {
        const template = templatesData.find(t => t.id === id);
        if (!template) return;

        editingTemplateId = id;
        document.getElementById('modalTitle').textContent = 'Edit Item Template';
        document.getElementById('modalItemName').value = template.item_name;
        document.getElementById('modalDescription').value = template.description || '';
        document.getElementById('modalDefaultQuantity').value = template.default_quantity;
        document.getElementById('modalDefaultUnitPrice').value = template.default_unit_price;
        document.getElementById('modalDefaultTaxPercentage').value = template.default_tax_percentage;
        document.getElementById('modalSortOrder').value = template.sort_order;
        document.getElementById('modalIsActive').checked = template.is_active;
        document.getElementById('templateModal').classList.add('active');
    } catch (error) {
        console.error('Error editing template:', error);
        alert('Error loading template. Please try again.');
    }
}

// Close Modal
function closeTemplateModal() {
    document.getElementById('templateModal').classList.remove('active');
    editingTemplateId = null;
}

// Save Template
async function saveTemplate(event) {
    event.preventDefault();

    const data = {
        item_name: document.getElementById('modalItemName').value,
        description: document.getElementById('modalDescription').value,
        default_quantity: parseFloat(document.getElementById('modalDefaultQuantity').value) || 1,
        default_unit_price: parseFloat(document.getElementById('modalDefaultUnitPrice').value) || 0,
        default_tax_percentage: parseFloat(document.getElementById('modalDefaultTaxPercentage').value) || 0,
        sort_order: parseInt(document.getElementById('modalSortOrder').value) || 0,
        is_active: document.getElementById('modalIsActive').checked,
    };

    try {
        const url = editingTemplateId ? API_ROUTES.update(editingTemplateId) : API_ROUTES.store;
        const method = editingTemplateId ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(data),
        });

        const result = await response.json();

        if (result.success) {
            closeTemplateModal();
            loadTemplates();
        } else {
            alert(result.message || 'Error saving template. Please try again.');
        }
    } catch (error) {
        console.error('Error saving template:', error);
        alert('Error saving template. Please try again.');
    }
}

// Delete Template
async function deleteTemplate(id) {
    if (!confirm('Are you sure you want to delete this template?')) {
        return;
    }

    try {
        const response = await fetch(API_ROUTES.destroy(id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const result = await response.json();

        if (result.success) {
            loadTemplates();
        } else {
            alert(result.message || 'Error deleting template. Please try again.');
        }
    } catch (error) {
        console.error('Error deleting template:', error);
        alert('Error deleting template. Please try again.');
    }
}

// Event Listeners
document.getElementById('templateSearch')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadTemplates();
    }, 500);
});

document.getElementById('activeFilter')?.addEventListener('change', function() {
    loadTemplates();
});

// Close modal on outside click
document.getElementById('templateModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeTemplateModal();
    }
});

// Load templates on page load
loadTemplates();
</script>
@endpush

