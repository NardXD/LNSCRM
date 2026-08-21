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

class LeadReportLeadsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(protected Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function title(): string
    {
        return 'Leads';
    }

    public function headings(): array
    {
        return [
            'Lead ID',
            'Name',
            'Company',
            'Status',
            'Source',
            'Labels',
            'Assignee',
            'Customer type',
            'City',
            'Address',
            'Phones',
            'Emails',
            'Facebook',
            'Instagram',
            'Created at',
            'Updated at',
            'First conversation at',
            'First conversation channel',
            'First conversation message',
            'Conversation channels',
            'Activity count',
            'Activity timeline',
            'Notes',
            'CRM URL',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function map($row): array
    {
        return [
            $row['id'] ?? '',
            $row['name'] ?? '',
            $row['company_name'] ?? '',
            $row['status'] ?? '',
            $row['source'] ?? '',
            $row['labels'] ?? '',
            $row['assignee'] ?? '',
            $row['customer_type'] ?? '',
            $row['city'] ?? '',
            $row['address'] ?? '',
            $row['phones'] ?? '',
            $row['emails'] ?? '',
            $row['facebook'] ?? '',
            $row['instagram'] ?? '',
            $row['created_at'] ?? '',
            $row['updated_at'] ?? '',
            $row['first_conversation_at'] ?? '',
            $row['first_conversation_channel'] ?? '',
            $row['first_conversation_message'] ?? '',
            $row['conversation_channels'] ?? '',
            $row['activity_count'] ?? 0,
            $row['activity_timeline'] ?? '',
            $row['notes'] ?? '',
            $row['crm_url'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
