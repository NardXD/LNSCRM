<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadReportActivitySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(protected Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows->isEmpty()
            ? collect([[
                'lead_id' => '',
                'lead_name' => '',
                'occurred_at' => '',
                'actor' => '',
                'action' => '',
                'summary' => 'No activity recorded for the filtered leads.',
                'details' => '',
            ]])
            : $this->rows;
    }

    public function title(): string
    {
        return 'Activity Log';
    }

    public function headings(): array
    {
        return [
            'Lead ID',
            'Lead name',
            'Occurred at',
            'Actor',
            'Action',
            'Summary',
            'Details',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['lead_id'] ?? '',
            $row['lead_name'] ?? '',
            $row['occurred_at'] ?? '',
            $row['actor'] ?? '',
            $row['action'] ?? '',
            $row['summary'] ?? '',
            $row['details'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
