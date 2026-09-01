<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #333; padding-bottom: 2px; }
    .muted { color: #555; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { border: 0.5pt solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
    th { background: #f3f4f6; font-weight: bold; }
    .text-right { text-align: right; }
    .no-border td { border: none; padding: 2px 6px; }
    .total-row td { font-weight: bold; background: #fef3c7; }
</style>
</head>
<body>
    <h1>LOC &amp; STOR 24/7</h1>
    <p class="muted">{{ $data['facility_label'] }} &mdash; Storage Quote</p>
    <p class="muted" style="font-size:9px;">Generated {{ $data['generated_at'] }}</p>

    <h2>Prepared For</h2>
    <table class="no-border">
        <tr>
            <td style="width:50%"><strong>Name:</strong> {{ trim($data['tenant']['mr_mrs'].' '.$data['tenant']['first_name'].' '.$data['tenant']['last_name']) }}</td>
            <td><strong>Email:</strong> {{ $data['tenant']['email'] ?: '-' }}</td>
        </tr>
    </table>

    <h2>Fee Schedule</h2>
    <table>
        <thead>
            <tr>
                <th>Unit</th>
                <th>Size (SQM)</th>
                <th class="text-right">Storage Fee (PHP/Month)</th>
                <th>Insurance Coverage</th>
                <th class="text-right">Insurance Fee</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['units'] as $unit)
                <tr>
                    <td>{{ $unit['code'] }}</td>
                    <td>{{ $unit['sqm'] }}</td>
                    <td class="text-right">{{ number_format($unit['price'], 2) }}</td>
                    <td>{{ $unit['insurance_coverage'] ?: '-' }}</td>
                    <td class="text-right">{{ number_format($unit['insurance_fee'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">Total</td>
                <td class="text-right">{{ number_format($data['totals']['storage_fee'], 2) }}</td>
                <td></td>
                <td class="text-right">{{ number_format($data['totals']['insurance_total'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Amount Payable for Initial Storage Period</h2>
    <table class="no-border">
        <tr>
            <td style="width:60%">Initial storage period</td>
            <td class="text-right">{{ $data['terms']['initial_period'] }} month(s)</td>
        </tr>
        <tr>
            <td>Security Deposit (non-VAT) &mdash; 1 month standard Storage Fee net of VAT</td>
            <td class="text-right">{{ number_format($data['totals']['deposit_notax'], 2) }}</td>
        </tr>
        <tr>
            <td>Total Insurance Fee &mdash; Initial Storage Period</td>
            <td class="text-right">{{ number_format($data['totals']['insurance_computation'], 2) }}</td>
        </tr>
        <tr>
            <td>Total Storage Service Fee &mdash; Initial Storage Period</td>
            <td class="text-right">{{ number_format($data['totals']['final_storage_fee'], 2) }}</td>
        </tr>
        @if ($data['totals']['reduction'] > 0)
            <tr>
                <td>Promo/Discount &mdash; From Prescribed Discount Plans Only</td>
                <td class="text-right">({{ number_format($data['totals']['reduction'], 2) }})</td>
            </tr>
        @endif
        <tr>
            <td>Admin Fee &mdash; Documentation and Processing Fee</td>
            <td class="text-right">{{ number_format($data['totals']['admin_fee'], 2) }}</td>
        </tr>
        @foreach ($data['terms']['adjustments'] as $index => $amount)
            @if ($amount != 0)
                <tr>
                    <td>Other Adjustment {{ $index + 1 }} @if($data['terms']['adjustment_remarks'][$index]) &mdash; {{ $data['terms']['adjustment_remarks'][$index] }} @endif</td>
                    <td class="text-right">{{ number_format($amount, 2) }}</td>
                </tr>
            @endif
        @endforeach
        @if ($data['terms']['adjustments_nonvat'] != 0)
            <tr>
                <td>Other Adjustment (non-VAT)</td>
                <td class="text-right">{{ number_format($data['terms']['adjustments_nonvat'], 2) }}</td>
            </tr>
        @endif
        @if ($data['terms']['withholding_tax'])
            <tr>
                <td>Withholding tax &mdash; If applicable</td>
                <td class="text-right">{{ number_format($data['totals']['withholding_tax_amount'], 2) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td>Total Amount Payable (VAT inclusive)</td>
            <td class="text-right">PHP {{ number_format($data['totals']['total_due'], 2) }}</td>
        </tr>
    </table>

    <p style="font-size:9px;" class="muted">This is an estimate only and does not constitute a binding contract. Final pricing and terms will be confirmed with our sales staff. Quotation valid for 30 days.</p>
</body>
</html>
