<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation #{{ $quotation->quotation_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4f46e5;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .detail-row {
            margin: 15px 0;
        }
        .label {
            font-weight: bold;
            color: #4f46e5;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Quotation #{{ $quotation->quotation_number }}</h1>
    </div>
    <div class="content">
        <p>Hello {{ $client->name }},</p>
        
        <p>We are pleased to present you with quotation #{{ $quotation->quotation_number }}.</p>
        
        <div class="detail-row">
            <span class="label">Date:</span> {{ $quotation->quotation_date->format('F d, Y') }}
        </div>
        <div class="detail-row">
            <span class="label">Valid Until:</span> {{ $quotation->valid_until->format('F d, Y') }}
        </div>
        <div class="detail-row">
            <span class="label">Total Amount:</span> ${{ number_format($quotation->total, 2) }}
        </div>
        
        @if($quotation->terms_conditions)
        <div style="margin-top: 25px;">
            <strong>Terms & Conditions:</strong>
            <p style="white-space: pre-wrap;">{{ $quotation->terms_conditions }}</p>
        </div>
        @endif
        
        <p style="margin-top: 25px;">Please find the detailed quotation attached as a PDF document.</p>
        
        <p>If you have any questions or need clarification, please don't hesitate to contact us.</p>
        
        <div class="footer">
            <p>Best regards,<br>
            <strong>{{ $company->name ?? 'Company' }}</strong></p>
        </div>
    </div>
</body>
</html>
