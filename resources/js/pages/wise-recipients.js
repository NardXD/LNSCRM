/* Vite page entry — IIFE preserves onclick globals */
(function () {
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('wise-load-btn');
    const panel = document.getElementById('wise-panel');
    if (!btn || !panel) return;
    const recipientsList = document.getElementById('wise-recipients-list');
    const recipientsError = document.getElementById('wise-recipients-error');
    const employeesAll = document.getElementById('wise-employees-all');
    const employeesWiseTags = document.getElementById('wise-employees-wise-tags');

    function escapeHtml(s) {
        if (s == null) return '';
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderEmployeeRow(emp, recipients, usedIds) {
        var myId = String(emp.wise_account || '');
        var available = recipients.filter(function(r) {
            // A recipient is "used" if either its UUID or its numeric account_id is already linked
            var rid = String(r.id);
            var raid = r.account_id != null ? String(r.account_id) : rid;
            return (!usedIds.has(rid) && !usedIds.has(raid)) || rid === myId || raid === myId;
        });
        var opts = '<option value="" data-currency="">— Not set —</option>' + available.map(function(r) {
            // Use numeric account_id for payroll transfers when available; fall back to UUID for tag-only contacts
            var payrollId = (r.account_id != null) ? String(r.account_id) : String(r.id);
            var sel = (myId === String(r.id) || myId === payrollId) ? ' selected' : '';
            var cur = (r.currency || '').toUpperCase().substring(0, 3);
            var detail = r.tag ? r.tag : (r.bank || r.accountNumber ? [r.bank, r.accountNumber].filter(Boolean).join(' ') : r.accountSummary || '');
            var label = escapeHtml(r.name) + (detail ? ' — ' + escapeHtml(detail) : '') + ' (' + (r.currency || '') + ')';
            return '<option value="' + payrollId + '" data-currency="' + escapeHtml(cur) + '"' + sel + '>' + label + '</option>';
        }).join('');
        var unlinkBtn = emp.wise_account ? '<button type="button" class="btn-secondary wise-unlink-btn" style="padding:0.375rem 0.5rem;font-size:0.75rem;">Unlink</button>' : '';
        return '<div class="wise-employee-row" data-user-id="' + emp.id + '">' +
            '<span class="emp-name">' + escapeHtml(emp.name) + '</span>' +
            '<select class="wise-account-select">' + opts + '</select>' +
            '<button type="button" class="btn-secondary wise-save-btn" style="padding:0.375rem 0.5rem;font-size:0.75rem;">Save</button>' +
            unlinkBtn +
            '</div>';
    }

    async function loadData() {
        btn.disabled = true;
        btn.textContent = 'Loading...';
        panel.style.display = 'block';
        recipientsList.innerHTML = '';
        recipientsError.style.display = 'none';
        employeesAll.innerHTML = '';
        employeesWiseTags.innerHTML = '';

        var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            var res = await Promise.all([
                fetch('/api/integrations/wise/recipients', { headers: { 'X-CSRF-TOKEN': csrf } }),
                fetch('/api/user-management/employees?per_page=1000', { headers: { 'X-CSRF-TOKEN': csrf } })
            ]);
            var recData = await res[0].json();
            var empData = await res[1].json();

            var recipients = res[0].ok && recData.recipients ? recData.recipients : [];
            var employees = res[1].ok && empData.data ? empData.data : [];

            var withWise = employees.filter(function(e) { return e.wise_account; });
            var withoutWise = employees.filter(function(e) { return !e.wise_account; });
            // usedIds contains whatever is stored in wise_account (may be UUID or numeric ID)
            var usedIds = new Set(withWise.map(function(e) { return String(e.wise_account); }));

            // A recipient is "linked" if its UUID or numeric account_id is already assigned to an employee.
            function isRecipientLinked(r) {
                var rid = String(r.id);
                var raid = r.account_id != null ? String(r.account_id) : rid;
                return usedIds.has(rid) || usedIds.has(raid);
            }

            // Left pool shows only recipients not yet linked to any employee.
            var availableRecipients = recipients.filter(function(r) { return !isRecipientLinked(r); });

            if (recipients.length === 0) {
                recipientsError.textContent = recData.error || 'No recipients found. Add recipients in Wise first.';
                recipientsError.style.display = 'block';
            } else if (availableRecipients.length === 0) {
                recipientsList.innerHTML = '<div class="wise-employee-section">All recipients are linked to an employee.</div>';
            } else {
                recipientsList.innerHTML = availableRecipients.map(function(r) {
                    var lines = '';
                    // Wise @tag line
                    if (r.tag) {
                        lines += '<div class="wise-recipient-tag">' + escapeHtml(r.tag) + '</div>';
                    }
                    // Bank + account number line
                    if (r.bank || r.accountNumber) {
                        var bankParts = [];
                        if (r.bank) bankParts.push(escapeHtml(r.bank));
                        if (r.accountNumber) bankParts.push(escapeHtml(r.accountNumber));
                        lines += '<div class="wise-recipient-detail">' + bankParts.join(' &middot; ') + '</div>';
                    } else if (!r.tag && r.accountSummary) {
                        // Fallback: show accountSummary when no structured detail available
                        lines += '<div class="wise-recipient-detail">' + escapeHtml(r.accountSummary) + '</div>';
                    }
                    return '<div class="wise-recipient-item">' +
                        '<div class="wise-recipient-name">' + escapeHtml(r.name) + (r.currency ? ' <span class="wise-recipient-currency">(' + escapeHtml(r.currency) + ')</span>' : '') + '</div>' +
                        lines +
                        '<div class="wise-recipient-id">ID: <strong>' + r.id + '</strong></div>' +
                    '</div>';
                }).join('');
            }

            // All Contacts shows only unlinked employees; once linked they move to Linked Contacts.
            employeesAll.innerHTML = withoutWise.length
                ? withoutWise.map(function(emp) { return renderEmployeeRow(emp, recipients, usedIds); }).join('')
                : '<div class="wise-employee-section">All contacts are linked to a Wise recipient.</div>';

            employeesWiseTags.innerHTML = withWise.length
                ? withWise.map(function(emp) { return renderEmployeeRow(emp, recipients, usedIds); }).join('')
                : '<div class="wise-employee-section">No linked contacts yet.</div>';

            document.getElementById('wise-tab-all-count').textContent = '(' + withoutWise.length + ')';
            document.getElementById('wise-tab-wisetags-count').textContent = '(' + withWise.length + ')';

            document.querySelectorAll('.wise-unlink-btn').forEach(function(unlinkBtn) {
                unlinkBtn.onclick = async function() {
                    var row = this.closest('.wise-employee-row');
                    var userId = row?.dataset?.userId;
                    if (!userId) return;
                    if (!confirm('Unlink Wise ID from this employee?')) return;
                    this.disabled = true;
                    this.textContent = '...';
                    try {
                        var r = await fetch('/api/integrations/wise/employees/' + userId + '/wise-account', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ wise_account: null })
                        });
                        var d = await r.json();
                        if (r.ok) {
                            loadData();
                        } else {
                            alert(d.error || 'Failed to unlink.');
                            this.disabled = false;
                            this.textContent = 'Unlink';
                        }
                    } catch (e) {
                        alert('Error. Please try again.');
                        this.disabled = false;
                        this.textContent = 'Unlink';
                    }
                };
            });

            document.querySelectorAll('.wise-save-btn').forEach(function(saveBtn) {
                saveBtn.onclick = async function() {
                    var row = this.closest('.wise-employee-row');
                    var userId = row?.dataset?.userId;
                    var select = row?.querySelector('.wise-account-select');
                    if (!userId || !select) return;
                    var val = select.value || '';
                    var opt = select.options[select.selectedIndex];
                    var currency = opt && opt.getAttribute('data-currency') ? opt.getAttribute('data-currency') : null;
                    this.disabled = true;
                    this.textContent = '...';
                    try {
                        var r = await fetch('/api/integrations/wise/employees/' + userId + '/wise-account', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                            body: JSON.stringify({ wise_account: val || null, wise_currency: currency || null })
                        });
                        var d = await r.json();
                        if (r.ok) {
                            this.textContent = 'Saved';
                            var self = this;
                            setTimeout(function() {
                                self.textContent = 'Save';
                                self.disabled = false;
                                loadData();
                            }, 800);
                        } else {
                            alert(d.error || 'Failed to update.');
                            this.textContent = 'Save';
                            this.disabled = false;
                        }
                    } catch (e) {
                        alert('Error updating. Please try again.');
                        this.textContent = 'Save';
                        this.disabled = false;
                    }
                };
            });
        } catch (e) {
            console.error(e);
            recipientsError.textContent = 'Error loading data. Please try again.';
            recipientsError.style.display = 'block';
        }
        btn.disabled = false;
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Refresh';
    }

    document.querySelectorAll('.wise-tab-btn').forEach(function(tab) {
        tab.addEventListener('click', function() {
            var t = this.dataset.tab;
            document.querySelectorAll('.wise-tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.wise-tab-content').forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('wise-tab-' + t).classList.add('active');
        });
    });

    btn.addEventListener('click', loadData);
    loadData();

    // Add Recipient Modal
    var addBtn = document.getElementById('wise-add-recipient-btn');
    var addModal = document.getElementById('wise-add-modal');
    var modalClose = document.getElementById('wise-modal-close');
    var modalCancel = document.getElementById('wise-modal-cancel');
    var addStep1 = document.getElementById('wise-add-step1');
    var addStep2 = document.getElementById('wise-add-step2');
    var addCurrency = document.getElementById('wise-add-currency');
    var loadReqBtn = document.getElementById('wise-add-load-req');
    var addBackBtn = document.getElementById('wise-add-back');
    var addSubmitBtn = document.getElementById('wise-add-submit');
    var addFormContainer = document.getElementById('wise-add-form-container');
    var addErrorEl = document.getElementById('wise-add-error');

    var wiseAddState = { quoteId: null, currency: null, requirements: [], selectedType: null };

    var addTagSection = document.getElementById('wise-add-tag');
    var addTagIdentifier = document.getElementById('wise-add-tag-identifier');
    var addTagCurrency = document.getElementById('wise-add-tag-currency');
    var addTagError = document.getElementById('wise-add-tag-error');
    var addTagSubmit = document.getElementById('wise-add-tag-submit');
    var addTagCancel = document.getElementById('wise-add-tag-cancel');
    var methodBtns = document.querySelectorAll('.wise-add-method-btn');

    function setAddMethod(method) {
        methodBtns.forEach(function(b) { b.classList.toggle('active', b.dataset.method === method); });
        var isTag = method === 'tag';
        addTagSection.style.display = isTag ? 'block' : 'none';
        addStep1.style.display = isTag ? 'none' : 'block';
        addStep2.style.display = 'none';
    }

    function openAddModal() {
        if (addModal) {
            addModal.style.display = 'flex';
            addCurrency.value = '';
            addErrorEl.style.display = 'none';
            if (addTagIdentifier) addTagIdentifier.value = '';
            if (addTagCurrency) addTagCurrency.value = '';
            if (addTagError) addTagError.style.display = 'none';
            wiseAddState = { quoteId: null, currency: null, requirements: [], selectedType: null };
            setAddMethod('bank');
        }
    }
    function closeAddModal() {
        if (addModal) addModal.style.display = 'none';
    }

    methodBtns.forEach(function(b) {
        b.addEventListener('click', function() { setAddMethod(this.dataset.method); });
    });

    if (addTagCancel) addTagCancel.addEventListener('click', closeAddModal);

    if (addTagSubmit) {
        addTagSubmit.addEventListener('click', async function() {
            var identifier = (addTagIdentifier.value || '').trim();
            if (!identifier) {
                addTagError.textContent = 'Enter a Wise tag, email, or phone number.';
                addTagError.style.display = 'block';
                return;
            }
            if (!addTagCurrency.value) {
                addTagError.textContent = 'Select a currency for this Wise recipient.';
                addTagError.style.display = 'block';
                return;
            }
            addTagSubmit.disabled = true;
            addTagSubmit.textContent = 'Adding...';
            addTagError.style.display = 'none';
            var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                var r = await fetch('/api/integrations/wise/contacts', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ identifier: identifier, currency: addTagCurrency.value })
                });
                var d = await r.json();
                if (r.ok) {
                    closeAddModal();
                    loadData();
                    alert('Recipient added successfully' + (d.recipient && d.recipient.name ? ': ' + d.recipient.name : '') + '.');
                } else {
                    addTagError.textContent = d.error || 'Failed to add recipient.';
                    addTagError.style.display = 'block';
                }
            } catch (e) {
                addTagError.textContent = 'Error adding recipient. Please try again.';
                addTagError.style.display = 'block';
            }
            addTagSubmit.disabled = false;
            addTagSubmit.textContent = 'Add recipient';
        });
    }

    function buildFormFromRequirements(reqs, currency) {
        if (!Array.isArray(reqs) || reqs.length === 0) return '';
        var typeOption = reqs[0];
        var rawType = (typeOption.type || 'iban').toString();
        var typeKey = rawType.indexOf('_') >= 0 ? rawType : rawType.replace(/([A-Z])/g, function(m) { return '_' + m.toLowerCase(); }).replace(/^_/, '');
        var title = typeOption.title || typeKey;
        var fields = typeOption.fields || [];
        var html = '<input type="hidden" name="currency" value="' + escapeHtml(currency) + '">';
        html += '<input type="hidden" name="type" value="' + escapeHtml(typeKey) + '">';

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var group = field.group || [];
            for (var j = 0; j < group.length; j++) {
                var g = group[j];
                var key = g.key || '';
                if (!key) continue;
                var label = g.name || key;
                var required = g.required ? ' required' : '';
                var fieldType = (g.type || 'text').toLowerCase();
                var placeholder = g.example || '';

                html += '<div class="wise-form-group">';
                html += '<label for="wise-f-' + escapeHtml(key.replace(/[^a-z0-9]/gi, '_')) + '">' + escapeHtml(label) + (g.required ? ' *' : '') + '</label>';

                if (fieldType === 'select') {
                    var vals = g.valuesAllowed || [];
                    html += '<select id="wise-f-' + escapeHtml(key.replace(/[^a-z0-9]/gi, '_')) + '" class="wise-form-input" name="' + escapeHtml(key) + '"' + required + '>';
                    html += '<option value="">Select...</option>';
                    for (var k = 0; k < vals.length; k++) {
                        var v = vals[k];
                        if (v.key === '' && v.name) continue;
                        html += '<option value="' + escapeHtml(String(v.key || '')) + '">' + escapeHtml(v.name || v.key || '') + '</option>';
                    }
                    html += '</select>';
                } else if (fieldType === 'date') {
                    html += '<input type="date" id="wise-f-' + escapeHtml(key.replace(/[^a-z0-9]/gi, '_')) + '" class="wise-form-input" name="' + escapeHtml(key) + '" placeholder="' + escapeHtml(placeholder) + '"' + required + '>';
                } else {
                    html += '<input type="text" id="wise-f-' + escapeHtml(key.replace(/[^a-z0-9]/gi, '_')) + '" class="wise-form-input" name="' + escapeHtml(key) + '" placeholder="' + escapeHtml(placeholder) + '"' + required + '>';
                }
                html += '</div>';
            }
        }
        return html;
    }

    function collectFormPayload(container, currency, type) {
        var inputs = container.querySelectorAll('input, select');
        var payload = { currency: currency, type: type, accountHolderName: '', details: {} };
        var bareAddressKeys = ['city', 'country', 'firstLine', 'postCode', 'state'];

        function setDeep(obj, dotPath, val) {
            var parts = dotPath.split('.');
            for (var p = 0; p < parts.length - 1; p++) {
                if (obj[parts[p]] == null || typeof obj[parts[p]] !== 'object') obj[parts[p]] = {};
                obj = obj[parts[p]];
            }
            obj[parts[parts.length - 1]] = val;
        }

        function pruneEmpty(obj) {
            Object.keys(obj).forEach(function(k) {
                if (obj[k] === '') {
                    delete obj[k];
                } else if (typeof obj[k] === 'object' && obj[k] !== null) {
                    pruneEmpty(obj[k]);
                    if (Object.keys(obj[k]).length === 0) delete obj[k];
                }
            });
        }

        for (var i = 0; i < inputs.length; i++) {
            var el = inputs[i];
            var name = el.getAttribute('name');
            if (!name || name === 'currency' || name === 'type') continue;
            var val = el.value ? String(el.value).trim() : '';
            var key = name.replace(/\//g, '.');
            if (key === 'accountHolderName') {
                payload.accountHolderName = val;
            } else if (key.indexOf('details.') === 0) {
                // 'details.address.city' → details.address.city
                setDeep(payload.details, key.slice(8), val);
            } else if (key.indexOf('address.') === 0) {
                // 'address.city' → details.address.city (Wise expects city inside details)
                setDeep(payload.details, key, val);
            } else if (bareAddressKeys.indexOf(key) >= 0) {
                // bare 'city' → details.address.city
                setDeep(payload.details, 'address.' + key, val);
            } else {
                setDeep(payload.details, key, val);
            }
        }

        pruneEmpty(payload.details);
        return payload;
    }

    if (addBtn) addBtn.addEventListener('click', openAddModal);
    if (modalClose) modalClose.addEventListener('click', closeAddModal);
    if (modalCancel) modalCancel.addEventListener('click', closeAddModal);
    addModal && addModal.addEventListener('click', function(e) { if (e.target === addModal) closeAddModal(); });

    if (loadReqBtn) {
        loadReqBtn.addEventListener('click', async function() {
            var cur = addCurrency.value;
            if (!cur) { alert('Please select a currency.'); return; }
            loadReqBtn.disabled = true;
            loadReqBtn.textContent = 'Loading...';
            addErrorEl.style.display = 'none';
            var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                var r = await fetch('/api/integrations/wise/recipients/requirements', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ currency: cur })
                });
                var d = await r.json();
                if (r.ok) {
                    wiseAddState.quoteId = d.quote_id;
                    wiseAddState.currency = d.currency;
                    wiseAddState.requirements = d.requirements || [];
                    addFormContainer.innerHTML = buildFormFromRequirements(wiseAddState.requirements, wiseAddState.currency);
                    addStep1.style.display = 'none';
                    addStep2.style.display = 'block';
                } else {
                    addErrorEl.textContent = d.error || 'Failed to load form.';
                    addErrorEl.style.display = 'block';
                }
            } catch (e) {
                addErrorEl.textContent = 'Error loading requirements. Please try again.';
                addErrorEl.style.display = 'block';
            }
            loadReqBtn.disabled = false;
            loadReqBtn.textContent = 'Load form';
        });
    }

    if (addBackBtn) addBackBtn.addEventListener('click', function() {
        addStep2.style.display = 'none';
        addStep1.style.display = 'block';
    });

    if (addSubmitBtn) {
        addSubmitBtn.addEventListener('click', async function() {
            var cur = wiseAddState.currency;
            var type = addFormContainer.querySelector('input[name="type"]');
            type = type ? type.value : 'iban';
            var payload = collectFormPayload(addFormContainer, cur, type);
            if (!payload.accountHolderName) {
                addErrorEl.textContent = 'Account holder name is required.';
                addErrorEl.style.display = 'block';
                return;
            }
            payload.profile = null;
            addSubmitBtn.disabled = true;
            addSubmitBtn.textContent = 'Creating...';
            addErrorEl.style.display = 'none';
            var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                var r = await fetch('/api/integrations/wise/recipients', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                });
                var d = await r.json();
                if (r.ok) {
                    closeAddModal();
                    loadData();
                    alert('Recipient created successfully. ID: ' + (d.recipient && d.recipient.id ? d.recipient.id : ''));
                } else {
                    addErrorEl.textContent = d.error || 'Failed to create recipient.';
                    addErrorEl.style.display = 'block';
                }
            } catch (e) {
                addErrorEl.textContent = 'Error creating recipient. Please try again.';
                addErrorEl.style.display = 'block';
            }
            addSubmitBtn.disabled = false;
            addSubmitBtn.textContent = 'Create recipient';
        });
    }
});


})();
