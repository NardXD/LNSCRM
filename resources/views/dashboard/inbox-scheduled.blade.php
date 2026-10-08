@extends('layouts.app')

@section('title', 'Scheduled Sends')

@push('styles')
<style>
    .sched-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 1rem; flex-wrap: wrap;
    }
    .sched-header-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .sched-summary {
        display: flex; gap: 0.4rem 1rem; flex-wrap: wrap; align-items: baseline;
        margin: 0.5rem 0 0; color: var(--text-secondary, #6b7280); font-size: 0.82rem;
    }
    .sched-summary strong { color: var(--text-primary, #111827); }
    .sched-card {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 12px;
        overflow: hidden;
    }
    .sched-table { width: 100%; min-width: 680px; border-collapse: collapse; font-size: 0.75rem; }
    .sched-table th {
        text-align: left;
        padding: 0.45rem 0.65rem;
        font-size: 0.625rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary, #6b7280);
        border-bottom: 1px solid var(--border, #e5e7eb);
        background: var(--bg-primary, #fafafa);
        white-space: nowrap;
    }
    .sched-table td {
        padding: 0.5rem 0.65rem;
        border-bottom: 1px solid var(--border, #e5e7eb);
        vertical-align: top;
    }
    .sched-table tbody tr:last-child td { border-bottom: none; }
    .sched-table tbody tr:hover { background: var(--bg-primary, #fafafa); }
    .sched-when { font-weight: 600; color: var(--text-primary, #111827); white-space: nowrap; }
    .sched-subject { font-weight: 600; color: var(--text-primary, #111827); max-width: 340px; overflow-wrap: anywhere; }
    .sched-to { color: var(--text-secondary, #6b7280); max-width: 200px; overflow-wrap: anywhere; }
    .sched-meta { color: var(--text-secondary, #6b7280); font-size: 0.6875rem; margin-top: 0.2rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .sched-due-note { color: #92400e; font-size: 0.6875rem; margin-left: 0.3rem; }
    .sched-type-pill {
        display: inline-block;
        padding: 0.08rem 0.45rem;
        border-radius: 999px;
        font-size: 0.625rem;
        font-weight: 600;
        background: var(--accent-light, #f0f0ff);
        color: var(--accent, #5f61e6);
        white-space: nowrap;
    }
    .sched-status-badge {
        display: inline-block;
        padding: 0.1rem 0.45rem;
        border-radius: 999px;
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }
    .sched-status-badge.is-scheduled { background: #e0e7ff; color: #3730a3; }
    .sched-status-badge.is-sending   { background: #fef3c7; color: #92400e; }
    .sched-status-badge.is-sent      { background: #dcfce7; color: #166534; }
    .sched-status-badge.is-failed    { background: #fee2e2; color: #991b1b; }
    .sched-status-badge.is-cancelled { background: #f3f4f6; color: #6b7280; }
    .sched-row-actions { display: flex; gap: 0.35rem; justify-content: flex-end; white-space: nowrap; }
    .sched-link {
        color: var(--accent, #5f61e6);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.72rem;
        padding: 0.25rem 0.45rem;
        border-radius: 6px;
    }
    .sched-link:hover { background: var(--accent-light, #f0f0ff); }
    .sched-cancel-btn {
        border: 1px solid var(--border, #e5e7eb);
        background: var(--bg-card, #fff);
        color: #b91c1c;
        font-weight: 600;
        font-size: 0.72rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        cursor: pointer;
    }
    .sched-cancel-btn:hover { background: #fef2f2; border-color: #fecaca; }
    .sched-cancel-btn:disabled { opacity: 0.6; cursor: default; }
    .sched-linkbtn {
        border: none; background: transparent; cursor: pointer; font: inherit;
        color: var(--accent, #5f61e6); font-weight: 600; font-size: 0.72rem;
        padding: 0.25rem 0.45rem; border-radius: 6px;
    }
    .sched-linkbtn:hover { background: var(--accent-light, #f0f0ff); }
    .sched-linkbtn:disabled { opacity: 0.6; cursor: default; }
    /* Failure detail row */
    .sched-detail-row td { background: #fff7f7; border-top: none; padding-top: 0; }
    .sched-table tbody tr.sched-detail-row:hover td { background: #fff7f7; }
    .sched-fail-reason {
        display: flex; align-items: flex-start; gap: 0.4rem;
        color: #991b1b; font-size: 0.7rem; line-height: 1.4;
    }
    .sched-fail-reason svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 1px; }
    .sched-edit {
        margin-top: 0.6rem; padding: 0.7rem; border: 1px solid var(--border, #e5e7eb);
        border-radius: 8px; background: var(--bg-card, #fff);
        display: flex; flex-direction: column; gap: 0.5rem; max-width: 720px;
    }
    .sched-edit-grid { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .sched-edit-grid .sched-edit-field { flex: 1 1 220px; }
    .sched-edit-field { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.68rem; font-weight: 600; color: var(--text-secondary, #6b7280); }
    .sched-edit-field input {
        font: inherit; font-size: 0.8rem; font-weight: 400; color: var(--text-primary, #111827);
        padding: 0.4rem 0.55rem; border: 1px solid var(--border, #e5e7eb); border-radius: 6px;
        background: var(--bg-card, #fff);
    }
    .sched-edit-field input:focus, .sched-edit-body:focus {
        outline: none; border-color: var(--accent, #5f61e6);
        box-shadow: 0 0 0 2px rgba(95, 97, 230, 0.12);
    }
    .sched-edit-body {
        min-height: 120px; max-height: 320px; overflow-y: auto;
        font: inherit; font-size: 0.8rem; font-weight: 400; color: var(--text-primary, #111827);
        padding: 0.5rem 0.6rem; border: 1px solid var(--border, #e5e7eb); border-radius: 6px;
        background: var(--bg-card, #fff);
    }
    .sched-edit-actions { display: flex; justify-content: flex-end; gap: 0.4rem; }
    .sched-empty {
        text-align: center;
        padding: 3rem 1.5rem;
        color: var(--text-secondary, #6b7280);
    }
    .sched-empty svg { width: 42px; height: 42px; margin-bottom: 0.75rem; color: var(--text-muted, #9ca3af); }
    .sched-empty h3 { color: var(--text-primary, #111827); margin-bottom: 0.35rem; font-size: 1rem; }
    .sched-card > .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .sched-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
        padding: 0.5rem 0.75rem;
        border-top: 1px solid var(--border, #e5e7eb);
        font-size: 0.6875rem;
        color: var(--text-secondary, #6b7280);
        background: var(--bg-card, #fff);
    }
    .sched-pagination > div { display: flex; gap: 0.35rem; align-items: center; }
    .sched-pagination .btn[aria-disabled="true"] { opacity: 0.5; pointer-events: none; }
    .sched-tz-note { color: var(--text-muted, #9ca3af); font-size: 0.78rem; margin-top: 0.25rem; }
    .sched-scope-toggle {
        display: inline-flex;
        border: 1px solid var(--border, #e5e7eb);
        border-radius: 8px;
        overflow: hidden;
    }
    .sched-scope-tab {
        padding: 0.4rem 0.8rem;
        font-size: 0.82rem;
        font-weight: 600;
        text-decoration: none;
        color: var(--text-secondary, #6b7280);
        background: var(--bg-card, #fff);
    }
    .sched-scope-tab + .sched-scope-tab { border-left: 1px solid var(--border, #e5e7eb); }
    .sched-scope-tab:hover { background: var(--accent-light, #f0f0ff); }
    .sched-scope-tab.is-active { background: var(--accent, #5f61e6); color: #fff; }
    @media (max-width: 640px) {
        .sched-table th.sched-col-inbox, .sched-table td.sched-col-inbox { display: none; }
    }
</style>
@endpush

@section('content')
    @php $schedColCount = $scheduledScope === 'all' ? 8 : 7; @endphp
    <div class="page-header sched-header">
        <div>
            <h1 class="page-title">Scheduled Sends</h1>
            <p class="page-subtitle">
                @if($scheduledScope === 'all')
                    <strong>Send later</strong> emails from everyone in your company, and their status.
                @else
                    Emails you queued with <strong>Send later</strong> and their status.
                @endif
            </p>
            @if($scheduledSendsCount > 0)
                <div class="sched-summary">
                    <span><strong id="schedWaitingCount">{{ $scheduledCounts['waiting'] }}</strong> waiting</span>
                    <span><strong>{{ $scheduledCounts['sent'] }}</strong> sent</span>
                    @if($scheduledCounts['failed'] > 0)
                        <span><strong>{{ $scheduledCounts['failed'] }}</strong> failed</span>
                    @endif
                    <span class="sched-tz-note">History: last 30 days · times in {{ $scheduledTimezone }}</span>
                </div>
            @else
                <p class="sched-tz-note">Times shown in {{ $scheduledTimezone }}.</p>
            @endif
        </div>
        <div class="sched-header-actions">
            @if($scheduledCanViewAll)
                <div class="sched-scope-toggle" role="tablist" aria-label="Scheduled sends scope">
                    <a href="{{ route('inbox.scheduled') }}"
                       class="sched-scope-tab {{ $scheduledScope === 'mine' ? 'is-active' : '' }}">Mine</a>
                    <a href="{{ route('inbox.scheduled', ['scope' => 'all']) }}"
                       class="sched-scope-tab {{ $scheduledScope === 'all' ? 'is-active' : '' }}">All users</a>
                </div>
            @endif
            <a href="{{ route('inbox') }}" class="btn btn-secondary">Back to Inbox</a>
        </div>
    </div>

    <div class="sched-card" id="schedCard">
        @if($scheduledSendsCount === 0)
            <div class="sched-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
                <h3>No scheduled sends</h3>
                @if($scheduledScope === 'all')
                    <p>No one has any Send later emails queued or sent in the last 30 days.</p>
                @else
                    <p>When you use “Send later” in the inbox, the queued email will appear here until it's sent.</p>
                @endif
            </div>
        @else
            <div class="table-container">
                <table class="sched-table data-table">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Scheduled for</th>
                            @if($scheduledScope === 'all')
                                <th>Scheduled by</th>
                            @endif
                            <th>Type</th>
                            <th>Subject</th>
                            <th>To</th>
                            <th class="sched-col-inbox">Inbox</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="schedTbody">
                        @foreach($scheduledSends as $item)
                            <tr data-row-id="{{ $item['id'] }}">
                                <td class="sched-status-cell">
                                    <span class="sched-status-badge {{ $item['status_class'] }}"@if($item['error_message']) title="{{ $item['error_message'] }}"@endif>{{ $item['status_label'] }}</span>
                                    @if($item['status'] === 'sent' && $item['sent_at_display'])
                                        <div class="sched-meta">{{ $item['sent_at_display'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="sched-when">{{ $item['send_at_display'] ?? '—' }}</span>
                                    @if($item['is_past_due'])
                                        <span class="sched-due-note" title="The send time has passed; it will go out on the next queue run.">due</span>
                                    @endif
                                </td>
                                @if($scheduledScope === 'all')
                                    <td class="sched-to">{{ $item['scheduled_by'] }}</td>
                                @endif
                                <td><span class="sched-type-pill">{{ $item['type_label'] }}</span></td>
                                <td>
                                    <div class="sched-subject">{{ $item['subject'] }}</div>
                                    @if($item['attachment_count'] > 0 || $item['archive_after'])
                                        <div class="sched-meta">
                                            @if($item['attachment_count'] > 0)
                                                <span>📎 {{ $item['attachment_count'] }} attachment{{ $item['attachment_count'] === 1 ? '' : 's' }}</span>
                                            @endif
                                            @if($item['archive_after'])
                                                <span>↓ archive after send</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="sched-to">{{ $item['to'] ?: '—' }}</td>
                                <td class="sched-to sched-col-inbox">{{ $item['inbox_name'] ?: '—' }}</td>
                                <td>
                                    <div class="sched-row-actions">
                                        <a class="sched-link" href="{{ $item['open_url'] }}">Open</a>
                                        @if($item['can_retry'])
                                            <button type="button" class="sched-linkbtn sched-retry-btn"
                                                    data-retry-url="{{ $item['retry_url'] }}"
                                                    data-row-id="{{ $item['id'] }}">Retry</button>
                                            <button type="button" class="sched-linkbtn sched-edit-btn"
                                                    data-row-id="{{ $item['id'] }}">Edit</button>
                                        @endif
                                        @if($item['can_cancel'])
                                            <button type="button" class="sched-cancel-btn"
                                                    data-cancel-url="{{ $item['cancel_url'] }}"
                                                    data-row-id="{{ $item['id'] }}">Cancel</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @if($item['status'] === 'failed')
                                <tr class="sched-detail-row" data-detail-for="{{ $item['id'] }}">
                                    <td colspan="{{ $schedColCount }}">
                                        <div class="sched-fail-reason">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                                <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                            </svg>
                                            <span><strong>Why it failed:</strong> {{ $item['error_message'] }}</span>
                                        </div>
                                        @if($item['can_retry'])
                                            <form class="sched-edit" data-retry-url="{{ $item['retry_url'] }}" data-row-id="{{ $item['id'] }}" hidden>
                                                <div class="sched-edit-grid">
                                                    <label class="sched-edit-field">To
                                                        <input type="text" class="sched-edit-to" value="{{ $item['to'] }}" placeholder="name@example.com">
                                                    </label>
                                                    <label class="sched-edit-field">Cc
                                                        <input type="text" class="sched-edit-cc" value="{{ $item['cc'] }}" placeholder="Optional">
                                                    </label>
                                                </div>
                                                <label class="sched-edit-field">Subject
                                                    <input type="text" class="sched-edit-subject" value="{{ $item['edit_subject'] }}">
                                                </label>
                                                <div class="sched-edit-field">
                                                    <span>Message</span>
                                                    <div class="sched-edit-body" contenteditable="true" role="textbox" aria-multiline="true"></div>
                                                    <textarea class="sched-edit-body-src" hidden>{{ $item['edit_body_html'] }}</textarea>
                                                </div>
                                                <div class="sched-edit-actions">
                                                    <button type="button" class="btn btn-secondary btn-sm sched-edit-cancel">Cancel</button>
                                                    <button type="submit" class="btn btn-primary btn-sm sched-edit-save">Save &amp; retry</button>
                                                </div>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="sched-pagination">
                <span>Showing {{ $scheduledFrom }}–{{ $scheduledTo }} of {{ $scheduledSendsCount }}</span>
                <div>
                    @if($scheduledPrevUrl)
                        <a class="btn btn-secondary btn-sm" href="{{ $scheduledPrevUrl }}">Previous</a>
                    @else
                        <span class="btn btn-secondary btn-sm" aria-disabled="true">Previous</span>
                    @endif
                    @if($scheduledNextUrl)
                        <a class="btn btn-secondary btn-sm" href="{{ $scheduledNextUrl }}">Next</a>
                    @else
                        <span class="btn btn-secondary btn-sm" aria-disabled="true">Next</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.sched-cancel-btn');
        if (!btn) return;

        const url = btn.dataset.cancelUrl;
        if (!url) return;
        if (!window.confirm('Cancel this scheduled send? The email will not go out.')) return;

        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = 'Cancelling…';

        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token,
                    'Accept': 'application/json',
                },
            });
            if (!res.ok) {
                let msg = 'Could not cancel this scheduled send.';
                try { const data = await res.json(); if (data && data.message) msg = data.message; } catch (_) {}
                throw new Error(msg);
            }

            // Flip the row to a "Cancelled" state in place and drop the Cancel button.
            const row = btn.closest('tr');
            if (row) {
                const badge = row.querySelector('.sched-status-badge');
                if (badge) {
                    badge.className = 'sched-status-badge is-cancelled';
                    badge.textContent = 'Cancelled';
                    badge.removeAttribute('title');
                }
                const dueNote = row.querySelector('.sched-due-note');
                if (dueNote) dueNote.remove();
            }
            btn.remove();

            // Keep the "waiting" tally in sync with the cancelled row.
            const waitingEl = document.getElementById('schedWaitingCount');
            if (waitingEl) {
                const next = Math.max(0, (parseInt(waitingEl.textContent, 10) || 0) - 1);
                waitingEl.textContent = String(next);
            }
        } catch (err) {
            alert(err.message || 'Could not cancel this scheduled send.');
            btn.disabled = false;
            btn.textContent = original;
        }
    });

    async function schedRetry(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload || {}),
        });
        if (!res.ok) {
            let msg = 'Could not retry this scheduled send.';
            try { const data = await res.json(); if (data && data.message) msg = data.message; } catch (_) {}
            throw new Error(msg);
        }
    }

    // Quick retry (no edits)
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.sched-retry-btn');
        if (!btn) return;
        const url = btn.dataset.retryUrl;
        if (!url) return;
        if (!window.confirm('Retry this failed send now?')) return;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = 'Retrying…';
        try {
            await schedRetry(url, {});
            window.location.reload();
        } catch (err) {
            alert(err.message || 'Could not retry this scheduled send.');
            btn.disabled = false;
            btn.textContent = original;
        }
    });

    // Toggle the inline edit form (populate the body editor on first open)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sched-edit-btn');
        if (!btn) return;
        const id = btn.dataset.rowId;
        const detail = document.querySelector('.sched-detail-row[data-detail-for="' + id + '"]');
        const form = detail ? detail.querySelector('.sched-edit') : null;
        if (!form) return;
        const willShow = form.hidden;
        form.hidden = !willShow;
        if (willShow && !form.dataset.ready) {
            const src = form.querySelector('.sched-edit-body-src');
            const body = form.querySelector('.sched-edit-body');
            if (src && body) body.innerHTML = src.value;
            form.dataset.ready = '1';
            form.querySelector('.sched-edit-to')?.focus();
        }
    });

    // Cancel edit
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sched-edit-cancel');
        if (!btn) return;
        const form = btn.closest('.sched-edit');
        if (form) form.hidden = true;
    });

    // Save edits + retry
    document.addEventListener('submit', async function (e) {
        const form = e.target.closest('.sched-edit');
        if (!form) return;
        e.preventDefault();
        const url = form.dataset.retryUrl;
        if (!url) return;

        const to = (form.querySelector('.sched-edit-to')?.value || '').trim();
        if (!to) { alert('At least one recipient is required.'); return; }

        const payload = {
            to: to,
            cc: (form.querySelector('.sched-edit-cc')?.value || '').trim(),
            subject: form.querySelector('.sched-edit-subject')?.value ?? '',
            body_html: form.querySelector('.sched-edit-body')?.innerHTML ?? '',
        };

        const saveBtn = form.querySelector('.sched-edit-save');
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }
        try {
            await schedRetry(url, payload);
            window.location.reload();
        } catch (err) {
            alert(err.message || 'Could not save and retry.');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save & retry'; }
        }
    });
})();
</script>
@endpush
