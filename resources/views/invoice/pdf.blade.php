<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
            padding: 40px;
        }

        .header {
            margin-bottom: 40px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            display: table;
            width: 100%;
        }

        .header-left, .header-right {
            display: table-cell;
            vertical-align: top;
        }

        .header-right {
            text-align: right;
        }

        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 9pt;
            color: #666;
            line-height: 1.6;
        }

        .invoice-title {
            font-size: 24pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .invoice-number {
            font-size: 12pt;
            color: #666;
            margin-bottom: 10px;
        }

        .invoice-meta {
            font-size: 9pt;
            color: #666;
            line-height: 1.6;
        }

        .invoice-meta strong {
            color: #333;
        }

        .client-section {
            margin: 30px 0;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .client-info {
            font-size: 10pt;
            color: #333;
            line-height: 1.8;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }

        .items-table thead {
            background-color: #f5f5f5;
        }

        .items-table th {
            padding: 12px 8px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
            color: #333;
            border: 1px solid #ddd;
        }

        .items-table td {
            padding: 10px 8px;
            font-size: 9pt;
            border: 1px solid #ddd;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary-section {
            margin-top: 20px;
            display: table;
            width: 100%;
        }

        .summary-spacer {
            display: table-cell;
            width: 60%;
        }

        .summary-box {
            display: table-cell;
            width: 40%;
            vertical-align: top;
        }

        .summary-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .summary-label, .summary-value {
            display: table-cell;
            padding: 6px 10px;
            font-size: 9pt;
        }

        .summary-label {
            text-align: right;
            color: #666;
            width: 60%;
        }

        .summary-value {
            text-align: right;
            font-weight: bold;
            width: 40%;
        }

        .summary-row.total {
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 10px;
        }

        .summary-row.total .summary-label,
        .summary-row.total .summary-value {
            font-size: 12pt;
            font-weight: bold;
            color: #1a1a1a;
        }

        .notes-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .notes-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .notes-content {
            font-size: 9pt;
            color: #666;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .payment-link-section {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .payment-link-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .payment-link-a {
            font-size: 9pt;
            color: #0d6efd;
            text-decoration: underline;
            word-break: break-all;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }

    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if($invoice->company && $invoice->company->pdfLogoPath())
                <img src="{{ $invoice->company->pdfLogoPath() }}" alt="{{ $invoice->company->name }}" class="company-logo">
            @endif
            <div class="company-name">{{ $invoice->company?->name ?? 'Company' }}</div>
            <div class="company-details">
                @if($invoice->company?->address)
                    {{ $invoice->company->address }}<br>
                @endif
                @if($invoice->company?->phone)
                    Phone: {{ $invoice->company->phone }}<br>
                @endif
                @if($invoice->company?->email)
                    Email: {{ $invoice->company->email }}<br>
                @endif
                @if($invoice->company?->website)
                    Website: {{ $invoice->company->website }}
                @endif
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">{{ $invoice->invoice_number }}</div>
            <div class="invoice-meta">
                <strong>Invoice Date:</strong> {{ $invoice->invoice_date->format('M d, Y') }}<br>
                <strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}
            </div>
        </div>
    </div>

    <div class="client-section">
        <div class="section-title">Bill To:</div>
        <div class="client-info">
            <strong>{{ $invoice->client?->name ?? 'Client' }}</strong><br>
            @if($invoice->client?->contact_person)
                Contact: {{ $invoice->client->contact_person }}<br>
            @endif
            @if($invoice->client?->email)
                Email: {{ $invoice->client->email }}<br>
            @endif
            @if($invoice->client?->phone)
                Phone: {{ $invoice->client->phone }}<br>
            @endif
            @if($invoice->client?->address)
                {{ $invoice->client->address }}
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 48%;">Description</th>
                <th style="width: 12%;" class="text-center">Hours</th>
                <th style="width: 17%;" class="text-right">Rate</th>
                <th style="width: 18%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items->sortBy('sort_order') as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $item->description ?: '-' }}</td>
                    <td class="text-center">{{ $item->hours_worked !== null ? number_format((float) $item->hours_worked, 2) : '—' }}</td>
                    <td class="text-right">${{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right"><strong>${{ number_format((float) $item->total, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-section">
        <div class="summary-spacer"></div>
        <div class="summary-box">
            <div class="summary-row">
                <div class="summary-label">Subtotal:</div>
                <div class="summary-value">${{ number_format((float) $invoice->subtotal, 2) }}</div>
            </div>
            @if((float) $invoice->tax_rate > 0)
                <div class="summary-row">
                    <div class="summary-label">Tax ({{ number_format((float) $invoice->tax_rate, 1) }}%):</div>
                    <div class="summary-value">${{ number_format((float) $invoice->tax_amount, 2) }}</div>
                </div>
            @endif
            <div class="summary-row total">
                <div class="summary-label">Total:</div>
                <div class="summary-value">${{ number_format((float) $invoice->total, 2) }}</div>
            </div>
        </div>
    </div>

    @if($invoice->stripe_payment_url)
        <div class="payment-link-section">
            <div class="payment-link-title">Pay Online</div>
            <a href="{{ $invoice->stripe_payment_url }}" class="payment-link-a">{{ $invoice->stripe_payment_url }}</a>
        </div>
    @endif

    @if($invoice->notes)
        <div class="notes-section">
            <div class="notes-title">Notes / Payment Terms:</div>
            <div class="notes-content">{{ $invoice->notes }}</div>
        </div>
    @endif

    <div class="footer">
        <p>Due date: {{ $invoice->due_date->format('F d, Y') }}</p>
        <p>Generated on {{ now()->format('F d, Y') }} at {{ now()->format('g:i A') }}</p>
    </div>
</body>
</html>
