<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Report</title>
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
        }
        
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .header h1 {
            font-size: 24pt;
            margin-bottom: 5px;
            color: #1a1a1a;
        }
        
        .company-info {
            margin-top: 10px;
            font-size: 9pt;
            color: #666;
        }
        
        .report-meta {
            display: table;
            width: 100%;
            margin-top: 15px;
        }
        
        .meta-row {
            display: table-row;
        }
        
        .meta-label, .meta-value {
            display: table-cell;
            padding: 3px 0;
        }
        
        .meta-label {
            font-weight: bold;
            width: 150px;
        }
        
        .summary-section {
            margin: 25px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
        }
        
        .summary-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .summary-grid {
            display: table;
            width: 100%;
        }
        
        .summary-item {
            display: table-cell;
            width: 33.33%;
            padding: 5px;
        }
        
        .summary-label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 3px;
        }
        
        .summary-value {
            font-size: 14pt;
            font-weight: bold;
            color: #1a1a1a;
        }
        
        .summary-total {
            color: #2563eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 9pt;
        }
        
        thead {
            background-color: #333;
            color: white;
        }
        
        th {
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #555;
        }
        
        td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Payroll Report</h1>
        @if($company)
        <div class="company-info">
            <strong>{{ $company->name }}</strong>
            @if($company->address)
                <br>{{ $company->address }}
            @endif
            @if($company->email)
                <br>Email: {{ $company->email }}
            @endif
            @if($company->phone)
                <br>Phone: {{ $company->phone }}
            @endif
        </div>
        @endif
        <div class="report-meta">
            <div class="meta-row">
                <div class="meta-label">Period:</div>
                <div class="meta-value">{{ $period['start_date'] }} - {{ $period['end_date'] }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">Generated:</div>
                <div class="meta-value">{{ $generated_date }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-label">Required Hours:</div>
                <div class="meta-value">{{ number_format($required_hours, 1) }} hours</div>
            </div>
        </div>
    </div>

    <div class="summary-section">
        <div class="summary-title">Summary</div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Employees</div>
                <div class="summary-value">{{ $summary['total_employees'] }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Gross Pay</div>
                <div class="summary-value">${{ number_format($summary['total_gross_pay'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Deductions</div>
                <div class="summary-value">${{ number_format($summary['total_deductions'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total Net Pay</div>
                <div class="summary-value summary-total">${{ number_format($summary['total_net_pay'], 2) }}</div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Employee Name</th>
                <th class="text-right">Base Salary</th>
                <th class="text-right">Hours Worked</th>
                <th class="text-right">Required Hours</th>
                <th class="text-right">Overtime</th>
                <th class="text-right">Allowances</th>
                <th class="text-right">Gross Pay</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net Pay</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportData as $employee)
            <tr>
                <td>{{ $employee['employee_name'] }}</td>
                <td class="text-right">${{ number_format($employee['base_salary'], 2) }}</td>
                <td class="text-right">{{ number_format($employee['hours_worked'], 1) }}</td>
                <td class="text-right">{{ number_format($employee['required_hours'], 1) }}</td>
                <td class="text-right">{{ number_format($employee['overtime_hours'], 1) }} hrs</td>
                <td class="text-right">${{ number_format($employee['allowances'], 2) }}</td>
                <td class="text-right">${{ number_format($employee['gross_pay'], 2) }}</td>
                <td class="text-right">${{ number_format($employee['deductions'], 2) }}</td>
                <td class="text-right"><strong>${{ number_format($employee['net_pay'], 2) }}</strong></td>
            </tr>
            @empty
            <tr>
                <td colspan="9" class="text-center" style="padding: 20px;">No data available</td>
            </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #e5e7eb; font-weight: bold;">
                <td>TOTAL</td>
                <td class="text-right">${{ number_format(array_sum(array_column($reportData, 'base_salary')), 2) }}</td>
                <td class="text-right">{{ number_format(array_sum(array_column($reportData, 'hours_worked')), 1) }}</td>
                <td></td>
                <td class="text-right">{{ number_format(array_sum(array_column($reportData, 'overtime_hours')), 1) }} hrs</td>
                <td class="text-right">${{ number_format(array_sum(array_column($reportData, 'allowances')), 2) }}</td>
                <td class="text-right">${{ number_format($summary['total_gross_pay'], 2) }}</td>
                <td class="text-right">${{ number_format($summary['total_deductions'], 2) }}</td>
                <td class="text-right">${{ number_format($summary['total_net_pay'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <p>This report was generated on {{ $generated_date }} for the period {{ $period['start_date'] }} to {{ $period['end_date'] }}</p>
        <p>Required Hours: {{ number_format($required_hours, 1) }} hours | Total Employees: {{ $summary['total_employees'] }}</p>
    </div>
</body>
</html>

