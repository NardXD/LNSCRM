<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contract {{ $contract->contract_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.5;
            padding: 40px 45px;
        }

        .header {
            margin-bottom: 32px;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 22px;
            display: table;
            width: 100%;
        }

        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: top;
        }

        .header-right {
            text-align: right;
            width: 42%;
        }

        .company-logo {
            max-width: 140px;
            max-height: 72px;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 17pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 6px;
        }

        .company-details {
            font-size: 9pt;
            color: #666;
            line-height: 1.65;
        }

        .doc-label {
            font-size: 22pt;
            font-weight: bold;
            color: #1a1a1a;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .doc-ref {
            font-size: 11pt;
            color: #555;
            margin-bottom: 12px;
        }

        .doc-meta {
            font-size: 9pt;
            color: #666;
            line-height: 1.75;
        }

        .doc-meta strong {
            color: #333;
        }

        .parties-section {
            display: table;
            width: 100%;
            margin-bottom: 28px;
        }

        .party-box {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }

        .party-box:last-child {
            padding-right: 0;
            padding-left: 16px;
        }

        .section-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .party-info {
            font-size: 9.5pt;
            color: #333;
            line-height: 1.75;
        }

        .party-info strong {
            font-size: 10.5pt;
            color: #1a1a1a;
        }

        .contract-heading {
            text-align: center;
            margin: 28px 0 24px;
            padding: 16px 20px;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
        }

        .contract-heading h1 {
            font-size: 16pt;
            font-weight: bold;
            color: #1a1a1a;
            line-height: 1.35;
        }

        .agreement-section {
            margin-bottom: 36px;
        }

        .agreement-body {
            font-size: 10pt;
            color: #333;
            line-height: 1.7;
            text-align: justify;
            padding: 4px 2px;
        }

        .agreement-body p {
            margin-bottom: 10px;
        }

        .agreement-body ul,
        .agreement-body ol {
            margin: 8px 0 12px 22px;
        }

        .agreement-body li {
            margin-bottom: 4px;
        }

        .agreement-body h2 {
            font-size: 12pt;
            font-weight: bold;
            color: #1a1a1a;
            margin: 18px 0 8px;
        }

        .agreement-body h3 {
            font-size: 11pt;
            font-weight: bold;
            color: #1a1a1a;
            margin: 14px 0 6px;
        }

        .agreement-body blockquote {
            margin: 10px 0;
            padding: 10px 14px;
            border-left: 3px solid #4f46e5;
            background: #f8f9fa;
            color: #555;
            font-style: italic;
        }

        .signatures-section {
            margin-top: 36px;
            page-break-inside: avoid;
        }

        .signatures-intro {
            font-size: 9pt;
            color: #666;
            margin-bottom: 16px;
            line-height: 1.6;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 10px 20px 0;
        }

        .signature-table td:nth-child(even) {
            padding-right: 0;
            padding-left: 10px;
        }

        .signature-card {
            border: 1px solid #ddd;
            padding: 14px 16px;
            min-height: 120px;
            background: #fafafa;
        }

        .signature-role {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            margin-bottom: 8px;
        }

        .signature-area {
            min-height: 52px;
            margin-bottom: 10px;
        }

        .signature-img {
            max-height: 52px;
            max-width: 180px;
        }

        .signature-line {
            border-top: 1px solid #1a1a1a;
            margin-bottom: 8px;
            padding-top: 8px;
        }

        .signature-name {
            font-size: 10pt;
            font-weight: bold;
            color: #1a1a1a;
        }

        .signature-meta {
            font-size: 8pt;
            color: #666;
            margin-top: 4px;
            line-height: 1.5;
        }

        .signature-pending {
            font-size: 8pt;
            color: #d97706;
            font-weight: bold;
            text-transform: uppercase;
        }

        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8pt;
            color: #999;
            line-height: 1.6;
        }

        .footer-ref {
            color: #666;
            font-size: 8pt;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    @php
        $company = $contract->company;
        $client = $contract->client;
    @endphp

    <div class="header">
        <div class="header-left">
            @if($company?->pdfLogoPath())
                <img src="{{ $company->pdfLogoPath() }}" alt="{{ $company->name }}" class="company-logo">
            @endif
            <div class="company-name">{{ $company->name ?? 'Company' }}</div>
            <div class="company-details">
                @if($company?->address)
                    {{ $company->address }}<br>
                @endif
                @if($company?->phone)
                    Phone: {{ $company->phone }}<br>
                @endif
                @if($company?->email)
                    Email: {{ $company->email }}<br>
                @endif
                @if($company?->website)
                    Website: {{ $company->website }}
                @endif
            </div>
        </div>
        <div class="header-right">
            <div class="doc-label">CONTRACT</div>
            <div class="doc-ref">{{ $contract->contract_number }}</div>
            <div class="doc-meta">
                @if($contract->effective_date)
                    <strong>Effective Date:</strong> {{ $contract->effective_date->format('M d, Y') }}<br>
                @endif
                @if($contract->expiry_date)
                    <strong>Expiry Date:</strong> {{ $contract->expiry_date->format('M d, Y') }}<br>
                @endif
                @if($contract->signed_at)
                    <strong>Executed:</strong> {{ $contract->signed_at->format('M d, Y') }}<br>
                @endif
            </div>
        </div>
    </div>

    <div class="parties-section">
        <div class="party-box">
            <div class="section-title">Service Provider</div>
            <div class="party-info">
                <strong>{{ $company->name ?? 'Company' }}</strong><br>
                @if($company?->email)
                    {{ $company->email }}<br>
                @endif
                @if($company?->phone)
                    {{ $company->phone }}<br>
                @endif
                @if($company?->address)
                    {{ $company->address }}
                @endif
            </div>
        </div>
        <div class="party-box">
            <div class="section-title">Client</div>
            <div class="party-info">
                <strong>{{ $client->name }}</strong><br>
                @if($client->contact_person)
                    Attn: {{ $client->contact_person }}<br>
                @endif
                @if($client->email)
                    {{ $client->email }}<br>
                @endif
                @if($client->phone)
                    {{ $client->phone }}<br>
                @endif
                @if($client->address)
                    {{ $client->address }}
                @endif
            </div>
        </div>
    </div>

    <div class="contract-heading">
        <h1>{{ $contract->title }}</h1>
    </div>

    <div class="agreement-section">
        <div class="section-title">Terms of Agreement</div>
        <div class="agreement-body">{!! $contract->content !!}</div>
    </div>

    <div class="signatures-section">
        <div class="section-title">Authorized Signatures</div>
        <p class="signatures-intro">
            The parties listed below acknowledge that they have read, understood, and agree to the terms set forth in this agreement.
            Electronic signatures captured through the platform are intended to be legally binding.
        </p>
        <table class="signature-table">
            @foreach($contract->signers->chunk(2) as $signerPair)
                <tr>
                    @foreach($signerPair as $signer)
                        <td>
                            <div class="signature-card">
                                <div class="signature-role">{{ ucfirst($signer->role) }} Signatory</div>
                                <div class="signature-area">
                                    @if($signer->status === 'signed' && $signer->getSignatureDataUri())
                                        <img src="{{ $signer->getSignatureDataUri() }}" class="signature-img" alt="Signature">
                                    @endif
                                </div>
                                <div class="signature-line">
                                    <div class="signature-name">{{ $signer->name }}</div>
                                    <div class="signature-meta">
                                        {{ $signer->email }}<br>
                                        @if($signer->signed_at)
                                            Signed on {{ $signer->signed_at->format('F d, Y \a\t g:i A') }}
                                        @else
                                            <span class="signature-pending">Signature Pending</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if($signerPair->count() === 1)
                        <td></td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>

    <div class="footer">
        <div class="footer-ref">Document Reference: {{ $contract->contract_number }}</div>
        <p>This document was generated on {{ now()->format('F d, Y \a\t g:i A') }}.</p>
    </div>
</body>
</html>
