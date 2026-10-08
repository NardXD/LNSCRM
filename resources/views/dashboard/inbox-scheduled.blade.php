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
    .sched-table { width: 100%; min-width: 640px; border-collapse: collapse; font-size: 0.875rem; }
    .sched-table th, .sched-table td {
        text-align: left;
        padding: 0.7rem 1rem;
        border-bottom: 1px solid var(--border, #e5e7eb);
        vertical-align: middle;
    }
    .sched-table thead th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-secondary, #6b7280);
        background: var(--bg-primary, #fafafa);
        white-space: nowrap;
    }
    .sched-table tbody tr:last-child td { border-bottom: none; }
    .sched-table tbody tr:hover { background: var(--accent-light, #f0f0ff); }
    .sched-when { font-weight: 600; color: var(--text-primary, #111827); white-space: nowrap; }
    .sched-subject { font-weight: 600; color: var(--text-primary, #111827); max-width: 360px; overflow-wrap: anywhere; }
    .sched-to { color: var(--text-secondary, #6b7280); max-width: 220px; overflow-wrap: anywhere; }
    .sched-meta { color: var(--text-secondary, #6b7280); font-size: 0.76rem; margin-top: 0.2rem; display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .sched-due-note { color: #92400e; font-size: 0.72rem; margin-left: 0.3rem; }
    .sched-type-pill {
        display: inline-block;
        padding: 0.1rem 0.5rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        background: var(--accent-light, #f0f0ff);
        color: var(--accent, #5f61e6);
        white-space: nowrap;
    }
    .sched-status-badge {
        display: inline-block;
        padding: 0.12rem 0.5rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .sched-status-badge.is-scheduled { background: #e0e7ff; color: #3730a3; }
    .sched-status-badge.is-sending   { background: #fef3c7; color: #92400e; }
    .sched-status-badge.is-sent      { background: #dcfce7; color: #166534; }
    .sched-status-badge.is-failed    { background: #fee2e2; color: #991b1b; }
    .sched-status-badge.is-cancelled { background: #f3f4f6; color: #6b7280; }
    .sched-row-actions { display: flex; gap: 0.4rem; justify-content: flex-end; white-space: nowrap; }
    .sched-link {
        color: var(--accent, #5f61e6);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.82rem;
        padding: 0.3rem 0.5rem;
        border-radius: 6px;
    }
    .sched-link:hover { background: var(--accent-light, #f0f0ff); }
    .sched-cancel-btn {
        border: 1px solid var(--border, #e5e7eb);
        background: var(--bg-card, #fff);
        color: #b91c1c;
        font-weight: 600;
        font-size: 0.82rem;
        padding: 0.3rem 0.6rem;
        border-radius: 6px;
        cursor: pointer;
    }
    .sched-cancel-btn:hover { background: #fef2f2; border-color: #fecaca; }
    .sched-cancel-btn:disabled { opacity: 0.6; cursor: default; }
    .sched-empty {
        text-align: center;
        padding: 3rem 1.5rem;
        color: var(--text-secondary, #6b7280);
    }
    .sched-empty svg { width: 42px; height: 42px; margin-bottom: 0.75rem; color: var(--text-muted, #9ca3af); }
    .sched-empty h3 { color: var(--text-primary, #111827); margin-bottom: 0.35rem; font-size: 1rem; }
    .sched-tablewrap { overflow-x: auto; }
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
            <div class="sched-tablewrap">
                <table class="sched-table">
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
                                        @if($item['can_cancel'])
                                            <button type="button" class="sched-cancel-btn"
                                                    data-cancel-url="{{ $item['cancel_url'] }}"
                                                    data-row-id="{{ $item['id'] }}">Cancel</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
})();
</script>
@endpush
