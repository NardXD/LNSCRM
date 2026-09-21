@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $canAccess = function ($permission, $moduleSlug = null) use ($userPermissions, $companyModuleSlugs) {
            return \App\Helpers\SidebarHelper::canAccessModule($userPermissions ?? [], $companyModuleSlugs ?? null, $permission, $moduleSlug);
        };
        $pipelineTotal = max(1, collect($pipeline ?? [])->sum('count'));
        $sourceTotal = max(1, collect($sources ?? [])->sum('count'));
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
        <p class="page-subtitle">Lead pipeline and channel activity for {{ $periodLabel ?? now()->format('F Y') }} across phone, inbox, Viber, Facebook, SMS, and WhatsApp.</p>
    </div>

    <div class="stats-grid" data-testid="lead-kpis">
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
            <div class="stat-value">{{ number_format($leads['total'] ?? 0) }}</div>
            <div class="stat-change {{ ($leads['month_change'] ?? 0) > 0 ? 'positive' : (($leads['month_change'] ?? 0) < 0 ? 'negative' : '') }}">
                vs last month
                @if(($leads['month_change'] ?? 0) != 0)
                    · {{ ($leads['month_change'] ?? 0) >= 0 ? '+' : '' }}{{ $leads['month_change'] ?? 0 }}%
                @endif
            </div>
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
            <div class="stat-value">{{ number_format($leads['new'] ?? 0) }}</div>
            <div class="stat-change">{{ $leads['unassigned'] ?? 0 }} unassigned</div>
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
            <div class="stat-value">{{ number_format($leads['converted'] ?? 0) }}</div>
            <div class="stat-change {{ ($leads['conversion_rate'] ?? 0) > 0 ? 'positive' : '' }}">{{ $leads['conversion_rate'] ?? 0 }}% conversion rate</div>
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
            <div class="stat-value">{{ number_format($leads['lost'] ?? 0) }}</div>
            <div class="stat-change">{{ $leads['snoozed'] ?? 0 }} snoozed</div>
        </div>
    </div>

    <div class="channel-grid" data-testid="channel-grid">
        @foreach($channels ?? [] as $channel)
            @php
                $open = $canAccess($channel['permission'], $channel['module_slug']) && \Illuminate\Support\Facades\Route::has($channel['route']);
            @endphp
            @if($open)
                <a href="{{ route($channel['route']) }}" class="channel-card channel-{{ $channel['key'] }}">
            @else
                <div class="channel-card channel-{{ $channel['key'] }} channel-card-disabled" title="You don't have access to this module">
            @endif
                <div class="channel-card-header">
                    <div class="channel-icon {{ $channel['key'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $channelIcons[$channel['key']] ?? $channelIcons['inbox'] !!}</svg>
                    </div>
                    <span class="channel-label">{{ $channel['label'] }}</span>
                </div>
                <div class="channel-primary">{{ number_format($channel['primary']['value'] ?? 0) }}</div>
                <div class="channel-primary-label">{{ $channel['primary']['label'] ?? '' }}</div>
                <div class="channel-metrics">
                    <span><strong>{{ number_format($channel['secondary']['value'] ?? 0) }}</strong> {{ $channel['secondary']['label'] ?? '' }}</span>
                    <span><strong>{{ number_format($channel['tertiary']['value'] ?? 0) }}</strong> {{ $channel['tertiary']['label'] ?? '' }}</span>
                </div>
                @if(!empty($channel['extra']))
                    <div class="channel-extra">{{ $channel['extra'] }}</div>
                @endif
            @if($open)
                </a>
            @else
                </div>
            @endif
        @endforeach
    </div>

    <div class="dashboard-widgets-grid">
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Lead pipeline</h3>
                @if($canAccess('view_leads', 'client-management'))
                    <a href="{{ route('leads') }}" class="widget-link">View leads</a>
                @else
                    <span class="widget-link disabled">View leads</span>
                @endif
            </div>
            <div class="widget-body">
                <div class="pipeline-list">
                    @forelse($pipeline ?? [] as $status)
                        <div class="pipeline-row">
                            <div class="pipeline-meta">
                                <span class="pipeline-label">{{ $status['label'] }}</span>
                                <span class="pipeline-count">{{ number_format($status['count']) }}</span>
                            </div>
                            <div class="pipeline-bar">
                                <span style="width: {{ round(($status['count'] / $pipelineTotal) * 100) }}%"></span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No leads yet</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Top sources</h3>
                @if($canAccess('view_lead_reports', 'client-management'))
                    <a href="{{ route('lead-reports') }}" class="widget-link">Lead reports</a>
                @else
                    <span class="widget-link disabled">Lead reports</span>
                @endif
            </div>
            <div class="widget-body">
                <div class="pipeline-list">
                    @forelse($sources ?? [] as $source)
                        <div class="pipeline-row">
                            <div class="pipeline-meta">
                                <span class="pipeline-label">{{ $source['label'] }}</span>
                                <span class="pipeline-count">{{ number_format($source['count']) }}</span>
                            </div>
                            <div class="pipeline-bar source">
                                <span style="width: {{ round(($source['count'] / $sourceTotal) * 100) }}%"></span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No source data yet</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Needs attention</h3>
            </div>
            <div class="widget-body">
                <div class="items-list">
                    @forelse($attention ?? [] as $item)
                        @php $open = $canAccess(
                            match($item['key'] ?? '') {
                                'phone' => 'view_phone_system',
                                'inbox' => 'view_inbox',
                                'viber' => 'view_viber',
                                'facebook' => 'view_facebook',
                                'sms' => 'view_sms',
                                'whatsapp' => 'view_whatsapp',
                                default => 'view_dashboard',
                            },
                            match($item['key'] ?? '') {
                                'phone' => 'phone-system',
                                'inbox' => 'inbox',
                                'viber' => 'viber',
                                'facebook' => 'facebook',
                                'sms' => 'sms',
                                'whatsapp' => 'whatsapp',
                                default => 'dashboard',
                            }
                        ); @endphp
                        @if($open && !empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']))
                            <a href="{{ route($item['route']) }}" class="item-row item-row-link" style="text-decoration: none; color: inherit;">
                        @else
                            <div class="item-row">
                        @endif
                            <div class="item-info">
                                <div class="item-title">{{ $item['title'] ?? '' }}</div>
                                <div class="item-subtitle">{{ $item['subtitle'] ?? '' }}</div>
                            </div>
                            <span class="item-badge {{ $item['key'] ?? 'open' }}">{{ $item['channel'] ?? '' }}</span>
                            <span class="item-date">{{ isset($item['at']) && $item['at'] ? $item['at']->diffForHumans() : '' }}</span>
                        @if($open && !empty($item['route']) && \Illuminate\Support\Facades\Route::has($item['route']))
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">Nothing waiting right now</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Recent leads</h3>
                @if($canAccess('view_leads', 'client-management'))
                    <a href="{{ route('leads') }}" class="widget-link">View all</a>
                @else
                    <span class="widget-link disabled">View all</span>
                @endif
            </div>
            <div class="widget-body">
                <div class="items-list">
                    @forelse($recentLeads ?? [] as $lead)
                        @if($canAccess('view_leads', 'client-management'))
                            <a href="{{ route('leads') }}" class="item-row item-row-link" style="text-decoration: none; color: inherit;">
                        @else
                            <div class="item-row item-row-disabled">
                        @endif
                            <div class="item-info">
                                <div class="item-title">{{ trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')) ?: $lead->name }}</div>
                                <div class="item-subtitle">{{ $lead->source ?: 'No source' }} · {{ $lead->assignedUser?->name ?? 'Unassigned' }}</div>
                            </div>
                            <span class="item-badge {{ $lead->status }}">{{ ucfirst(str_replace('-', ' ', $lead->status)) }}</span>
                            <span class="item-date">{{ $lead->updated_at?->diffForHumans() }}</span>
                        @if($canAccess('view_leads', 'client-management'))
                            </a>
                        @else
                            </div>
                        @endif
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No recent leads</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Lead activity</h3>
            </div>
            <div class="widget-body">
                <div class="activity-list">
                    @forelse($recentActivity ?? [] as $activity)
                        <div class="activity-item">
                            <div class="activity-icon blue">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">{{ $activity['text'] ?? '' }}</div>
                                <div class="activity-meta">
                                    <span>{{ $activity['lead'] ?? '' }}</span>
                                    @if(!empty($activity['user']))
                                        <span>•</span><span>{{ $activity['user'] }}</span>
                                    @endif
                                    <span>•</span>
                                    <span>{{ isset($activity['at']) && $activity['at'] ? $activity['at']->diffForHumans() : '' }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No recent lead activity</p>
                    @endforelse
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
