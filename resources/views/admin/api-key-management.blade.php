@extends('layouts.app')

@section('title', 'API Key Management')

@section('content')
    <div class="page-header">
        <h1 class="page-title">API Key Management</h1>
        <p class="page-subtitle">Create API keys and control which endpoints each company can access</p>
    </div>

    @if(session('new_api_key'))
        <div class="api-key-reveal">
            <div class="api-key-reveal-head">
                <strong>New API key created</strong>
                <span>Copy it now — it will not be shown again.</span>
            </div>
            <div class="api-key-reveal-value">
                <code id="newApiKeyValue">{{ session('new_api_key') }}</code>
                <button type="button" class="btn-sm btn-secondary" onclick="copyNewKey()">Copy</button>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="flash-alert flash-alert-error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="api-card">
        <div class="api-card-header">
            <h2 class="api-card-title">Create API Key</h2>
            <p class="api-card-subtitle">Generate a key scoped to a single company</p>
        </div>
        <form method="POST" action="{{ route('admin.api-key-management.store') }}" class="api-form">
            @csrf
            <div class="api-form-row">
                <label class="api-field">
                    <span class="api-label">Company</span>
                    <select name="company_id" required class="api-input">
                        <option value="">Select a company…</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }} ({{ $company->subdomain }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="api-field">
                    <span class="api-label">Key name / label</span>
                    <input type="text" name="name" required maxlength="255" placeholder="e.g. Partner Integration" class="api-input">
                </label>
            </div>

            <label class="api-check-inline">
                <input type="checkbox" name="can_write" value="1">
                <span>Allow write access (create &amp; update records)</span>
            </label>

            <div class="api-endpoints" data-scope="create">
                <label class="api-check-inline api-endpoints-toggle">
                    <input type="checkbox" class="js-limit-endpoints" data-target="create-endpoints">
                    <span>Restrict to specific endpoints (default: all endpoints allowed)</span>
                </label>

                <div class="api-endpoints-grid" id="create-endpoints" hidden>
                    @foreach($toolGroups as $groupName => $tools)
                        <div class="api-endpoint-group">
                            <div class="api-endpoint-group-head">
                                <span>{{ $groupName }}</span>
                                <button type="button" class="api-link-btn js-toggle-group" data-group="create">Toggle all</button>
                            </div>
                            @foreach($tools as $tool)
                                <label class="api-check-inline">
                                    <input type="checkbox" name="allowed_tools[]" value="{{ $tool['name'] }}" class="js-endpoint">
                                    <span>{{ $tool['label'] }}
                                        <code>{{ $tool['name'] }}</code>
                                        @if($tool['write'])<span class="api-badge api-badge-write">write</span>@endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="api-form-actions">
                <button type="submit" class="btn-sm btn-primary">Create Key</button>
            </div>
        </form>
    </div>

    <div class="api-card">
        <div class="api-card-header">
            <h2 class="api-card-title">Existing Keys</h2>
            <p class="api-card-subtitle">{{ $apiKeys->count() }} key(s) across all companies</p>
        </div>

        @if($apiKeys->isEmpty())
            <p class="api-empty">No API keys yet. Create one above.</p>
        @else
            <div class="api-table-wrap">
                <table class="api-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Name</th>
                            <th>Prefix</th>
                            <th>Access</th>
                            <th>Endpoints</th>
                            <th>Last used</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($apiKeys as $key)
                            <tr>
                                <td>{{ $key->company?->name ?? '—' }}</td>
                                <td>{{ $key->name }}</td>
                                <td><code>{{ $key->key_prefix }}…</code></td>
                                <td>
                                    @if($key->can_write)
                                        <span class="api-badge api-badge-write">read + write</span>
                                    @else
                                        <span class="api-badge">read-only</span>
                                    @endif
                                </td>
                                <td>
                                    @if(empty($key->allowed_tools))
                                        All
                                    @else
                                        {{ count($key->allowed_tools) }} selected
                                    @endif
                                </td>
                                <td>{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="api-actions">
                                    <details class="api-edit">
                                        <summary class="btn-sm btn-secondary">Edit</summary>
                                        <div class="api-edit-panel">
                                            <form method="POST" action="{{ route('admin.api-key-management.update', $key) }}">
                                                @csrf
                                                @method('PUT')
                                                <label class="api-field">
                                                    <span class="api-label">Key name</span>
                                                    <input type="text" name="name" value="{{ $key->name }}" required maxlength="255" class="api-input">
                                                </label>
                                                <label class="api-check-inline">
                                                    <input type="checkbox" name="can_write" value="1" @checked($key->can_write)>
                                                    <span>Allow write access</span>
                                                </label>
                                                <label class="api-check-inline api-endpoints-toggle">
                                                    <input type="checkbox" class="js-limit-endpoints" data-target="edit-endpoints-{{ $key->id }}" @checked(! empty($key->allowed_tools))>
                                                    <span>Restrict to specific endpoints</span>
                                                </label>
                                                <div class="api-endpoints-grid" id="edit-endpoints-{{ $key->id }}" @if(empty($key->allowed_tools)) hidden @endif>
                                                    @foreach($toolGroups as $groupName => $tools)
                                                        <div class="api-endpoint-group">
                                                            <div class="api-endpoint-group-head"><span>{{ $groupName }}</span></div>
                                                            @foreach($tools as $tool)
                                                                <label class="api-check-inline">
                                                                    <input type="checkbox" name="allowed_tools[]" value="{{ $tool['name'] }}"
                                                                        @checked(! empty($key->allowed_tools) && in_array($tool['name'], $key->allowed_tools, true))>
                                                                    <span>{{ $tool['label'] }} <code>{{ $tool['name'] }}</code>
                                                                        @if($tool['write'])<span class="api-badge api-badge-write">write</span>@endif
                                                                    </span>
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="api-form-actions">
                                                    <button type="submit" class="btn-sm btn-primary">Save</button>
                                                </div>
                                            </form>
                                            <form method="POST" action="{{ route('admin.api-key-management.destroy', $key) }}"
                                                onsubmit="return confirm('Revoke this API key? Applications using it will stop working.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-sm btn-danger">Revoke key</button>
                                            </form>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection

@push('styles')
<style>
    .api-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }
    .api-card-header { margin-bottom: 1.25rem; }
    .api-card-title { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); }
    .api-card-subtitle { font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.25rem; }

    .api-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    @media (max-width: 640px) { .api-form-row { grid-template-columns: 1fr; } }

    .api-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .api-label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }
    .api-input {
        padding: 0.6rem 0.75rem; border: 1px solid var(--border);
        border-radius: 8px; font-size: 0.9rem; background: var(--bg-primary); color: var(--text-primary);
        font-family: inherit;
    }
    .api-input:focus { outline: none; border-color: var(--accent); }

    .api-check-inline { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.88rem; color: var(--text-primary); margin: 0.5rem 0; cursor: pointer; }
    .api-check-inline input { margin-top: 0.2rem; }
    .api-check-inline code { font-size: 0.75rem; color: var(--text-muted); }

    .api-endpoints { border-top: 1px dashed var(--border); margin-top: 1rem; padding-top: 0.75rem; }
    .api-endpoints-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1rem; margin-top: 0.75rem;
        background: var(--bg-primary); border: 1px solid var(--border); border-radius: 10px; padding: 1rem;
    }
    .api-endpoint-group-head {
        display: flex; align-items: center; justify-content: space-between;
        font-weight: 600; font-size: 0.8rem; color: var(--text-secondary);
        text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.4rem;
    }
    .api-link-btn { background: none; border: none; color: var(--accent); cursor: pointer; font-size: 0.75rem; }

    .api-badge {
        display: inline-block; font-size: 0.68rem; padding: 1px 7px; border-radius: 20px;
        background: var(--accent-light); color: var(--accent); font-weight: 600; vertical-align: middle;
    }
    .api-badge-write { background: #fef3c7; color: #b45309; }

    .api-form-actions { margin-top: 1rem; display: flex; gap: 0.5rem; }

    .api-table-wrap { overflow-x: auto; }
    .api-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    .api-table th, .api-table td { text-align: left; padding: 0.7rem 0.75rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .api-table th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); }
    .api-table code { font-size: 0.78rem; color: var(--text-secondary); }
    .api-empty { color: var(--text-secondary); font-size: 0.9rem; }

    .api-actions .api-edit { position: relative; }
    .api-edit summary { list-style: none; cursor: pointer; display: inline-block; }
    .api-edit summary::-webkit-details-marker { display: none; }
    .api-edit-panel {
        margin-top: 0.75rem; padding: 1rem; border: 1px solid var(--border);
        border-radius: 10px; background: var(--bg-primary); min-width: 280px;
    }
    .api-edit-panel form + form { margin-top: 0.75rem; border-top: 1px dashed var(--border); padding-top: 0.75rem; }

    .btn-danger { background: #ef4444; color: #fff; border: none; border-radius: 8px; padding: 0.45rem 0.9rem; cursor: pointer; font-size: 0.82rem; }
    .btn-danger:hover { background: #dc2626; }

    .api-key-reveal {
        background: #ecfdf5; border: 1px solid #6ee7b7; border-radius: 12px;
        padding: 1rem 1.25rem; margin-bottom: 1.5rem;
    }
    .api-key-reveal-head { display: flex; flex-direction: column; gap: 0.15rem; margin-bottom: 0.6rem; }
    .api-key-reveal-head strong { color: #065f46; }
    .api-key-reveal-head span { font-size: 0.8rem; color: #047857; }
    .api-key-reveal-value { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
    .api-key-reveal-value code {
        background: #022c22; color: #6ee7b7; padding: 0.5rem 0.75rem; border-radius: 8px;
        font-size: 0.85rem; word-break: break-all; flex: 1; min-width: 240px;
    }
</style>
@endpush

@push('scripts')
<script>
    document.querySelectorAll('.js-limit-endpoints').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            var target = document.getElementById(toggle.dataset.target);
            if (!target) return;
            target.hidden = !toggle.checked;
            if (!toggle.checked) {
                target.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
            }
        });
    });

    document.querySelectorAll('.js-toggle-group').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.api-endpoint-group');
            var boxes = group.querySelectorAll('input[type="checkbox"]');
            var allChecked = Array.prototype.every.call(boxes, function (b) { return b.checked; });
            boxes.forEach(function (b) { b.checked = !allChecked; });
        });
    });

    function copyNewKey() {
        var el = document.getElementById('newApiKeyValue');
        if (!el) return;
        navigator.clipboard.writeText(el.textContent.trim());
    }
</script>
@endpush
