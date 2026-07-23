<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $invoice->invoice_number }}</title>
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
        .payment-link {
            margin-top: 15px;
            padding: 12px;
            background: #e0e7ff;
            border-radius: 6px;
        }
        .payment-link a {
            color: #4f46e5;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice #{{ $invoice->invoice_number }}</h1>
    </div>
    <div class="content">
        <p>Hello {{ $client->name }},</p>

        <p>Please find attached your invoice #{{ $invoice->invoice_number }}.</p>

        <div class="detail-row">
            <span class="label">Invoice Date:</span> {{ $invoice->invoice_date->format('F d, Y') }}
        </div>
        <div class="detail-row">
            <span class="label">Due Date:</span> {{ $invoice->due_date->format('F d, Y') }}
        </div>
        <div class="detail-row">
            <span class="label">Total Amount:</span> ${{ number_format((float) $invoice->total, 2) }}
        </div>

        @if($invoice->stripe_payment_url)
        <div class="payment-link">
            <strong>Pay online (Card / Stripe):</strong><br>
            <a href="{{ $invoice->stripe_payment_url }}">{{ $invoice->stripe_payment_url }}</a>
        </div>
        @endif

        @if($invoice->wise_payment_url)
        <div class="payment-link" style="background: #d1fae5;">
            <strong>Pay with Wise:</strong><br>
            <a href="{{ $invoice->wise_payment_url }}" style="color: #047857;">{{ $invoice->wise_payment_url }}</a>
        </div>
        @endif

        @if($invoice->notes)
        <div style="margin-top: 25px;">
            <strong>Notes / Payment Terms:</strong>
            <p style="white-space: pre-wrap;">{{ $invoice->notes }}</p>
        </div>
        @endif

        <p style="margin-top: 25px;">Please find the detailed invoice attached as a PDF document.</p>

        <p>If you have any questions, please don't hesitate to contact us.</p>

        <div class="footer">
            <p>Best regards,<br>
            <strong>{{ $company->name ?? 'Company' }}</strong></p>
        </div>
    </div>
</body>
</html>
