<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Please Sign: {{ $contract->title }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4f46e5; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
        .detail-row { margin: 12px 0; }
        .label { font-weight: bold; color: #4f46e5; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Contract Signature Request</h1>
    </div>
    <div class="content">
        <p>Hello {{ $signer->name }},</p>

        <p>{{ $company->name ?? 'Our team' }} has sent you a contract that requires your electronic signature.</p>

        <div class="detail-row">
            <span class="label">Contract:</span> {{ $contract->title }}
        </div>
        <div class="detail-row">
            <span class="label">Reference:</span> {{ $contract->contract_number }}
        </div>
        @if($contract->effective_date)
        <div class="detail-row">
            <span class="label">Effective Date:</span> {{ $contract->effective_date->format('F d, Y') }}
        </div>
        @endif

        <p>Please review the contract and sign electronically using the secure link below:</p>

        <a href="{{ $signingUrl }}" class="btn">Review &amp; Sign Contract</a>

        <p style="font-size: 14px; color: #6b7280;">This link is unique to you and expires in 30 days. If you did not expect this email, you can safely ignore it.</p>

        <div class="footer">
            <p>Sent by {{ $company->name ?? 'Company' }}</p>
        </div>
    </div>
</body>
</html>
