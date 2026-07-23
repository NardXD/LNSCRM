<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Description - {{ $item->job_title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.5;
            padding: 40px;
        }
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            display: table;
            width: 100%;
        }
        .header-left, .header-right { display: table-cell; vertical-align: top; }
        .header-right { text-align: right; }
        .company-logo { max-width: 120px; max-height: 60px; margin-bottom: 8px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1a1a1a; margin-bottom: 4px; }
        .company-details { font-size: 9pt; color: #666; line-height: 1.5; }
        .job-title { font-size: 18pt; font-weight: bold; color: #1a1a1a; margin-bottom: 8px; }
        .job-meta { font-size: 9pt; color: #666; margin-bottom: 24px; }
        .description { white-space: pre-wrap; font-size: 10pt; line-height: 1.6; }
        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #ddd; text-align: center; font-size: 8pt; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            @if($company && $company->pdfLogoPath())
                <img src="{{ $company->pdfLogoPath() }}" alt="{{ $company->name }}" class="company-logo">
            @endif
            @if($company)
                <div class="company-name">{{ $company->name }}</div>
                @if($company->address || $company->email)
                    <div class="company-details">
                        @if($company->address) {{ $company->address }}<br> @endif
                        @if($company->email) {{ $company->email }} @endif
                    </div>
                @endif
            @endif
        </div>
        <div class="header-right">
            <div class="job-meta">
                <strong>Job Description</strong><br>
                Created: {{ $item->created_at->format('M d, Y') }}<br>
                Status: {{ ucfirst($item->status) }}
            </div>
        </div>
    </div>

    <div class="job-title">{{ $item->job_title }}</div>

    <div class="description">{{ $item->full_description }}</div>

    <div class="footer">
        Generated on {{ now()->format('F d, Y') }} at {{ now()->format('g:i A') }}
    </div>
</body>
</html>
