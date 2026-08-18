<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const routes = {
        history: @json(route('twilio.call-history', [], false)),
        contacts: @json(route('twilio.contacts', [], false)),
        contactsStore: @json(route('twilio.contacts.store', [], false)),
        contactsUpdate: @json(url('/twilio/contacts/__ID__')),
        contactsDelete: @json(url('/twilio/contacts/__ID__')),
        numbers: @json(route('twilio.numbers', [], false)),
        numbersSearch: @json(route('twilio.numbers.search', [], false)),
        numbersPurchase: @json(route('twilio.numbers.purchase', [], false)),
        numbersSync: @json(route('twilio.numbers.sync', [], false)),
        numbersAssign: @json(url('/twilio/numbers/__ID__/assign')),
        numbersUnassign: @json(url('/twilio/numbers/__ID__/unassign')),
        numbersEmployees: @json(route('twilio.numbers.employees', [], false)),
        agentPresence: @json(route('twilio.agent-presence', [], false)),
        agentPresenceUpdate: @json(route('twilio.agent-presence.update', [], false)),
    };

    const flags = {
        history: @json(auth()->user()?->hasPermission('view_call_history') ?? false),
        contacts: @json(!empty($canManageContacts) && $canManageContacts),
        numbers: @json(!empty($canManageNumbers) && $canManageNumbers),
    };

    function apiFetch(url, options = {}) {
        const { headers: optionHeaders, ...rest } = options;
        return fetch(url, {
            ...rest,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(optionHeaders || {}),
            },
        }).then(async (r) => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                const msg = data.message
                    || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                    || `Request failed (${r.status})`;
                throw new Error(msg);
            }
            return data;
        });
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function dialFromPanel(number) {
        const input = document.getElementById('phoneNumber');
        if (input) {
            input.value = number;
            input.dispatchEvent(new Event('input'));
        }
        if (typeof window.makeCall === 'function') {
            window.makeCall();
        }
    }

    // Tab switching
    document.querySelectorAll('.phone-tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-phone-tab');
            document.querySelectorAll('.phone-tab-btn').forEach((b) => b.classList.remove('active'));
            document.querySelectorAll('.phone-tab-panel').forEach((p) => p.classList.remove('active'));
            btn.classList.add('active');
            const panel = document.querySelector(`[data-phone-panel="${tab}"]`);
            if (panel) panel.classList.add('active');
            document.getElementById('clearLogBtn').style.display = tab === 'live' ? '' : 'none';

            if (tab === 'history' && flags.history) loadCallHistory();
            if (tab === 'contacts' && flags.contacts) loadContacts();
            if (tab === 'numbers' && flags.numbers) loadNumbersPanel();
        });
    });

    // Call history
    async function loadCallHistory() {
        const list = document.getElementById('callHistoryList');
        if (!list) return;
        list.innerHTML = '<p class="phone-empty-msg">Loading…</p>';
        try {
            const dir = document.getElementById('historyDirectionFilter')?.value || 'all';
            const url = routes.history + (dir !== 'all' ? '?direction=' + encodeURIComponent(dir) : '');
            const res = await apiFetch(url);
            const rows = res.data || [];
            if (!rows.length) {
                list.innerHTML = '<p class="phone-empty-msg">No calls recorded yet.</p>';
                return;
            }
            list.innerHTML = rows.map((row) => `
                <div class="phone-list-item">
                    <div class="phone-list-item-header">
                        <span>${escapeHtml(row.direction || 'call')}</span>
                        <span class="status-badge ${escapeHtml(row.status || '')}">${escapeHtml(row.status || '')}</span>
                    </div>
                    <div class="phone-list-item-meta">
                        ${escapeHtml(row.from)} → ${escapeHtml(row.to)}
                        ${row.duration ? ' · ' + row.duration + 's' : ''}
                        ${row.has_recording ? ' · Recorded' : ''}
                    </div>
                    <div class="phone-list-item-meta">${escapeHtml(row.created_at ? new Date(row.created_at).toLocaleString() : '')}</div>
                    ${row.has_recording && row.recording_url ? `
                        <audio class="phone-call-recording" controls preload="none" src="${escapeHtml(row.recording_url)}"></audio>
                    ` : ''}
                    <div class="phone-list-item-actions">
                        <button type="button" class="btn-secondary btn-sm" data-dial="${escapeHtml(row.direction?.includes('inbound') ? row.from : row.to)}">Call back</button>
                    </div>
                </div>
            `).join('');
            list.querySelectorAll('[data-dial]').forEach((btn) => {
                btn.addEventListener('click', () => dialFromPanel(btn.getAttribute('data-dial')));
            });
        } catch (e) {
            list.innerHTML = `<p class="phone-empty-msg">${escapeHtml(e.message)}</p>`;
        }
    }

    document.getElementById('refreshHistoryBtn')?.addEventListener('click', loadCallHistory);
    document.getElementById('historyDirectionFilter')?.addEventListener('change', loadCallHistory);

    // Contacts
    async function loadContacts() {
        const list = document.getElementById('contactsList');
        if (!list) return;
        try {
            const res = await apiFetch(routes.contacts);
            const rows = res.data || [];
            if (!rows.length) {
                list.innerHTML = '<p class="phone-empty-msg">No contacts yet.</p>';
                return;
            }
            list.innerHTML = rows.map((c) => `
                <div class="phone-list-item">
                    <div class="phone-list-item-header"><span>${escapeHtml(c.name)}</span></div>
                    <div class="phone-list-item-meta">${escapeHtml(c.phone)}${c.email ? ' · ' + escapeHtml(c.email) : ''}</div>
                    <div class="phone-list-item-actions">
                        <button type="button" class="btn-primary btn-sm" data-dial="${escapeHtml(c.phone)}">Call</button>
                        <button type="button" class="btn-secondary btn-sm" data-edit-contact='${JSON.stringify(c).replace(/'/g, '&#39;')}'>Edit</button>
                        <button type="button" class="btn-secondary btn-sm" data-delete-contact="${c.id}">Delete</button>
                    </div>
                </div>
            `).join('');
            list.querySelectorAll('[data-dial]').forEach((btn) => {
                btn.addEventListener('click', () => dialFromPanel(btn.getAttribute('data-dial')));
            });
            list.querySelectorAll('[data-edit-contact]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const c = JSON.parse(btn.getAttribute('data-edit-contact'));
                    document.getElementById('contactEditId').value = c.id;
                    document.getElementById('contactName').value = c.name || '';
                    document.getElementById('contactPhone').value = c.phone || '';
                    document.getElementById('contactEmail').value = c.email || '';
                    document.getElementById('contactNotes').value = c.notes || '';
                    document.getElementById('contactForm').style.display = '';
                });
            });
            list.querySelectorAll('[data-delete-contact]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    if (!confirm('Delete this contact?')) return;
                    await apiFetch(routes.contactsDelete.replace('__ID__', btn.getAttribute('data-delete-contact')), { method: 'DELETE' });
                    loadContacts();
                });
            });
        } catch (e) {
            list.innerHTML = `<p class="phone-empty-msg">${escapeHtml(e.message)}</p>`;
        }
    }

    document.getElementById('addContactBtn')?.addEventListener('click', () => {
        document.getElementById('contactEditId').value = '';
        document.getElementById('contactName').value = '';
        document.getElementById('contactPhone').value = '+';
        document.getElementById('contactEmail').value = '';
        document.getElementById('contactNotes').value = '';
        document.getElementById('contactForm').style.display = '';
    });
    document.getElementById('cancelContactBtn')?.addEventListener('click', () => {
        document.getElementById('contactForm').style.display = 'none';
    });
    document.getElementById('refreshContactsBtn')?.addEventListener('click', loadContacts);
    document.getElementById('saveContactBtn')?.addEventListener('click', async () => {
        const id = document.getElementById('contactEditId').value;
        const body = JSON.stringify({
            name: document.getElementById('contactName').value,
            phone: document.getElementById('contactPhone').value,
            email: document.getElementById('contactEmail').value,
            notes: document.getElementById('contactNotes').value,
        });
        if (id) {
            await apiFetch(routes.contactsUpdate.replace('__ID__', id), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body,
            });
        } else {
            await apiFetch(routes.contactsStore, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body,
            });
        }
        document.getElementById('contactForm').style.display = 'none';
        loadContacts();
    });

    // Numbers
    function numberAssignmentSummary(n) {
        const voiceNames = (n.voice_users || []).map((u) => u.name).filter(Boolean);
        const smsNames = (n.sms_users || []).map((u) => u.name).filter(Boolean);
        const parts = [];
        if (voiceNames.length) {
            parts.push(`Phone: ${escapeHtml(voiceNames.join(', '))}`);
        }
        if (smsNames.length) {
            parts.push(`SMS: ${escapeHtml(smsNames.join(', '))}`);
        }
        return parts.length ? parts.join(' · ') : '<em>Unassigned</em>';
    }

    function numberUnassignButtons(n) {
        const voiceBtns = (n.voice_users || []).map((u) =>
            `<button type="button" class="btn-secondary btn-sm" data-unassign="${n.id}" data-purpose="voice" data-user-id="${u.id}">Unassign Phone (${escapeHtml(u.name)})</button>`
        );
        const smsBtns = (n.sms_users || []).map((u) =>
            `<button type="button" class="btn-secondary btn-sm" data-unassign="${n.id}" data-purpose="sms" data-user-id="${u.id}">Unassign SMS (${escapeHtml(u.name)})</button>`
        );
        return [...voiceBtns, ...smsBtns].join('');
    }

    async function loadNumbersPanel() {
        const list = document.getElementById('companyNumbersList');
        const numSelect = document.getElementById('assignNumberSelect');
        const empSelect = document.getElementById('assignEmployeeSelect');
        if (!list) return;
        try {
            const [numsRes, empRes] = await Promise.all([
                apiFetch(routes.numbers),
                apiFetch(routes.numbersEmployees),
            ]);
            const numbers = numsRes.data || [];
            const employees = empRes.data || [];

            if (!numbers.length) {
                list.innerHTML = '<p class="phone-empty-msg">No numbers in inventory. Search and purchase or sync from Twilio.</p>';
            } else {
                list.innerHTML = numbers.map((n) => `
                    <div class="phone-list-item">
                        <div class="phone-list-item-header">
                            <span>${escapeHtml(n.phone_number)}</span>
                            <span>${numberAssignmentSummary(n)}</span>
                        </div>
                        <div class="phone-list-item-actions">
                            ${numberUnassignButtons(n)}
                        </div>
                    </div>
                `).join('');
                list.querySelectorAll('[data-unassign]').forEach((btn) => {
                    btn.addEventListener('click', async () => {
                        await apiFetch(routes.numbersUnassign.replace('__ID__', btn.getAttribute('data-unassign')), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                purpose: btn.getAttribute('data-purpose'),
                                user_id: parseInt(btn.getAttribute('data-user-id'), 10),
                            }),
                        });
                        loadNumbersPanel();
                    });
                });
            }

            if (numSelect) {
                numSelect.innerHTML = '<option value="">Select number</option>' +
                    numbers.map((n) =>
                        `<option value="${n.id}">${escapeHtml(n.phone_number)}</option>`
                    ).join('');
            }
            if (empSelect) {
                empSelect.innerHTML = '<option value="">Select employee</option>' +
                    employees.map((e) =>
                        `<option value="${e.id}">${escapeHtml(e.name)}</option>`
                    ).join('');
            }
        } catch (e) {
            list.innerHTML = `<p class="phone-empty-msg">${escapeHtml(e.message)}</p>`;
        }
    }

    document.getElementById('searchNumbersBtn')?.addEventListener('click', async () => {
        const area = document.getElementById('areaCodeInput')?.value || '';
        const list = document.getElementById('availableNumbersList');
        list.innerHTML = '<p class="phone-empty-msg">Searching…</p>';
        try {
            const res = await apiFetch(routes.numbersSearch + '?area_code=' + encodeURIComponent(area));
            const rows = res.data || [];
            if (!rows.length) {
                list.innerHTML = '<p class="phone-empty-msg">No numbers found.</p>';
                return;
            }
            list.innerHTML = rows.map((n) => `
                <div class="phone-list-item">
                    <span>${escapeHtml(n.phone_number)}</span>
                    <button type="button" class="btn-primary btn-sm" data-buy="${escapeHtml(n.phone_number)}">Buy</button>
                </div>
            `).join('');
            list.querySelectorAll('[data-buy]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    if (!confirm('Purchase ' + btn.getAttribute('data-buy') + '?')) return;
                    await apiFetch(routes.numbersPurchase, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ phone_number: btn.getAttribute('data-buy') }),
                    });
                    loadNumbersPanel();
                });
            });
        } catch (e) {
            list.innerHTML = `<p class="phone-empty-msg">${escapeHtml(e.message)}</p>`;
        }
    });

    document.getElementById('syncNumbersBtn')?.addEventListener('click', async () => {
        await apiFetch(routes.numbersSync, { method: 'POST' });
        loadNumbersPanel();
    });

    document.getElementById('assignNumberBtn')?.addEventListener('click', async () => {
        const numId = document.getElementById('assignNumberSelect')?.value;
        const userId = document.getElementById('assignEmployeeSelect')?.value;
        const purpose = document.getElementById('assignPurposeSelect')?.value || 'voice';
        if (!numId || !userId) return alert('Select a number, employee, and purpose.');
        await apiFetch(routes.numbersAssign.replace('__ID__', numId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: parseInt(userId, 10), purpose }),
        });
        loadNumbersPanel();
    });

    function renderAgentQueue(data) {
        const toggle = document.getElementById('agentAvailableToggle');
        const label = document.getElementById('agentAvailableLabel');
        const subtitle = document.getElementById('agentQueueSubtitle');
        const listEl = document.getElementById('agentQueueList');
        const nextEl = document.getElementById('agentQueueNext');
        const availableCountEl = document.getElementById('agentQueueAvailableCount');
        const busyCountEl = document.getElementById('agentQueueBusyCount');
        const totalCountEl = document.getElementById('agentQueueTotalCount');
        if (!toggle || !data) return;

        const status = data.me?.status || 'offline';
        const isOn = status === 'available' || status === 'busy';
        toggle.checked = isOn;
        if (label) {
            label.textContent = status === 'busy' ? 'On call' : (isOn ? 'Available' : 'Offline');
        }
        if (subtitle) {
            subtitle.textContent = isOn
                ? 'You are in the inbound round-robin queue'
                : 'Turn on to receive round-robin inbound calls';
        }

        const counts = data.counts || {};
        if (availableCountEl) availableCountEl.textContent = String(counts.available ?? 0);
        if (busyCountEl) busyCountEl.textContent = String(counts.busy ?? 0);
        if (totalCountEl) totalCountEl.textContent = String(counts.in_queue ?? 0);

        if (nextEl) {
            nextEl.innerHTML = data.next_agent
                ? `Next up: <strong>${escapeHtml(data.next_agent.name)}</strong>`
                : 'Next up: —';
        }

        if (!listEl) return;

        const queueOrder = data.queue_order || [];
        const busyAgents = data.busy_agents || [];
        const rows = [];

        queueOrder.forEach((agent) => {
            const badge = agent.is_next
                ? '<span class="agent-queue-badge next">Next</span>'
                : '<span class="agent-queue-badge available">Available</span>';
            rows.push(`
                <div class="agent-queue-item${agent.is_next ? ' is-next' : ''}">
                    <div class="agent-queue-item-main">
                        <div class="agent-queue-item-name">${escapeHtml(agent.name)}</div>
                        <div class="agent-queue-item-meta">#${agent.position} in round-robin</div>
                    </div>
                    ${badge}
                </div>
            `);
        });

        busyAgents.forEach((agent) => {
            rows.push(`
                <div class="agent-queue-item">
                    <div class="agent-queue-item-main">
                        <div class="agent-queue-item-name">${escapeHtml(agent.name)}</div>
                        <div class="agent-queue-item-meta">Currently on a call</div>
                    </div>
                    <span class="agent-queue-badge busy">On call</span>
                </div>
            `);
        });

        listEl.innerHTML = rows.length
            ? rows.join('')
            : '<p class="agent-queue-empty">No agents in the queue yet</p>';
    }

    async function loadAgentPresence() {
        try {
            const res = await apiFetch(routes.agentPresence);
            renderAgentQueue(res.data);
            if (typeof window.syncCallQueuePresence === 'function') {
                window.syncCallQueuePresence(res.data?.me?.status === 'available' || res.data?.me?.status === 'busy');
            }
        } catch (e) {
            console.warn('Failed to load agent presence', e);
        }
    }

    document.getElementById('agentAvailableToggle')?.addEventListener('change', async (event) => {
        const on = !!event.target.checked;
        const label = document.getElementById('agentAvailableLabel');
        if (label) label.textContent = on ? 'Available' : 'Offline';
        try {
            const res = await apiFetch(routes.agentPresenceUpdate, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: on ? 'available' : 'offline' }),
            });
            renderAgentQueue(res.data);
            if (typeof window.syncCallQueuePresence === 'function') {
                window.syncCallQueuePresence(on);
            }
        } catch (e) {
            event.target.checked = !on;
            if (label) label.textContent = on ? 'Offline' : 'Available';
            alert(e.message || 'Failed to update availability');
        }
    });

    loadAgentPresence();
    setInterval(loadAgentPresence, 15000);
})();
</script>
