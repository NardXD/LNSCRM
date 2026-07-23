<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Report {{ $periodStart }} - {{ $periodEnd }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #333; padding: 24px; }
        .header { margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 12px; }
        .company-name { font-size: 14pt; font-weight: bold; margin-bottom: 4px; }
        .period { font-size: 11pt; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #333; color: #fff; font-weight: bold; }
        td.numeric { text-align: right; }
        .total-row { font-weight: bold; background: #eee; }
        .footer { margin-top: 20px; font-size: 9pt; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        @if($company)
            <div class="company-name">{{ $company->name }}</div>
            @if($company->address)
                <div style="font-size: 9pt; color: #666;">{{ $company->address }}</div>
            @endif
        @endif
        <div class="period" style="margin-top: 8px;">Period: {{ $periodStart }} – {{ $periodEnd }}</div>
        <div style="font-size: 9pt; color: #666;">Generated: {{ now()->format('M d, Y') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th class="numeric">Base Salary</th>
                <th class="numeric">Hours</th>
                <th class="numeric">Required Hrs</th>
                <th class="numeric">Allowances</th>
                <th class="numeric">Gross Pay</th>
                <th class="numeric">Deductions</th>
                <th class="numeric">Net Pay</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalGross = 0;
                $totalDeductions = 0;
                $totalNet = 0;
            @endphp
            @foreach($report->items as $item)
                @php
                    $gross = (float) ($item->gross_pay ?? 0);
                    $ded = (float) ($item->deductions ?? 0);
                    $net = (float) ($item->net_pay ?? 0);
                    $totalGross += $gross;
                    $totalDeductions += $ded;
                    $totalNet += $net;
                @endphp
                <tr>
                    <td>{{ $item->employee_name ?? '--' }}</td>
                    <td class="numeric">{{ number_format((float)($item->base_salary ?? 0), 2) }}</td>
                    <td class="numeric">{{ number_format((float)($item->hours_worked ?? 0), 1) }}</td>
                    <td class="numeric">{{ number_format((float)($item->required_hours ?? 0), 1) }}</td>
                    <td class="numeric">{{ number_format((float)($item->allowances ?? 0), 2) }}</td>
                    <td class="numeric">{{ number_format($gross, 2) }}</td>
                    <td class="numeric">{{ number_format($ded, 2) }}</td>
                    <td class="numeric">{{ number_format($net, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>TOTAL</td>
                <td class="numeric">—</td>
                <td class="numeric">—</td>
                <td class="numeric">—</td>
                <td class="numeric">—</td>
                <td class="numeric">{{ number_format($totalGross, 2) }}</td>
                <td class="numeric">{{ number_format($totalDeductions, 2) }}</td>
                <td class="numeric">{{ number_format($totalNet, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="footer" style="margin-top: 16px;">
        Currency: {{ strtoupper($report->currency ?? 'USD') }} · {{ $report->items->count() }} employee(s)
    </div>
</body>
</html>
