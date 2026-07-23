@extends('layouts.app')

@section('title', 'Support & Override - Admin')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Admin Support & Override Controls</h1>
        <p class="page-subtitle">Bypass restrictions, provide support, and troubleshoot issues</p>
    </div>

    <!-- Admin Stats Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Active Sessions</span>
                <div class="stat-icon blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">2</div>
            <div class="stat-change">Support sessions</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Open Tickets</span>
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">12</div>
            <div class="stat-change">Requires attention</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Bypass Actions</span>
                <div class="stat-icon purple">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">24</div>
            <div class="stat-change">This week</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Emergency Grants</span>
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">3</div>
            <div class="stat-change">Active now</div>
        </div>
    </div>

    <!-- Support & Override Section -->
    <div class="admin-sections-grid">
        @include('admin.sections.support-override')
    </div>

    @include('admin.partials.modals')
@endsection

@push('styles')
    @include('admin.partials.styles')
@endpush

@push('scripts')
    @include('admin.partials.scripts')
@endpush

