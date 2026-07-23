<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

class PayrollReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithMapping, WithCustomStartCell, WithEvents
{
    protected $reportData;
    protected $summary;
    protected $company;
    protected $period;
    protected $generatedDate;
    protected $requiredHours;

    public function __construct($reportData, $summary, $company, $period, $generatedDate, $requiredHours)
    {
        $this->reportData = $reportData;
        $this->summary = $summary;
        $this->company = $company;
        $this->period = $period;
        $this->generatedDate = $generatedDate;
        $this->requiredHours = $requiredHours;
    }

    public function collection()
    {
        return collect($this->reportData);
    }

    public function headings(): array
    {
        return [
            'Employee Name',
            'Client(s)',
            'Client Invoice Amount',
            'Base Salary',
            'Hours Worked',
            'Required Hours',
            'Overtime',
            'Deductions',
            'Net Pay',
        ];
    }

    public function map($row): array
    {
        $hoursDisplay = isset($row['hours_worked_seconds'])
            ? $this->formatSecondsToHms((int) $row['hours_worked_seconds'])
            : (string) ($row['hours_worked'] ?? '');

        return [
            $row['employee_name'],
            $row['clients'] ?? '—',
            $row['client_invoice_amount'] ?? 0,
            $row['base_salary'],
            $hoursDisplay,
            $row['required_hours'],
            $row['overtime_hours'] ?? 0,
            $row['deductions'],
            $row['net_pay'],
        ];
    }

    private function formatSecondsToHms(int $totalSeconds): string
    {
        $n = max(0, $totalSeconds);
        $h = intdiv($n, 3600);
        $m = intdiv($n % 3600, 60);
        $s = $n % 60;

        return sprintf('%d:%02d:%02d', $h, $m, $s);
    }

    public function startCell(): string
    {
        return 'A7'; // Start data after header information
    }

    public function title(): string
    {
        return 'Payroll Report';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25, // Employee Name
            'B' => 30, // Client(s)
            'C' => 18, // Client Invoice Amount
            'D' => 15, // Base Salary
            'E' => 15, // Hours Worked
            'F' => 15, // Required Hours
            'G' => 12, // Overtime
            'H' => 15, // Deductions
            'I' => 15, // Net Pay
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            7 => ['font' => ['bold' => true]], // Header row
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Set header information
                $sheet->setCellValue('A1', 'PAYROLL REPORT');
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                if ($this->company) {
                    $row = 2;
                    if ($this->company->name) {
                        $sheet->setCellValue('A' . $row, $this->company->name);
                        $sheet->mergeCells('A' . $row . ':I' . $row);
                        $sheet->getStyle('A' . $row)->applyFromArray([
                            'font' => ['bold' => true, 'size' => 12],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        $row++;
                    }
                    if ($this->company->address) {
                        $sheet->setCellValue('A' . $row, $this->company->address);
                        $sheet->mergeCells('A' . $row . ':I' . $row);
                        $sheet->getStyle('A' . $row)->applyFromArray([
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        $row++;
                    }
                }

                // Period and Generated Date
                $infoRow = $sheet->getHighestRow() < 4 ? 4 : $sheet->getHighestRow() + 1;
                $sheet->setCellValue('A' . $infoRow, 'Period: ' . $this->period['start_date'] . ' - ' . $this->period['end_date']);
                $sheet->setCellValue('D' . $infoRow, 'Generated: ' . $this->generatedDate);
                $sheet->setCellValue('G' . $infoRow, 'Required Hours: ' . ($this->requiredHours !== null ? number_format($this->requiredHours, 1) : 'Per employee'));

                // Summary section
                $summaryRow = $infoRow + 1;
                $sheet->setCellValue('A' . $summaryRow, 'SUMMARY');
                $sheet->mergeCells('A' . $summaryRow . ':I' . $summaryRow);
                $sheet->getStyle('A' . $summaryRow)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E5E7EB'],
                    ],
                ]);

                $summaryRow++;
                $sheet->setCellValue('A' . $summaryRow, 'Total Employees: ' . $this->summary['total_employees']);
                $sheet->setCellValue('D' . $summaryRow, 'Total Gross Pay: $' . number_format($this->summary['total_gross_pay'], 2));
                $sheet->setCellValue('G' . $summaryRow, 'Total Deductions: $' . number_format($this->summary['total_deductions'], 2));
                $summaryRow++;
                $sheet->setCellValue('A' . $summaryRow, 'Total Net Pay: $' . number_format($this->summary['total_net_pay'], 2));
                $sheet->mergeCells('A' . $summaryRow . ':C' . $summaryRow);
                $sheet->getStyle('A' . $summaryRow)->applyFromArray([
                    'font' => ['bold' => true],
                ]);

                // Style header row
                $headerRow = 7;
                $sheet->getStyle('A' . $headerRow . ':I' . $headerRow)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '333333'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Style data rows
                $lastRow = $sheet->getHighestRow();
                if ($lastRow > $headerRow) {
                    $dataRange = 'A' . ($headerRow + 1) . ':I' . $lastRow;
                    $sheet->getStyle($dataRange)->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);

                    // Right align numeric columns
                    $numericColumns = ['C', 'D', 'E', 'F', 'G', 'H', 'I'];
                    foreach ($numericColumns as $col) {
                        $sheet->getStyle($col . ($headerRow + 1) . ':' . $col . $lastRow)->applyFromArray([
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                            'numberFormat' => [
                                'formatCode' => '#,##0.00',
                            ],
                        ]);
                    }

                    // Format specific columns - Hours: E, F, G
                    $sheet->getStyle('E' . ($headerRow + 1) . ':G' . ($headerRow + 1))->getNumberFormat()->setFormatCode('#,##0.0'); // Hours
                    
                    // Add total row
                    $totalRow = $lastRow + 1;
                    $sheet->setCellValue('A' . $totalRow, 'TOTAL');
                    $sheet->setCellValue('D' . $totalRow, '=SUM(D' . ($headerRow + 1) . ':D' . $lastRow . ')');
                    $sheet->setCellValue('E' . $totalRow, '=SUM(E' . ($headerRow + 1) . ':E' . $lastRow . ')');
                    $sheet->setCellValue('G' . $totalRow, '=SUM(G' . ($headerRow + 1) . ':G' . $lastRow . ')');
                    $sheet->setCellValue('H' . $totalRow, '=SUM(H' . ($headerRow + 1) . ':H' . $lastRow . ')');
                    $sheet->setCellValue('I' . $totalRow, '=SUM(I' . ($headerRow + 1) . ':I' . $lastRow . ')');
                    
                    $sheet->getStyle('A' . $totalRow . ':I' . $totalRow)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'E5E7EB'],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                            ],
                        ],
                    ]);
                }
            },
        ];
    }
}
