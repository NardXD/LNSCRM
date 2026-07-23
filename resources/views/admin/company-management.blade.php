@extends('layouts.app')

@section('title', 'Company Management - Admin')

@section('content')
    <div class="page-header">
        <a href="{{ route('admin-control') }}" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Admin
        </a>
        <h1 class="page-title">Company Management</h1>
        <p class="page-subtitle">Create and manage all companies. Account creation is restricted to admin only.</p>
    </div>

    <!-- Admin Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Companies</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="statTotalCompanies">{{ $companies->count() }}</div>
            <div class="stat-change positive" id="statCompaniesLabel">Companies in system</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Companies</span>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="statActiveCompanies">{{ $companies->whereIn('status', ['active', 'trial'])->count() }}</div>
            <div class="stat-change positive">Active / Trial</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Users</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="statTotalUsers">{{ $companies->sum(fn($c) => $c->users_count) }}</div>
            <div class="stat-change">Across all companies</div>
        </div>
    </div>

    <!-- Company Management Section -->
    <div class="admin-sections-grid">
        @include('admin.sections.company-management')
    </div>

    @include('admin.partials.modals')
@endsection

@push('styles')
    @include('admin.partials.styles')
    <style>
        .modules-checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.5rem;
            max-height: 280px;
            overflow-y: auto;
            padding: 0.25rem;
        }
        .module-checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
        }
        .module-checkbox-item:hover { background: var(--bg-primary, #f9fafb); }
        .module-checkbox-item.check-all-item {
            grid-column: 1 / -1;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--border, #e5e7eb);
        }
        .module-checkbox-item input { accent-color: var(--accent, #5f61e6); }
        .loading-text, .error-text { color: var(--text-secondary, #6b7280); }
        .error-text { color: #dc2626; }
        .status-select {
            width: 100%;
            max-width: 108px;
            padding: 0.25rem 1.75rem 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.25;
            border-radius: 999px;
            border: 1px solid var(--border);
            background-color: var(--bg-card);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.4rem center;
            appearance: none;
            cursor: pointer;
        }
        .status-select.status-active { color: #059669; background-color: #ecfdf5; border-color: #a7f3d0; }
        .status-select.status-trial { color: #2563eb; background-color: #eff6ff; border-color: #bfdbfe; }
        .status-select.status-suspended { color: #d97706; background-color: #fffbeb; border-color: #fde68a; }

        .companies-table-wrap {
            border-radius: 10px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #companies.admin-section-card {
            overflow: visible;
        }

        #companies .section-card-body {
            overflow: visible;
        }

        .companies-table {
            table-layout: fixed;
            width: 100%;
            min-width: 880px;
        }

        .companies-table col.col-company { width: 18%; }
        .companies-table col.col-subdomain { width: 12%; }
        .companies-table col.col-email { width: 22%; }
        .companies-table col.col-status { width: 10%; }
        .companies-table col.col-plan { width: 9%; }
        .companies-table col.col-users { width: 6%; }
        .companies-table col.col-created { width: 11%; }
        .companies-table col.col-actions { width: 48px; }

        .companies-table th,
        .companies-table td {
            padding: 0.625rem 0.75rem;
            font-size: 0.8125rem;
            vertical-align: middle;
        }

        .companies-table th {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--text-muted);
            white-space: nowrap;
        }

        .companies-table tbody tr:hover {
            background: #fafafa;
        }

        .companies-table .col-actions-header,
        .companies-table .cell-actions {
            text-align: center;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
        }

        .company-name {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cell-truncate {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: var(--text-secondary);
        }

        .subdomain-badge {
            display: inline-block;
            max-width: 100%;
            padding: 0.125rem 0.4375rem;
            border-radius: 4px;
            background: var(--bg-primary);
            border: 1px solid var(--border);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.6875rem;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }

        .cell-plan,
        .cell-users,
        .cell-created {
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .cell-users {
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        .cell-created {
            font-size: 0.75rem;
        }

        .row-actions-menu {
            position: relative;
            display: inline-block;
        }

        .row-actions-trigger {
            list-style: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
        }

        .row-actions-trigger::-webkit-details-marker { display: none; }

        .row-actions-trigger:hover,
        .row-actions-menu[open] .row-actions-trigger {
            background: var(--bg-primary);
            border-color: var(--border);
            color: var(--text-primary);
        }

        .row-actions-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 4px);
            z-index: 20;
            min-width: 188px;
            padding: 0.25rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .row-actions-dropdown form {
            margin: 0;
        }

        .row-action-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.4375rem 0.625rem;
            border: none;
            border-radius: 6px;
            background: transparent;
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-primary);
            text-align: left;
            cursor: pointer;
            transition: background 0.15s;
        }

        .row-action-item:hover {
            background: var(--bg-primary);
        }

        .row-action-item.row-action-primary {
            color: var(--accent);
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 768px) {
            .companies-table {
                min-width: 760px;
            }

            .companies-table th,
            .companies-table td {
                padding: 0.5rem 0.625rem;
            }
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-decoration: none;
            margin-bottom: 0.75rem;
            transition: color 0.15s;
        }
        .back-link:hover { color: var(--accent); }
        .back-link svg { flex-shrink: 0; }
        .history-timeline { max-height: 360px; overflow-y: auto; }
        .history-item { display: flex; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--border, #e5e7eb); }
        .history-item:last-child { border-bottom: none; }
        .history-icon { flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--accent-light, #f0f0ff); color: var(--accent, #5f61e6); }
        .history-icon.created { background: #dcfce7; color: #16a34a; }
        .history-icon.status_changed { background: #fef3c7; color: #d97706; }
        .history-icon.modules_updated { background: #dbeafe; color: #2563eb; }
        .history-content { flex: 1; min-width: 0; }
        .history-summary { font-weight: 500; color: var(--text-primary); }
        .history-meta { font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem; }
    </style>
@endpush

@push('scripts')
    <script>
        window.__companyManagementConfig = {
            apiBase: @json(url('/admin')),
            apiUrl: @json(url('/admin/api')),
        };
    </script>
    <script src="{{ asset('js/admin-company-management.js') }}?v={{ filemtime(public_path('js/admin-company-management.js')) }}" defer></script>
@endpush
