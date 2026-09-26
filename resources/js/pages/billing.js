/* Vite page entry — IIFE preserves onclick globals */
(function () {
    // Tab Switching
    function kebabToCamel(str) {
        return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            const camelTabId = kebabToCamel(tabId);
            
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const tabContent = document.getElementById(camelTabId + 'Tab');
            if (tabContent) {
                tabContent.classList.add('active');
            }
            if (tabId === 'payment-tracking') {
                loadPaymentTracking();
            }
        });
    });

    const INVOICE_API = window.__billingConfig?.invoiceApi || '/api/billing-invoices';
    const SUBSCRIPTIONS_API = INVOICE_API + '/subscriptions';
    const PAYMENT_TRACKING_URL = window.__billingConfig?.paymentTrackingUrl || '/api/billing-invoices/payment-tracking';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const BILLING_CLIENTS = [];
    const WISE_DEFAULT_LINK_URL = INVOICE_API + '/wise-default-link';
    const STRIPE_CONNECTED = !!window.__billingConfig?.stripeConnected;
    const userPermissions = window.__billingConfig?.userPermissions || [];
    const canDeleteInvoices = userPermissions.includes('delete_billing');
    let wiseDefaultLink = window.__billingConfig?.wiseDefaultLink || '';

    let invoicesData = [];
    let clientsData = [];
    let selectedInvoiceIds = new Set();
    let invoiceClientEmployees = [];

    function escapeHtmlAttr(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function bindInvoiceLineDescriptionAutocomplete(containerId, inputName) {
        const container = document.getElementById(containerId || 'invoiceLineItems');
        if (!container) {
            return;
        }
        const name = inputName || 'line_desc[]';
        container.querySelectorAll('input[name="' + name + '"]').forEach(function (input) {
            input.setAttribute('list', 'invoiceLineEmployeeOptions');
            input.setAttribute('autocomplete', 'off');
            input.classList.add('invoice-line-desc');
            if (!input.getAttribute('placeholder')) {
                input.setAttribute('placeholder', 'Description (employee name)');
            }
        });
    }

    async function loadInvoiceClientEmployees(clientId) {
        invoiceClientEmployees = [];
        const datalist = document.getElementById('invoiceLineEmployeeOptions');
        if (datalist) {
            datalist.innerHTML = '';
        }

        if (!clientId) {
            return;
        }

        try {
            const params = new URLSearchParams({ employees_for: String(clientId) });
            const r = await fetch(INVOICE_API + '/clients?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!r.ok) {
                throw new Error('HTTP ' + r.status);
            }
            const contentType = r.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Expected JSON response');
            }
            const json = await r.json();
            if (json.success && Array.isArray(json.data)) {
                invoiceClientEmployees = json.data;
                if (datalist) {
                    datalist.innerHTML = json.data.map(function (employee) {
                        return '<option value="' + escapeHtmlAttr(employee.name) + '"></option>';
                    }).join('');
                }
            }
        } catch (e) {
            console.error('Load client employees:', e);
        }
    }

    async function onInvoiceClientChanged(clientId) {
        await loadInvoiceClientEmployees(clientId);
        bindInvoiceLineDescriptionAutocomplete('invoiceLineItems', 'line_desc[]');
    }

    async function onEditInvoiceClientChanged(clientId) {
        await loadInvoiceClientEmployees(clientId);
        bindInvoiceLineDescriptionAutocomplete('editInvoiceLineItems', 'edit_line_desc[]');
    }

    function populateClientSelects(clients) {
        const list = Array.isArray(clients) ? clients : [];
        const opts = list.map(c => `<option value="${c.id}">${(c.name || '').replace(/</g, '&lt;')}</option>`).join('');
        ['invoiceClient', 'editInvoiceClient', 'subscriptionClient'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerHTML = '<option value="">Select client...</option>' + opts;
        });
    }

    async function loadClients() {
        try {
            const r = await fetch(INVOICE_API + '/clients');
            const json = await r.json();
            if (json.success && json.data) {
                clientsData = json.data;
                populateClientSelects(clientsData);
            } else {
                populateClientSelects(BILLING_CLIENTS);
            }
        } catch (e) {
            console.error('Load clients:', e);
            populateClientSelects(BILLING_CLIENTS);
        }
    }

    async function loadInvoices() {
        const status = document.getElementById('invoiceStatusFilter')?.value || 'all';
        const month = document.getElementById('invoiceMonthFilter')?.value || '';
        let url = INVOICE_API + '?status=' + status + '&per_page=50';
        if (month) url += '&month=' + encodeURIComponent(month);
        try {
            const r = await fetch(url);
            const json = await r.json();
            if (json.success && json.data) {
                invoicesData = json.data;
                renderInvoices();
                const p = json.pagination || {};
                const total = p.total || invoicesData.length;
                const perPage = p.per_page || total;
                const start = total > 0 ? 1 : 0;
                const end = total > 0 ? Math.min(perPage, total) : 0;
                document.getElementById('invoicesPaginationInfo').textContent = `Showing ${start} to ${end} of ${total} results`;
            }
        } catch (e) { console.error('Load invoices:', e); }
    }

    async function loadInvoiceStats() {
        const month = document.getElementById('invoiceMonthFilter')?.value || '';
        let url = INVOICE_API + '/stats';
        if (month) url += '?month=' + encodeURIComponent(month);
        try {
            const r = await fetch(url);
            const json = await r.json();
            if (json.success && json.data) {
                const d = json.data;
                document.getElementById('statTotalInvoices').textContent = d.total_invoices ?? 0;
                document.getElementById('statPendingAmount').textContent = '$' + (d.pending_amount ?? 0).toLocaleString();
                document.getElementById('statPaidThisMonth').textContent = '$' + (d.paid_this_month ?? 0).toLocaleString();
                document.getElementById('statOverdueAmount').textContent = '$' + (d.overdue_amount ?? 0).toLocaleString();
            }
        } catch (e) { console.error('Load stats:', e); }
    }

    let paymentsData = [];

    let subscriptionsData = [];
    let subscriptionsStatusFilter = 'all';

    async function loadSubscriptions() {
        try {
            const r = await fetch(SUBSCRIPTIONS_API);
            const json = await r.json();
            if (json.success && json.data) {
                subscriptionsData = json.data;
                renderSubscriptionStats();
                renderSubscriptions();
                updateBillingPagination();
            }
        } catch (e) { console.error('Load subscriptions:', e); }
    }

    function renderSubscriptionStats() {
        const active = subscriptionsData.filter(s => (s.status || '') !== 'canceled').length;
        let mrr = 0;
        const now = new Date();
        const thisMonthStart = new Date(now.getFullYear(), now.getMonth(), 1);
        const thisMonthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59);
        let trials = 0;
        let cancelledThisMonth = 0;

        subscriptionsData.forEach(sub => {
            if ((sub.status || '') === 'canceled') {
                const canceledAt = sub.canceled_at ? new Date(sub.canceled_at) : null;
                if (canceledAt && canceledAt >= thisMonthStart && canceledAt <= thisMonthEnd) cancelledThisMonth++;
                return;
            }
            if ((sub.status || '') === 'trialing') trials++;
            const amt = Number(sub.amount) || 0;
            const interval = sub.interval || 'month';
            const count = Math.max(1, parseInt(sub.interval_count, 10) || 1);
            let monthsPerCycle = 1;
            if (interval === 'day') monthsPerCycle = count / 30;
            else if (interval === 'week') monthsPerCycle = count / 4.33;
            else if (interval === 'month') monthsPerCycle = count;
            else if (interval === 'year') monthsPerCycle = count * 12;
            if (monthsPerCycle > 0) mrr += amt / monthsPerCycle;
        });

        const fmt = (n) => '$' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const activeEl = document.getElementById('subscriptionStatActive');
        const mrrEl = document.getElementById('subscriptionStatMRR');
        const trialsEl = document.getElementById('subscriptionStatTrials');
        const cancelledEl = document.getElementById('subscriptionStatCancelled');
        if (activeEl) activeEl.textContent = active;
        if (mrrEl) mrrEl.textContent = fmt(mrr);
        if (trialsEl) trialsEl.textContent = trials;
        if (cancelledEl) cancelledEl.textContent = cancelledThisMonth;
    }

    let dashboardData = null;

    // Render Functions
    function renderInvoices() {
        const tbody = document.getElementById('invoicesTableBody');
        const cards = document.getElementById('invoicesCards');

        if (window.innerWidth > 768) {
            tbody.innerHTML = invoicesData.map(invoice => `
                <tr class="${selectedInvoiceIds.has(invoice.id) ? 'row-selected' : ''}">
                    <td class="checkbox-col"><input type="checkbox" class="invoice-select-checkbox" value="${invoice.id}" ${selectedInvoiceIds.has(invoice.id) ? 'checked' : ''} onchange="toggleInvoiceSelection(${invoice.id}, this.checked)" onclick="event.stopPropagation()"></td>
                    <td><strong>${invoice.invoiceNumber}</strong></td>
                    <td>${invoice.client}</td>
                    <td>${invoice.date}</td>
                    <td>${invoice.dueDate}</td>
                    <td><strong>$${invoice.amount.toLocaleString()}</strong></td>
                    <td><span class="status-badge ${invoice.status}">${invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="icon-btn" title="View" onclick="event.stopPropagation(); openViewInvoiceModal(${invoice.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            <button class="icon-btn" title="Download PDF" onclick="event.stopPropagation(); downloadInvoicePdf(${invoice.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                            </button>
                            ${!['paid', 'rejected'].includes((invoice.status || '').toLowerCase()) ? `
                            <button class="icon-btn" title="Edit" onclick="event.stopPropagation(); openEditInvoiceModal(${invoice.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            ` : ''}
                            <button class="icon-btn" title="${invoice.email_sent ? 'Email already sent' : (invoice.status || '').toLowerCase() === 'sent' ? 'Already sent' : ['paid', 'rejected'].includes((invoice.status || '').toLowerCase()) ? 'Invoice is ' + (invoice.status || '').toLowerCase() : 'Send Email'}" ${(invoice.email_sent || ['sent', 'paid', 'rejected'].includes((invoice.status || '').toLowerCase())) ? 'disabled' : ''} onclick="event.stopPropagation(); if (this.disabled) return; openSendInvoiceEmailModal(${invoice.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"/>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                                </svg>
                            </button>
                            ${canDeleteInvoices && !['paid', 'rejected'].includes((invoice.status || '').toLowerCase()) ? `
                            <button class="icon-btn icon-btn-danger" title="Delete" onclick="event.stopPropagation(); deleteInvoice(${invoice.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            cards.innerHTML = invoicesData.map(invoice => `
                <div class="invoice-card ${selectedInvoiceIds.has(invoice.id) ? 'row-selected' : ''}" onclick="openViewInvoiceModal(${invoice.id})">
                    <div class="card-header">
                        <div style="display: flex; align-items: flex-start; gap: 0.5rem;">
                            <input type="checkbox" class="invoice-select-checkbox" value="${invoice.id}" ${selectedInvoiceIds.has(invoice.id) ? 'checked' : ''} onchange="toggleInvoiceSelection(${invoice.id}, this.checked)" onclick="event.stopPropagation()">
                            <div>
                                <div class="card-title">${invoice.invoiceNumber}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${invoice.client}</div>
                            </div>
                        </div>
                        <span class="status-badge ${invoice.status}">${invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}</span>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Date</span>
                            <span class="card-value">${invoice.date}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Due Date</span>
                            <span class="card-value">${invoice.dueDate}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Amount</span>
                            <span class="card-value">$${invoice.amount.toLocaleString()}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        updateInvoiceBulkBar();
    }

    function toggleInvoiceSelection(id, checked) {
        if (checked) {
            selectedInvoiceIds.add(id);
        } else {
            selectedInvoiceIds.delete(id);
        }
        const row = document.querySelector(`.invoice-select-checkbox[value="${id}"]`)?.closest('tr, .invoice-card');
        if (row) row.classList.toggle('row-selected', checked);
        updateInvoiceBulkBar();
    }

    function toggleSelectAllInvoices(checkbox) {
        if (checkbox.checked) {
            invoicesData.forEach(inv => selectedInvoiceIds.add(inv.id));
        } else {
            invoicesData.forEach(inv => selectedInvoiceIds.delete(inv.id));
        }
        renderInvoices();
    }

    function clearInvoiceSelection() {
        selectedInvoiceIds.clear();
        renderInvoices();
    }

    function updateInvoiceBulkBar() {
        const visibleIds = new Set(invoicesData.map(i => i.id));
        for (const id of [...selectedInvoiceIds]) {
            if (!visibleIds.has(id)) selectedInvoiceIds.delete(id);
        }
        const count = selectedInvoiceIds.size;
        const bar = document.getElementById('invoiceBulkBar');
        const countEl = document.getElementById('invoiceBulkCount');
        if (bar) bar.style.display = count > 0 ? 'flex' : 'none';
        if (countEl) countEl.textContent = count + ' selected';

        const selectAll = document.getElementById('invoiceSelectAll');
        if (selectAll) {
            const total = invoicesData.length;
            selectAll.checked = total > 0 && count === total;
            selectAll.indeterminate = count > 0 && count < total;
        }

        const bulkStripeBtn = document.getElementById('bulkStripeLinkBtn');
        if (bulkStripeBtn) {
            bulkStripeBtn.disabled = !STRIPE_CONNECTED;
            bulkStripeBtn.title = STRIPE_CONNECTED ? '' : 'Connect Stripe in Integrations to generate payment links';
        }
    }

    let sendInvoiceEmailState = { mode: 'single', invoiceId: null, subjectManuallyEdited: false };

    function formatDateRangeLabel(startRaw, endRaw) {
        if (!startRaw || !endRaw) return '';
        const start = new Date(startRaw + 'T00:00:00');
        const end = new Date(endRaw + 'T00:00:00');
        const startMonth = start.toLocaleDateString('en-US', { month: 'long' });
        const endMonth = end.toLocaleDateString('en-US', { month: 'long' });
        const startDay = start.getDate();
        const endDay = end.getDate();
        if (startMonth === endMonth) {
            return `${startMonth} ${startDay}-${endDay}`;
        }
        const startLabel = start.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
        const endLabel = end.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
        return `${startLabel}-${endLabel}`;
    }

    function formatInvoiceCutoffRange(inv) {
        if (inv?.payroll_period_start && inv?.payroll_period_end) {
            return formatDateRangeLabel(inv.payroll_period_start, inv.payroll_period_end);
        }
        if (inv?.date_raw && inv?.due_date_raw) {
            return formatDateRangeLabel(inv.date_raw, inv.due_date_raw);
        }
        return '';
    }

    function formatInvoiceAmount(amount) {
        return '$' + Number(amount ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function normalizeInvoiceForEmail(data) {
        if (!data) return null;
        const client = typeof data.client === 'string'
            ? data.client
            : (data.client?.name ?? '');
        return {
            id: data.id,
            invoiceNumber: data.invoiceNumber ?? data.invoice_number ?? '',
            client: client || 'Client',
            amount: data.amount ?? data.total ?? 0,
            date_raw: data.date_raw ?? data.invoice_date ?? null,
            due_date_raw: data.due_date_raw ?? data.due_date ?? null,
            payroll_period_start: data.payroll_period_start ?? null,
            payroll_period_end: data.payroll_period_end ?? null,
        };
    }

    function isPlaceholderEmailSubject(subject) {
        if (!subject) return true;
        return /^Default:\s*Itsworkplace:/i.test(subject)
            || /Company Name Cutoff Date/i.test(subject)
            || /INV-XXXX/i.test(subject);
    }

    function buildDefaultInvoiceEmailSubject(inv, cutoffDate) {
        const normalized = normalizeInvoiceForEmail(inv);
        if (!normalized) return '';
        const cutoffPart = cutoffDate ? ` Cutoff ${cutoffDate}` : '';
        return `Itsworkplace: ${normalized.client}${cutoffPart} / Invoice #${normalized.invoiceNumber} (${formatInvoiceAmount(normalized.amount)})`;
    }

    function resolveSendInvoiceEmailSubject() {
        const cutoff = document.getElementById('sendInvoiceEmailCutoff')?.value?.trim() || '';
        let subject = document.getElementById('sendInvoiceEmailSubject')?.value?.trim() || '';
        if (!isPlaceholderEmailSubject(subject)) {
            return { subject, cutoff };
        }
        const inv = sendInvoiceEmailState.invoice
            || invoicesData.find(i => i.id === sendInvoiceEmailState.invoiceId);
        if (inv) {
            subject = buildDefaultInvoiceEmailSubject(inv, cutoff);
            const subjectEl = document.getElementById('sendInvoiceEmailSubject');
            if (subjectEl) subjectEl.value = subject;
        } else {
            subject = '';
        }
        return { subject, cutoff };
    }

    function updateSendInvoiceEmailSubjectFromCutoff() {
        if (sendInvoiceEmailState.mode !== 'single' || sendInvoiceEmailState.subjectManuallyEdited) return;
        const inv = sendInvoiceEmailState.invoice
            || normalizeInvoiceForEmail(invoicesData.find(i => i.id === sendInvoiceEmailState.invoiceId));
        if (!inv) return;
        const cutoff = document.getElementById('sendInvoiceEmailCutoff')?.value?.trim() || '';
        const subjectEl = document.getElementById('sendInvoiceEmailSubject');
        if (subjectEl) subjectEl.value = buildDefaultInvoiceEmailSubject(inv, cutoff);
    }

    async function openSendInvoiceEmailModal(invoiceId) {
        const ids = invoiceId != null
            ? [invoiceId]
            : [...selectedInvoiceIds];

        if (ids.length === 0) {
            const viewId = window.viewingInvoiceId;
            if (viewId) ids.push(viewId);
        }

        if (ids.length === 0) {
            alert('No invoice selected.');
            return;
        }

        const isBulk = ids.length > 1;
        let inv = isBulk ? null : invoicesData.find(i => i.id === ids[0]);

        if (!isBulk && !inv) {
            try {
                const r = await fetch(INVOICE_API + '/' + ids[0], {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await r.json().catch(() => ({}));
                if (r.ok && json.success && json.data) {
                    inv = normalizeInvoiceForEmail(json.data);
                }
            } catch (e) {
                console.error('Load invoice for email:', e);
            }
        } else if (inv) {
            inv = normalizeInvoiceForEmail(inv);
        }

        sendInvoiceEmailState = {
            mode: isBulk ? 'bulk' : 'single',
            invoiceId: isBulk ? null : ids[0],
            invoiceIds: ids,
            invoice: inv,
            subjectManuallyEdited: false,
        };
        const titleEl = document.getElementById('sendInvoiceEmailModalTitle');
        const hintEl = document.getElementById('sendInvoiceEmailModalHint');
        const subjectGroup = document.getElementById('sendInvoiceEmailSubjectGroup');
        const bulkNote = document.getElementById('sendInvoiceEmailBulkNote');
        const cutoffEl = document.getElementById('sendInvoiceEmailCutoff');
        const subjectEl = document.getElementById('sendInvoiceEmailSubject');

        if (titleEl) titleEl.textContent = isBulk ? `Send ${ids.length} Invoice Emails` : 'Send Invoice Email';
        if (hintEl) {
            hintEl.textContent = isBulk
                ? `Send ${ids.length} invoice(s) by email to their clients.`
                : `Send invoice ${inv?.invoiceNumber || ''} to ${inv?.client || 'the client'}.`;
        }
        if (subjectGroup) subjectGroup.style.display = isBulk ? 'none' : '';
        if (bulkNote) bulkNote.style.display = isBulk ? '' : 'none';

        const defaultCutoff = inv ? formatInvoiceCutoffRange(inv) : '';
        if (cutoffEl) {
            cutoffEl.value = defaultCutoff;
            cutoffEl.oninput = updateSendInvoiceEmailSubjectFromCutoff;
        }
        if (subjectEl) {
            subjectEl.value = '';
            subjectEl.value = inv ? buildDefaultInvoiceEmailSubject(inv, defaultCutoff) : '';
            subjectEl.oninput = () => { sendInvoiceEmailState.subjectManuallyEdited = true; };
        }

        document.getElementById('sendInvoiceEmailModal').style.display = 'flex';
    }

    function closeSendInvoiceEmailModal() {
        const modal = document.getElementById('sendInvoiceEmailModal');
        if (modal) modal.style.display = 'none';
        sendInvoiceEmailState = { mode: 'single', invoiceId: null, subjectManuallyEdited: false };
    }

    async function confirmSendInvoiceEmail() {
        const { subject, cutoff } = resolveSendInvoiceEmailSubject();

        if (sendInvoiceEmailState.mode === 'bulk') {
            closeSendInvoiceEmailModal();
            await bulkSendInvoiceEmails(sendInvoiceEmailState.invoiceIds, cutoff);
            return;
        }

        const id = sendInvoiceEmailState.invoiceId ?? window.viewingInvoiceId;
        if (!id) {
            alert('No invoice selected.');
            return;
        }

        closeSendInvoiceEmailModal();
        await sendInvoiceEmail(id, { email_subject: subject, cutoff_date: cutoff });
    }

    async function bulkSendInvoiceEmails(ids, cutoffDate) {
        if (!ids || ids.length === 0) ids = [...selectedInvoiceIds];
        if (ids.length === 0) return;

        const btn = document.getElementById('bulkSendEmailBtn');
        const original = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
        const overlay = document.getElementById('sendEmailOverlay');
        if (overlay) overlay.style.display = 'flex';
        try {
            const payload = { invoice_ids: ids };
            if (cutoffDate) payload.cutoff_date = cutoffDate;
            const r = await fetch(INVOICE_API + '/bulk-send-email', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload),
            });
            const json = await r.json().catch(() => ({}));
            if (overlay) overlay.style.display = 'none';
            alert(buildBulkResultMessage(json));
            if (r.ok && json.success) {
                clearInvoiceSelection();
                loadInvoices();
                loadInvoiceStats();
                loadPaymentTracking();
            }
        } catch (e) {
            console.error(e);
            if (overlay) overlay.style.display = 'none';
            alert('Error sending invoices.');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = original; }
        }
    }

    async function bulkGenerateStripeLinks() {
        if (!STRIPE_CONNECTED) { alert('Connect Stripe in Integrations to generate payment links.'); return; }
        const ids = [...selectedInvoiceIds];
        if (ids.length === 0) return;
        if (!confirm(`Generate a Stripe payment link for ${ids.length} invoice(s)?`)) return;

        const btn = document.getElementById('bulkStripeLinkBtn');
        const original = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Generating...'; }
        try {
            const r = await fetch(INVOICE_API + '/bulk-stripe-payment-link', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ invoice_ids: ids, currency: 'usd' }),
            });
            const json = await r.json().catch(() => ({}));
            alert(buildBulkResultMessage(json));
            if (r.ok && json.success) {
                clearInvoiceSelection();
                loadInvoices();
                loadPaymentTracking();
            }
        } catch (e) {
            console.error(e);
            alert('Error generating payment links.');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = original; }
        }
    }

    function buildBulkResultMessage(json) {
        let msg = json.message || 'Done.';
        const failures = json.data?.failures || [];
        if (failures.length > 0) {
            msg += '\n\nFailed:\n' + failures.map(f => `• ${f.invoice}: ${f.reason}`).join('\n');
        }
        return msg;
    }

    async function loadPaymentTracking() {
        const month = document.getElementById('paymentDateFilter')?.value || '';
        let url = PAYMENT_TRACKING_URL;
        if (month) url += (url.includes('?') ? '&' : '?') + 'month=' + encodeURIComponent(month);
        const paginationEl = document.getElementById('paymentsPaginationInfo');
        try {
            const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const text = await r.text();
            let json = {};
            try { json = text ? JSON.parse(text) : {}; } catch (_) {}
            if (r.ok && json.success && json.data) {
                paymentsData = json.data.payments || [];
                document.getElementById('paymentTotalReceived').textContent = '$' + (json.data.total_received ?? 0).toLocaleString();
                document.getElementById('paymentPendingAmount').textContent = '$' + (json.data.pending_amount ?? 0).toLocaleString();
                const pc = json.data.pending_count ?? 0;
                document.getElementById('paymentPendingCount').textContent = pc + ' invoice' + (pc !== 1 ? 's' : '');
                renderPayments();
                const n = paymentsData.length;
                if (paginationEl) paginationEl.textContent = n > 0 ? `Showing 1 to ${n} of ${n} results` : 'Showing 0 of 0 results';
            } else {
                paymentsData = [];
                renderPayments();
                if (paginationEl) paginationEl.textContent = r.status === 404 ? 'Route not found (404)' : 'Error loading data';
            }
        } catch (e) {
            console.error('Load payment tracking:', e);
            paymentsData = [];
            renderPayments();
            if (paginationEl) paginationEl.textContent = 'Error loading data';
        }
    }

    function renderPayments() {
        const tbody = document.getElementById('paymentsTableBody');
        const cards = document.getElementById('paymentsCards');
        if (!tbody || !cards) return;

        if (window.innerWidth > 768) {
            tbody.innerHTML = paymentsData.map(payment => `
                <tr>
                    <td><strong>${payment.payment_id || 'PAY-' + payment.invoice_number}</strong></td>
                    <td>${payment.invoice_number}</td>
                    <td>${payment.client}</td>
                    <td>${payment.date}</td>
                    <td><strong>$${(payment.amount || 0).toLocaleString()}</strong></td>
                    <td>${payment.method || 'Manual'}</td>
                    <td><span class="status-badge ${payment.status || 'completed'}">${(payment.status || 'Completed').charAt(0).toUpperCase() + (payment.status || 'completed').slice(1)}</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="icon-btn" title="View" onclick="event.stopPropagation(); openViewInvoiceModal(${payment.invoice_id}, true)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            cards.innerHTML = paymentsData.map(payment => `
                <div class="payment-card" onclick="openViewInvoiceModal(${payment.invoice_id}, true)">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${payment.payment_id || 'PAY-' + payment.invoice_number}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${payment.client}</div>
                        </div>
                        <span class="status-badge ${payment.status || 'completed'}">${(payment.status || 'Completed').charAt(0).toUpperCase() + (payment.status || 'completed').slice(1)}</span>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Invoice</span>
                            <span class="card-value">${payment.invoice_number}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Date</span>
                            <span class="card-value">${payment.date}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Amount</span>
                            <span class="card-value">$${(payment.amount || 0).toLocaleString()}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Method</span>
                            <span class="card-value">${payment.method || 'Manual'}</span>
                        </div>
                    </div>
                </div>
            `).join('');
        }
    }

    function renderSubscriptions() {
        const tbody = document.getElementById('subscriptionsTableBody');
        const cards = document.getElementById('subscriptionsCards');
        const formatAmount = (amt, curr) => {
            const sym = { usd: '$', eur: '€', gbp: '£', cad: 'C$', aud: 'A$' }[curr] || curr?.toUpperCase() + ' ';
            return (sym || '$') + (Number(amt) || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
        };
        const fmtStatus = s => (s || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());

        const filtered = subscriptionsData.filter(sub => {
            if (subscriptionsStatusFilter === 'all') return true;
            if (subscriptionsStatusFilter === 'active') return (sub.status || '') !== 'canceled';
            if (subscriptionsStatusFilter === 'canceled') return (sub.status || '') === 'canceled';
            return true;
        });

        if (window.innerWidth > 768) {
            tbody.innerHTML = filtered.map(sub => `
                <tr>
                    <td><strong>${(sub.client || '-').replace(/</g, '&lt;')}</strong></td>
                    <td>${(sub.product_name || '-').replace(/</g, '&lt;')}</td>
                    <td>${(sub.billing_cycle_display || sub.interval || '-').replace(/</g, '&lt;')}</td>
                    <td><strong>${formatAmount(sub.amount, sub.currency)}</strong></td>
                    <td>${(sub.start_date || '-').replace(/</g, '&lt;')}</td>
                    <td>${(sub.next_billing || '-').replace(/</g, '&lt;')}</td>
                    <td><span class="status-badge ${(sub.status || '').replace('trialing','trial').replace('canceled','cancelled')}">${fmtStatus(sub.status || '')}</span></td>
                    <td>
                        <div class="table-actions">
                            <button class="icon-btn" title="Copy payment link" onclick="copySubscriptionPaymentLink(${sub.id}, ${sub.hosted_invoice_url ? JSON.stringify(sub.hosted_invoice_url) : 'null'})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                </svg>
                            </button>
                            ${(sub.status || '') !== 'canceled' ? `
                            <button class="icon-btn" title="Update subscription" onclick="openEditSubscriptionModal(${sub.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button class="icon-btn" title="Cancel subscription" onclick="cancelSubscription(${sub.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                </svg>
                            </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            cards.innerHTML = filtered.map(sub => `
                <div class="subscription-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${(sub.client || '-').replace(/</g, '&lt;')}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${(sub.product_name || '-').replace(/</g, '&lt;')}</div>
                        </div>
                        <span class="status-badge ${(sub.status || '').replace('trialing','trial').replace('canceled','cancelled')}">${fmtStatus(sub.status || '')}</span>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Cycle</span>
                            <span class="card-value">${(sub.billing_cycle_display || sub.interval || '-').replace(/</g, '&lt;')}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Amount</span>
                            <span class="card-value">${formatAmount(sub.amount, sub.currency)}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Next Billing</span>
                            <span class="card-value">${(sub.next_billing || '-').replace(/</g, '&lt;')}</span>
                        </div>
                    </div>
                    <div style="margin-top: 0.75rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        ${sub.stripe_subscription_id ? `<button class="btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" onclick="copySubscriptionPaymentLink(${sub.id}, ${sub.hosted_invoice_url ? JSON.stringify(sub.hosted_invoice_url) : 'null'})">Copy link</button>` : ''}
                        ${(sub.status || '') !== 'canceled' ? `
                        <button class="btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" onclick="openEditSubscriptionModal(${sub.id})">Update</button>
                        <button class="btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem; color: var(--danger, #dc2626);" onclick="cancelSubscription(${sub.id})">Cancel</button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }
    }

    let dashboardSource = 'stripe';

    function switchDashboardSource(source) {
        dashboardSource = source;
        document.querySelectorAll('.dashboard-source-btn').forEach(b => b.classList.toggle('active', b.dataset.source === source));
        loadDashboard();
    }

    function loadDashboard() {
        return dashboardSource === 'wise' ? loadWiseDashboard() : loadStripeDashboard();
    }

    async function loadWiseDashboard() {
        const period = document.getElementById('dashboardPeriodFilter')?.value || 'this-month';
        try {
            const r = await fetch(INVOICE_API + '/wise-dashboard?period=' + encodeURIComponent(period), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await r.json();
            dashboardData = (json.success && json.data) ? json.data : { period_label: period.replace(/-/g, ' ') };
            renderStripeDashboard();
        } catch (e) {
            console.error('Load Wise dashboard:', e);
            dashboardData = null;
            renderStripeDashboard();
        }
    }

    async function loadStripeDashboard() {
        const period = document.getElementById('dashboardPeriodFilter')?.value || 'this-month';
        try {
            const r = await fetch(INVOICE_API + '/stripe-dashboard?period=' + encodeURIComponent(period), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const json = await r.json();
            if (json.success && json.data) {
                dashboardData = json.data;
                renderStripeDashboard();
            } else {
                dashboardData = {
                    period_label: period.replace(/-/g, ' '),
                    revenue_total: 0,
                    revenue_chart: [],
                    chart_max: 1,
                    payment_methods: [],
                    paid_amount: 0, paid_count: 0, paid_percentage: 0,
                    pending_amount: 0, pending_count: 0, pending_percentage: 0,
                    overdue_amount: 0, overdue_count: 0, overdue_percentage: 0,
                    pending_payment_links_count: 0,
                    pending_payment_links_amount: 0,
                    paid_payment_links_count: 0,
                    paid_payment_links_amount: 0,
                    recent_activity: []
                };
                if (json.message) dashboardData.message = json.message;
                renderStripeDashboard();
            }
        } catch (e) {
            console.error('Load Stripe dashboard:', e);
            dashboardData = null;
            renderStripeDashboard();
        }
    }

    function renderStripeDashboard() {
        const d = dashboardData || {};
        const fmt = (n) => '$' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('dashboardPeriodLabel').textContent = d.period_label || '—';
        document.getElementById('dashboardRevenueTotal').textContent = fmt(d.revenue_total);
        const chartEl = document.getElementById('dashboardChartBars');
        if (chartEl) {
            const bars = d.revenue_chart || [];
            const max = d.chart_max || 1;
            chartEl.innerHTML = bars.length ? bars.map(v => `<div class="chart-bar" style="height: ${max > 0 ? (100 * v / max) : 0}%"></div>`).join('') : '<div class="empty-state" style="padding: 1rem;">No revenue data for this period</div>';
        }
        const pmEl = document.getElementById('dashboardPaymentMethods');
        if (pmEl) {
            const pms = d.payment_methods || [];
            pmEl.innerHTML = pms.length ? pms.map(p => `
                <div class="payment-method-item">
                    <div class="method-info">
                        <span class="method-name">${(p.name || '').replace(/</g, '&lt;')}</span>
                        <span class="method-percentage">${p.percentage || 0}%</span>
                    </div>
                    <div class="method-bar">
                        <div class="method-fill" style="width: ${Math.min(100, p.percentage || 0)}%"></div>
                    </div>
                </div>
            `).join('') : '<div class="payment-method-item empty-state">No payment data for this period</div>';
        }
        document.getElementById('dashboardPaidValue').textContent = fmt(d.paid_amount);
        document.getElementById('dashboardPaidBar').style.width = (d.paid_percentage || 0) + '%';
        document.getElementById('dashboardPaidCount').textContent = (d.paid_count || 0) + ' Subscription' + ((d.paid_count || 0) !== 1 ? 's' : '');
        document.getElementById('dashboardPendingValue').textContent = fmt(d.pending_amount);
        document.getElementById('dashboardPendingBar').style.width = (d.pending_percentage || 0) + '%';
        document.getElementById('dashboardPendingCount').textContent = (d.pending_count || 0) + ' Subscriptions' + ((d.pending_count || 0) !== 1 ? 's' : '');
        document.getElementById('dashboardOverdueValue').textContent = fmt(d.overdue_amount);
        document.getElementById('dashboardOverdueBar').style.width = (d.overdue_percentage || 0) + '%';
        document.getElementById('dashboardOverdueCount').textContent = (d.overdue_count || 0) + ' Subscriptions' + ((d.overdue_count || 0) !== 1 ? 's' : '');
        const ppending = d.pending_payment_links_count ?? 0;
        const ppaid = d.paid_payment_links_count ?? 0;
        const ppendingAmt = d.pending_payment_links_amount ?? 0;
        const ppaidAmt = d.paid_payment_links_amount ?? 0;
        const plAmtTotal = ppendingAmt + ppaidAmt;
        const ppendingPct = plAmtTotal > 0 ? Math.round(100 * ppendingAmt / plAmtTotal) : (ppending + ppaid > 0 ? Math.round(100 * ppending / (ppending + ppaid)) : 0);
        const ppaidPct = plAmtTotal > 0 ? Math.round(100 * ppaidAmt / plAmtTotal) : (ppending + ppaid > 0 ? Math.round(100 * ppaid / (ppending + ppaid)) : 0);
        const plPendingValEl = document.getElementById('dashboardPendingPaymentLinksValue');
        const plPaidValEl = document.getElementById('dashboardPaidPaymentLinksValue');
        if (plPendingValEl) plPendingValEl.textContent = fmt(ppendingAmt);
        if (plPaidValEl) plPaidValEl.textContent = fmt(ppaidAmt);
        const plPendingBar = document.getElementById('dashboardPendingPaymentLinksBar');
        const plPaidBar = document.getElementById('dashboardPaidPaymentLinksBar');
        if (plPendingBar) plPendingBar.style.width = ppendingPct + '%';
        if (plPaidBar) plPaidBar.style.width = ppaidPct + '%';
        const plPendingLabel = document.getElementById('dashboardPendingPaymentLinksLabel');
        const plPaidLabel = document.getElementById('dashboardPaidPaymentLinksLabel');
        if (plPendingLabel) plPendingLabel.textContent = ppending + ' Invoice' + (ppending !== 1 ? 's' : '');
        if (plPaidLabel) plPaidLabel.textContent = ppaid + ' Invoice' + (ppaid !== 1 ? 's' : '');
        const actEl = document.getElementById('dashboardActivityList');
        if (actEl) {
            const activities = d.recent_activity || [];
            actEl.innerHTML = activities.length ? activities.map(a => `
                <div class="activity-item">
                    <div class="activity-icon green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">${(a.description || 'Payment').replace(/</g, '&lt;')}${(a.product_name && a.product_name.trim()) ? ' · ' + String(a.product_name).replace(/</g, '&lt;') : ''}</div>
                        <div class="activity-time">${(a.created_human || '').replace(/</g, '&lt;')}</div>
                    </div>
                    <div class="activity-amount">${fmt(a.amount)}</div>
                </div>
            `).join('') : '<div class="empty-state">No recent payments</div>';
        }
    }

    // New Invoice Modal Functions
    async function createInvoice() {
        const modal = document.getElementById('newInvoiceModal');
        const form = document.getElementById('newInvoiceForm');
        form.reset();

        // Set default dates
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('invoiceDate').value = today;
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 30);
        document.getElementById('invoiceDueDate').value = dueDate.toISOString().split('T')[0];

        // Get next invoice number from API
        try {
            const r = await fetch(INVOICE_API + '/next-number');
            const json = await r.json();
            if (json.success && json.data?.invoice_number) {
                document.getElementById('invoiceNumber').value = json.data.invoice_number;
            } else {
                document.getElementById('invoiceNumber').value = 'INV-' + new Date().getFullYear() + '-001';
            }
        } catch (e) {
            document.getElementById('invoiceNumber').value = 'INV-' + new Date().getFullYear() + '-001';
        }

        // Reset line items to single row
        const container = document.getElementById('invoiceLineItems');
        container.innerHTML = `
            <div class="line-item-row line-item-grid new-invoice-line-grid">
                <input type="text" class="form-input invoice-line-desc" placeholder="Description (employee name)" name="line_desc[]" list="invoiceLineEmployeeOptions" autocomplete="off">
                <input type="number" class="form-input form-input-narrow" placeholder="Hours" name="line_hours[]" min="0" step="0.01" title="Hours worked">
                <input type="number" class="form-input form-input-narrow" placeholder="Net Pay ($)" name="line_net_pay[]" min="0" step="0.01" title="Net pay for P&amp;L">
                <input type="number" class="form-input form-input-narrow" placeholder="Rate ($)" name="line_rate[]" min="0" step="0.01" oninput="updateInvoiceTotals()">
                <span class="line-amount">$0.00</span>
                <button type="button" class="icon-btn icon-btn-danger" onclick="removeLineItem(this)" title="Remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        `;

        document.getElementById('invoiceTaxRate').value = 0;
        const wiseField = document.getElementById('invoiceWisePaymentUrl');
        if (wiseField) wiseField.value = wiseDefaultLink || '';
        bindInvoiceLineDescriptionAutocomplete('invoiceLineItems', 'line_desc[]');
        updateInvoiceTotals();
        modal.style.display = 'flex';
    }

    function closeNewInvoiceModal() {
        document.getElementById('newInvoiceModal').style.display = 'none';
    }

    // View Invoice Modal (fromPaymentTracking = true hides Send, Delete, Update)
    async function openViewInvoiceModal(invoiceId, fromPaymentTracking = false) {
        let invoice = invoicesData.find(i => i.id === invoiceId);
        if (!invoice) {
            const p = paymentsData.find(p => p.invoice_id === invoiceId);
            if (p) {
                invoice = { id: invoiceId, invoiceNumber: p.invoice_number, client: p.client, date: p.date, dueDate: p.date, amount: p.amount, status: p.status, stripe_payment_url: p.method === 'Stripe' ? '' : null };
            } else {
                try {
                    const r = await fetch(INVOICE_API + '/' + invoiceId);
                    const json = await r.json();
                    if (r.ok && json.success && json.data) {
                        const d = json.data;
                        invoice = { id: d.id, invoiceNumber: d.invoice_number ?? d.invoiceNumber, client: d.client?.name ?? d.client ?? '-', date: d.date ?? d.invoice_date, dueDate: d.dueDate ?? d.due_date, date_raw: d.date_raw, due_date_raw: d.due_date_raw, payroll_period_start: d.payroll_period_start, payroll_period_end: d.payroll_period_end, amount: d.amount, status: d.status, stripe_payment_url: d.stripe_payment_url };
                    } else { return; }
                } catch (e) { return; }
            }
        }
        const sendBtn = document.getElementById('viewSendEmailBtn');
        const deleteBtn = document.getElementById('viewDeleteBtn');
        const updateBtn = document.getElementById('viewUpdateBtn');
        const stripeSection = document.getElementById('viewStripePaymentSection');
        const wiseSection = document.getElementById('viewWisePaymentSection');
        const footerLeft = document.querySelector('.view-invoice-footer-left');
        const statusLower = (invoice.status || '').toLowerCase();
        const isSent = statusLower === 'sent';
        const isPaid = statusLower === 'paid';
        const isRejected = statusLower === 'rejected';
        if (fromPaymentTracking) {
            if (sendBtn) sendBtn.style.display = 'none';
            if (deleteBtn) deleteBtn.style.display = 'none';
            if (updateBtn) updateBtn.style.display = 'none';
            if (stripeSection) stripeSection.style.display = 'none';
            if (wiseSection) wiseSection.style.display = 'none';
            if (footerLeft) footerLeft.style.display = 'none';
        } else {
            if (sendBtn) sendBtn.style.display = (invoice.email_sent || isSent || isPaid || isRejected) ? 'none' : '';
            const canShowDelete = canDeleteInvoices && !(isPaid || isRejected);
            if (deleteBtn) deleteBtn.style.display = canShowDelete ? '' : 'none';
            if (footerLeft) footerLeft.style.display = canShowDelete ? '' : 'none';
            if (updateBtn) {
                if (isPaid || isRejected || isSent) {
                    updateBtn.style.display = 'none';
                } else {
                    updateBtn.style.display = '';
                    updateBtn.disabled = false;
                    updateBtn.title = 'Edit invoice';
                }
            }
            if (stripeSection) stripeSection.style.display = (isPaid || isRejected) ? 'none' : '';
            if (wiseSection) wiseSection.style.display = (isPaid || isRejected) ? 'none' : '';
        }
        document.getElementById('viewInvoiceNumber').textContent = invoice.invoiceNumber || invoice.invoice_number || '-';
        const status = invoice.status || 'draft';
        document.getElementById('viewInvoiceStatus').innerHTML = `<span class="status-badge ${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
        document.getElementById('viewInvoiceClient').textContent = invoice.client || '-';
        document.getElementById('viewInvoiceDate').textContent = invoice.date || '-';
        document.getElementById('viewInvoiceDueDate').textContent = invoice.dueDate || invoice.date || '-';
        const amt = Number(invoice.amount) || 0;
        document.getElementById('viewInvoiceAmount').textContent = '$' + amt.toLocaleString();
        const itemsEl = document.getElementById('viewInvoiceItems');
        const itemsHeader = document.getElementById('viewInvoiceItemsHeader');
        itemsEl.className = 'view-invoice-items';
        if (itemsHeader) itemsHeader.style.display = 'none';
        itemsEl.innerHTML = `<div class="view-line-item"><span>Invoice total</span><span></span><span></span><span></span><span>$${amt.toLocaleString()}</span></div>`;
        const stripeInput = document.getElementById('viewStripePaymentUrl');
        stripeInput.value = isRejected ? '' : (invoice.stripe_payment_url || '');
        updateViewGenerateLinkBtn(!isRejected && (invoice.stripe_link_generated || !!invoice.stripe_payment_url));
        const wiseInput = document.getElementById('viewWisePaymentUrl');
        if (wiseInput) wiseInput.value = isRejected ? '' : (invoice.wise_payment_url || '');
        window.viewingInvoiceId = invoiceId;
        document.getElementById('viewInvoiceModal').style.display = 'flex';
        try {
            const r = await fetch(INVOICE_API + '/' + invoiceId);
            const json = await r.json();
            if (r.ok && json.success && json.data) {
                const d = json.data;
                if (d.items?.length) {
                    const isPayrollInvoice = !!d.is_payroll_invoice;
                    itemsEl.className = 'view-invoice-items view-line-items-body' + (isPayrollInvoice ? ' view-line-items-body--payroll' : '');
                    if (itemsHeader) {
                        itemsHeader.style.display = '';
                        const headerGrid = itemsHeader.querySelector('.line-item-header');
                        if (headerGrid) {
                            headerGrid.className = 'line-item-header line-item-grid view-line-item-grid' + (isPayrollInvoice ? ' view-line-item-grid--payroll' : '');
                            headerGrid.innerHTML = `
                                <span class="line-item-col-desc">Description</span>
                                <span class="line-item-col-hours">Hours</span>
                                <span class="line-item-col-net-pay">Net Pay</span>
                                ${isPayrollInvoice ? '<span class="line-item-col-commission">Commission</span>' : ''}
                                <span class="line-item-col-rate">Rate</span>
                                <span class="line-item-col-amount">Amount</span>
                            `;
                        }
                    }
                    const formatMoney = (val) => val != null && val !== ''
                        ? '$' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                        : '—';
                    itemsEl.innerHTML = d.items.map(i => {
                        const amt = i.total ?? (i.quantity * i.unit_price) ?? 0;
                        const hours = i.hours_worked != null && i.hours_worked !== ''
                            ? Number(i.hours_worked).toLocaleString(undefined, { maximumFractionDigits: 2 })
                            : '—';
                        return `<div class="view-line-item">
                            <span>${(i.description || 'Item').replace(/</g, '&lt;')}</span>
                            <span>${hours}</span>
                            <span>${formatMoney(i.net_pay)}</span>
                            ${isPayrollInvoice ? `<span>${formatMoney(i.commission)}</span>` : ''}
                            <span>$${Number(i.unit_price || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                            <span>$${Number(amt).toLocaleString(undefined, { minimumFractionDigits: 2 })}</span>
                        </div>`;
                    }).join('');
                }
                if (d.stripe_payment_url && !isRejected) {
                    stripeInput.value = d.stripe_payment_url;
                }
                if (wiseInput && !isRejected) {
                    wiseInput.value = d.wise_payment_url || '';
                }
                updateViewGenerateLinkBtn(d.stripe_link_generated || !!d.stripe_payment_url);
            }
        } catch (e) { /* keep default */ }
    }

    function updateViewGenerateLinkBtn(alreadyGenerated) {
        const btn = document.getElementById('viewGenerateLinkBtn');
        if (!btn) return;
        if (!STRIPE_CONNECTED) {
            btn.disabled = true;
            btn.textContent = 'Generate Link';
            btn.title = 'Connect Stripe in Integrations to generate payment links';
            return;
        }
        if (alreadyGenerated) {
            btn.disabled = true;
            btn.textContent = 'Link Generated';
            btn.title = 'A payment link has already been generated for this invoice';
        } else {
            btn.disabled = false;
            btn.textContent = 'Generate Link';
            btn.title = 'Generate with invoice ID for webhook';
        }
    }

    function copyViewStripePaymentLink() {
        const input = document.getElementById('viewStripePaymentUrl');
        if (!input?.value) {
            alert('No payment link to copy.');
            return;
        }
        navigator.clipboard.writeText(input.value).then(() => alert('Link copied to clipboard.')).catch(() => alert('Could not copy.'));
    }

    function copyViewWisePaymentLink() {
        const input = document.getElementById('viewWisePaymentUrl');
        if (!input?.value) {
            alert('No Wise payment link to copy.');
            return;
        }
        navigator.clipboard.writeText(input.value).then(() => alert('Link copied to clipboard.')).catch(() => alert('Could not copy.'));
    }

    async function saveViewWisePaymentLink() {
        const id = window.viewingInvoiceId;
        const inv = invoicesData.find(i => i.id === id);
        if (!id || !inv) return;
        const url = (document.getElementById('viewWisePaymentUrl')?.value || '').trim();
        const btn = document.getElementById('viewSaveWiseLinkBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
        try {
            const payload = {
                client_id: inv.client_id,
                invoice_date: inv.date_raw || parseDisplayDate(inv.date),
                due_date: inv.due_date_raw || parseDisplayDate(inv.dueDate),
                status: inv.status || 'draft',
                tax_rate: inv.taxRate ?? 0,
                notes: inv.notes ?? null,
                wise_payment_url: url || null,
            };
            const r = await fetch(INVOICE_API + '/' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload),
            });
            const json = await r.json().catch(() => ({}));
            if (r.ok && json.success) {
                const idx = invoicesData.findIndex(i => i.id === id);
                if (idx >= 0) invoicesData[idx] = { ...invoicesData[idx], wise_payment_url: url || null };
                alert('Wise payment link saved.');
            } else {
                alert(json.message || 'Failed to save Wise link.');
            }
        } catch (e) {
            console.error(e);
            alert('Error saving Wise link.');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
        }
    }

    async function saveWiseDefaultLink() {
        const url = (document.getElementById('wiseDefaultLinkInput')?.value || '').trim();
        const btn = document.getElementById('saveWiseDefaultLinkBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
        try {
            const r = await fetch(WISE_DEFAULT_LINK_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ wise_payment_url: url || null }),
            });
            const json = await r.json().catch(() => ({}));
            if (r.ok && json.success) {
                wiseDefaultLink = json.data?.wise_payment_url || '';
                alert(json.message || 'Saved.');
            } else {
                const firstError = json.errors ? Object.values(json.errors)[0]?.[0] : null;
                alert(firstError || json.message || 'Failed to save default Wise link.');
            }
        } catch (e) {
            console.error(e);
            alert('Error saving default Wise link.');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
        }
    }

    let wiseWebhookActive = false;

    async function loadWiseReconcileStatus() {
        const controls = document.getElementById('wiseReconcileControls');
        try {
            const r = await fetch(INVOICE_API + '/wise-webhook-status', { headers: { 'Accept': 'application/json' } });
            const json = await r.json().catch(() => ({}));
            const d = json.data || {};
            if (!d.wise_configured) {
                if (controls) controls.style.display = 'none';
                return;
            }
            if (controls) controls.style.display = 'flex';
            wiseWebhookActive = !!d.webhook_active;
            renderWiseReconcileStatus();
        } catch (e) {
            console.error('Wise reconcile status:', e);
            if (controls) controls.style.display = 'none';
        }
    }

    function renderWiseReconcileStatus() {
        const dot = document.getElementById('wiseReconcileDot');
        const label = document.getElementById('wiseReconcileLabel');
        const btn = document.getElementById('wiseReconcileToggleBtn');
        if (dot) dot.className = 'wise-reconcile-dot ' + (wiseWebhookActive ? 'on' : 'off');
        if (label) label.textContent = wiseWebhookActive
            ? 'Auto-reconciliation: on (invoices auto-marked Paid by reference)'
            : 'Auto-reconciliation: off';
        if (btn) {
            btn.style.display = '';
            btn.textContent = wiseWebhookActive ? 'Disable' : 'Enable';
        }
    }

    async function toggleWiseReconciliation() {
        const btn = document.getElementById('wiseReconcileToggleBtn');
        const enabling = !wiseWebhookActive;
        if (enabling && !confirm('Enable Wise auto-reconciliation? Wise will notify this app of incoming payments and matching invoices (by invoice number reference) will be marked Paid automatically.')) return;
        if (btn) { btn.disabled = true; btn.textContent = enabling ? 'Enabling…' : 'Disabling…'; }
        try {
            const url = INVOICE_API + '/wise-webhook/' + (enabling ? 'enable' : 'disable');
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: '{}',
            });
            const json = await r.json().catch(() => ({}));
            alert(json.message || (r.ok ? 'Done.' : 'Failed.'));
            if (r.ok && json.success) {
                wiseWebhookActive = enabling;
            }
        } catch (e) {
            console.error(e);
            alert('Error updating auto-reconciliation.');
        } finally {
            if (btn) { btn.disabled = false; }
            renderWiseReconcileStatus();
        }
    }

    let wiseUnpaidInvoices = [];

    function openWiseIncomingModal() {
        document.getElementById('wiseIncomingModal').style.display = 'flex';
        loadWiseIncomingPayments();
    }

    function closeWiseIncomingModal() {
        document.getElementById('wiseIncomingModal').style.display = 'none';
    }

    async function loadWiseIncomingPayments() {
        const content = document.getElementById('wiseIncomingContent');
        content.innerHTML = '<div class="wise-incoming-empty">Loading…</div>';
        try {
            const [payRes, invRes] = await Promise.all([
                fetch(INVOICE_API + '/wise-incoming-payments?days=90', { headers: { 'Accept': 'application/json' } }),
                fetch(INVOICE_API + '?status=all&per_page=200', { headers: { 'Accept': 'application/json' } }),
            ]);
            const payJson = await payRes.json().catch(() => ({}));
            const invJson = await invRes.json().catch(() => ({}));
            if (!payRes.ok || !payJson.success) {
                content.innerHTML = `<div class="wise-incoming-empty">${(payJson.message || 'Could not load incoming payments.').replace(/</g, '&lt;')}</div>`;
                return;
            }
            wiseUnpaidInvoices = (invJson.data || []).filter(i => !['paid', 'rejected'].includes((i.status || '').toLowerCase()));
            renderWiseIncomingPayments(payJson.data?.payments || []);
        } catch (e) {
            console.error(e);
            content.innerHTML = '<div class="wise-incoming-empty">Error loading incoming payments.</div>';
        }
    }

    function renderWiseIncomingPayments(payments) {
        const content = document.getElementById('wiseIncomingContent');
        if (!payments.length) {
            content.innerHTML = '<div class="wise-incoming-empty">No incoming payments found in the last 90 days.</div>';
            return;
        }

        const optionsFor = (payment) => {
            // Suggest an unpaid invoice: reference contains invoice #, else exact amount match.
            const ref = (payment.reference || '').toLowerCase().replace(/\s+/g, '');
            let suggestedId = null;
            for (const inv of wiseUnpaidInvoices) {
                const num = (inv.invoiceNumber || '').toLowerCase().replace(/\s+/g, '');
                if (num && ref.includes(num)) { suggestedId = inv.id; break; }
            }
            if (!suggestedId) {
                const match = wiseUnpaidInvoices.find(inv => Math.abs((inv.amount || 0) - (payment.amount || 0)) < 0.01);
                if (match) suggestedId = match.id;
            }
            const opts = wiseUnpaidInvoices.map(inv =>
                `<option value="${inv.id}" ${inv.id === suggestedId ? 'selected' : ''}>${(inv.invoiceNumber || '').replace(/</g, '&lt;')} · ${(inv.client || '').replace(/</g, '&lt;')} · $${(inv.amount || 0).toLocaleString()}</option>`
            ).join('');
            return `<option value="">Select invoice…</option>${opts}`;
        };

        content.innerHTML = `
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr><th>Date</th><th>Sender</th><th>Reference</th><th>Amount</th><th>Match to invoice</th></tr>
                    </thead>
                    <tbody>
                        ${payments.map((p, idx) => {
                            const sender = (p.sender || '-').replace(/</g, '&lt;');
                            const ref = (p.reference || p.description || '-').replace(/</g, '&lt;');
                            return `
                            <tr id="wisePayRow-${idx}">
                                <td class="wic-date">${(p.date || '-').replace(/</g, '&lt;')}</td>
                                <td class="wic-sender"><span class="wic-trunc" title="${sender}">${sender}</span></td>
                                <td class="wic-ref"><span class="wic-trunc" title="${ref}">${ref}</span></td>
                                <td class="wic-amount"><strong>${(p.amount || 0).toLocaleString()} ${(p.currency || '').replace(/</g, '&lt;')}</strong></td>
                                <td class="wic-match">
                                    <div class="wic-match-controls">
                                        <select class="form-input wise-incoming-select" id="wisePaySelect-${idx}">${optionsFor(p)}</select>
                                        <button class="btn-secondary btn-sm" onclick="markWisePaymentPaid(${idx}, '${String(p.id || '').replace(/'/g, '')}')">Mark Paid</button>
                                    </div>
                                </td>
                            </tr>
                        `;}).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    async function markWisePaymentPaid(idx, paymentId) {
        const select = document.getElementById('wisePaySelect-' + idx);
        const invoiceId = parseInt(select?.value, 10);
        if (!invoiceId) { alert('Select an invoice to match.'); return; }
        if (!confirm('Mark the selected invoice as Paid?')) return;
        try {
            const r = await fetch(INVOICE_API + '/' + invoiceId + '/mark-wise-paid', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ wise_transaction_id: paymentId || null }),
            });
            const json = await r.json().catch(() => ({}));
            if (r.ok && json.success) {
                const row = document.getElementById('wisePayRow-' + idx);
                if (row) row.remove();
                wiseUnpaidInvoices = wiseUnpaidInvoices.filter(i => i.id !== invoiceId);
                loadInvoices();
                loadInvoiceStats();
                loadPaymentTracking();
            } else {
                alert(json.message || 'Failed to mark paid.');
            }
        } catch (e) {
            console.error(e);
            alert('Error marking invoice paid.');
        }
    }

    async function generateViewStripePaymentLink() {
        if (!STRIPE_CONNECTED) { alert('Connect Stripe in Integrations to generate payment links.'); return; }
        const id = window.viewingInvoiceId;
        const inv = invoicesData.find(i => i.id === id);
        if (!id || !inv) return;
        const btn = document.getElementById('viewGenerateLinkBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'Generating...'; }
        try {
            const r = await fetch(INVOICE_API + '/stripe-payment-link', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    amount: inv.amount,
                    invoice_number: inv.invoiceNumber,
                    invoice_id: id,
                    currency: 'usd',
                }),
            });
            const json = await r.json().catch(() => ({}));
            if (r.ok && json.success && json.data?.url) {
                document.getElementById('viewStripePaymentUrl').value = json.data.url;
                const idx = invoicesData.findIndex(i => i.id === id);
                if (idx >= 0) invoicesData[idx] = { ...invoicesData[idx], stripe_payment_url: json.data.url, stripe_link_generated: true };
                updateViewGenerateLinkBtn(true);
                loadInvoices();
                loadPaymentTracking();
            } else {
                alert(json.message || 'Failed to generate payment link.');
            }
        } catch (e) {
            console.error(e);
            alert('Error generating payment link.');
        } finally {
            const stillGenerated = document.getElementById('viewStripePaymentUrl')?.value;
            if (btn && !stillGenerated) { btn.disabled = false; btn.textContent = 'Generate Link'; }
        }
    }

    function closeViewInvoiceModal() {
        document.getElementById('viewInvoiceModal').style.display = 'none';
        window.viewingInvoiceId = null;
    }

    function downloadInvoicePdf(invoiceId) {
        const id = invoiceId ?? window.viewingInvoiceId;
        if (!id) {
            alert('No invoice selected.');
            return;
        }
        const inv = invoicesData.find(i => i.id === id);
        const filename = (inv?.invoiceNumber || 'invoice') + '.pdf';
        const pdfUrl = INVOICE_API + "/" + id + "/pdf";
        const a = document.createElement('a');
        a.href = pdfUrl;
        a.download = filename;
        a.rel = 'noopener noreferrer';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function downloadInvoice() {
        downloadInvoicePdf(window.viewingInvoiceId);
    }

    async function sendInvoiceEmail(invoiceId, options = {}) {
        const id = invoiceId ?? window.viewingInvoiceId;
        if (!id) {
            alert('No invoice selected.');
            return;
        }
        const overlay = document.getElementById('sendEmailOverlay');
        if (overlay) overlay.style.display = 'flex';
        try {
            const payload = {};
            if (options.email_subject && !isPlaceholderEmailSubject(options.email_subject)) {
                payload.email_subject = options.email_subject;
            }
            if (options.cutoff_date) payload.cutoff_date = options.cutoff_date;
            const r = await fetch(INVOICE_API + '/' + id + '/send-email', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload),
            });
            const json = await r.json().catch(() => ({}));
            if (overlay) overlay.style.display = 'none';
            if (r.ok && json.success) {
                alert(json.message || 'Invoice sent successfully!');
                loadInvoices();
                loadInvoiceStats();
                loadPaymentTracking();
            } else {
                alert(json.message || 'Failed to send invoice.');
            }
        } catch (e) {
            console.error(e);
            if (overlay) overlay.style.display = 'none';
            alert('Error sending invoice.');
        }
    }

    function updateViewInvoice() {
        const invoice = invoicesData.find(i => i.id === window.viewingInvoiceId);
        if (!invoice) return;
        closeViewInvoiceModal();
        openEditInvoiceModal(invoice.id);
    }

    async function deleteInvoice(invoiceId) {
        if (!canDeleteInvoices) return;
        const invoice = invoicesData.find(i => i.id === invoiceId);
        const label = invoice ? invoice.invoiceNumber : 'this invoice';
        if (!confirm('Are you sure you want to delete invoice ' + label + '? This cannot be undone.')) return;
        try {
            const r = await fetch(INVOICE_API + '/' + invoiceId, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
            const json = await r.json();
            if (r.ok && json.success) {
                loadInvoices();
                loadInvoiceStats();
                loadPaymentTracking();
            } else {
                alert(json.message || 'Failed to delete invoice.');
            }
        } catch (e) {
            console.error(e);
            alert('Error deleting invoice.');
        }
    }

    async function deleteViewInvoice() {
        const invoice = invoicesData.find(i => i.id === window.viewingInvoiceId);
        if (!invoice) return;
        if (!confirm('Are you sure you want to delete invoice ' + invoice.invoiceNumber + '? This cannot be undone.')) return;
        try {
            const r = await fetch(INVOICE_API + '/' + invoice.id, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': CSRF } });
            const json = await r.json();
            if (r.ok && json.success) {
                closeViewInvoiceModal();
                loadInvoices();
                loadInvoiceStats();
                loadPaymentTracking();
            } else {
                alert(json.message || 'Failed to delete invoice.');
            }
        } catch (e) {
            console.error(e);
            alert('Error deleting invoice.');
        }
    }

    // Edit Invoice Modal
    function parseDisplayDate(str) {
        if (!str) return '';
        const d = new Date(str);
        if (isNaN(d.getTime())) return '';
        return d.toISOString().split('T')[0];
    }

    async function openEditInvoiceModal(invoiceId) {
        try {
            const r = await fetch(INVOICE_API + '/' + invoiceId);
            const json = await r.json();
            if (!r.ok || !json.success || !json.data) {
                alert('Could not load invoice.');
                return;
            }
            const invoice = json.data;
            document.getElementById('editInvoiceId').value = invoice.id;
            document.getElementById('editInvoiceOriginalStatus').value = invoice.status || 'draft';
            document.getElementById('editInvoiceNumber').value = invoice.invoiceNumber;
            document.getElementById('editInvoiceClient').value = invoice.client_id;
            document.getElementById('editInvoiceDate').value = invoice.date_raw || parseDisplayDate(invoice.date);
            document.getElementById('editInvoiceDueDate').value = invoice.due_date_raw || parseDisplayDate(invoice.dueDate);
            document.getElementById('editInvoiceStatus').value = invoice.status || 'draft';
            document.getElementById('editInvoiceNotes').value = invoice.notes || '';
            document.getElementById('editInvoiceTaxRate').value = invoice.taxRate ?? 0;
            const editWiseField = document.getElementById('editInvoiceWisePaymentUrl');
            if (editWiseField) editWiseField.value = invoice.wise_payment_url || '';

            const isSent = (invoice.status || '').toLowerCase() === 'sent';
            const container = document.getElementById('editInvoiceLineItems');
            const editHeader = document.getElementById('editLineItemHeader');
            const items = invoice.items && invoice.items.length ? invoice.items : [{ description: 'Invoice amount', quantity: 1, unit_price: invoice.amount }];

            if (isSent) {
                if (editHeader) editHeader.style.display = 'none';
                // Read-only display for sent invoices
                container.innerHTML = `
                    <div style="background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:0.5rem;">
                        <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--border);">
                                    <th style="padding:0.5rem 0.75rem;text-align:left;color:var(--text-secondary);font-weight:500;">Description</th>
                                    <th style="padding:0.5rem 0.75rem;text-align:right;color:var(--text-secondary);font-weight:500;">Hours</th>
                                    <th style="padding:0.5rem 0.75rem;text-align:right;color:var(--text-secondary);font-weight:500;">Net Pay</th>
                                    <th style="padding:0.5rem 0.75rem;text-align:right;color:var(--text-secondary);font-weight:500;">Rate</th>
                                    <th style="padding:0.5rem 0.75rem;text-align:right;color:var(--text-secondary);font-weight:500;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${items.map(item => `
                                    <tr style="border-bottom:1px solid var(--border);">
                                        <td style="padding:0.625rem 0.75rem;color:var(--text-primary);">${(item.description || '—').replace(/</g,'&lt;')}</td>
                                        <td style="padding:0.625rem 0.75rem;text-align:right;">
                                            <input type="number" class="form-input form-input-narrow edit-sent-line-hours" data-item-id="${item.id}" min="0" step="0.01" value="${item.hours_worked != null && item.hours_worked !== '' ? item.hours_worked : ''}" style="max-width:90px;margin-left:auto;display:block;" title="Hours worked">
                                        </td>
                                        <td style="padding:0.625rem 0.75rem;text-align:right;">
                                            <input type="number" class="form-input form-input-narrow edit-sent-line-net-pay" data-item-id="${item.id}" min="0" step="0.01" value="${item.net_pay != null && item.net_pay !== '' ? item.net_pay : ''}" style="max-width:90px;margin-left:auto;display:block;" title="Net pay for P&amp;L">
                                        </td>
                                        <td style="padding:0.625rem 0.75rem;text-align:right;color:var(--text-primary);">$${(+item.unit_price).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                                        <td style="padding:0.625rem 0.75rem;text-align:right;font-weight:600;color:var(--text-primary);">$${Number(item.total ?? item.unit_price ?? 0).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
                                    </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>
                    <p style="font-size:0.8125rem;color:var(--text-muted);display:flex;align-items:center;gap:0.375rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;flex-shrink:0;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Line items are locked for sent invoices, but hours and net pay can still be updated.
                    </p>`;
                document.querySelector('[onclick="addEditLineItem()"]').style.display = 'none';
                document.getElementById('editInvoiceTaxRate').setAttribute('readonly', true);
                document.getElementById('editInvoiceTaxRate').style.opacity = '0.6';
                document.getElementById('editInvoiceTaxRate').style.pointerEvents = 'none';
            } else {
                if (editHeader) editHeader.style.display = 'grid';
                container.innerHTML = items.map(item => `
                    <div class="line-item-row line-item-grid new-invoice-line-grid">
                        <input type="text" class="form-input invoice-line-desc" placeholder="Description (employee name)" name="edit_line_desc[]" list="invoiceLineEmployeeOptions" autocomplete="off" value="${(item.description || '').replace(/"/g, '&quot;')}">
                        <input type="number" class="form-input form-input-narrow" placeholder="Hours" name="edit_line_hours[]" min="0" step="0.01" title="Hours worked" value="${item.hours_worked != null && item.hours_worked !== '' ? item.hours_worked : ''}">
                        <input type="number" class="form-input form-input-narrow" placeholder="Net Pay ($)" name="edit_line_net_pay[]" min="0" step="0.01" title="Net pay for P&amp;L" value="${item.net_pay != null && item.net_pay !== '' ? item.net_pay : ''}">
                        <input type="number" class="form-input form-input-narrow" placeholder="Rate ($)" name="edit_line_rate[]" min="0" step="0.01" value="${item.unit_price || 0}" oninput="updateEditInvoiceTotals()">
                        <span class="line-amount">$${(item.quantity * item.unit_price || 0).toLocaleString()}</span>
                        <button type="button" class="icon-btn icon-btn-danger" onclick="removeEditLineItem(this)" title="Remove">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                `).join('');
                document.querySelector('[onclick="addEditLineItem()"]').style.display = '';
                document.getElementById('editInvoiceTaxRate').removeAttribute('readonly');
                document.getElementById('editInvoiceTaxRate').style.opacity = '';
                document.getElementById('editInvoiceTaxRate').style.pointerEvents = '';
            }
            updateEditInvoiceTotals();
            await onEditInvoiceClientChanged(document.getElementById('editInvoiceClient')?.value || '');
            document.getElementById('editInvoiceModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        } catch (e) {
            console.error(e);
            alert('Error loading invoice.');
        }
    }

    function closeEditInvoiceModal() {
        document.getElementById('editInvoiceModal').style.display = 'none';
        document.body.style.overflow = '';
    }

    function addEditLineItem() {
        const container = document.getElementById('editInvoiceLineItems');
        const row = document.createElement('div');
        row.className = 'line-item-row line-item-grid new-invoice-line-grid';
        row.innerHTML = `
            <input type="text" class="form-input invoice-line-desc" placeholder="Description (employee name)" name="edit_line_desc[]" list="invoiceLineEmployeeOptions" autocomplete="off">
            <input type="number" class="form-input form-input-narrow" placeholder="Hours" name="edit_line_hours[]" min="0" step="0.01" title="Hours worked">
            <input type="number" class="form-input form-input-narrow" placeholder="Net Pay ($)" name="edit_line_net_pay[]" min="0" step="0.01" title="Net pay for P&amp;L">
            <input type="number" class="form-input form-input-narrow" placeholder="Rate ($)" name="edit_line_rate[]" min="0" step="0.01" oninput="updateEditInvoiceTotals()">
            <span class="line-amount">$0.00</span>
            <button type="button" class="icon-btn icon-btn-danger" onclick="removeEditLineItem(this)" title="Remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        `;
        container.appendChild(row);
        bindInvoiceLineDescriptionAutocomplete('editInvoiceLineItems', 'edit_line_desc[]');
    }

    function removeEditLineItem(btn) {
        const rows = document.querySelectorAll('#editInvoiceLineItems .line-item-row');
        if (rows.length > 1) {
            btn.closest('.line-item-row').remove();
            updateEditInvoiceTotals();
        }
    }

    function updateEditInvoiceTotals() {
        const rows = document.querySelectorAll('#editInvoiceLineItems .line-item-row');
        let subtotal = 0;
        rows.forEach(row => {
            const rate = parseFloat(row.querySelector('[name="edit_line_rate[]"]')?.value) || 0;
            const amount = rate;
            subtotal += amount;
            const amtEl = row.querySelector('.line-amount');
            if (amtEl) amtEl.textContent = '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });
        const taxRate = parseFloat(document.getElementById('editInvoiceTaxRate')?.value) || 0;
        const taxAmount = subtotal * (taxRate / 100);
        const total = subtotal + taxAmount;
        document.getElementById('editInvoiceSubtotal').textContent = '$' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('editInvoiceTaxAmount').textContent = '$' + taxAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('editInvoiceTotal').textContent = '$' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function parseLineItemHours(raw) {
        if (raw === '' || raw == null) return null;
        const value = parseFloat(raw);
        return Number.isNaN(value) ? null : value;
    }

    async function saveEditInvoice() {
        const id = parseInt(document.getElementById('editInvoiceId').value, 10);
        const originalStatus = document.getElementById('editInvoiceOriginalStatus').value;
        const isSent = originalStatus === 'sent';
        const clientId = document.getElementById('editInvoiceClient').value;
        const date = document.getElementById('editInvoiceDate').value;
        const dueDate = document.getElementById('editInvoiceDueDate').value;
        const status = document.getElementById('editInvoiceStatus').value;
        const notes = document.getElementById('editInvoiceNotes').value;
        const taxRate = parseFloat(document.getElementById('editInvoiceTaxRate')?.value) || 0;
        const wiseUrl = (document.getElementById('editInvoiceWisePaymentUrl')?.value || '').trim() || null;

        if (!clientId || !date || !dueDate) {
            alert('Please fill in required fields.');
            return;
        }

        const payload = {
            client_id: parseInt(clientId, 10),
            invoice_date: date,
            due_date: dueDate,
            status,
            tax_rate: taxRate,
            notes: notes || null,
            wise_payment_url: wiseUrl,
        };

        if (!isSent) {
            const items = [];
            document.querySelectorAll('#editInvoiceLineItems .line-item-row').forEach(row => {
                const rate = parseFloat(row.querySelector('[name="edit_line_rate[]"]')?.value) || 0;
                const desc = row.querySelector('[name="edit_line_desc[]"]')?.value || '';
                const hours = parseLineItemHours(row.querySelector('[name="edit_line_hours[]"]')?.value);
                const netPay = parseFloat(row.querySelector('[name="edit_line_net_pay[]"]')?.value) || 0;
                if (rate > 0) {
                    const item = { description: desc, quantity: 1, unit_price: rate };
                    if (hours !== null) item.hours_worked = hours;
                    if (netPay > 0) item.net_pay = netPay;
                    items.push(item);
                }
            });

            if (items.length === 0) {
                alert('Please add at least one line item.');
                return;
            }

            payload.items = items;
        } else {
            const sentItemsById = new Map();
            document.querySelectorAll('.edit-sent-line-hours').forEach(input => {
                const itemId = parseInt(input.dataset.itemId, 10);
                if (!itemId) return;
                if (!sentItemsById.has(itemId)) {
                    sentItemsById.set(itemId, { id: itemId });
                }
                sentItemsById.get(itemId).hours_worked = parseLineItemHours(input.value);
            });
            document.querySelectorAll('.edit-sent-line-net-pay').forEach(input => {
                const itemId = parseInt(input.dataset.itemId, 10);
                if (!itemId) return;
                if (!sentItemsById.has(itemId)) {
                    sentItemsById.set(itemId, { id: itemId });
                }
                const netPay = parseFloat(input.value);
                if (!isNaN(netPay) && netPay > 0) {
                    sentItemsById.get(itemId).net_pay = netPay;
                }
            });
            const hoursItems = Array.from(sentItemsById.values());
            if (hoursItems.length > 0) {
                payload.items = hoursItems;
            }
        }

        try {
            const r = await fetch(INVOICE_API + '/' + id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify(payload)
            });
            const json = await r.json();
            if (r.ok && json.success) {
                closeEditInvoiceModal();
                loadInvoices();
                loadInvoiceStats();
            } else {
                alert(json.message || 'Failed to update invoice.');
            }
        } catch (e) {
            console.error(e);
            alert('Error updating invoice.');
        }
    }

    function addLineItem() {
        const container = document.getElementById('invoiceLineItems');
        const row = document.createElement('div');
        row.className = 'line-item-row line-item-grid new-invoice-line-grid';
        row.innerHTML = `
            <input type="text" class="form-input invoice-line-desc" placeholder="Description (employee name)" name="line_desc[]" list="invoiceLineEmployeeOptions" autocomplete="off">
            <input type="number" class="form-input form-input-narrow" placeholder="Hours" name="line_hours[]" min="0" step="0.01" title="Hours worked">
            <input type="number" class="form-input form-input-narrow" placeholder="Net Pay ($)" name="line_net_pay[]" min="0" step="0.01" title="Net pay for P&amp;L">
            <input type="number" class="form-input form-input-narrow" placeholder="Rate ($)" name="line_rate[]" min="0" step="0.01" oninput="updateInvoiceTotals()">
            <span class="line-amount">$0.00</span>
            <button type="button" class="icon-btn icon-btn-danger" onclick="removeLineItem(this)" title="Remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        `;
        container.appendChild(row);
        bindInvoiceLineDescriptionAutocomplete('invoiceLineItems', 'line_desc[]');
    }

    function removeLineItem(btn) {
        const rows = document.querySelectorAll('#invoiceLineItems .line-item-row');
        if (rows.length > 1) {
            btn.closest('.line-item-row').remove();
            updateInvoiceTotals();
        }
    }

    function updateInvoiceTotals() {
        const rows = document.querySelectorAll('#invoiceLineItems .line-item-row');
        let subtotal = 0;

        rows.forEach(row => {
            const rate = parseFloat(row.querySelector('[name="line_rate[]"]').value) || 0;
            const amount = rate;
            subtotal += amount;
            row.querySelector('.line-amount').textContent = '$' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });

        const taxRate = parseFloat(document.getElementById('invoiceTaxRate').value) || 0;
        const taxAmount = subtotal * (taxRate / 100);
        const total = subtotal + taxAmount;

        document.getElementById('invoiceSubtotal').textContent = '$' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('invoiceTaxAmount').textContent = '$' + taxAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('invoiceTotal').textContent = '$' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    async function saveNewInvoice() {
        const clientId = document.getElementById('invoiceClient').value;
        const date = document.getElementById('invoiceDate').value;
        const dueDate = document.getElementById('invoiceDueDate').value;
        const notes = document.getElementById('invoiceNotes').value;
        const taxRate = parseFloat(document.getElementById('invoiceTaxRate')?.value) || 0;
        const wiseUrl = (document.getElementById('invoiceWisePaymentUrl')?.value || '').trim() || null;

        if (!clientId) {
            alert('Please select a client.');
            return;
        }
        if (!date || !dueDate) {
            alert('Please fill in the invoice and due dates.');
            return;
        }

        const items = [];
        document.querySelectorAll('#invoiceLineItems .line-item-row').forEach(row => {
            const rate = parseFloat(row.querySelector('[name="line_rate[]"]')?.value) || 0;
            const desc = row.querySelector('[name="line_desc[]"]')?.value || '';
            const hours = parseLineItemHours(row.querySelector('[name="line_hours[]"]')?.value);
            const netPay = parseFloat(row.querySelector('[name="line_net_pay[]"]')?.value) || 0;
            if (rate > 0) {
                const item = { description: desc, quantity: 1, unit_price: rate };
                if (hours !== null) item.hours_worked = hours;
                if (netPay > 0) item.net_pay = netPay;
                items.push(item);
            }
        });

        if (items.length === 0) {
            alert('Please add at least one line item.');
            return;
        }

        try {
            const r = await fetch(INVOICE_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({
                    client_id: parseInt(clientId, 10),
                    invoice_date: date,
                    due_date: dueDate,
                    status: 'draft',
                    tax_rate: taxRate,
                    notes: notes || null,
                    stripe_payment_url: null,
                    wise_payment_url: wiseUrl,
                    items
                })
            });
            const json = await r.json();
            if (r.ok && json.success) {
                closeNewInvoiceModal();
                loadInvoices();
                loadInvoiceStats();
                loadPaymentTracking();
            } else {
                alert(json.message || 'Failed to create invoice.');
            }
        } catch (e) {
            console.error(e);
            alert('Error creating invoice. Please try again.');
        }
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        const newInvoiceModal = document.getElementById('newInvoiceModal');
        if (newInvoiceModal && event.target === newInvoiceModal) closeNewInvoiceModal();
        const viewInvoiceModal = document.getElementById('viewInvoiceModal');
        if (viewInvoiceModal && event.target === viewInvoiceModal) closeViewInvoiceModal();
        const editInvoiceModal = document.getElementById('editInvoiceModal');
        if (editInvoiceModal && event.target === editInvoiceModal) closeEditInvoiceModal();
        const newSubscriptionModal = document.getElementById('newSubscriptionModal');
        if (newSubscriptionModal && event.target === newSubscriptionModal) closeNewSubscriptionModal();
        const editSubscriptionModal = document.getElementById('editSubscriptionModal');
        if (editSubscriptionModal && event.target === editSubscriptionModal) closeEditSubscriptionModal();
        const wiseIncomingModal = document.getElementById('wiseIncomingModal');
        if (wiseIncomingModal && event.target === wiseIncomingModal) closeWiseIncomingModal();
    });

    function openAutomationSettings() {
        alert('Automated invoice generation settings would open here');
    }

    function recordPayment() {
        alert('Record payment modal would open here');
    }

    function createSubscription() {
        const modal = document.getElementById('newSubscriptionModal');
        const form = document.getElementById('newSubscriptionForm');
        form.reset();
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('subscriptionStartDate').value = today;
        modal.style.display = 'flex';
    }

    function closeNewSubscriptionModal() {
        document.getElementById('newSubscriptionModal').style.display = 'none';
    }

    function parseCycle(cycleValue) {
        const map = { month_1: ['month', 1], month_3: ['month', 3], month_6: ['month', 6], year_1: ['year', 1] };
        return map[cycleValue] || ['month', 1];
    }

    async function saveNewSubscription() {
        const clientId = document.getElementById('subscriptionClient')?.value;
        const productName = document.getElementById('subscriptionPlan')?.value?.trim();
        const cycleValue = document.getElementById('subscriptionCycle')?.value;
        const amountDollars = parseFloat(document.getElementById('subscriptionAmount')?.value) || 0;
        const currency = document.getElementById('subscriptionCurrency')?.value || 'usd';
        const startDateRaw = document.getElementById('subscriptionStartDate')?.value;
        const status = document.getElementById('subscriptionStatus')?.value || 'active';
        const trialDays = parseInt(document.getElementById('subscriptionTrialDays')?.value, 10) || 0;
        const notes = document.getElementById('subscriptionNotes')?.value?.trim() || null;

        if (!clientId || !productName || !cycleValue || !startDateRaw) {
            alert('Please fill in Client, Product/Plan, Billing Cycle, and Current Period Start.');
            return;
        }

        const [interval, intervalCount] = parseCycle(cycleValue);
        const unitAmount = Math.round(amountDollars * 100);

        const trialEnd = trialDays > 0 ? (() => {
            const d = new Date(startDateRaw);
            d.setDate(d.getDate() + trialDays);
            return d.toISOString().slice(0, 10);
        })() : null;

        const payload = {
            client_id: parseInt(clientId, 10),
            product_name: productName,
            unit_amount: unitAmount,
            currency: currency,
            interval,
            interval_count: intervalCount,
            status,
            current_period_start: startDateRaw,
            trial_end: trialEnd,
            notes,
        };

        try {
            const r = await fetch(SUBSCRIPTIONS_API, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify(payload),
            });
            const json = await r.json();
            if (json.success && json.data) {
                subscriptionsData.push(json.data);
                renderSubscriptionStats();
                renderSubscriptions();
                updateBillingPagination();
                closeNewSubscriptionModal();
                alert('Subscription created successfully.');
            } else {
                const msg = json.message || json.errors ? Object.values(json.errors || {}).flat().join(' ') : 'Failed to create subscription.';
                alert(msg);
            }
        } catch (e) {
            console.error('Save subscription:', e);
            alert('Failed to create subscription. Please try again.');
        }
    }

    function isValidPaymentUrl(u) {
        return u && typeof u === 'string' && (u.startsWith('http://') || u.startsWith('https://'));
    }

    async function copySubscriptionPaymentLink(subId, cachedUrl) {
        let url = isValidPaymentUrl(cachedUrl) ? cachedUrl : null;
        if (!url) {
            try {
                const r = await fetch(SUBSCRIPTIONS_API + '/' + subId + '/payment-link');
                const json = await r.json();
                if (json.success && isValidPaymentUrl(json.url)) {
                    url = json.url;
                    const sub = subscriptionsData.find(s => s.id === subId);
                    if (sub) sub.hosted_invoice_url = url;
                }
            } catch (e) { console.error(e); }
        }
        if (url) {
            try {
                await navigator.clipboard.writeText(url);
                alert('Payment link copied to clipboard. Send this link to your client to view and pay their invoice.\n\nNote: In Stripe test mode, no emails are sent—use this link to share with your client.');
            } catch (e) {
                prompt('Copy this payment link to send to your client:', url);
            }
        } else {
            alert('No payment link available. The subscription may have a trial (link available after trial ends), or it may not be connected to Stripe.');
        }
    }

    function getCycleValue(interval, intervalCount) {
        const map = { 'month_1': ['month', 1], 'month_3': ['month', 3], 'month_6': ['month', 6], 'year_1': ['year', 1] };
        for (const [k, [i, c]] of Object.entries(map)) {
            if (i === interval && c === intervalCount) return k;
        }
        return intervalCount === 1 ? (interval === 'month' ? 'month_1' : 'year_1') : 'month_1';
    }

    function openEditSubscriptionModal(subId) {
        const sub = subscriptionsData.find(s => s.id === subId || s.id === parseInt(subId, 10));
        if (!sub) return;
        document.getElementById('editSubscriptionId').value = sub.id;
        document.getElementById('editSubscriptionClient').value = sub.client || '-';
        document.getElementById('editSubscriptionPlan').value = sub.product_name || '';
        document.getElementById('editSubscriptionCycle').value = getCycleValue(sub.interval || 'month', sub.interval_count || 1);
        document.getElementById('editSubscriptionAmount').value = sub.amount ?? '';
        document.getElementById('editSubscriptionCurrency').value = (sub.currency || 'usd').toLowerCase();
        const startStr = sub.current_period_start;
        document.getElementById('editSubscriptionStartDate').value = startStr ? startStr.slice(0, 10) : '';
        document.getElementById('editSubscriptionStatus').value = sub.status || 'active';
        document.getElementById('editSubscriptionTrialDays').value = sub.trial_end ? '' : '';
        document.getElementById('editSubscriptionNotes').value = sub.notes || '';
        document.getElementById('editSubscriptionModal').style.display = 'flex';
    }

    function closeEditSubscriptionModal() {
        document.getElementById('editSubscriptionModal').style.display = 'none';
    }

    async function saveUpdatedSubscription() {
        const id = document.getElementById('editSubscriptionId')?.value;
        if (!id) return;
        const productName = document.getElementById('editSubscriptionPlan')?.value?.trim();
        const cycleValue = document.getElementById('editSubscriptionCycle')?.value;
        const amountDollars = parseFloat(document.getElementById('editSubscriptionAmount')?.value) || 0;
        const currency = document.getElementById('editSubscriptionCurrency')?.value || 'usd';
        const startDateRaw = document.getElementById('editSubscriptionStartDate')?.value;
        const status = document.getElementById('editSubscriptionStatus')?.value;
        const notes = document.getElementById('editSubscriptionNotes')?.value?.trim() || null;

        const [interval, intervalCount] = parseCycle(cycleValue);
        const unitAmount = Math.round(amountDollars * 100);

        const payload = {
            product_name: productName,
            unit_amount: unitAmount,
            currency,
            interval,
            interval_count: intervalCount,
            status,
            notes,
        };
        if (startDateRaw) payload.current_period_start = startDateRaw;

        try {
            const r = await fetch(SUBSCRIPTIONS_API + '/' + id, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify(payload),
            });
            const json = await r.json();
            if (json.success && json.data) {
                const idx = subscriptionsData.findIndex(s => s.id == id);
                if (idx >= 0) subscriptionsData[idx] = json.data;
                renderSubscriptionStats();
                renderSubscriptions();
                updateBillingPagination();
                closeEditSubscriptionModal();
                alert('Subscription updated successfully.');
            } else {
                alert(json.message || 'Failed to update subscription.');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to update subscription. Please try again.');
        }
    }

    async function cancelSubscription(subId) {
        if (!confirm('Are you sure you want to cancel this subscription? This will also cancel it in Stripe if connected.')) return;
        try {
            const r = await fetch(SUBSCRIPTIONS_API + '/' + subId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ status: 'canceled' }),
            });
            const json = await r.json();
            if (json.success && json.data) {
                const idx = subscriptionsData.findIndex(s => s.id == subId);
                if (idx >= 0) subscriptionsData[idx] = json.data;
                renderSubscriptionStats();
                renderSubscriptions();
                updateBillingPagination();
                alert('Subscription canceled.');
            } else {
                alert(json.message || 'Failed to cancel subscription.');
            }
        } catch (e) {
            console.error(e);
            alert('Failed to cancel subscription. Please try again.');
        }
    }

    // Window Resize Handler
    window.addEventListener('resize', () => {
        renderInvoices();
        renderPayments();
        renderSubscriptions();
    });

    function updateBillingPagination() {
        const filtered = subscriptionsData.filter(sub => {
            if (subscriptionsStatusFilter === 'all') return true;
            if (subscriptionsStatusFilter === 'active') return (sub.status || '') !== 'canceled';
            if (subscriptionsStatusFilter === 'canceled') return (sub.status || '') === 'canceled';
            return true;
        });
        const subsInfo = document.getElementById('subscriptionsPaginationInfo');
        if (subsInfo) subsInfo.textContent = `Showing 1 to ${filtered.length} of ${filtered.length} results`;
    }

    document.querySelectorAll('.sub-tab-btn[data-subscription-status]').forEach(btn => {
        btn.addEventListener('click', function() {
            subscriptionsStatusFilter = this.dataset.subscriptionStatus;
            document.querySelectorAll('.sub-tab-btn[data-subscription-status]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            renderSubscriptions();
            updateBillingPagination();
        });
    });

    document.getElementById('invoiceStatusFilter')?.addEventListener('change', () => { loadInvoices(); loadInvoiceStats(); });
    document.getElementById('invoiceMonthFilter')?.addEventListener('change', () => { loadInvoices(); loadInvoiceStats(); });
    document.getElementById('paymentDateFilter')?.addEventListener('change', () => loadPaymentTracking());
    document.getElementById('dashboardPeriodFilter')?.addEventListener('change', () => loadDashboard());

    document.getElementById('invoiceClient')?.addEventListener('change', function () {
        onInvoiceClientChanged(this.value);
    });
    document.getElementById('editInvoiceClient')?.addEventListener('change', function () {
        onEditInvoiceClientChanged(this.value);
    });

    // Initialize - populate clients from server first, then refresh from API
    populateClientSelects(BILLING_CLIENTS);
    loadClients();
    loadInvoices().then(() => {
        const params = new URLSearchParams(window.location.search);
        const invoiceId = params.get('invoice');
        if (invoiceId) {
            openViewInvoiceModal(parseInt(invoiceId, 10));
            history.replaceState({}, '', window.location.pathname);
        }
    });
    loadInvoiceStats();
    loadPaymentTracking();
    loadSubscriptions();
    loadDashboard();
    loadWiseReconcileStatus();

    if (typeof addEditLineItem === 'function') window.addEditLineItem = addEditLineItem;
    if (typeof addLineItem === 'function') window.addLineItem = addLineItem;
    if (typeof bulkGenerateStripeLinks === 'function') window.bulkGenerateStripeLinks = bulkGenerateStripeLinks;
    if (typeof cancelSubscription === 'function') window.cancelSubscription = cancelSubscription;
    if (typeof clearInvoiceSelection === 'function') window.clearInvoiceSelection = clearInvoiceSelection;
    if (typeof closeEditInvoiceModal === 'function') window.closeEditInvoiceModal = closeEditInvoiceModal;
    if (typeof closeEditSubscriptionModal === 'function') window.closeEditSubscriptionModal = closeEditSubscriptionModal;
    if (typeof closeNewInvoiceModal === 'function') window.closeNewInvoiceModal = closeNewInvoiceModal;
    if (typeof closeNewSubscriptionModal === 'function') window.closeNewSubscriptionModal = closeNewSubscriptionModal;
    if (typeof closeSendInvoiceEmailModal === 'function') window.closeSendInvoiceEmailModal = closeSendInvoiceEmailModal;
    if (typeof closeViewInvoiceModal === 'function') window.closeViewInvoiceModal = closeViewInvoiceModal;
    if (typeof closeWiseIncomingModal === 'function') window.closeWiseIncomingModal = closeWiseIncomingModal;
    if (typeof confirmSendInvoiceEmail === 'function') window.confirmSendInvoiceEmail = confirmSendInvoiceEmail;
    if (typeof copyViewStripePaymentLink === 'function') window.copyViewStripePaymentLink = copyViewStripePaymentLink;
    if (typeof copyViewWisePaymentLink === 'function') window.copyViewWisePaymentLink = copyViewWisePaymentLink;
    if (typeof createInvoice === 'function') window.createInvoice = createInvoice;
    if (typeof createSubscription === 'function') window.createSubscription = createSubscription;
    if (typeof deleteInvoice === 'function') window.deleteInvoice = deleteInvoice;
    if (typeof deleteViewInvoice === 'function') window.deleteViewInvoice = deleteViewInvoice;
    if (typeof downloadInvoice === 'function') window.downloadInvoice = downloadInvoice;
    if (typeof downloadInvoicePdf === 'function') window.downloadInvoicePdf = downloadInvoicePdf;
    if (typeof generateViewStripePaymentLink === 'function') window.generateViewStripePaymentLink = generateViewStripePaymentLink;
    if (typeof loadWiseIncomingPayments === 'function') window.loadWiseIncomingPayments = loadWiseIncomingPayments;
    if (typeof markWisePaymentPaid === 'function') window.markWisePaymentPaid = markWisePaymentPaid;
    if (typeof openEditInvoiceModal === 'function') window.openEditInvoiceModal = openEditInvoiceModal;
    if (typeof openEditSubscriptionModal === 'function') window.openEditSubscriptionModal = openEditSubscriptionModal;
    if (typeof openSendInvoiceEmailModal === 'function') window.openSendInvoiceEmailModal = openSendInvoiceEmailModal;
    if (typeof openViewInvoiceModal === 'function') window.openViewInvoiceModal = openViewInvoiceModal;
    if (typeof openWiseIncomingModal === 'function') window.openWiseIncomingModal = openWiseIncomingModal;
    if (typeof removeEditLineItem === 'function') window.removeEditLineItem = removeEditLineItem;
    if (typeof removeLineItem === 'function') window.removeLineItem = removeLineItem;
    if (typeof saveEditInvoice === 'function') window.saveEditInvoice = saveEditInvoice;
    if (typeof saveNewInvoice === 'function') window.saveNewInvoice = saveNewInvoice;
    if (typeof saveNewSubscription === 'function') window.saveNewSubscription = saveNewSubscription;
    if (typeof saveUpdatedSubscription === 'function') window.saveUpdatedSubscription = saveUpdatedSubscription;
    if (typeof saveViewWisePaymentLink === 'function') window.saveViewWisePaymentLink = saveViewWisePaymentLink;
    if (typeof saveWiseDefaultLink === 'function') window.saveWiseDefaultLink = saveWiseDefaultLink;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
    if (typeof stringify === 'function') window.stringify = stringify;
    if (typeof switchDashboardSource === 'function') window.switchDashboardSource = switchDashboardSource;
    if (typeof toggleInvoiceSelection === 'function') window.toggleInvoiceSelection = toggleInvoiceSelection;
    if (typeof toggleSelectAllInvoices === 'function') window.toggleSelectAllInvoices = toggleSelectAllInvoices;
    if (typeof toggleWiseReconciliation === 'function') window.toggleWiseReconciliation = toggleWiseReconciliation;
    if (typeof updateViewInvoice === 'function') window.updateViewInvoice = updateViewInvoice;
})();
