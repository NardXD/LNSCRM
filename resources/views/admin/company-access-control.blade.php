@extends('layouts.app')

@section('title', 'Company Access Control - Admin')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Company Access Control</h1>
        <p class="page-subtitle">Manage feature access and permissions</p>
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
                <span class="stat-label">Active Companies</span>
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
            <div class="stat-value">1,247</div>
            <div class="stat-change positive">All systems operational</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-label">Access Changes</span>
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-value">24</div>
            <div class="stat-change">This week</div>
        </div>
    </div>

    <!-- Company Access Control Section -->
    <div class="admin-sections-grid">
        @include('admin.sections.company-access-control')
    </div>

    @include('admin.partials.modals')
@endsection

@push('styles')
    @include('admin.partials.styles')
@endpush

@push('scripts')
    @include('admin.partials.scripts')
@endpush

