@extends('layouts.app')

@section('title', 'SMTP Settings')

@section('content')
    <div class="page-header">
        <h1 class="page-title">SMTP Settings</h1>
        <p class="page-subtitle">Configure the platform email server used for welcome emails, password resets, and system notifications</p>
    </div>

    @if(session('success'))
        <div class="flash-alert flash-alert-success" role="alert">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="flash-alert flash-alert-error" role="alert">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="flash-alert flash-alert-error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">SMTP Status</span>
                <div class="stat-icon {{ $isConfigured ? 'green' : 'orange' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">
                @if($isConfigured)
                    {{ strtoupper($settings['mailer']) === 'LOG' ? 'Log Mode' : 'Configured' }}
                @else
                    Not Set
                @endif
            </div>
            <div class="stat-change">Platform email server</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Mail Driver</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ strtoupper($settings['mailer'] ?: config('mail.default', 'log')) }}</div>
            <div class="stat-change">Active mailer</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">From Address</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" style="font-size: 0.95rem; word-break: break-all;">
                {{ $settings['from_address'] ?: config('mail.from.address', '—') }}
            </div>
            <div class="stat-change">Sender email</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">SMTP Host</span>
                <div class="stat-icon {{ $isConfigured ? 'green' : 'orange' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
                        <line x1="6" y1="6" x2="6.01" y2="6"/>
                        <line x1="6" y1="18" x2="6.01" y2="18"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" style="font-size: 0.95rem;">{{ $settings['host'] ?: '—' }}</div>
            <div class="stat-change">Mail server host</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="admin-sections-grid" style="margin-top: 2rem;">

        <!-- SMTP Configuration -->
        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">SMTP Configuration</h2>
                <p class="section-subtitle">Settings are stored in the database and applied at runtime — no .env edit required</p>
            </div>
            <div class="section-card-body">
                <form method="POST" action="{{ route('admin.smtp-settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="form-row">
                        <div class="form-group">
                            <label for="mailer">Mail Driver</label>
                            <select id="mailer" name="mailer" class="form-control" onchange="toggleSmtpFields(this.value)">
                                <option value="smtp"     {{ ($settings['mailer'] ?: 'smtp') === 'smtp'     ? 'selected' : '' }}>SMTP</option>
                                <option value="log"      {{ ($settings['mailer'] ?: '')      === 'log'      ? 'selected' : '' }}>Log (testing only)</option>
                                <option value="sendmail" {{ ($settings['mailer'] ?: '')      === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="encryption">Encryption</label>
                            <select id="encryption" name="encryption" class="form-control">
                                <option value="tls" {{ ($settings['encryption'] ?: 'tls') === 'tls' ? 'selected' : '' }}>TLS (recommended)</option>
                                <option value="ssl" {{ ($settings['encryption'] ?: '')    === 'ssl' ? 'selected' : '' }}>SSL</option>
                                <option value=""    {{ ($settings['encryption'] ?: '')    === ''    ? 'selected' : '' }}>None</option>
                            </select>
                        </div>
                    </div>

                    <div id="smtp-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="host">SMTP Host</label>
                                <input type="text" id="host" name="host" class="form-control"
                                    value="{{ old('host', $settings['host']) }}"
                                    placeholder="smtp.gmail.com">
                                <p class="setting-desc" style="margin-top:0.4rem;">
                                    Gmail: smtp.gmail.com &nbsp;·&nbsp; Outlook: smtp.office365.com &nbsp;·&nbsp; SendGrid: smtp.sendgrid.net
                                </p>
                            </div>
                            <div class="form-group">
                                <label for="port">Port</label>
                                <input type="number" id="port" name="port" class="form-control"
                                    value="{{ old('port', $settings['port'] ?: 587) }}"
                                    placeholder="587">
                                <p class="setting-desc" style="margin-top:0.4rem;">TLS → 587 &nbsp;·&nbsp; SSL → 465</p>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control"
                                    value="{{ old('username', $settings['username']) }}"
                                    placeholder="your@gmail.com" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label for="password">Password / App Password</label>
                                <input type="password" id="password" name="password" class="form-control"
                                    placeholder="{{ $settings['password'] ? '•••••••••••••••• (saved — leave blank to keep)' : 'Enter password or app password' }}"
                                    autocomplete="new-password">
                                <p class="setting-desc" style="margin-top:0.4rem;">Leave blank to keep the existing password</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="from_address">From Email Address</label>
                            <input type="email" id="from_address" name="from_address" class="form-control"
                                value="{{ old('from_address', $settings['from_address'] ?: config('mail.from.address')) }}"
                                placeholder="noreply@yourcompany.com" required>
                        </div>
                        <div class="form-group">
                            <label for="from_name">From Name</label>
                            <input type="text" id="from_name" name="from_name" class="form-control"
                                value="{{ old('from_name', $settings['from_name'] ?: config('mail.from.name')) }}"
                                placeholder="{{ config('app.name') }}" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-sm btn-primary">Save SMTP Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Test + Quick Reference Grid -->
    <div class="admin-sections-grid" style="margin-top: 1.5rem;">

        <!-- Send Test Email -->
        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">Send Test Email</h2>
                <p class="section-subtitle">Verify your SMTP configuration is working correctly</p>
            </div>
            <div class="section-card-body">
                <form method="POST" action="{{ route('admin.smtp-settings.test') }}">
                    @csrf
                    <div class="form-group">
                        <label for="test_email">Recipient Email</label>
                        <input type="email" id="test_email" name="test_email" class="form-control"
                            value="{{ old('test_email') }}"
                            placeholder="you@example.com" required>
                    </div>

                    @if($isConfigured)
                        <button type="submit" class="btn-sm btn-primary">Send Test Email</button>
                    @else
                        <button type="submit" class="btn-sm btn-primary" disabled style="opacity:0.5;cursor:not-allowed;">Send Test Email</button>
                        <p class="setting-desc" style="margin-top:0.5rem; color:#f59e0b;">
                            ⚠ Configure and save SMTP settings first
                        </p>
                    @endif
                </form>
            </div>
        </div>

        <!-- Quick Reference -->
        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">Quick Reference</h2>
                <p class="section-subtitle">Common SMTP provider settings</p>
            </div>
            <div class="section-card-body">
                <table class="smtp-ref-table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Host</th>
                            <th>Port</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Gmail</strong></td><td>smtp.gmail.com</td><td>587 / TLS</td></tr>
                        <tr><td><strong>Outlook</strong></td><td>smtp.office365.com</td><td>587 / TLS</td></tr>
                        <tr><td><strong>SendGrid</strong></td><td>smtp.sendgrid.net</td><td>587 / TLS</td></tr>
                        <tr><td><strong>Mailgun</strong></td><td>smtp.mailgun.org</td><td>587 / TLS</td></tr>
                        <tr><td><strong>Amazon SES</strong></td><td>email-smtp.*.amazonaws.com</td><td>587 / TLS</td></tr>
                    </tbody>
                </table>

                <div class="gmail-tip">
                    <strong>Gmail tip:</strong> Use an <em>App Password</em>, not your regular password.<br>
                    Google Account → Security → 2-Step Verification → App passwords
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    @include('admin.partials.styles')
    <style>
        .smtp-ref-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
        .smtp-ref-table th {
            text-align: left;
            padding: 0.6rem 0.75rem;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
        }
        .smtp-ref-table td {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid var(--border);
            color: var(--text-primary);
        }
        .smtp-ref-table tbody tr:last-child td {
            border-bottom: none;
        }
        .gmail-tip {
            background: #fefce8;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 0.875rem 1rem;
            font-size: 0.8125rem;
            color: #92400e;
            line-height: 1.6;
        }
    </style>
@endpush

@push('scripts')
<script>
function toggleSmtpFields(value) {
    document.getElementById('smtp-fields').style.display = value === 'smtp' ? '' : 'none';
}
toggleSmtpFields(document.getElementById('mailer').value);
</script>
@endpush
