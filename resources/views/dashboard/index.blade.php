@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $canAccess = function ($permission, $moduleSlug = null) use ($userPermissions, $companyModuleSlugs) {
            return \App\Helpers\SidebarHelper::canAccessModule($userPermissions ?? [], $companyModuleSlugs ?? null, $permission, $moduleSlug);
        };
        $channelShells = [
            ['key' => 'phone', 'label' => 'Phone System'],
            ['key' => 'inbox', 'label' => 'Inbox'],
            ['key' => 'viber', 'label' => 'Viber'],
            ['key' => 'facebook', 'label' => 'Facebook'],
            ['key' => 'sms', 'label' => 'SMS'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp'],
        ];
        $channelIcons = [
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>',
            'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
            'viber' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
            'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
            'sms' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'whatsapp' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
        ];
    @endphp
    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Lead pipeline and channel activity for <span id="dashPeriodLabel">{{ now()->format('F Y') }}</span> across phone, inbox, Viber, Facebook, SMS, and WhatsApp.</p>
    </div>

    <div class="stats-grid" data-testid="lead-kpis" aria-busy="true">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Leads this month</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="dashLeadsTotal"><span class="page-skel-stat"></span></div>
            <div class="stat-change" id="dashLeadsChange">vs last month</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">New</span>
                <div class="stat-icon indigo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="dashLeadsNew"><span class="page-skel-stat"></span></div>
            <div class="stat-change" id="dashLeadsUnassigned"> </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Converted</span>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="dashLeadsConverted"><span class="page-skel-stat"></span></div>
            <div class="stat-change" id="dashLeadsConversion">conversion rate</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Lost</span>
                <div class="stat-icon red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="dashLeadsLost"><span class="page-skel-stat"></span></div>
            <div class="stat-change" id="dashLeadsSnoozed"> </div>
        </div>
    </div>

    <div class="channel-grid" data-testid="channel-grid" id="channelGrid" aria-busy="true">
        @foreach($channelShells as $channel)
            <div class="channel-card channel-{{ $channel['key'] }} channel-card-disabled" data-channel-key="{{ $channel['key'] }}">
                <div class="channel-card-header">
                    <div class="channel-icon {{ $channel['key'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $channelIcons[$channel['key']] !!}</svg>
                    </div>
                    <span class="channel-label">{{ $channel['label'] }}</span>
                </div>
                <div class="channel-primary" data-channel-primary><span class="page-skel-stat"></span></div>
                <div class="channel-primary-label" data-channel-primary-label></div>
                <div class="channel-metrics">
                    <span data-channel-secondary><span class="page-skel-line w-50"></span></span>
                    <span data-channel-tertiary><span class="page-skel-line w-40"></span></span>
                </div>
                <div class="channel-extra" data-channel-extra></div>
            </div>
        @endforeach
    </div>

    <div class="dashboard-widgets-grid">
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Lead pipeline</h3>
                <a href="{{ $canAccess('view_leads', 'client-management') ? route('leads') : '#' }}" class="widget-link {{ $canAccess('view_leads', 'client-management') ? '' : 'disabled' }}" id="dashPipelineLink">View leads</a>
            </div>
            <div class="widget-body">
                <div class="pipeline-list" id="dashPipeline" aria-busy="true">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="pipeline-row">
                            <div class="pipeline-meta">
                                <span class="page-skel-line w-40"></span>
                                <span class="page-skel-line w-35"></span>
                            </div>
                            <div class="pipeline-bar"><span style="width: {{ 70 - $i * 15 }}%"></span></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Top sources</h3>
                <a href="{{ $canAccess('view_lead_reports', 'client-management') ? route('lead-reports') : '#' }}" class="widget-link {{ $canAccess('view_lead_reports', 'client-management') ? '' : 'disabled' }}" id="dashSourcesLink">Lead reports</a>
            </div>
            <div class="widget-body">
                <div class="pipeline-list" id="dashSources" aria-busy="true">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="pipeline-row">
                            <div class="pipeline-meta">
                                <span class="page-skel-line w-50"></span>
                                <span class="page-skel-line w-35"></span>
                            </div>
                            <div class="pipeline-bar source"><span style="width: {{ 65 - $i * 12 }}%"></span></div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Needs attention</h3>
            </div>
            <div class="widget-body">
                <div class="items-list" id="dashAttention" aria-busy="true">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="item-row">
                            <div class="item-info" style="flex:1;">
                                <span class="page-skel-line w-70"></span>
                                <span class="page-skel-line w-50" style="margin-top:0.4rem;"></span>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Recent leads</h3>
                <a href="{{ $canAccess('view_leads', 'client-management') ? route('leads') : '#' }}" class="widget-link {{ $canAccess('view_leads', 'client-management') ? '' : 'disabled' }}" id="dashRecentLeadsLink">View all</a>
            </div>
            <div class="widget-body">
                <div class="items-list" id="dashRecentLeads" aria-busy="true">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="item-row">
                            <div class="item-info" style="flex:1;">
                                <span class="page-skel-line w-55"></span>
                                <span class="page-skel-line w-70" style="margin-top:0.4rem;"></span>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Lead activity</h3>
            </div>
            <div class="widget-body">
                <div class="activity-list" id="dashActivity" aria-busy="true">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="activity-item">
                            <div class="activity-icon blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="activity-content" style="flex:1;">
                                <span class="page-skel-line w-80"></span>
                                <span class="page-skel-line w-50" style="margin-top:0.4rem;"></span>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Quick actions</h3>
            </div>
            <div class="widget-body">
                <div class="quick-actions-grid">
                    @foreach([
                        ['label' => 'Leads', 'route' => 'leads', 'permission' => 'view_leads', 'module' => 'client-management', 'icon' => 'blue', 'svg' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>'],
                        ['label' => 'Inbox', 'route' => 'inbox', 'permission' => 'view_inbox', 'module' => 'inbox', 'icon' => 'orange', 'svg' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>'],
                        ['label' => 'Phone', 'route' => 'twilio.call', 'permission' => 'view_phone_system', 'module' => 'phone-system', 'icon' => 'green', 'svg' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.81.36 1.6.68 2.35a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.75.32 1.54.55 2.35.68A2 2 0 0 1 22 16.92z"/>'],
                        ['label' => 'SMS', 'route' => 'sms', 'permission' => 'view_sms', 'module' => 'sms', 'icon' => 'teal', 'svg' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
                        ['label' => 'WhatsApp', 'route' => 'whatsapp', 'permission' => 'view_whatsapp', 'module' => 'whatsapp', 'icon' => 'green', 'svg' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
                        ['label' => 'Facebook', 'route' => 'facebook', 'permission' => 'view_facebook', 'module' => 'facebook', 'icon' => 'indigo', 'svg' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>'],
                        ['label' => 'Viber', 'route' => 'viber', 'permission' => 'view_viber', 'module' => 'viber', 'icon' => 'purple', 'svg' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
                        ['label' => 'Reports', 'route' => 'lead-reports', 'permission' => 'view_lead_reports', 'module' => 'client-management', 'icon' => 'orange', 'svg' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
                    ] as $action)
                        @if($canAccess($action['permission'], $action['module']))
                            <a href="{{ route($action['route']) }}" class="quick-action-btn">
                        @else
                            <span class="quick-action-btn quick-action-btn-disabled" title="You don't have access to this module">
                        @endif
                            <div class="quick-action-icon {{ $action['icon'] }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $action['svg'] !!}</svg>
                            </div>
                            <span>{{ $action['label'] }}</span>
                        @if($canAccess($action['permission'], $action['module']))
                            </a>
                        @else
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .stat-icon.red { background: #fee2e2; color: #dc2626; }
    .stat-icon.teal { background: #ccfbf1; color: #0d9488; }
    .stat-icon.indigo { background: #e0e7ff; color: #4f46e5; }

    .channel-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .channel-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.15rem 1.25rem;
        text-decoration: none;
        color: inherit;
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        transition: border-color 0.15s, transform 0.15s, box-shadow 0.15s;
    }

    a.channel-card:hover {
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
    }

    .channel-card-disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .channel-card-header {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 0.55rem;
    }

    .channel-icon {
        width: 36px;
        height: 36px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .channel-icon svg { width: 18px; height: 18px; }
    .channel-icon.phone { background: #d1fae5; color: #059669; }
    .channel-icon.inbox { background: #ffedd5; color: #ea580c; }
    .channel-icon.viber { background: #ede9fe; color: #7c3aed; }
    .channel-icon.facebook { background: #dbeafe; color: #2563eb; }
    .channel-icon.sms { background: #ccfbf1; color: #0d9488; }
    .channel-icon.whatsapp { background: #dcfce7; color: #16a34a; }

    .channel-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .channel-primary {
        font-size: 1.65rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1.1;
    }

    .channel-primary-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 0.55rem;
    }

    .channel-metrics {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .channel-extra {
        margin-top: 0.45rem;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .dashboard-widgets-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
    }

    .widget-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .widget-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .widget-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .widget-link {
        font-size: 0.875rem;
        color: var(--accent);
        text-decoration: none;
        font-weight: 500;
    }

    .widget-link:hover { color: var(--accent-hover); }
    .widget-link.disabled {
        color: var(--text-muted);
        cursor: not-allowed;
        pointer-events: none;
    }

    .widget-body { padding: 1.5rem; flex: 1; }

    .pipeline-list { display: flex; flex-direction: column; gap: 0.85rem; }
    .pipeline-meta {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.35rem;
        font-size: 0.8125rem;
    }
    .pipeline-label { color: var(--text-primary); font-weight: 500; }
    .pipeline-count { color: var(--text-muted); }
    .pipeline-bar {
        height: 8px;
        background: var(--bg-primary);
        border-radius: 999px;
        overflow: hidden;
    }
    .pipeline-bar span {
        display: block;
        height: 100%;
        background: var(--accent);
        border-radius: 999px;
        min-width: 0;
    }
    .pipeline-bar.source span { background: #7c3aed; }

    .activity-list { display: flex; flex-direction: column; gap: 1rem; }
    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
    }
    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: #dbeafe;
        color: #2563eb;
    }
    .activity-icon svg { width: 18px; height: 18px; }
    .activity-content { flex: 1; min-width: 0; }
    .activity-text { font-size: 0.875rem; color: var(--text-primary); margin-bottom: 0.25rem; }
    .activity-meta { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--text-muted); }

    .items-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .item-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
        gap: 0.5rem;
    }
    .item-row:hover { background: var(--border); }
    .item-row-disabled { cursor: default; opacity: 0.7; pointer-events: none; }
    .item-info { flex: 1; min-width: 0; }
    .item-title { font-size: 0.875rem; font-weight: 500; color: var(--text-primary); margin-bottom: 0.25rem; }
    .item-subtitle { font-size: 0.75rem; color: var(--text-muted); }
    .item-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        flex-shrink: 0;
        background: #e5e7eb;
        color: #6b7280;
    }
    .item-badge.new, .item-badge.open, .item-badge.inbox { background: #dbeafe; color: #2563eb; }
    .item-badge.contacted, .item-badge.in-progress { background: #fef3c7; color: #d97706; }
    .item-badge.qualified, .item-badge.sms { background: #ccfbf1; color: #0d9488; }
    .item-badge.converted, .item-badge.whatsapp, .item-badge.active { background: #d1fae5; color: #059669; }
    .item-badge.lost, .item-badge.urgent { background: #fee2e2; color: #dc2626; }
    .item-badge.snoozed, .item-badge.pending { background: #fef3c7; color: #d97706; }
    .item-badge.phone { background: #d1fae5; color: #047857; }
    .item-badge.viber { background: #ede9fe; color: #7c3aed; }
    .item-badge.facebook { background: #dbeafe; color: #1d4ed8; }
    .item-date { font-size: 0.75rem; color: var(--text-muted); flex-shrink: 0; }

    .quick-actions-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        text-decoration: none;
        color: var(--text-primary);
        transition: all 0.15s;
    }
    .quick-action-btn:hover {
        background: var(--border);
        border-color: var(--accent);
        transform: translateY(-2px);
    }
    .quick-action-btn-disabled { cursor: not-allowed; opacity: 0.6; pointer-events: none; }
    .quick-action-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .quick-action-icon svg { width: 24px; height: 24px; }
    .quick-action-icon.blue { background: #dbeafe; color: #2563eb; }
    .quick-action-icon.orange { background: #fed7aa; color: #ea580c; }
    .quick-action-icon.green { background: #d1fae5; color: #059669; }
    .quick-action-icon.purple { background: #ede9fe; color: #7c3aed; }
    .quick-action-icon.teal { background: #ccfbf1; color: #0d9488; }
    .quick-action-icon.indigo { background: #e0e7ff; color: #4f46e5; }
    .quick-action-btn span { font-size: 0.8125rem; font-weight: 500; text-align: center; }

    @media (max-width: 1024px) {
        .dashboard-widgets-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .stats-grid, .channel-grid { grid-template-columns: repeat(2, 1fr); }
        .dashboard-widgets-grid { grid-template-columns: 1fr; }
        .quick-actions-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 480px) {
        .stats-grid, .channel-grid, .quick-actions-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const overviewUrl = @json(route('api.dashboard.overview'));

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    function fmt(n) {
        return Number(n || 0).toLocaleString();
    }

    function emptyNote(text) {
        return `<p class="text-muted" style="font-size: 0.875rem;">${esc(text)}</p>`;
    }

    function renderBars(items, barClass) {
        const total = Math.max(1, items.reduce((sum, row) => sum + (row.count || 0), 0));
        if (!items.length) return '';
        return items.map((row) => `
            <div class="pipeline-row">
                <div class="pipeline-meta">
                    <span class="pipeline-label">${esc(row.label)}</span>
                    <span class="pipeline-count">${fmt(row.count)}</span>
                </div>
                <div class="pipeline-bar${barClass ? ' ' + barClass : ''}">
                    <span style="width: ${Math.round(((row.count || 0) / total) * 100)}%"></span>
                </div>
            </div>
        `).join('');
    }

    function fillChannel(card, channel) {
        const primary = card.querySelector('[data-channel-primary]');
        const primaryLabel = card.querySelector('[data-channel-primary-label]');
        const secondary = card.querySelector('[data-channel-secondary]');
        const tertiary = card.querySelector('[data-channel-tertiary]');
        const extra = card.querySelector('[data-channel-extra]');
        if (primary) primary.textContent = fmt(channel.primary?.value);
        if (primaryLabel) primaryLabel.textContent = channel.primary?.label || '';
        if (secondary) secondary.innerHTML = `<strong>${fmt(channel.secondary?.value)}</strong> ${esc(channel.secondary?.label || '')}`;
        if (tertiary) tertiary.innerHTML = `<strong>${fmt(channel.tertiary?.value)}</strong> ${esc(channel.tertiary?.label || '')}`;
        if (extra) extra.textContent = channel.extra || '';

        card.classList.remove('channel-card-disabled');
        if (channel.can_open && channel.url) {
            const link = document.createElement('a');
            link.href = channel.url;
            link.className = card.className;
            link.dataset.channelKey = channel.key;
            link.innerHTML = card.innerHTML;
            card.replaceWith(link);
        } else {
            card.classList.add('channel-card-disabled');
            card.title = "You don't have access to this module";
        }
    }

    fetch(overviewUrl, { headers: { Accept: 'application/json' } })
        .then((res) => {
            if (!res.ok) throw new Error('Failed to load dashboard');
            return res.json();
        })
        .then((data) => {
            const leads = data.leads || {};
            document.querySelector('[data-testid="lead-kpis"]')?.removeAttribute('aria-busy');
            document.getElementById('dashPeriodLabel').textContent = data.period_label || '';
            document.getElementById('dashLeadsTotal').textContent = fmt(leads.total);
            const changeEl = document.getElementById('dashLeadsChange');
            const change = Number(leads.month_change || 0);
            changeEl.classList.toggle('positive', change > 0);
            changeEl.classList.toggle('negative', change < 0);
            changeEl.textContent = change !== 0
                ? `vs last month · ${change >= 0 ? '+' : ''}${change}%`
                : 'vs last month';
            document.getElementById('dashLeadsNew').textContent = fmt(leads.new);
            document.getElementById('dashLeadsUnassigned').textContent = `${fmt(leads.unassigned)} unassigned`;
            document.getElementById('dashLeadsConverted').textContent = fmt(leads.converted);
            const conv = document.getElementById('dashLeadsConversion');
            conv.textContent = `${leads.conversion_rate ?? 0}% conversion rate`;
            conv.classList.toggle('positive', Number(leads.conversion_rate || 0) > 0);
            document.getElementById('dashLeadsLost').textContent = fmt(leads.lost);
            document.getElementById('dashLeadsSnoozed').textContent = `${fmt(leads.snoozed)} snoozed`;

            const grid = document.getElementById('channelGrid');
            grid.removeAttribute('aria-busy');
            (data.channels || []).forEach((channel) => {
                const card = grid.querySelector(`[data-channel-key="${channel.key}"]`);
                if (card) fillChannel(card, channel);
            });

            const pipeline = document.getElementById('dashPipeline');
            pipeline.removeAttribute('aria-busy');
            pipeline.innerHTML = (data.pipeline || []).length
                ? renderBars(data.pipeline, '')
                : emptyNote('No leads yet');

            const sources = document.getElementById('dashSources');
            sources.removeAttribute('aria-busy');
            sources.innerHTML = (data.sources || []).length
                ? renderBars(data.sources, 'source')
                : emptyNote('No source data yet');

            const attention = document.getElementById('dashAttention');
            attention.removeAttribute('aria-busy');
            const attentionItems = data.attention || [];
            attention.innerHTML = attentionItems.length
                ? attentionItems.map((item) => {
                    const inner = `
                        <div class="item-info">
                            <div class="item-title">${esc(item.title)}</div>
                            <div class="item-subtitle">${esc(item.subtitle)}</div>
                        </div>
                        <span class="item-badge ${esc(item.key || 'open')}">${esc(item.channel)}</span>
                        <span class="item-date">${esc(item.at_human)}</span>`;
                    return item.can_open && item.url
                        ? `<a href="${esc(item.url)}" class="item-row item-row-link" style="text-decoration:none;color:inherit;">${inner}</a>`
                        : `<div class="item-row">${inner}</div>`;
                }).join('')
                : emptyNote('Nothing waiting right now');

            const canLeads = !!(data.links && data.links.leads);
            const leadsUrl = data.links?.leads_url || '{{ route('leads') }}';
            const recent = document.getElementById('dashRecentLeads');
            recent.removeAttribute('aria-busy');
            const recentItems = data.recentLeads || [];
            recent.innerHTML = recentItems.length
                ? recentItems.map((lead) => {
                    const inner = `
                        <div class="item-info">
                            <div class="item-title">${esc(lead.name)}</div>
                            <div class="item-subtitle">${esc(lead.source)} · ${esc(lead.assigned)}</div>
                        </div>
                        <span class="item-badge ${esc(lead.status)}">${esc(lead.status_label)}</span>
                        <span class="item-date">${esc(lead.updated_human)}</span>`;
                    return canLeads
                        ? `<a href="${esc(leadsUrl)}" class="item-row item-row-link" style="text-decoration:none;color:inherit;">${inner}</a>`
                        : `<div class="item-row item-row-disabled">${inner}</div>`;
                }).join('')
                : emptyNote('No recent leads');

            const activity = document.getElementById('dashActivity');
            activity.removeAttribute('aria-busy');
            const activityItems = data.recentActivity || [];
            activity.innerHTML = activityItems.length
                ? activityItems.map((row) => `
                    <div class="activity-item">
                        <div class="activity-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                        </div>
                        <div class="activity-content">
                            <div class="activity-text">${esc(row.text)}</div>
                            <div class="activity-meta">
                                <span>${esc(row.lead)}</span>
                                ${row.user ? `<span>•</span><span>${esc(row.user)}</span>` : ''}
                                <span>•</span>
                                <span>${esc(row.at_human)}</span>
                            </div>
                        </div>
                    </div>
                `).join('')
                : emptyNote('No recent lead activity');
        })
        .catch((err) => {
            console.error(err);
        });
})();
</script>
@endpush
