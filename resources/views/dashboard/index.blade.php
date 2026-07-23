@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $canAccess = function ($permission, $moduleSlug = null) use ($userPermissions, $companyModuleSlugs) {
            if (! auth()->check()) return false;
            if (! in_array($permission, $userPermissions ?? [])) return false;
            if ($moduleSlug !== null && $companyModuleSlugs !== null && ! in_array($moduleSlug, $companyModuleSlugs)) return false;
            return true;
        };
    @endphp
    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome back! Here's what's happening with your business today.</p>
    </div>

    <!-- Main Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Revenue</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">${{ number_format($stats['total_revenue'] ?? 0, 0) }}</div>
            <div class="stat-change {{ ($stats['revenue_change'] ?? 0) >= 0 ? 'positive' : '' }}">
                @if(($stats['revenue_change'] ?? 0) != 0)
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                @endif
                {{ ($stats['revenue_change'] ?? 0) >= 0 ? '+' : '' }}{{ $stats['revenue_change'] ?? 0 }}% from last month
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Total Clients</span>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ number_format($stats['total_clients'] ?? 0) }}</div>
            <div class="stat-change {{ ($stats['clients_this_month'] ?? 0) > 0 ? 'positive' : '' }}">
                @if(($stats['clients_this_month'] ?? 0) > 0)
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                @endif
                +{{ $stats['clients_this_month'] ?? 0 }} this month
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Projects</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                        <line x1="12" y1="22.08" x2="12" y2="12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['active_projects'] ?? 0 }}</div>
            <div class="stat-change positive">{{ $stats['avg_progress'] ?? 0 }}% completion rate</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Open Tickets</span>
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['open_tickets'] ?? 0 }}</div>
            <div class="stat-change">{{ $stats['in_progress_tickets'] ?? 0 }} in progress</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Pending Invoices</span>
                <div class="stat-icon red">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">${{ number_format($stats['pending_invoices_amount'] ?? 0, 0) }}</div>
            <div class="stat-change">{{ $stats['pending_invoices_count'] ?? 0 }} invoices</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Employees</span>
                <div class="stat-icon teal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['active_employees'] ?? 0 }}</div>
            <div class="stat-change positive">All systems operational</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Recordings Today</span>
                <div class="stat-icon indigo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 7l-7 5 7 5V7z"/>
                        <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['recordings_today'] ?? 0 }}</div>
            <div class="stat-change">Screen recordings today</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">On Leave Today</span>
                <div class="stat-icon pink">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                        <path d="M12 2v5"/>
                        <path d="M12 12v7"/>
                        <path d="M9 16l3-3 3 3"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">{{ $stats['on_leave_today'] ?? 0 }}</div>
            <div class="stat-change">Employees on leave</div>
        </div>
    </div>

    <!-- Dashboard Widgets Grid -->
    <div class="dashboard-widgets-grid">
        <!-- Recent Activity -->
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Recent Activity</h3>
                <a href="#" class="widget-link">View All</a>
            </div>
            <div class="widget-body">
                <div class="activity-list" id="recentActivityList">
                    @forelse($recentActivity ?? [] as $activity)
                        <div class="activity-item">
                            <div class="activity-icon {{ $activity['icon'] ?? 'blue' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    @if(($activity['type'] ?? '') === 'quotation')
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    @elseif(($activity['type'] ?? '') === 'ticket')
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                                    @elseif(($activity['type'] ?? '') === 'payment')
                                        <polyline points="20 6 9 17 4 12"/>
                                    @elseif(($activity['type'] ?? '') === 'project')
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                    @else
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    @endif
                                </svg>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">{{ $activity['text'] ?? '' }}</div>
                                <div class="activity-meta">
                                    <span>{{ isset($activity['at']) ? $activity['at']->diffForHumans() : '' }}</span>
                                    @if(!empty($activity['amount']))
                                        <span>•</span><span>{{ $activity['amount'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No recent activity</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Quotations -->
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Recent Quotations</h3>
                @if($canAccess('view_quotation_builder', 'quotation-builder'))
                    <a href="{{ route('quotation-builder') }}" class="widget-link">View All</a>
                @else
                    <span class="widget-link disabled" title="You don't have access to this module">View All</span>
                @endif
            </div>
            <div class="widget-body">
                <div class="items-list" id="recentQuotationsList">
                    @forelse($recentQuotations ?? [] as $quotation)
                        @if($canAccess('view_quotation_builder', 'quotation-builder'))
                        <a href="{{ route('quotation-builder') }}" class="item-row item-row-link" style="text-decoration: none; color: inherit;">
                            <div class="item-info">
                                <div class="item-title">{{ $quotation->quotation_number }}</div>
                                <div class="item-subtitle">{{ $quotation->client?->name ?? '-' }}</div>
                            </div>
                            <span class="item-badge {{ $quotation->status }}">{{ ucfirst($quotation->status) }}</span>
                            <span class="item-amount">${{ number_format($quotation->total ?? 0, 0) }}</span>
                            <span class="item-date">{{ $quotation->created_at->diffForHumans() }}</span>
                        </a>
                        @else
                        <div class="item-row item-row-disabled">
                            <div class="item-info">
                                <div class="item-title">{{ $quotation->quotation_number }}</div>
                                <div class="item-subtitle">{{ $quotation->client?->name ?? '-' }}</div>
                            </div>
                            <span class="item-badge {{ $quotation->status }}">{{ ucfirst($quotation->status) }}</span>
                            <span class="item-amount">${{ number_format($quotation->total ?? 0, 0) }}</span>
                            <span class="item-date">{{ $quotation->created_at->diffForHumans() }}</span>
                        </div>
                        @endif
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No recent quotations</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Recent Tickets -->
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Recent Tickets</h3>
                @if($canAccess('view_tickets', 'tickets'))
                    <a href="{{ route('tickets') }}" class="widget-link">View All</a>
                @else
                    <span class="widget-link disabled" title="You don't have access to this module">View All</span>
                @endif
            </div>
            <div class="widget-body">
                <div class="items-list" id="recentTicketsList">
                    @forelse($recentTickets ?? [] as $ticket)
                        @if($canAccess('view_tickets', 'tickets'))
                        <a href="{{ route('tickets') }}" class="item-row item-row-link" style="text-decoration: none; color: inherit;">
                            <div class="item-info">
                                <div class="item-title">{{ $ticket->ticket_number }}</div>
                                <div class="item-subtitle">{{ $ticket->subject }}</div>
                            </div>
                            <span class="item-badge {{ $ticket->status }} {{ $ticket->priority === 'urgent' ? 'urgent' : '' }}">{{ ucfirst(str_replace('-', ' ', $ticket->status)) }}</span>
                            <span class="item-date">{{ $ticket->created_at->diffForHumans() }}</span>
                        </a>
                        @else
                        <div class="item-row item-row-disabled">
                            <div class="item-info">
                                <div class="item-title">{{ $ticket->ticket_number }}</div>
                                <div class="item-subtitle">{{ $ticket->subject }}</div>
                            </div>
                            <span class="item-badge {{ $ticket->status }} {{ $ticket->priority === 'urgent' ? 'urgent' : '' }}">{{ ucfirst(str_replace('-', ' ', $ticket->status)) }}</span>
                            <span class="item-date">{{ $ticket->created_at->diffForHumans() }}</span>
                        </div>
                        @endif
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No recent tickets</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Active Projects -->
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Active Projects</h3>
                @if($canAccess('view_project_management', 'project-management'))
                    <a href="{{ route('project-management') }}" class="widget-link">View All</a>
                @else
                    <span class="widget-link disabled" title="You don't have access to this module">View All</span>
                @endif
            </div>
            <div class="widget-body">
                <div class="items-list" id="activeProjectsList">
                    @forelse($activeProjects ?? [] as $project)
                        @if($canAccess('view_project_management', 'project-management'))
                        <a href="{{ route('project-management') }}" class="item-row item-row-link" style="text-decoration: none; color: inherit;">
                            <div class="item-info">
                                <div class="item-title">{{ $project->title }}</div>
                                <div class="item-subtitle">{{ $project->client?->name ?? $project->client ?? '-' }} • {{ $project->progress ?? 0 }}% complete</div>
                            </div>
                            <span class="item-badge active">Active</span>
                            <span class="item-date">{{ $project->deadline?->format('M d, Y') ?? '-' }}</span>
                        </a>
                        @else
                        <div class="item-row item-row-disabled">
                            <div class="item-info">
                                <div class="item-title">{{ $project->title }}</div>
                                <div class="item-subtitle">{{ $project->client?->name ?? $project->client ?? '-' }} • {{ $project->progress ?? 0 }}% complete</div>
                            </div>
                            <span class="item-badge active">Active</span>
                            <span class="item-date">{{ $project->deadline?->format('M d, Y') ?? '-' }}</span>
                        </div>
                        @endif
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No active projects</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Upcoming Deadlines</h3>
                @if($canAccess('view_calendar', 'calendar'))
                    <a href="{{ route('calendar') }}" class="widget-link">View Calendar</a>
                @else
                    <span class="widget-link disabled" title="You don't have access to this module">View Calendar</span>
                @endif
            </div>
            <div class="widget-body">
                <div class="items-list" id="upcomingDeadlinesList">
                    @forelse($upcomingDeadlines ?? [] as $deadline)
                        <div class="item-row">
                            <div class="item-info">
                                <div class="item-title">{{ $deadline['title'] ?? '' }}</div>
                                <div class="item-subtitle">Due in {{ $deadline['days'] ?? 0 }} days</div>
                            </div>
                            <span class="item-badge {{ (($deadline['priority'] ?? 'medium') === 'urgent') ? 'urgent' : (($deadline['priority'] ?? 'medium') === 'high' ? 'sent' : 'in-progress') }}">{{ ucfirst($deadline['priority'] ?? 'medium') }}</span>
                            <span class="item-date">{{ $deadline['deadline'] ?? '' }}</span>
                        </div>
                    @empty
                        <p class="text-muted" style="font-size: 0.875rem;">No upcoming deadlines</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="widget-card">
            <div class="widget-header">
                <h3 class="widget-title">Quick Actions</h3>
            </div>
            <div class="widget-body">
                <div class="quick-actions-grid">
                    @if($canAccess('view_quotation_builder', 'quotation-builder'))
                    <a href="{{ route('quotation-builder') }}" class="quick-action-btn">
                    @else
                    <span class="quick-action-btn quick-action-btn-disabled" title="You don't have access to this module">
                    @endif
                        <div class="quick-action-icon blue">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                            </svg>
                        </div>
                        <span>New Quotation</span>
                    @if($canAccess('view_quotation_builder', 'quotation-builder'))
                    </a>
                    @else
                    </span>
                    @endif
                    @if($canAccess('view_tickets', 'tickets'))
                    <a href="{{ route('tickets') }}" class="quick-action-btn">
                    @else
                    <span class="quick-action-btn quick-action-btn-disabled" title="You don't have access to this module">
                    @endif
                        <div class="quick-action-icon orange">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </div>
                        <span>New Ticket</span>
                    @if($canAccess('view_tickets', 'tickets'))
                    </a>
                    @else
                    </span>
                    @endif
                    @if($canAccess('view_client_management', 'client-management'))
                    <a href="{{ route('client-management') }}" class="quick-action-btn">
                    @else
                    <span class="quick-action-btn quick-action-btn-disabled" title="You don't have access to this module">
                    @endif
                        <div class="quick-action-icon green">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <span>New Client</span>
                    @if($canAccess('view_client_management', 'client-management'))
                    </a>
                    @else
                    </span>
                    @endif
                    @if($canAccess('view_project_management', 'project-management'))
                    <a href="{{ route('project-management') }}" class="quick-action-btn">
                    @else
                    <span class="quick-action-btn quick-action-btn-disabled" title="You don't have access to this module">
                    @endif
                        <div class="quick-action-icon purple">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            </svg>
                        </div>
                        <span>New Project</span>
                    @if($canAccess('view_project_management', 'project-management'))
                    </a>
                    @else
                    </span>
                    @endif
                    @if($canAccess('view_billing', 'billing'))
                    <a href="{{ route('billing') }}" class="quick-action-btn">
                    @else
                    <span class="quick-action-btn quick-action-btn-disabled" title="You don't have access to this module">
                    @endif
                        <div class="quick-action-icon teal">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <span>New Invoice</span>
                    @if($canAccess('view_billing', 'billing'))
                    </a>
                    @else
                    </span>
                    @endif
                    @if($canAccess('view_time_tracking', 'time-tracking'))
                    <a href="{{ route('time-tracking') }}" class="quick-action-btn">
                    @else
                    <span class="quick-action-btn quick-action-btn-disabled" title="You don't have access to this module">
                    @endif
                        <div class="quick-action-icon indigo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <span>Time Tracking</span>
                    @if($canAccess('view_time_tracking', 'time-tracking'))
                    </a>
                    @else
                    </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Stats Grid - Enhanced */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-icon.red {
        background: #fee2e2;
        color: #dc2626;
    }

    .stat-icon.teal {
        background: #ccfbf1;
        color: #0d9488;
    }

    .stat-icon.indigo {
        background: #e0e7ff;
        color: #4f46e5;
    }

    .stat-icon.pink {
        background: #fce7f3;
        color: #db2777;
    }

    /* Dashboard Widgets */
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
        transition: color 0.15s;
    }

    .widget-link:hover {
        color: var(--accent-hover);
    }

    .widget-link.disabled {
        color: var(--text-muted);
        cursor: not-allowed;
        pointer-events: none;
    }

    .item-row-disabled {
        cursor: default;
        opacity: 0.7;
        pointer-events: none;
    }

    .quick-action-btn-disabled {
        cursor: not-allowed;
        opacity: 0.6;
        pointer-events: none;
    }

    .widget-body {
        padding: 1.5rem;
        flex: 1;
    }

    /* Activity List */
    .activity-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
        transition: all 0.15s;
    }

    .activity-item:hover {
        background: var(--border);
    }

    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .activity-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .activity-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .activity-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .activity-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .activity-icon.red {
        background: #fee2e2;
        color: #dc2626;
    }

    .activity-icon svg {
        width: 18px;
        height: 18px;
    }

    .activity-content {
        flex: 1;
        min-width: 0;
    }

    .activity-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
        line-height: 1.4;
    }

    .activity-meta {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Items List */
    .items-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .item-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
        transition: all 0.15s;
        cursor: pointer;
    }

    .item-row:hover {
        background: var(--border);
    }

    .item-info {
        flex: 1;
        min-width: 0;
    }

    .item-title {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .item-subtitle {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .item-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 0.75rem;
        flex-shrink: 0;
    }

    .item-badge.sent {
        background: #dbeafe;
        color: #2563eb;
    }

    .item-badge.open {
        background: #dbeafe;
        color: #2563eb;
    }

    .item-badge.in-progress {
        background: #fef3c7;
        color: #d97706;
    }

    .item-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    .item-badge.urgent {
        background: #fee2e2;
        color: #dc2626;
    }

    .item-badge.pending {
        background: #fef3c7;
        color: #d97706;
    }

    .item-badge.resolved, .item-badge.closed, .item-badge.accepted {
        background: #d1fae5;
        color: #059669;
    }

    .item-badge.draft {
        background: #e5e7eb;
        color: #6b7280;
    }

    .item-badge.rejected, .item-badge.expired {
        background: #fee2e2;
        color: #dc2626;
    }

    .item-amount {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-left: 0.75rem;
        flex-shrink: 0;
    }

    .item-date {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-left: 0.75rem;
        flex-shrink: 0;
    }

    /* Quick Actions */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }

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
        -webkit-tap-highlight-color: transparent;
    }

    .quick-action-btn:hover {
        background: var(--border);
        border-color: var(--accent);
        transform: translateY(-2px);
    }

    .quick-action-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .quick-action-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .quick-action-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .quick-action-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .quick-action-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .quick-action-icon.teal {
        background: #ccfbf1;
        color: #0d9488;
    }

    .quick-action-icon.indigo {
        background: #e0e7ff;
        color: #4f46e5;
    }

    .quick-action-icon svg {
        width: 24px;
        height: 24px;
    }

    .quick-action-btn span {
        font-size: 0.8125rem;
        font-weight: 500;
        text-align: center;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .dashboard-widgets-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .dashboard-widgets-grid {
            grid-template-columns: 1fr;
        }

        .quick-actions-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>
@endpush

@push('scripts')
@endpush

