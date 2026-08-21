<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LeadReportExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  Collection<int, array<string, mixed>>  $leads
     * @param  Collection<int, array<string, mixed>>  $activities
     * @param  Collection<int, array<string, mixed>>  $conversations
     */
    public function __construct(
        protected Collection $leads,
        protected Collection $activities,
        protected Collection $conversations,
        protected string $companyName = ''
    ) {}

    public function sheets(): array
    {
        return [
            new LeadReportLeadsSheet($this->leads),
            new LeadReportActivitySheet($this->activities),
            new LeadReportConversationsSheet($this->conversations),
        ];
    }
}
