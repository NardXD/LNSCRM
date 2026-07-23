@extends('layouts.app')

@section('title', 'Admin Control Panel')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Admin Control Panel</h1>
        <p class="page-subtitle">Manage companies</p>
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
            <div class="stat-value" id="statTotalCompanies">{{ $stats['total_companies'] }}</div>
            <div class="stat-change positive" id="statCompaniesChange">From database</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Subscriptions</span>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="statActiveSubscriptions">{{ $stats['active_subscriptions'] }}</div>
            <div class="stat-change positive" id="statSubscriptionsChange">From database</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Monthly Revenue</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="statMonthlyRevenue">${{ number_format($stats['monthly_revenue'], 0) }}</div>
            <div class="stat-change positive" id="statRevenueChange">From database</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Pending Approvals</span>
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value" id="statPendingApprovals">{{ $stats['pending_approvals'] }}</div>
            <div class="stat-change" id="statApprovalsChange">Requires attention</div>
        </div>
    </div>

    <!-- Main Admin Section -->
    <div class="admin-sections-grid">
        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">Company Management</h2>
                <p class="section-subtitle">Create and manage all companies</p>
            </div>
            <div class="section-card-body">
                <a href="{{ route('admin.company-management') }}" class="btn-sm btn-primary">Go to Company Management</a>
            </div>
        </div>

        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">API Key Management</h2>
                <p class="section-subtitle">Issue API keys and control endpoint access per company</p>
            </div>
            <div class="section-card-body">
                <a href="{{ route('admin.api-key-management') }}" class="btn-sm btn-primary">Go to API Keys</a>
            </div>
        </div>

        <div class="admin-section-card">
            <div class="section-card-header">
                <h2 class="section-title">Main AI</h2>
                <p class="section-subtitle">Connect new accounts to your main AI and set token limits</p>
            </div>
            <div class="section-card-body">
                <a href="{{ route('admin.ai-settings') }}" class="btn-sm btn-primary">Go to Main AI</a>
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
@endpush

