@extends('layouts.app')

@section('title', 'Screen Recording Management - Admin')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Screen Recording Management</h1>
        <p class="page-subtitle">Bulk delete screen recordings by date range</p>
    </div>

    @if (session('success'))
        <div class="alert-success" style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 8px; margin-bottom: 1.5rem;">
            <svg style="width: 24px; height: 24px; color: #059669; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <div style="flex: 1; font-size: 0.875rem; color: #065f46;">{{ session('success') }}</div>
        </div>
    @endif

    <div class="admin-sections-grid">
        <div class="admin-section-card">
            <div class="section-card-header">
                <div class="section-card-title">
                    <div class="section-icon orange">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="23 7 16 12 23 17 23 7"/>
                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="section-title">Bulk Delete Screen Recordings</h2>
                        <p class="section-subtitle">Delete database records and recording files within a date range</p>
                    </div>
                </div>
            </div>

            <div class="section-card-body">
                <div class="alert-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <div>
                        <strong>Warning:</strong> This action is irreversible. All screen recordings (database records and files) within the selected date range will be permanently deleted.
                    </div>
                </div>

                <form action="{{ route('admin.screen-recording-management.bulk-delete') }}" method="POST" id="bulkDeleteForm">
                    @csrf

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group">
                            <label for="date_from">Start Date</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ old('date_from') }}" required>
                            @error('date_from')
                                <span class="text-danger" style="font-size: 0.75rem; color: #dc2626;">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="date_to">End Date</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ old('date_to') }}" required>
                            @error('date_to')
                                <span class="text-danger" style="font-size: 0.75rem; color: #dc2626;">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="company_id">Company (optional)</label>
                        <select name="company_id" id="company_id" class="form-control">
                            <option value="">All Companies</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <span class="form-hint" style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem; display: block;">Leave empty to delete from all companies</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <button type="button" class="btn-secondary btn-sm" id="previewCountBtn">
                            Preview count
                        </button>
                        <span id="previewCountResult" style="margin-left: 0.5rem; font-size: 0.875rem; color: var(--text-secondary);"></span>
                    </div>

                    <div class="form-actions" style="display: flex; gap: 0.75rem; align-items: center;">
                        <button type="submit" class="btn-danger btn-primary" id="deleteBtn" onclick="return confirm('Are you sure you want to permanently delete all screen recordings in this date range? This cannot be undone.');">
                            Delete Recordings
                        </button>
                        <a href="{{ route('admin-control') }}" class="btn-secondary">Cancel</a>
                    </div>
                </form>

                <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--border-color);">

                <div>
                    <h3 style="margin-bottom: 0.5rem;">Upload Sync Health</h3>
                    <p style="margin-bottom: 1rem; font-size: 0.875rem; color: var(--text-secondary);">Queued, uploading, uploaded and failed recorder uploads per company.</p>
                    <button type="button" class="btn-secondary btn-sm" id="loadSyncOverviewBtn">Load sync overview</button>
                    <div id="syncOverviewResult" style="margin-top: 1rem; overflow-x: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.modals')
@endsection

@push('styles')
    @include('admin.partials.styles')
@endpush

@push('scripts')
    @include('admin.partials.scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const previewBtn = document.getElementById('previewCountBtn');
            const previewResult = document.getElementById('previewCountResult');
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');
            const companyId = document.getElementById('company_id');
            const loadSyncOverviewBtn = document.getElementById('loadSyncOverviewBtn');
            const syncOverviewResult = document.getElementById('syncOverviewResult');

            previewBtn.addEventListener('click', function() {
                const from = dateFrom.value;
                const to = dateTo.value;
                if (!from || !to) {
                    previewResult.textContent = 'Please select both dates.';
                    return;
                }
                if (new Date(to) < new Date(from)) {
                    previewResult.textContent = 'End date must be on or after start date.';
                    return;
                }

                previewResult.textContent = 'Loading...';
                previewBtn.disabled = true;

                fetch('{{ route("admin.screen-recording-management.preview") }}?' + new URLSearchParams({
                    date_from: from,
                    date_to: to,
                    company_id: companyId.value || ''
                }), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    previewResult.textContent = data.count + ' recording(s) would be deleted.';
                })
                .catch(() => {
                    previewResult.textContent = 'Could not fetch count.';
                })
                .finally(() => {
                    previewBtn.disabled = false;
                });
            });

            loadSyncOverviewBtn.addEventListener('click', function() {
                syncOverviewResult.textContent = 'Loading sync overview...';
                loadSyncOverviewBtn.disabled = true;

                fetch('{{ route("admin.screen-recording-management.sync-overview") }}?' + new URLSearchParams({
                    date_from: dateFrom.value || '',
                    date_to: dateTo.value || ''
                }), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    if (!data.summary || data.summary.length === 0) {
                        syncOverviewResult.textContent = 'No sync data found for the selected range.';
                        return;
                    }

                    let html = '<table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">';
                    html += '<thead><tr><th style="text-align:left; padding: 0.5rem; border-bottom: 1px solid var(--border-color);">Company</th><th style="text-align:right; padding: 0.5rem; border-bottom: 1px solid var(--border-color);">Queued</th><th style="text-align:right; padding: 0.5rem; border-bottom: 1px solid var(--border-color);">Uploading</th><th style="text-align:right; padding: 0.5rem; border-bottom: 1px solid var(--border-color);">Uploaded</th><th style="text-align:right; padding: 0.5rem; border-bottom: 1px solid var(--border-color);">Failed</th></tr></thead><tbody>';

                    data.summary.forEach((row) => {
                        html += '<tr>';
                        html += `<td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color);">${row.company_name}</td>`;
                        html += `<td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color); text-align:right;">${row.queued}</td>`;
                        html += `<td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color); text-align:right;">${row.uploading}</td>`;
                        html += `<td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color); text-align:right;">${row.uploaded}</td>`;
                        html += `<td style="padding: 0.5rem; border-bottom: 1px solid var(--border-color); text-align:right;">${row.failed}</td>`;
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    syncOverviewResult.innerHTML = html;
                })
                .catch(() => {
                    syncOverviewResult.textContent = 'Unable to load sync overview.';
                })
                .finally(() => {
                    loadSyncOverviewBtn.disabled = false;
                });
            });
        });
    </script>
@endpush
