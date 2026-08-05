{{-- Phone system right panel: tabbed Live / History / Contacts / Numbers --}}
<div class="call-card phone-panel-card">
    <div class="call-card-header phone-panel-header">
        <div class="phone-panel-tabs" role="tablist">
            <button type="button" class="phone-tab-btn active" data-phone-tab="live" role="tab">Live</button>
            @if(auth()->user()?->hasPermission('view_call_history'))
                <button type="button" class="phone-tab-btn" data-phone-tab="history" role="tab">Call History</button>
            @endif
            @if(!empty($canManageContacts) && $canManageContacts)
                <button type="button" class="phone-tab-btn" data-phone-tab="contacts" role="tab">Contacts</button>
            @endif
            @if(!empty($canManageNumbers) && $canManageNumbers)
                <button type="button" class="phone-tab-btn" data-phone-tab="numbers" role="tab">Numbers</button>
            @endif
        </div>
        <button type="button" class="btn-clear-log" onclick="clearLog()" id="clearLogBtn" style="display: none;">Clear</button>
    </div>
    <div class="call-card-body phone-panel-body">
        {{-- Live session log --}}
        <div class="phone-tab-panel active" id="phoneTabLive" data-phone-panel="live">
            <div class="agent-queue-card" id="agentQueueCard">
                <div class="agent-queue-row">
                    <div>
                        <div class="agent-queue-title">Inbound call queue</div>
                        <div class="agent-queue-subtitle" id="agentQueueSubtitle">Turn on to receive round-robin inbound calls</div>
                    </div>
                    <label class="agent-queue-toggle">
                        <input type="checkbox" id="agentAvailableToggle" aria-label="Available for inbound calls">
                        <span class="agent-queue-toggle-ui"></span>
                        <span class="agent-queue-toggle-label" id="agentAvailableLabel">Offline</span>
                    </label>
                </div>
                <div class="agent-queue-stats" id="agentQueueStats">
                    <div class="agent-queue-stat">
                        <span class="agent-queue-stat-value" id="agentQueueAvailableCount">0</span>
                        <span class="agent-queue-stat-label">Available</span>
                    </div>
                    <div class="agent-queue-stat">
                        <span class="agent-queue-stat-value" id="agentQueueBusyCount">0</span>
                        <span class="agent-queue-stat-label">On call</span>
                    </div>
                    <div class="agent-queue-stat">
                        <span class="agent-queue-stat-value" id="agentQueueTotalCount">0</span>
                        <span class="agent-queue-stat-label">In queue</span>
                    </div>
                </div>
                <div class="agent-queue-next" id="agentQueueNext">Next up: —</div>
                <div class="agent-queue-list" id="agentQueueList">
                    <p class="agent-queue-empty">No agents in the queue yet</p>
                </div>
            </div>
            <p class="phone-recording-notice">Calls are recorded automatically. Play them back from Call History after the call ends.</p>
            <div class="call-log-area" id="callLogArea">
                <div class="call-log-entry">
                    <svg class="call-log-icon check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Twilio device ready</span>
                    <span class="log-timestamp">{{ now()->format('H:i:s') }}</span>
                </div>
            </div>
            @if((!empty($canSendSms) && $canSendSms) || (!empty($canViewSms) && $canViewSms))
                <p class="phone-sms-page-hint">
                    Text messaging lives on the dedicated
                    <a href="{{ route('sms') }}">SMS</a> page (same as Viber / WhatsApp).
                </p>
            @endif
        </div>

        @if(auth()->user()?->hasPermission('view_call_history'))
        <div class="phone-tab-panel" id="phoneTabHistory" data-phone-panel="history">
            <div class="phone-panel-toolbar">
                <select id="historyDirectionFilter" class="form-input phone-filter-select">
                    <option value="all">All directions</option>
                    <option value="inbound">Inbound</option>
                    <option value="outbound">Outbound</option>
                </select>
                <button type="button" class="btn-secondary btn-sm" id="refreshHistoryBtn">Refresh</button>
            </div>
            <div class="phone-list" id="callHistoryList">
                <p class="phone-empty-msg">Loading call history…</p>
            </div>
        </div>
        @endif

        @if(!empty($canManageContacts) && $canManageContacts)
        <div class="phone-tab-panel" id="phoneTabContacts" data-phone-panel="contacts">
            <div class="phone-panel-toolbar">
                <button type="button" class="btn-primary btn-sm" id="addContactBtn">+ Add Contact</button>
                <button type="button" class="btn-secondary btn-sm" id="refreshContactsBtn">Refresh</button>
            </div>
            <div class="phone-list" id="contactsList">
                <p class="phone-empty-msg">No contacts yet.</p>
            </div>
            <div class="phone-inline-form" id="contactForm" style="display:none;">
                <input type="hidden" id="contactEditId">
                <input type="text" id="contactName" class="form-input" placeholder="Name">
                <input type="text" id="contactPhone" class="form-input" placeholder="+1234567890">
                <input type="email" id="contactEmail" class="form-input" placeholder="Email (optional)">
                <textarea id="contactNotes" class="form-input" rows="2" placeholder="Notes"></textarea>
                <div class="phone-form-actions">
                    <button type="button" class="btn-primary btn-sm" id="saveContactBtn">Save</button>
                    <button type="button" class="btn-secondary btn-sm" id="cancelContactBtn">Cancel</button>
                </div>
            </div>
        </div>
        @endif

        @if(!empty($canManageNumbers) && $canManageNumbers)
        <div class="phone-tab-panel" id="phoneTabNumbers" data-phone-panel="numbers">
            <div class="phone-panel-toolbar">
                <input type="text" id="areaCodeInput" class="form-input phone-area-input" placeholder="Area code" maxlength="5">
                <button type="button" class="btn-secondary btn-sm" id="searchNumbersBtn">Search</button>
                <button type="button" class="btn-secondary btn-sm" id="syncNumbersBtn">Sync</button>
            </div>
            <div class="phone-list phone-list-compact" id="availableNumbersList"></div>
            <h3 class="phone-subsection-title">Company Numbers</h3>
            <div class="phone-list" id="companyNumbersList">
                <p class="phone-empty-msg">Loading numbers…</p>
            </div>
            <h3 class="phone-subsection-title">Assign to Employee</h3>
            <div class="phone-assign-row">
                <select id="assignNumberSelect" class="form-input"></select>
                <select id="assignEmployeeSelect" class="form-input"></select>
                <button type="button" class="btn-primary btn-sm" id="assignNumberBtn">Assign</button>
            </div>
        </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .phone-panel-card { min-height: 480px; }
    .phone-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; }
    .phone-panel-tabs { display: flex; flex-wrap: wrap; gap: 0.35rem; }
    .phone-tab-btn {
        padding: 0.35rem 0.65rem;
        font-size: 0.8rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg-primary);
        color: var(--text-secondary);
        cursor: pointer;
    }
    .phone-tab-btn.active { background: var(--primary-color, #6366f1); color: #fff; border-color: transparent; }
    .phone-tab-panel { display: none; }
    .phone-tab-panel.active { display: block; }
    .phone-panel-toolbar { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; flex-wrap: wrap; align-items: center; }
    .phone-filter-select, .phone-area-input { max-width: 140px; padding: 0.4rem 0.5rem; font-size: 0.85rem; }
    .phone-list { max-height: 360px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; }
    .phone-list-compact { max-height: 120px; margin-bottom: 1rem; }
    .phone-list-item {
        padding: 0.65rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-primary);
        font-size: 0.85rem;
    }
    .phone-list-item-header { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; font-weight: 600; }
    .phone-list-item-meta { color: var(--text-secondary); font-size: 0.78rem; margin-top: 0.25rem; }
    .phone-list-item-actions { display: flex; gap: 0.35rem; margin-top: 0.5rem; flex-wrap: wrap; }
    .phone-empty-msg { color: var(--text-secondary); font-size: 0.85rem; text-align: center; padding: 1rem; }
    .phone-recording-notice {
        margin: 0 0 0.75rem;
        padding: 0.5rem 0.65rem;
        font-size: 0.8rem;
        color: var(--text-secondary);
        background: var(--bg-secondary, #f8fafc);
        border: 1px solid var(--border);
        border-radius: 6px;
    }
    .agent-queue-card {
        margin: 0 0 0.85rem;
        padding: 0.75rem 0.85rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-secondary, #f8fafc);
    }
    .agent-queue-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .agent-queue-title { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
    .agent-queue-subtitle { font-size: 0.78rem; color: var(--text-secondary); margin-top: 0.15rem; }
    .agent-queue-toggle {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        cursor: pointer;
        user-select: none;
        flex-shrink: 0;
    }
    .agent-queue-toggle input { position: absolute; opacity: 0; pointer-events: none; }
    .agent-queue-toggle-ui {
        width: 40px;
        height: 22px;
        border-radius: 999px;
        background: #cbd5e1;
        position: relative;
        transition: background 0.15s ease;
    }
    .agent-queue-toggle-ui::after {
        content: '';
        position: absolute;
        top: 2px;
        left: 2px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        transition: transform 0.15s ease;
    }
    .agent-queue-toggle input:checked + .agent-queue-toggle-ui { background: #16a34a; }
    .agent-queue-toggle input:checked + .agent-queue-toggle-ui::after { transform: translateX(18px); }
    .agent-queue-toggle-label { font-size: 0.78rem; font-weight: 600; min-width: 4.5rem; color: var(--text-secondary); }
    .agent-queue-toggle input:checked ~ .agent-queue-toggle-label { color: #166534; }
    .agent-queue-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-top: 0.75rem;
    }
    .agent-queue-stat {
        text-align: center;
        padding: 0.5rem 0.35rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-card, #fff);
    }
    .agent-queue-stat-value {
        display: block;
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.2;
    }
    .agent-queue-stat-label {
        display: block;
        margin-top: 0.15rem;
        font-size: 0.7rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .agent-queue-next {
        margin-top: 0.65rem;
        font-size: 0.8rem;
        color: var(--text-secondary);
    }
    .agent-queue-next strong { color: var(--text-primary); }
    .agent-queue-list {
        margin-top: 0.55rem;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        max-height: 180px;
        overflow-y: auto;
    }
    .agent-queue-empty {
        margin: 0;
        font-size: 0.78rem;
        color: var(--text-secondary);
        text-align: center;
        padding: 0.5rem 0;
    }
    .agent-queue-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.45rem 0.55rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--bg-card, #fff);
        font-size: 0.8rem;
    }
    .agent-queue-item.is-next {
        border-color: #86efac;
        background: #f0fdf4;
    }
    .agent-queue-item-main {
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        min-width: 0;
    }
    .agent-queue-item-name {
        font-weight: 600;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .agent-queue-item-meta {
        font-size: 0.72rem;
        color: var(--text-secondary);
    }
    .agent-queue-badge {
        flex-shrink: 0;
        padding: 0.12rem 0.45rem;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .agent-queue-badge.available { background: #dcfce7; color: #166534; }
    .agent-queue-badge.busy { background: #ffedd5; color: #9a3412; }
    .agent-queue-badge.next { background: #16a34a; color: #fff; }
    .phone-call-recording {
        display: block;
        width: 100%;
        margin-top: 0.5rem;
        height: 32px;
    }
    .phone-inline-form { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--border); }
    .phone-form-actions { display: flex; gap: 0.5rem; }
    .phone-subsection-title { font-size: 0.9rem; font-weight: 600; margin: 0.75rem 0 0.5rem; color: var(--text-primary); }
    .phone-assign-row { display: grid; grid-template-columns: 1fr 1fr auto; gap: 0.5rem; align-items: end; }
    .phone-sms-page-hint {
        margin: 0.85rem 0 0;
        padding-top: 0.75rem;
        border-top: 1px solid var(--border);
        font-size: 0.82rem;
        color: var(--text-secondary);
    }
    .phone-sms-page-hint a { color: #0ea5e9; font-weight: 600; text-decoration: none; }
    .phone-sms-page-hint a:hover { text-decoration: underline; }
    .btn-sm { padding: 0.35rem 0.65rem; font-size: 0.8rem; }
    .status-badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.72rem; text-transform: capitalize; }
    .status-badge.completed { background: #dcfce7; color: #166534; }
    .status-badge.failed, .status-badge.busy, .status-badge.no-answer { background: #fee2e2; color: #991b1b; }
    .status-badge.ringing, .status-badge.initiated { background: #fef9c3; color: #854d0e; }
    @media (max-width: 900px) {
        .phone-assign-row { grid-template-columns: 1fr; }
    }
</style>
@endpush
