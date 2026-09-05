<?php

namespace App\Services;

use App\Models\FacebookMessage;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacebookReportService
{
    /**
     * @param  array<string, mixed>|Request  $filters
     * @return array{date_from: ?string, date_to: ?string, messages_received: int, messages_by_channel: array{messenger: int, instagram: int}, leads_created: int}
     */
    public function summary(int $companyId, array|Request $filters): array
    {
        [$dateFrom, $dateTo] = $this->normalizeDates($filters);

        $messages = FacebookMessage::query()
            ->join('facebook_conversations', 'facebook_conversations.id', '=', 'facebook_messages.facebook_conversation_id')
            ->where('facebook_messages.company_id', $companyId)
            ->where('facebook_messages.direction', 'inbound');

        $this->applyMessageDateRange($messages, $dateFrom, $dateTo);

        $byChannel = (clone $messages)
            ->select('facebook_conversations.channel', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('facebook_conversations.channel')
            ->pluck('aggregate', 'channel');

        $leadsQuery = Lead::query()
            ->where('company_id', $companyId)
            ->where('source', 'facebook');
        if ($dateFrom !== null) {
            $leadsQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $leadsQuery->whereDate('created_at', '<=', $dateTo);
        }

        $messengerCount = (int) ($byChannel['messenger'] ?? 0);
        $instagramCount = (int) ($byChannel['instagram'] ?? 0);

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'messages_received' => $messengerCount + $instagramCount,
            'messages_by_channel' => [
                'messenger' => $messengerCount,
                'instagram' => $instagramCount,
            ],
            'leads_created' => $leadsQuery->count(),
        ];
    }

    /**
     * @param  array<string, mixed>|Request  $filters
     * @return array{0: ?string, 1: ?string}
     */
    protected function normalizeDates(array|Request $filters): array
    {
        $data = $filters instanceof Request ? $filters->all() : $filters;

        $dateFrom = trim((string) ($data['date_from'] ?? ''));
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : null;

        $dateTo = trim((string) ($data['date_to'] ?? ''));
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : null;

        return [$dateFrom, $dateTo];
    }

    /**
     * @param  Builder<FacebookMessage>  $query
     */
    protected function applyMessageDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        // sent_at is nullable on a handful of rows; fall back to created_at like the lead report's message helpers do.
        if ($dateFrom !== null) {
            $query->whereRaw('DATE(COALESCE(facebook_messages.sent_at, facebook_messages.created_at)) >= ?', [$dateFrom]);
        }
        if ($dateTo !== null) {
            $query->whereRaw('DATE(COALESCE(facebook_messages.sent_at, facebook_messages.created_at)) <= ?', [$dateTo]);
        }
    }
}
