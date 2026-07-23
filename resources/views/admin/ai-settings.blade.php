@extends('layouts.app')

@section('title', 'Main AI')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Main AI</h1>
        <p class="page-subtitle">Connect all new accounts to your main AI and control token usage limits</p>
    </div>

    @if($errors->any())
        <div class="flash-alert flash-alert-error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Main AI Status</span>
                <div class="stat-icon {{ ($settings['has_api_key'] ?? false) ? 'green' : 'orange' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ ($settings['has_api_key'] ?? false) ? 'Configured' : 'Not Set' }}</div>
            <div class="stat-change">Shared API key</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Connected Companies</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ number_format($connectedCount) }}</div>
            <div class="stat-change">Using main AI</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Tokens Used</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ number_format($totalTokensUsed) }}</div>
            <div class="stat-change">Across connected companies</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Auto-connect</span>
                <div class="stat-icon {{ ($settings['auto_connect'] ?? false) ? 'green' : 'orange' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ ($settings['auto_connect'] ?? false) ? 'On' : 'Off' }}</div>
            <div class="stat-change">New accounts</div>
        </div>
    </div>

    <!-- Settings Form -->
    <div class="admin-sections-grid" style="margin-top: 2rem;">
        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">Main AI Connection</h2>
                <p class="section-subtitle">Configure the shared AI used by companies and their token limits</p>
            </div>
            <div class="section-card-body">
                <form action="{{ route('admin.ai-settings.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="ai-setting-row">
                        <div class="setting-info">
                            <div class="setting-name">Auto-connect new companies</div>
                            <p class="setting-desc">Every newly registered or created company is automatically connected to the platform main AI.</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="auto_connect" value="1" {{ ($settings['auto_connect'] ?? false) ? 'checked' : '' }}>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="main_api_key">Main AI API Key</label>
                        <input type="password" id="main_api_key" name="main_api_key" class="form-control"
                            autocomplete="new-password"
                            placeholder="{{ ($settings['has_api_key'] ?? false) ? '•••••••••• (configured — leave blank to keep)' : 'sk-...' }}">
                        <p class="setting-desc" style="margin-top: 0.5rem;">Shared key used by all companies connected to the main AI. Stored encrypted.</p>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="default_token_limit">Default token limit per company</label>
                            <input type="number" id="default_token_limit" name="default_token_limit" class="form-control"
                                min="0" step="1000" value="{{ $settings['default_token_limit'] ?? 0 }}">
                            <p class="setting-desc" style="margin-top: 0.5rem;">0 means unlimited tokens.</p>
                        </div>
                        <div class="form-group">
                            <label for="main_model">Default model</label>
                            <select id="main_model" name="main_model" class="form-control">
                                @php($currentModel = $settings['main_model'] ?? 'gpt-4o')
                                @foreach (['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'] as $model)
                                    <option value="{{ $model }}" {{ $currentModel === $model ? 'selected' : '' }}>{{ $model }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="ai-setting-row">
                        <div class="setting-info">
                            <div class="setting-name">Apply to existing companies</div>
                            <p class="setting-desc">Also connect all current companies to the main AI using the default token limit above.</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="apply_to_all" value="1">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <button type="submit" class="btn-sm btn-primary">Save AI Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Per-company token usage -->
    <div class="admin-sections-grid" style="margin-top: 2rem;">
        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">Token Usage by Company</h2>
                <p class="section-subtitle">Monitor how many tokens each company has consumed</p>
            </div>
            <div class="section-card-body">
                @if($companyUsage->isEmpty())
                    <p class="setting-desc">No companies found.</p>
                @else
                    <div class="usage-table-wrap">
                        <table class="usage-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Source</th>
                                    <th>Tokens Used</th>
                                    <th>Usage</th>
                                    <th>Token Limit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($companyUsage as $row)
                                    <tr>
                                        <td class="usage-company">{{ $row['company'] }}</td>
                                        <td>
                                            @if($row['source'] === 'own')
                                                <span class="usage-tag own">Company Owned</span>
                                            @elseif($row['source'] === 'main')
                                                <span class="usage-tag main">Main AI</span>
                                            @else
                                                <span class="usage-tag none">Not connected</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($row['tokens_used']) }}</td>
                                        <td>
                                            @if($row['percent'] !== null)
                                                <div class="usage-bar">
                                                    <div class="usage-bar-fill {{ $row['percent'] >= 90 ? 'danger' : ($row['percent'] >= 70 ? 'warning' : '') }}" style="width: {{ $row['percent'] }}%"></div>
                                                </div>
                                                <span class="usage-percent">{{ $row['percent'] }}%</span>
                                            @else
                                                <span class="usage-percent">Unlimited</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.ai-settings.company-limit', $row['id']) }}" class="limit-form">
                                                @csrf
                                                @method('PUT')
                                                <input type="number" name="token_limit" min="0" step="1000"
                                                    value="{{ $row['token_limit'] }}" placeholder="0 = unlimited" class="limit-input">
                                                <button type="submit" class="btn-sm btn-secondary">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('admin.partials.styles')
    <style>
        .ai-setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }

        .usage-table-wrap {
            overflow-x: auto;
        }

        .usage-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .usage-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        .usage-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
            vertical-align: middle;
        }

        .usage-table tbody tr:last-child td {
            border-bottom: none;
        }

        .usage-company {
            font-weight: 600;
        }

        .usage-tag {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .usage-tag.main {
            background: #ede9fe;
            color: #7c3aed;
        }

        .usage-tag.own {
            background: #e0f2fe;
            color: #0369a1;
        }

        .usage-tag.none {
            background: var(--border);
            color: var(--text-secondary);
        }

        .limit-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .limit-input {
            width: 130px;
            padding: 0.4rem 0.6rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-card);
            color: var(--text-primary);
            font-size: 0.85rem;
        }

        .limit-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .usage-bar {
            display: inline-block;
            width: 120px;
            height: 8px;
            background: var(--border);
            border-radius: 999px;
            overflow: hidden;
            vertical-align: middle;
            margin-right: 0.5rem;
        }

        .usage-bar-fill {
            height: 100%;
            background: #22c55e;
            border-radius: 999px;
        }

        .usage-bar-fill.warning {
            background: #f59e0b;
        }

        .usage-bar-fill.danger {
            background: #ef4444;
        }

        .usage-percent {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
    </style>
@endpush
