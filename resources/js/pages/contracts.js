/* Vite page entry — IIFE preserves onclick globals */
(function () {
const CFG = window.__contractsConfig || {};
    const permissions = CFG.permissions || [];
    const canCreate = permissions.includes('create_contracts');
    const canSend = permissions.includes('send_contracts');
    const canDelete = permissions.includes('delete_contracts');
    const CONTRACT_API = CFG.contractApi || '/api/contracts';
    const CONTRACT_HISTORY_API = (id) => (CFG.statusHistoryUrlTemplate || '/api/contracts/:id/status-history').replace(':id', id);

    let leads = [];
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
                <td>${escapeHtml(c.lead)}</td>
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
                        ${canCreate && c.status === 'draft' && c.content_type !== 'storage_quote' ? `
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
                    <div class="card-detail"><span class="card-label">Lead</span><span class="card-value">${escapeHtml(c.lead)}</span></div>
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

    async function loadLeads() {
        const data = await api(CONTRACT_API + '/leads');
        if (data.success) leads = data.data;
        document.getElementById('leadId').innerHTML = '<option value="">Select lead...</option>' +
            leads.map(l => `<option value="${l.id}">${escapeHtml(l.name)}</option>`).join('');
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
        document.getElementById('leadId').value = c.lead_id;
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
            lead_id: document.getElementById('leadId').value,
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
        document.getElementById('viewContractClient').textContent = c.lead?.name || '-';
        document.getElementById('viewContractEffective').textContent = c.effective_date || '-';
        document.getElementById('viewContractExpiry').textContent = c.expiry_date || '-';
        const signed = c.signers.filter(s => s.status === 'signed').length;
        document.getElementById('viewContractProgress').textContent = `${signed}/${c.signers.length} signed`;
        document.getElementById('viewContractCreator').textContent = c.created_by || '-';
        document.getElementById('viewContractContent').innerHTML = c.content_type === 'storage_quote'
            ? (c.rendered_content || '<p style="color:var(--text-muted)">No content</p>')
            : (c.content || '<p style="color:var(--text-muted)">No content</p>');
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
        document.getElementById('viewEditBtn').style.display = canCreate && c.status === 'draft' && c.content_type !== 'storage_quote' ? 'inline-flex' : 'none';
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

    document.getElementById('leadId').addEventListener('change', function() {
        const lead = leads.find(l => l.id == this.value);
        if (!lead || document.getElementById('contractId').value) return;
        const list = document.getElementById('signersList');
        if (list.children.length === 1 && !list.querySelector('.signer-name').value) {
            list.innerHTML = '';
            addSignerRow({ name: lead.name, email: lead.email || '', role: 'client', signing_order: 1 });
        }
    });

    loadLeads();
    loadStats();
    loadContracts();

    const openContractId = new URLSearchParams(window.location.search).get('open');
    if (openContractId) {
        openViewContractModal(parseInt(openContractId, 10));
    }

    if (typeof deleteContract === 'function') window.deleteContract = deleteContract;
    if (typeof downloadContractPdf === 'function') window.downloadContractPdf = downloadContractPdf;
    if (typeof editContract === 'function') window.editContract = editContract;
    if (typeof loadContracts === 'function') window.loadContracts = loadContracts;
    if (typeof openViewContractModal === 'function') window.openViewContractModal = openViewContractModal;
    if (typeof remove === 'function') window.remove = remove;
    if (typeof sendContract === 'function') window.sendContract = sendContract;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
    if (typeof viewContractHistory === 'function') window.viewContractHistory = viewContractHistory;
})();
