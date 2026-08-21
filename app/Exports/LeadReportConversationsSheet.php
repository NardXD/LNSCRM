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

class LeadReportConversationsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
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
                'channel' => '',
                'thread_title' => '',
                'started_at' => '',
                'direction' => '',
                'first_message' => 'No conversations found for the filtered leads.',
                'last_preview' => '',
                'deep_link' => '',
            ]])
            : $this->rows;
    }

    public function title(): string
    {
        return 'Conversations';
    }

    public function headings(): array
    {
        return [
            'Lead ID',
            'Lead name',
            'Channel',
            'Thread title',
            'Conversation started at',
            'First message direction',
            'First message',
            'Latest preview',
            'Deep link',
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
            $row['channel'] ?? '',
            $row['thread_title'] ?? '',
            $row['started_at'] ?? '',
            $row['direction'] ?? '',
            $row['first_message'] ?? '',
            $row['last_preview'] ?? '',
            $row['deep_link'] ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
