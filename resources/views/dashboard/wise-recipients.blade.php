@extends('layouts.app')

@section('title', 'Wise Recipients & Employees')

@push('styles')
    @vite(['resources/css/pages/wise-recipients.css'])
@endpush

@push('scripts')
    @vite(['resources/js/pages/wise-recipients.js'])
@endpush

@section('content')
    @if(auth()->user()?->hasPermission('view_wise_recipients'))
    <div class="page-header">
        <h1 class="page-title">Wise Recipients &amp; Employee Assignment</h1>
        <p class="page-subtitle">Assign Wise recipient IDs to employees for payroll. Each ID can only be used once. Ensure Wise is connected in <a href="{{ route('integrations') }}">Integrations</a> first.</p>
    </div>

    <div class="wise-recipients-section">
        <div class="section-header">
            <h2 class="section-title">Manage Recipients &amp; Employees</h2>
            <div class="section-actions">
                <button type="button" class="btn-primary" id="wise-add-recipient-btn" title="Add a new recipient via Wise API">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Recipient
                </button>
                <button type="button" class="btn-secondary" id="wise-load-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;">
                        <polyline points="23 4 23 10 17 10"/>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <div class="table-container" id="wise-panel" style="display: none;">
            <div class="wise-panel-grid">
                <div class="wise-panel-col">
                    <h3 class="subsection-title">Wise Recipients</h3>
                    <div id="wise-recipients-list" class="wise-list"></div>
                    <div id="wise-recipients-error" class="wise-error" style="display: none;"></div>
                </div>
                <div class="wise-panel-col">
                    <div class="wise-tabs-row">
                        <button type="button" class="tab-btn wise-tab-btn active" data-tab="all">Unlinked Contacts <span id="wise-tab-all-count" class="wise-tab-count"></span></button>
                        <button type="button" class="tab-btn wise-tab-btn" data-tab="wise-tags">Linked Contacts <span id="wise-tab-wisetags-count" class="wise-tab-count"></span></button>
                    </div>
                    <div id="wise-tab-all" class="tab-content wise-tab-content active">
                        <div id="wise-employees-all" class="wise-list"></div>
                    </div>
                    <div id="wise-tab-wise-tags" class="tab-content wise-tab-content">
                        <div id="wise-employees-wise-tags" class="wise-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Recipient Modal -->
    <div class="wise-modal-overlay" id="wise-add-modal" style="display: none;">
        <div class="wise-modal">
            <div class="wise-modal-header">
                <h3 class="wise-modal-title">Add Wise Recipient</h3>
                <button type="button" class="wise-modal-close" id="wise-modal-close">&times;</button>
            </div>
            <div class="wise-modal-body">
                <div class="wise-add-method-row" role="tablist">
                    <button type="button" class="wise-add-method-btn active" data-method="bank">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="5" width="20" height="14" rx="2"/>
                            <line x1="2" y1="10" x2="22" y2="10"/>
                        </svg>
                        Bank account
                    </button>
                    <button type="button" class="wise-add-method-btn" data-method="tag">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                        </svg>
                        Wise tag
                    </button>
                </div>

                <div id="wise-add-tag" style="display: none;">
                    <p class="wise-modal-desc">Add a recipient by their Wise tag, email, or phone number. They must have a discoverable Wise account. This is added to your Wise recipients.</p>
                    <div class="wise-form-group">
                        <label for="wise-add-tag-identifier">Wise tag, email, or phone</label>
                        <input type="text" id="wise-add-tag-identifier" class="wise-form-input" placeholder="@wisetag, name@email.com, or +1234567890">
                    </div>
                    <div class="wise-form-group">
                        <label for="wise-add-tag-currency">Currency</label>
                        <select id="wise-add-tag-currency" class="wise-form-input">
                            <option value="">Select currency...</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="CHF">CHF</option>
                            <option value="AUD">AUD</option>
                            <option value="CAD">CAD</option>
                            <option value="SGD">SGD</option>
                            <option value="JPY">JPY</option>
                            <option value="INR">INR</option>
                            <option value="PHP">PHP</option>
                            <option value="THB">THB</option>
                        </select>
                    </div>
                    <div id="wise-add-tag-error" class="wise-error" style="display: none;"></div>
                    <div class="wise-modal-actions">
                        <button type="button" class="btn-secondary" id="wise-add-tag-cancel">Cancel</button>
                        <button type="button" class="btn-primary" id="wise-add-tag-submit">Add recipient</button>
                    </div>
                </div>

                <div id="wise-add-step1">
                    <p class="wise-modal-desc">Select the recipient's currency to load the required fields.</p>
                    <div class="wise-form-group">
                        <label for="wise-add-currency">Currency</label>
                        <select id="wise-add-currency" class="wise-form-input">
                            <option value="">Select currency...</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="CHF">CHF</option>
                            <option value="AUD">AUD</option>
                            <option value="CAD">CAD</option>
                            <option value="SGD">SGD</option>
                            <option value="JPY">JPY</option>
                            <option value="INR">INR</option>
                            <option value="PHP">PHP</option>
                            <option value="THB">THB</option>
                            <option value="PLN">PLN</option>
                            <option value="SEK">SEK</option>
                            <option value="NOK">NOK</option>
                            <option value="DKK">DKK</option>
                            <option value="CZK">CZK</option>
                            <option value="HUF">HUF</option>
                            <option value="RON">RON</option>
                            <option value="BGN">BGN</option>
                        </select>
                    </div>
                    <div class="wise-modal-actions">
                        <button type="button" class="btn-secondary" id="wise-modal-cancel">Cancel</button>
                        <button type="button" class="btn-primary" id="wise-add-load-req">Load form</button>
                    </div>
                </div>
                <div id="wise-add-step2" style="display: none;">
                    <div id="wise-add-form-container"></div>
                    <div id="wise-add-error" class="wise-error" style="display: none;"></div>
                    <div class="wise-modal-actions">
                        <button type="button" class="btn-secondary" id="wise-add-back">Back</button>
                        <button type="button" class="btn-primary" id="wise-add-submit">Create recipient</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="page-header">
        <p class="page-subtitle">You do not have permission to access Wise Recipients.</p>
    </div>
    @endif
@endsection
