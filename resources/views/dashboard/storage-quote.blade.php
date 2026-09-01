@extends('layouts.app')

@section('title', 'Storage Quote')

@push('styles')
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<style>
    .storage-quote-page { max-width: 1200px; margin: 0 auto; padding: 0 1rem 2rem; }
    .storage-quote-back { display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 1rem; color: var(--text-secondary); text-decoration: none; font-size: 0.875rem; }
    .storage-quote-back:hover { color: var(--accent); }
    .storage-quote-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.25rem; }
    .storage-quote-card h2, .storage-quote-card h3 { margin: 0 0 1rem; font-size: 1rem; }
    .storage-quote-grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .storage-quote-label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.35rem; }
    .storage-quote-input, .storage-quote-select { width: 100%; padding: 0.625rem 0.75rem; border: 1px solid var(--border); border-radius: 8px; font: inherit; background: #fff; }
    .storage-quote-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 0.5rem; margin-top: 1rem; }
    .storage-quote-btn { border: 1px solid var(--border); background: #fff; color: var(--text-primary); border-radius: 8px; padding: 0.625rem 1rem; font: inherit; cursor: pointer; }
    .storage-quote-btn-primary { background: var(--accent); border-color: var(--accent); color: #fff; }
    .storage-quote-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .storage-quote-alert { padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; }
    .storage-quote-alert-warning { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .storage-quote-message { margin-bottom: 0.75rem; font-size: 0.875rem; }
    .storage-quote-message.success { color: #059669; }
    .storage-quote-message.error { color: #dc2626; }
    .ui-autocomplete { max-height: 200px; overflow-y: auto; overflow-x: hidden; z-index: 2000; }
    .ds-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #e2e8f0; border-top-color: #f5b301; border-radius: 50%; animation: ds-spin 0.6s linear infinite; vertical-align: middle; }
    @keyframes ds-spin { to { transform: rotate(360deg); } }
    .storage-quote-schedule { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; background: #fff; }
</style>
@endpush

@section('content')
<div class="storage-quote-page">
    <a href="{{ route('quotation-builder') }}" class="storage-quote-back">&larr; Back to Quotation Builder</a>

    <div class="page-header">
        <h1 class="page-title">Storage Quote</h1>
        <p class="page-subtitle">{{ trim(($tenant['sFName'] ?? '').' '.($tenant['sLName'] ?? '')) ?: $lead->name }}</p>
    </div>

    @if (! $storeganiseConfigured)
        <div class="storage-quote-alert storage-quote-alert-warning">
            Storeganise is not connected. Unit search and pricing need an active Storeganise integration under Integrations.
        </div>
    @endif

    @php
        $email = is_array($tenant['sEmail'] ?? null) ? '' : ($tenant['sEmail'] ?? '');
        $insuranceValues = [50,100,150,200,250,300,350,400,450,500,550,600,650,700,750,1000,1500,2000,2500,3500,5000,8000];
    @endphp

    <form method="POST" action="{{ route('api.quotation-builder.storage-quotes.print') }}" target="_blank" enctype="multipart/form-data" id="storageQuoteForm">
        @csrf
        <div class="storage-quote-card">
            <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:1rem; margin-bottom:1rem;">
                <div>
                    <p class="storage-quote-label">{{ $facilityLabel ?: 'Facility' }}</p>
                    <h2 style="font-size:1.25rem;">{{ $tenant['sFName'] }} {{ $tenant['sLName'] }}</h2>
                    @if ($email)
                        <p style="color:var(--text-secondary); font-size:0.875rem;">{{ $email }}</p>
                    @endif
                </div>
                @if (count($facilityOptions) > 1)
                    <div style="min-width:220px;">
                        <label class="storage-quote-label" for="facility_select">Facility</label>
                        <select class="storage-quote-select" id="facility_select" name="facility_select">
                            <option value="">Select facility</option>
                            @foreach ($facilityOptions as $code => $label)
                                <option value="{{ $code }}" @selected($locode === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="storage-quote-grid">
                <div>
                    <label class="storage-quote-label" for="email">Email</label>
                    <input type="text" name="email" id="email" value="{{ $email }}" class="storage-quote-input">
                </div>
            </div>

            <div class="storage-quote-grid" style="margin-top:1rem;">
                <div>
                    @for ($i = 1; $i <= 4; $i++)
                        <div style="margin-bottom:0.75rem;">
                            <label class="storage-quote-label" @if($i > 1) id="unit{{ $i }}_label" @endif>
                                Storage unit {{ $i }}
                                <span id="unit{{ $i }}_loading" class="ds-spinner" hidden></span>
                            </label>
                            <input type="text" name="unit{{ $i }}" id="unit{{ $i }}" class="storage-quote-input" @if($i === 1) required @else placeholder="If applicable" @endif>
                            <input type="hidden" name="selsr{{ $i }}" id="selsr{{ $i }}">
                        </div>
                    @endfor
                </div>
                <div>
                    @for ($i = 1; $i <= 4; $i++)
                        <div style="margin-bottom:0.75rem;">
                            <label class="storage-quote-label" @if($i > 1) id="insurance_unit{{ $i }}_label" @endif>Insurance unit {{ $i }}</label>
                            <select class="storage-quote-select" name="insurance_unit{{ $i }}" id="insurance_unit{{ $i }}">
                                <option value="0">Select</option>
                                @foreach ($insuranceValues as $value)
                                    <option value="{{ $value }}">{{ $value >= 1000 ? rtrim(rtrim(number_format($value / 1000, 1), '0'), '.').'M' : $value }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="storage-quote-card">
            <h3>Terms and adjustments</h3>
            <div class="storage-quote-grid">
                <div>
                    <label class="storage-quote-label">Initial period</label>
                    <input type="number" name="initial_period" id="initial_period" placeholder="Not including partial month" class="storage-quote-input" required>
                </div>
                <div>
                    <label class="storage-quote-label">Storage fee discount</label>
                    <select class="storage-quote-select" name="fee_discount" id="fee_discount">
                        <option value="">Select</option>
                        @foreach ($discountOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="storage-quote-label">1 month free promo</label>
                    <select class="storage-quote-select" name="fee_promo" id="fee_promo">
                        <option value="">No, if taking discount</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div>
                    <label class="storage-quote-label">Start date</label>
                    <input type="date" name="start" id="start" class="storage-quote-input" required>
                </div>
                <div>
                    <label class="storage-quote-label">Anniversary or begin month</label>
                    <select class="storage-quote-select" name="anniversary" id="anniversary" required>
                        <option value="">Select</option>
                        <option value="Anniversary">Anniversary</option>
                        <option value="Mth">Begin Mth</option>
                    </select>
                </div>
                <div>
                    <label class="storage-quote-label">Adjustments (non-VAT)</label>
                    <input type="text" name="adjustments_nonvat" id="adjustments_nonvat" class="storage-quote-input">
                </div>
                @for ($i = 1; $i <= 4; $i++)
                    <div>
                        <label class="storage-quote-label">Adjustment {{ $i }}</label>
                        <input type="text" name="adjustments{{ $i }}" id="adjustments{{ $i }}" class="storage-quote-input">
                    </div>
                @endfor
                <div>
                    <label class="storage-quote-label">Withholding tax</label>
                    <select name="withholding_tax" id="withholding_tax" class="storage-quote-select">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div>
                    <label class="storage-quote-label">Tax exempt</label>
                    <select name="tax_exempt" id="tax_exempt" class="storage-quote-select">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div>
                    <label class="storage-quote-label">Renewal</label>
                    <select name="renewal" id="renewal" class="storage-quote-select">
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div>
                    <label class="storage-quote-label">Reserved</label>
                    <select name="reserved" id="reserved" class="storage-quote-select" required>
                        <option value="">Select</option>
                        <option value="No">No</option>
                        <option value="Yes">Yes</option>
                    </select>
                </div>
                <div>
                    <label class="storage-quote-label">Unit size</label>
                    <input type="text" name="unit_size" id="unit_size" placeholder="e.g. Gigantic sized unit, 40.2 sqm" class="storage-quote-input" required>
                </div>
                <div>
                    <label class="storage-quote-label">Signature (PNG)</label>
                    <input type="file" name="fileToUpload" id="fileToUpload" class="storage-quote-input">
                </div>
            </div>
        </div>

        <div class="storage-quote-card">
            <input type="hidden" name="lo_code" id="lo_code" value="{{ $locode }}">
            <input type="hidden" name="lead_id" value="{{ $lead->id }}">
            <input type="hidden" name="tin" value="{{ $tenant['sTaxID'] ?? '' }}">
            <input type="hidden" name="tenant_company" value="{{ $tenant['sCompany'] ?? '' }}">
            <input type="hidden" name="mr_mrs" value="{{ $tenant['sMrMrs'] ?? '' }}">
            <input type="hidden" name="fname" value="{{ $tenant['sFName'] ?? '' }}">
            <input type="hidden" name="lname" value="{{ $tenant['sLName'] ?? '' }}">
            <input type="hidden" name="address" value="{{ $tenant['address'] ?? trim(($tenant['sAddr1'] ?? '').($tenant['sAddr2'] ?? '').', '.($tenant['sCity'] ?? '')) }}">
            <input type="hidden" name="postal" value="{{ $tenant['sPostalCode'] ?? '' }}">
            <input type="hidden" name="number" value="{{ is_array($tenant['sPhone'] ?? null) ? '' : ($tenant['sPhone'] ?? '') }}">
            <input type="hidden" name="mr_mrs_alt" value="{{ $tenant['sMrMrsAlt'] ?? '' }}">
            <input type="hidden" name="fname_alt" value="{{ $tenant['sFNameAlt'] ?? '' }}">
            <input type="hidden" name="lname_alt" value="{{ $tenant['sLNameAlt'] ?? '' }}">
            <input type="hidden" name="number_alt" value="{{ $tenant['sPhoneAlt'] ?? '' }}">
            <input type="hidden" name="address_alt" value="{{ $tenant['address_alt'] ?? trim(($tenant['sAddr1Alt'] ?? '').($tenant['sAddr2Alt'] ?? '').', '.($tenant['sCityAlt'] ?? '')) }}">
            <input type="hidden" name="postal_alt" value="{{ $tenant['sPostalCodeAlt'] ?? '' }}">
            <input type="hidden" name="email_alt" value="{{ $tenant['sEmailAlt'] ?? '' }}">
            <input type="hidden" name="start_date" id="start_date">

            <div class="storage-quote-schedule">
                @include('quotes._schedule')
            </div>

            <div id="quote_action_message" class="storage-quote-message" hidden></div>

            <div class="storage-quote-actions">
                <button type="submit" class="storage-quote-btn" name="print_qoute" id="print_qoute">Print</button>
                <button type="button" class="storage-quote-btn" name="email_qoute" id="email_qoute">Email quote</button>
                <button type="submit" class="storage-quote-btn storage-quote-btn-primary" name="download_contract" id="download_contract">Download contract</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="{{ asset('assets/js/jquery-ui.js') }}"></script>
<script>
    window.STORAGE_QUOTE_UNITS_URL = @json(route('api.quotation-builder.storage-quotes.units'));
    window.STORAGE_QUOTE_API = {
        search: @json(route('api.quotation-builder.storage-quotes.units.search')),
        print: @json(route('api.quotation-builder.storage-quotes.print')),
        download: @json(route('api.quotation-builder.storage-quotes.download')),
        email: @json(route('api.quotation-builder.storage-quotes.email')),
    };
</script>
<script src="{{ asset('assets/js/storage-quote.js') }}?v=1"></script>
<script>
$(function () {
    var $facilitySelect = $('#facility_select');
    if ($facilitySelect.length) {
        $facilitySelect.on('change', function () {
            $('#lo_code').val($(this).val());
        });
    }

    var searchUrl = window.STORAGE_QUOTE_API.search;

    function bindUnitAutocomplete(id) {
        var $loading = $('#' + id + '_loading');

        $('#' + id).autocomplete({
            source: function (request, response) {
                var loCode = $('#lo_code').val();
                if (!loCode) {
                    response([]);
                    return;
                }
                $loading.prop('hidden', false);
                $.getJSON(searchUrl, { term: request.term, lo_code: loCode })
                    .done(function (data) {
                        response($.map(data, function (item) {
                            return { label: item.code + ' (' + item.price + ')', value: item.code };
                        }));
                    })
                    .fail(function () { response([]); })
                    .always(function () { $loading.prop('hidden', true); });
            },
            minLength: 1,
            select: function (event, ui) {
                $(this).val(ui.item.value);
                $(this).trigger('change');
                return false;
            },
        });
    }

    bindUnitAutocomplete('unit1');
    bindUnitAutocomplete('unit2');
    bindUnitAutocomplete('unit3');
    bindUnitAutocomplete('unit4');

    var printUrl = window.STORAGE_QUOTE_API.print;
    var downloadUrl = window.STORAGE_QUOTE_API.download;
    var emailUrl = window.STORAGE_QUOTE_API.email;
    var $form = $('#storageQuoteForm');
    var $message = $('#quote_action_message');

    function showMessage(text, isError) {
        $message.text(text).removeClass('success error').addClass(isError ? 'error' : 'success').prop('hidden', false);
    }

    $('#print_qoute').on('click', function (event) {
        if (!confirm('Are you sure you want to print?')) {
            event.preventDefault();
            return;
        }
        $form.attr('action', printUrl);
    });

    $('#download_contract').on('click', function (event) {
        if (!confirm('Are you sure you want to download?')) {
            event.preventDefault();
            return;
        }
        $form.attr('action', downloadUrl);
    });

    $('#email_qoute').on('click', function () {
        if (!$form[0].reportValidity()) {
            return;
        }
        if (!$('#lo_code').val()) {
            showMessage('Select a facility before emailing the quote.', true);
            return;
        }
        if (!confirm('Are you sure you want to email quote only?')) {
            return;
        }

        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Sending...');
        $message.prop('hidden', true);

        $.ajax({
            url: emailUrl,
            method: 'POST',
            data: new FormData($form[0]),
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        })
            .done(function (response) { showMessage(response.message || 'Quote emailed.', false); })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not send the email. Please try again.';
                showMessage(msg, true);
            })
            .always(function () { $btn.prop('disabled', false).text(originalText); });
    });
});
</script>
@endpush
