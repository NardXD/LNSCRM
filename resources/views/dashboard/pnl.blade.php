@extends('layouts.app')

@section('title', 'P&L')

@section('content')
    @include('dashboard.partials.payroll-pnl-styles')
    @include('dashboard.partials.payroll-pnl-scripts')
    <div class="pnl-page">
        <div class="page-header">
            <h1 class="page-title">P&amp;L</h1>
            <p class="page-subtitle">Invoice-line collections (payroll conversion + billing), payroll cost, and commission for the selected month</p>
        </div>

        @include('dashboard.partials.payroll-pnl-module')
    </div>
@endsection
