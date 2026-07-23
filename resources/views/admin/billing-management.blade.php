@extends('layouts.app')

@section('title', 'Billing Management - Admin')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Billing Management</h1>
        <p class="page-subtitle">Control subscriptions, plans, and payments</p>
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
            <div class="stat-value">156</div>
            <div class="stat-change positive">+8 this month</div>
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
            <div class="stat-value">142</div>
            <div class="stat-change positive">91% active rate</div>
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
            <div class="stat-value">$245,680</div>
            <div class="stat-change positive">+15.2% from last month</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Pending Payments</span>
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">$45,680</div>
            <div class="stat-change">18 invoices</div>
        </div>
    </div>

    <!-- Billing Management Section -->
    <div class="admin-sections-grid">
        @include('admin.sections.billing-management')
    </div>

    @include('admin.partials.modals')
@endsection

@push('styles')
    @include('admin.partials.styles')
@endpush

@push('scripts')
    <script>
        // Initialize data from server-side rendering for instant display
        window.initialBillingData = {
            plans: @json($plans ?? []),
            companies: @json($companies ?? []),
            payments: @json($recentPayments ?? [])
        };
    </script>
    @include('admin.partials.scripts')
@endpush

