<?php

namespace App\Services;

use App\Models\FacebookMessage;
use App\Models\Lead;
use App\Models\LeadIdentity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FacebookReportService
{
    /**
     * @param  array<string, mixed>|Request  $filters
     * @return array{
     *     date_from: ?string,
     *     date_to: ?string,
     *     messages_received: int,
     *     messages_by_channel: array{messenger: int, instagram: int},
     *     people_messaged: int,
     *     people_by_channel: array{messenger: int, instagram: int},
     *     leads_created: int,
     *     leads_by_channel: array{messenger: int, instagram: int, unknown: int},
     *     conversion_rate: float,
     *     daily: list<array{date: string, messenger_messages: int, instagram_messages: int, leads_created: int}>,
     *     leads_preview: list<array<string, mixed>>
     * }
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

        $peopleByChannel = (clone $messages)
            ->select('facebook_conversations.channel', DB::raw('COUNT(DISTINCT facebook_messages.facebook_conversation_id) as aggregate'))
            ->groupBy('facebook_conversations.channel')
            ->pluck('aggregate', 'channel');

        $dailyMessageRows = (clone $messages)
            ->selectRaw('DATE(COALESCE(facebook_messages.sent_at, facebook_messages.created_at)) as day, facebook_conversations.channel as channel, COUNT(*) as aggregate')
            ->groupBy('day', 'channel')
            ->orderBy('day')
            ->get();

        $leadsBase = Lead::query()
            ->where('company_id', $companyId)
            ->where('source', 'facebook');
        if ($dateFrom !== null) {
            $leadsBase->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $leadsBase->whereDate('created_at', '<=', $dateTo);
        }

        $totalLeadsCount = (clone $leadsBase)->count();

        $instagramLeadIds = (clone $leadsBase)
            ->whereHas('identities', fn ($q) => $q->where('type', LeadIdentity::TYPE_INSTAGRAM))
            ->pluck('id');

        $messengerLeadIds = (clone $leadsBase)
            ->whereHas('identities', fn ($q) => $q->where('type', LeadIdentity::TYPE_FACEBOOK))
            ->whereNotIn('id', $instagramLeadIds)
            ->pluck('id');

        $instagramLeadsCount = $instagramLeadIds->count();
        $messengerLeadsCount = $messengerLeadIds->count();
        $unknownLeadsCount = max(0, $totalLeadsCount - $instagramLeadsCount - $messengerLeadsCount);

        $dailyLeadRows = (clone $leadsBase)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day');

        $leadsPreview = (clone $leadsBase)
            ->with(['assignedUser:id,name', 'identities:id,lead_id,type'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (Lead $lead) {
                $hasIg = $lead->identities->contains('type', LeadIdentity::TYPE_INSTAGRAM);
                $hasFb = $lead->identities->contains('type', LeadIdentity::TYPE_FACEBOOK);
                $channel = $hasIg ? 'instagram' : ($hasFb ? 'messenger' : 'unknown');

                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'channel' => $channel,
                    'status' => $lead->status,
                    'assigned_user' => $lead->assignedUser ? ['name' => $lead->assignedUser->name] : null,
                    'created_at' => optional($lead->created_at)->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $messengerCount = (int) ($byChannel['messenger'] ?? 0);
        $instagramCount = (int) ($byChannel['instagram'] ?? 0);
        $messengerPeopleCount = (int) ($peopleByChannel['messenger'] ?? 0);
        $instagramPeopleCount = (int) ($peopleByChannel['instagram'] ?? 0);
        $peopleMessaged = $messengerPeopleCount + $instagramPeopleCount;

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'messages_received' => $messengerCount + $instagramCount,
            'messages_by_channel' => [
                'messenger' => $messengerCount,
                'instagram' => $instagramCount,
            ],
            'people_messaged' => $peopleMessaged,
            'people_by_channel' => [
                'messenger' => $messengerPeopleCount,
                'instagram' => $instagramPeopleCount,
            ],
            'leads_created' => $totalLeadsCount,
            'leads_by_channel' => [
                'messenger' => $messengerLeadsCount,
                'instagram' => $instagramLeadsCount,
                'unknown' => $unknownLeadsCount,
            ],
            'conversion_rate' => $peopleMessaged > 0 ? round($totalLeadsCount / $peopleMessaged * 100, 1) : 0.0,
            'daily' => $this->buildDailySeries($dateFrom, $dateTo, $dailyMessageRows, $dailyLeadRows),
            'leads_preview' => $leadsPreview,
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

    /**
     * @param  \Illuminate\Support\Collection<int, object{day: string, channel: string, aggregate: int}>  $messageRows
     * @param  \Illuminate\Support\Collection<string, int>  $leadRows
     * @return list<array{date: string, messenger_messages: int, instagram_messages: int, leads_created: int}>
     */
    protected function buildDailySeries(?string $dateFrom, ?string $dateTo, $messageRows, $leadRows): array
    {
        $messagesByDay = [];
        foreach ($messageRows as $row) {
            $messagesByDay[$row->day][$row->channel] = (int) $row->aggregate;
        }

        $days = array_keys($messagesByDay);
        foreach ($leadRows as $day => $count) {
            $days[] = $day;
        }
        $days = array_values(array_unique($days));

        if ($dateFrom !== null && $dateTo !== null) {
            try {
                $start = Carbon::parse($dateFrom);
                $end = Carbon::parse($dateTo);
                if ($start->lte($end) && $start->diffInDays($end) <= 366) {
                    $days = [];
                    for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
                        $days[] = $cursor->toDateString();
                    }
                }
            } catch (\Throwable) {
                // Fall back to the union of days that actually have data.
            }
        }

        sort($days);

        return array_map(fn (string $day) => [
            'date' => $day,
            'messenger_messages' => (int) ($messagesByDay[$day]['messenger'] ?? 0),
            'instagram_messages' => (int) ($messagesByDay[$day]['instagram'] ?? 0),
            'leads_created' => (int) ($leadRows[$day] ?? 0),
        ], $days);
    }
}
