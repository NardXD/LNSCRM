/**
 * Admin Company Management
 */

(function() {
    const config = window.__companyManagementConfig || {};
    const API_BASE = config.apiBase || '/admin';
    const API_URL = config.apiUrl || '/admin/api';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let managePermissionCompanyId = null;

    function openCreateCompanyModal() {
        const modal = document.getElementById('createCompanyModal');
        if (modal) modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateCompanyModal() {
        const modal = document.getElementById('createCompanyModal');
        if (modal) modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    async function openManagePermissionModal(companyId, companyName) {
        managePermissionCompanyId = companyId;
        document.getElementById('managePermissionCompanyName').textContent = companyName;
        document.getElementById('managePermissionModulesGrid').innerHTML = '<span class="loading-text">Loading modules…</span>';
        document.getElementById('managePermissionModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            const [modulesRes, companyModulesRes] = await Promise.all([
                fetch(API_URL + '/modules', { headers: { 'Accept': 'application/json' } }),
                fetch(API_URL + '/companies/' + companyId + '/modules', { headers: { 'Accept': 'application/json' } })
            ]);

            const modules = await modulesRes.json();
            const { modules: enabledSlugs } = await companyModulesRes.json();

            const grid = document.getElementById('managePermissionModulesGrid');
            const checkAllHtml = '<label class="module-checkbox-item check-all-item">' +
                '<input type="checkbox" id="checkAllManageModules" onchange="toggleAllManageModules(this.checked)"> ' +
                '<span><strong>Check all</strong></span></label>';
            const modulesHtml = modules.map(function(m) {
                const checked = Array.isArray(enabledSlugs) && enabledSlugs.includes(m.slug) ? ' checked' : '';
                return '<label class="module-checkbox-item">' +
                    '<input type="checkbox" name="module[]" value="' + m.slug + '"' + checked + '> ' +
                    '<span>' + (m.name || m.slug) + '</span>' +
                    '</label>';
            }).join('');
            grid.innerHTML = checkAllHtml + modulesHtml;
            const allEnabled = modules.length > 0 && Array.isArray(enabledSlugs) && enabledSlugs.length === modules.length;
            const checkAll = document.getElementById('checkAllManageModules');
            if (checkAll) checkAll.checked = allEnabled;
        } catch (err) {
            console.error(err);
            document.getElementById('managePermissionModulesGrid').innerHTML =
                '<span class="error-text">Failed to load modules. Please try again.</span>';
        }
    }

    function closeManagePermissionModal() {
        managePermissionCompanyId = null;
        document.getElementById('managePermissionModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    async function openHistoryModal(companyId, companyName) {
        document.getElementById('historyCompanyName').textContent = companyName;
        document.getElementById('historyList').innerHTML = '<span class="loading-text">Loading history…</span>';
        document.getElementById('historyModal').classList.add('active');
        document.body.style.overflow = 'hidden';

        try {
            const res = await fetch(API_URL + '/companies/' + companyId + '/history', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (!res.ok) throw new Error('Failed to load history');

            const container = document.getElementById('historyList');
            if (!data.histories || data.histories.length === 0) {
                container.innerHTML = '<p class="loading-text">No history yet.</p>';
                return;
            }

            const sorted = data.histories.slice().sort(function (a, b) { return (b.id || 0) - (a.id || 0); });

            const iconClass = {
                created: 'created',
                status_changed: 'status_changed',
                modules_updated: 'modules_updated'
            };

            container.innerHTML = sorted.map(function (h) {
                var icon = iconClass[h.action] || '';
                return '<div class="history-item">' +
                    '<div class="history-icon ' + icon + '">' +
                    (h.action === 'created' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 5v14M5 12h14"/></svg>' :
                     h.action === 'status_changed' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M3 6h18M3 12h18M3 18h18"/></svg>' :
                     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V4a2 2 0 00-2-2H4a2 2 0 00-2 2v4h16z"/></svg>') +
                    '</div>' +
                    '<div class="history-content">' +
                    '<div class="history-summary">' + (h.summary || h.description || 'Updated') + '</div>' +
                    '<div class="history-meta">' + h.created_at + ' · ' + (h.changed_by || 'System') + '</div>' +
                    '</div></div>';
            }).join('');
        } catch (err) {
            console.error(err);
            document.getElementById('historyList').innerHTML =
                '<span class="error-text">Failed to load history. Please try again.</span>';
        }
    }

    function closeHistoryModal() {
        document.getElementById('historyModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    async function saveManagePermission() {
        if (!managePermissionCompanyId) return;

        const checkboxes = document.querySelectorAll('#managePermissionModulesGrid input[name="module[]"]:checked');
        const modules = Array.from(checkboxes).map(function(cb) { return cb.value; });

        const btn = document.getElementById('saveManagePermissionBtn');
        btn.disabled = true;
        btn.textContent = 'Saving…';

        try {
            const res = await fetch(API_URL + '/companies/' + managePermissionCompanyId + '/modules', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({ modules: modules })
            });

            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Failed to save');
            }

            alert('Module access updated successfully.');
            closeManagePermissionModal();
        } catch (err) {
            alert(err.message || 'Failed to save. Please try again.');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save Changes';
        }
    }

    function updateCompanyStatus(companyId, status) {
        var row = document.querySelector('#companiesTableBody tr[data-id="' + companyId + '"]');
        var select = document.querySelector('.status-select[data-company-id="' + companyId + '"]');
        var previousStatus = row ? row.getAttribute('data-status') : status;

        fetch(API_BASE + '/company-management/' + companyId + '/status', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            body: JSON.stringify({ status: status }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    if (row) row.setAttribute('data-status', status);
                } else {
                    if (select) select.value = previousStatus;
                    alert(data.message || 'Failed to update status.');
                }
            })
            .catch(function (err) {
                if (select) select.value = previousStatus;
                alert('Failed to update status. Please try again.');
            });
    }

    function toggleAllCreateModules(checked) {
        const inputs = document.querySelectorAll('#createCompanyModal input[name="modules[]"]');
        inputs.forEach(function(inp) { inp.checked = checked; });
    }

    function toggleAllManageModules(checked) {
        const inputs = document.querySelectorAll('#managePermissionModal input[name="module[]"]');
        inputs.forEach(function(inp) { inp.checked = checked; });
    }

    function filterCompanies() {
        const search = (document.getElementById('companySearch')?.value || '').toLowerCase();
        const status = document.getElementById('companyStatusFilter')?.value || '';
        const rows = document.querySelectorAll('#companiesTableBody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status') || '';
            const matchSearch = !search || text.includes(search);
            const matchStatus = !status || rowStatus === status;
            row.style.display = matchSearch && matchStatus ? '' : 'none';
        });
    }

    function handleCreateCompanySubmit(e) {
        e.preventDefault();
        const form = document.getElementById('createCompanyForm');
        if (!form) return;

        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Creating…';
        }

        const formData = new FormData(form);
        const body = {
            company: formData.get('company'),
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email'),
            password: formData.get('password'),
            password_confirmation: formData.get('password_confirmation'),
            plan: formData.get('plan') || 'free',
            status: 'trial',
        };
        const modules = formData.getAll('modules[]');
        // Always include leave-management and team-management for new companies
        const coreModules = ['leave-management', 'team-management'];
        const allModules = [...new Set([...coreModules, ...modules])];
        if (allModules.length) body.modules = allModules;

        fetch(API_BASE + '/company-management', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
            body: JSON.stringify(body),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, status: res.status, data: data };
                });
            })
            .then(function (result) {
                if (result.ok && result.data.success) {
                    alert(result.data.message || 'Company created successfully.');
                    closeCreateCompanyModal();
                    form.reset();
                    location.reload();
                } else {
                    var d = result.data;
                    var msg = d.message || (d.errors ? Object.values(d.errors).flat().join('\n') : 'Something went wrong.');
                    alert(msg);
                }
            })
            .catch(function (err) {
                alert(err.message || 'Failed to create company. Please try again.');
            })
            .finally(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
    }

    function closeRowActionMenus() {
        document.querySelectorAll('.row-actions-menu[open]').forEach(function (menu) {
            menu.removeAttribute('open');
        });
    }

    function handleRowActionClick(event) {
        const actionButton = event.target.closest('[data-row-action]');
        if (!actionButton) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const companyId = Number.parseInt(actionButton.dataset.companyId || '', 10);
        const companyName = actionButton.dataset.companyName || '';
        const action = actionButton.dataset.rowAction;

        if (!Number.isFinite(companyId)) {
            return;
        }

        closeRowActionMenus();

        if (action === 'manage-permissions') {
            openManagePermissionModal(companyId, companyName);
        } else if (action === 'view-history') {
            openHistoryModal(companyId, companyName);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('createCompanyForm');
        if (form) form.addEventListener('submit', handleCreateCompanySubmit);

        document.addEventListener('click', handleRowActionClick);

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.row-actions-menu')) {
                closeRowActionMenus();
            }
        });

        document.querySelectorAll('.row-actions-menu').forEach(function (menu) {
            menu.addEventListener('toggle', function () {
                if (menu.open) {
                    document.querySelectorAll('.row-actions-menu[open]').forEach(function (other) {
                        if (other !== menu) {
                            other.removeAttribute('open');
                        }
                    });
                }
            });
        });
    });

    // Expose globally for onclick handlers
    window.openCreateCompanyModal = openCreateCompanyModal;
    window.closeCreateCompanyModal = closeCreateCompanyModal;
    window.openManagePermissionModal = openManagePermissionModal;
    window.closeManagePermissionModal = closeManagePermissionModal;
    window.saveManagePermission = saveManagePermission;
    window.filterCompanies = filterCompanies;
    window.updateCompanyStatus = updateCompanyStatus;
    window.openHistoryModal = openHistoryModal;
    window.closeHistoryModal = closeHistoryModal;
    window.toggleAllCreateModules = toggleAllCreateModules;
    window.toggleAllManageModules = toggleAllManageModules;
    window.closeRowActionMenus = closeRowActionMenus;
})();
