<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation {{ $quotation->quotation_number }}</title>
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

        .quotation-title {
            font-size: 24pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .quotation-number {
            font-size: 12pt;
            color: #666;
            margin-bottom: 10px;
        }

        .quotation-meta {
            font-size: 9pt;
            color: #666;
            line-height: 1.6;
        }

        .quotation-meta strong {
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

        .terms-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .terms-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .terms-content {
            font-size: 9pt;
            color: #666;
            line-height: 1.6;
            white-space: pre-wrap;
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
            @if($quotation->company->pdfLogoPath())
                <img src="{{ $quotation->company->pdfLogoPath() }}" alt="{{ $quotation->company->name }}" class="company-logo">
            @endif
            <div class="company-name">{{ $quotation->company->name }}</div>
            <div class="company-details">
                @if($quotation->company->address)
                    {{ $quotation->company->address }}<br>
                @endif
                @if($quotation->company->phone)
                    Phone: {{ $quotation->company->phone }}<br>
                @endif
                @if($quotation->company->email)
                    Email: {{ $quotation->company->email }}<br>
                @endif
                @if($quotation->company->website)
                    Website: {{ $quotation->company->website }}
                @endif
            </div>
        </div>
        <div class="header-right">
            <div class="quotation-title">QUOTATION</div>
            <div class="quotation-number">{{ $quotation->quotation_number }}</div>
            <div class="quotation-meta">
                <strong>Date:</strong> {{ $quotation->quotation_date->format('M d, Y') }}<br>
                <strong>Valid Until:</strong> {{ $quotation->valid_until->format('M d, Y') }}
            </div>
        </div>
    </div>

    <div class="client-section">
        <div class="section-title">Bill To:</div>
        <div class="client-info">
            <strong>{{ $quotation->client->name }}</strong><br>
            @if($quotation->client->contact_person)
                Contact: {{ $quotation->client->contact_person }}<br>
            @endif
            @if($quotation->client->email)
                Email: {{ $quotation->client->email }}<br>
            @endif
            @if($quotation->client->phone)
                Phone: {{ $quotation->client->phone }}<br>
            @endif
            @if($quotation->client->address)
                {{ $quotation->client->address }}
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Item</th>
                <th style="width: 35%;">Description</th>
                <th style="width: 8%;" class="text-center">Qty</th>
                <th style="width: 10%;" class="text-right">Unit Price</th>
                <th style="width: 7%;" class="text-center">Tax %</th>
                <th style="width: 10%;" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td><strong>{{ $item->item_name }}</strong></td>
                    <td>{{ $item->description ?: '-' }}</td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">${{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-center">{{ number_format($item->tax_percentage, 1) }}%</td>
                    <td class="text-right"><strong>${{ number_format($item->total, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-section">
        <div class="summary-spacer"></div>
        <div class="summary-box">
            <div class="summary-row">
                <div class="summary-label">Subtotal:</div>
                <div class="summary-value">${{ number_format($quotation->subtotal, 2) }}</div>
            </div>
            <div class="summary-row">
                <div class="summary-label">Tax:</div>
                <div class="summary-value">${{ number_format($quotation->tax_amount, 2) }}</div>
            </div>
            @if($quotation->discount_amount > 0)
                @php
                    $displayDiscount = $quotation->discount_type === 'percent' 
                        ? ($quotation->subtotal * ($quotation->discount_amount / 100))
                        : $quotation->discount_amount;
                @endphp
                <div class="summary-row">
                    <div class="summary-label">
                        Discount 
                        @if($quotation->discount_type === 'percent')
                            ({{ number_format($quotation->discount_amount, 1) }}%):
                        @else
                            :
                        @endif
                    </div>
                    <div class="summary-value">-${{ number_format($displayDiscount, 2) }}</div>
                </div>
            @endif
            <div class="summary-row total">
                <div class="summary-label">Total:</div>
                <div class="summary-value">${{ number_format($quotation->total, 2) }}</div>
            </div>
        </div>
    </div>

    @if($quotation->terms_conditions)
        <div class="terms-section">
            <div class="terms-title">Terms & Conditions:</div>
            <div class="terms-content">{{ $quotation->terms_conditions }}</div>
        </div>
    @endif

    <div class="footer">
        <p>This quotation is valid until {{ $quotation->valid_until->format('F d, Y') }}.</p>
        <p>Generated on {{ now()->format('F d, Y') }} at {{ now()->format('g:i A') }}</p>
    </div>
</body>
</html>

