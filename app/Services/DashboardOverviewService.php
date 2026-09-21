<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\PhoneCallLog;
use App\Models\SmsConversation;
use App\Models\ViberConversation;
use App\Models\WhatsAppConversation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardOverviewService
{
    /**
     * @return array{
     *     period_label: string,
     *     leads: array<string, int|float>,
     *     pipeline: list<array{slug: string, label: string, count: int}>,
     *     sources: list<array{label: string, count: int}>,
     *     recentLeads: Collection<int, Lead>,
     *     recentActivity: Collection<int, array<string, mixed>>,
     *     channels: list<array<string, mixed>>,
     *     attention: Collection<int, array<string, mixed>>
     * }
     */
    public function forCompany(?int $companyId): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        $lastMonthStart = $monthStart->copy()->subMonth();
        $todayStart = Carbon::today();
        $tomorrowStart = $todayStart->copy()->addDay();
        $periodLabel = $monthStart->format('F Y');

        if (! $companyId) {
            return $this->emptyPayload($periodLabel);
        }

        [$leads, $pipeline] = $this->leadStatsAndPipeline(
            $companyId,
            $monthStart,
            $lastMonthStart,
        );

        return [
            'period_label' => $periodLabel,
            'leads' => $leads,
            'pipeline' => $pipeline,
            'sources' => $this->topSources($companyId, $monthStart),
            'recentLeads' => $this->recentLeads($companyId, $monthStart),
            'recentActivity' => $this->recentActivity($companyId, $monthStart),
            'channels' => $this->channels($companyId, $monthStart, $nextMonthStart, $todayStart, $tomorrowStart),
            'attention' => $this->attention($companyId, $monthStart),
        ];
    }

    /**
     * @return array{0: array<string, int|float>, 1: list<array{slug: string, label: string, count: int}>}
     */
    protected function leadStatsAndPipeline(
        int $companyId,
        Carbon $monthStart,
        Carbon $lastMonthStart,
    ): array {
        $rows = Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw(
                'status, COUNT(*) as aggregate,
                SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned'
            )
            ->groupBy('status')
            ->get();

        $byStatus = [];
        $unassigned = 0;
        foreach ($rows as $row) {
            $slug = (string) $row->status;
            $byStatus[$slug] = (int) $row->aggregate;
            $unassigned += (int) $row->unassigned;
        }

        $total = array_sum($byStatus);
        $converted = $byStatus['converted'] ?? 0;
        $lastMonth = Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->where('created_at', '>=', $lastMonthStart)
            ->where('created_at', '<', $monthStart)
            ->count();
        $monthChange = $lastMonth > 0
            ? round((($total - $lastMonth) / $lastMonth) * 100, 1)
            : ($total > 0 ? 100 : 0);

        $leads = [
            'total' => $total,
            'new' => $byStatus['new'] ?? 0,
            'contacted' => $byStatus['contacted'] ?? 0,
            'qualified' => $byStatus['qualified'] ?? 0,
            'converted' => $converted,
            'lost' => $byStatus['lost'] ?? 0,
            'snoozed' => $byStatus[Lead::STATUS_SNOOZED] ?? 0,
            'unassigned' => $unassigned,
            'this_month' => $total,
            'last_month' => $lastMonth,
            'month_change' => $monthChange,
            'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0.0,
        ];

        $statusNames = LeadStatus::forCompany($companyId)->pluck('name', 'slug');
        $pipeline = [];
        foreach ($statusNames as $slug => $name) {
            if ($slug === Lead::STATUS_ARCHIVED) {
                continue;
            }
            $pipeline[] = [
                'slug' => (string) $slug,
                'label' => (string) $name,
                'count' => $byStatus[$slug] ?? 0,
            ];
        }
        foreach ($byStatus as $slug => $count) {
            if ($slug === Lead::STATUS_ARCHIVED || $statusNames->has($slug)) {
                continue;
            }
            $pipeline[] = [
                'slug' => (string) $slug,
                'label' => ucfirst((string) $slug),
                'count' => $count,
            ];
        }

        return [$leads, $pipeline];
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    protected function topSources(int $companyId, Carbon $monthStart): array
    {
        return Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->where('created_at', '>=', $monthStart)
            ->select([
                DB::raw("COALESCE(NULLIF(TRIM(source), ''), 'Unspecified') as source_label"),
                DB::raw('COUNT(*) as aggregate'),
            ])
            ->groupBy('source_label')
            ->orderByDesc('aggregate')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->source_label,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Lead>
     */
    protected function recentLeads(int $companyId, Carbon $monthStart): Collection
    {
        return Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->where('created_at', '>=', $monthStart)
            ->with('assignedUser:id,name')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'company_id', 'name', 'first_name', 'last_name', 'status', 'source', 'assigned_to', 'updated_at']);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function recentActivity(int $companyId, Carbon $monthStart): Collection
    {
        return LeadActivity::query()
            ->select('lead_activities.*')
            ->join('leads', 'leads.id', '=', 'lead_activities.lead_id')
            ->where('leads.company_id', $companyId)
            ->where('lead_activities.created_at', '>=', $monthStart)
            ->with(['lead:id,name,first_name,last_name,status', 'user:id,name'])
            ->orderByDesc('lead_activities.created_at')
            ->limit(8)
            ->get()
            ->map(function (LeadActivity $activity) {
                $lead = $activity->lead;
                $leadName = trim(($lead?->first_name ?? '').' '.($lead?->last_name ?? ''));
                if ($leadName === '') {
                    $leadName = (string) ($lead?->name ?? 'Lead');
                }

                return [
                    'text' => $activity->summary !== ''
                        ? $activity->summary
                        : ucfirst(str_replace('_', ' ', $activity->action)).' · '.$leadName,
                    'lead' => $leadName,
                    'user' => $activity->user?->name,
                    'at' => $activity->created_at,
                    'action' => $activity->action,
                ];
            })
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function channels(
        int $companyId,
        Carbon $monthStart,
        Carbon $nextMonthStart,
        Carbon $todayStart,
        Carbon $tomorrowStart,
    ): array {
        $month = $monthStart->toDateTimeString();
        $today = $todayStart->toDateTimeString();
        $tomorrow = $tomorrowStart->toDateTimeString();

        $calls = PhoneCallLog::query()
            ->where('company_id', $companyId)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $nextMonthStart)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status IN ('no-answer', 'busy', 'failed', 'canceled') THEN 1 ELSE 0 END) as missed")
            ->selectRaw("SUM(CASE WHEN direction LIKE '%inbound%' THEN 1 ELSE 0 END) as inbound")
            ->selectRaw("SUM(CASE WHEN direction LIKE '%outbound%' THEN 1 ELSE 0 END) as outbound")
            ->first();

        $inbox = InboxConversation::query()
            ->where('company_id', $companyId)
            ->whereNull('merged_into_id')
            ->where('folder', 'inbox')
            ->where('status', 'open')
            ->where('last_message_at', '>=', $monthStart)
            ->selectRaw('COUNT(*) as open_count')
            ->selectRaw('SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread')
            ->selectRaw('SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned')
            ->selectRaw('SUM(CASE WHEN lead_id IS NOT NULL THEN 1 ELSE 0 END) as linked')
            ->selectRaw('SUM(CASE WHEN last_message_at >= ? AND last_message_at < ? THEN 1 ELSE 0 END) as today', [$today, $tomorrow])
            ->first();

        return [
            $this->channel(
                key: 'phone',
                label: 'Phone System',
                permission: 'view_phone_system',
                moduleSlug: 'phone-system',
                route: 'twilio.call',
                primary: ['value' => (int) ($calls->total ?? 0), 'label' => 'calls this month'],
                secondary: ['value' => (int) ($calls->missed ?? 0), 'label' => 'missed'],
                tertiary: ['value' => (int) ($calls->inbound ?? 0), 'label' => 'inbound'],
                extra: ((int) ($calls->outbound ?? 0)).' outbound',
            ),
            $this->channel(
                key: 'inbox',
                label: 'Inbox',
                permission: 'view_inbox',
                moduleSlug: 'inbox',
                route: 'inbox',
                primary: ['value' => (int) ($inbox->open_count ?? 0), 'label' => 'open threads'],
                secondary: ['value' => (int) ($inbox->unread ?? 0), 'label' => 'unread'],
                tertiary: ['value' => (int) ($inbox->today ?? 0), 'label' => 'active today'],
                extra: ((int) ($inbox->unassigned ?? 0)).' unassigned · '.((int) ($inbox->linked ?? 0)).' linked to leads',
            ),
            $this->messagingChannel($companyId, $month, $today, $tomorrow, 'viber', 'Viber', 'view_viber', 'viber', 'viber', ViberConversation::class),
            $this->messagingChannel($companyId, $month, $today, $tomorrow, 'facebook', 'Facebook', 'view_facebook', 'facebook', 'facebook', FacebookConversation::class),
            $this->messagingChannel($companyId, $month, $today, $tomorrow, 'sms', 'SMS', 'view_sms', 'sms', 'sms', SmsConversation::class),
            $this->messagingChannel($companyId, $month, $today, $tomorrow, 'whatsapp', 'WhatsApp', 'view_whatsapp', 'whatsapp', 'whatsapp', WhatsAppConversation::class),
        ];
    }

    /**
     * @param  class-string  $model
     * @return array<string, mixed>
     */
    protected function messagingChannel(
        int $companyId,
        string $month,
        string $today,
        string $tomorrow,
        string $key,
        string $label,
        string $permission,
        string $moduleSlug,
        string $route,
        string $model,
    ): array {
        $row = $model::query()
            ->where('company_id', $companyId)
            ->where('last_message_at', '>=', $month)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(unread_count), 0) as unread')
            ->selectRaw('SUM(CASE WHEN last_message_at >= ? AND last_message_at < ? THEN 1 ELSE 0 END) as today', [$today, $tomorrow])
            ->first();

        return $this->channel(
            key: $key,
            label: $label,
            permission: $permission,
            moduleSlug: $moduleSlug,
            route: $route,
            primary: ['value' => (int) ($row->total ?? 0), 'label' => 'conversations'],
            secondary: ['value' => (int) ($row->unread ?? 0), 'label' => 'unread'],
            tertiary: ['value' => (int) ($row->today ?? 0), 'label' => 'active today'],
        );
    }

    /**
     * @param  array{value: int, label: string}  $primary
     * @param  array{value: int, label: string}  $secondary
     * @param  array{value: int, label: string}  $tertiary
     * @return array<string, mixed>
     */
    protected function channel(
        string $key,
        string $label,
        string $permission,
        string $moduleSlug,
        string $route,
        array $primary,
        array $secondary,
        array $tertiary,
        ?string $extra = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'permission' => $permission,
            'module_slug' => $moduleSlug,
            'route' => $route,
            'primary' => $primary,
            'secondary' => $secondary,
            'tertiary' => $tertiary,
            'extra' => $extra,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function attention(int $companyId, Carbon $monthStart): Collection
    {
        $items = collect();

        InboxConversation::query()
            ->where('company_id', $companyId)
            ->whereNull('merged_into_id')
            ->where('folder', 'inbox')
            ->where('status', 'open')
            ->where('is_read', false)
            ->where('last_message_at', '>=', $monthStart)
            ->orderByDesc('last_message_at')
            ->limit(6)
            ->get(['subject', 'from_name', 'from_email', 'snippet', 'last_message_at'])
            ->each(function (InboxConversation $conversation) use ($items) {
                $items->push([
                    'channel' => 'Inbox',
                    'key' => 'inbox',
                    'title' => $conversation->subject ?: ($conversation->from_name ?: $conversation->from_email ?: 'Untitled thread'),
                    'subtitle' => $conversation->snippet ?: ($conversation->from_email ?: ''),
                    'at' => $conversation->last_message_at,
                    'route' => 'inbox',
                ]);
            });

        PhoneCallLog::query()
            ->where('company_id', $companyId)
            ->where('created_at', '>=', $monthStart)
            ->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled'])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get(['direction', 'from_number', 'to_number', 'status', 'started_at', 'created_at'])
            ->each(function (PhoneCallLog $log) use ($items) {
                $peer = str_contains(strtolower((string) $log->direction), 'outbound')
                    ? $log->to_number
                    : $log->from_number;
                $items->push([
                    'channel' => 'Phone',
                    'key' => 'phone',
                    'title' => $peer ?: 'Unknown number',
                    'subtitle' => ucfirst(str_replace('-', ' ', (string) $log->status)).' · '.($log->direction ?: 'call'),
                    'at' => $log->started_at ?? $log->created_at,
                    'route' => 'twilio.call',
                ]);
            });

        foreach ([
            ['model' => ViberConversation::class, 'channel' => 'Viber', 'key' => 'viber', 'route' => 'viber', 'columns' => ['name', 'phone', 'unread_count', 'last_message_preview', 'last_message_at'], 'name' => fn ($c) => $c->name ?: $c->phone],
            ['model' => FacebookConversation::class, 'channel' => 'Facebook', 'key' => 'facebook', 'route' => 'facebook', 'columns' => ['name', 'username', 'unread_count', 'last_message_preview', 'last_message_at'], 'name' => fn ($c) => $c->name ?: $c->username],
            ['model' => SmsConversation::class, 'channel' => 'SMS', 'key' => 'sms', 'route' => 'sms', 'columns' => ['name', 'peer_phone', 'unread_count', 'last_message_preview', 'last_message_at'], 'name' => fn ($c) => $c->name ?: $c->peer_phone],
            ['model' => WhatsAppConversation::class, 'channel' => 'WhatsApp', 'key' => 'whatsapp', 'route' => 'whatsapp', 'columns' => ['profile_name', 'name', 'phone', 'unread_count', 'last_message_preview', 'last_message_at'], 'name' => fn ($c) => $c->profile_name ?: $c->name ?: $c->phone],
        ] as $channel) {
            $channel['model']::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->where('last_message_at', '>=', $monthStart)
                ->orderByDesc('last_message_at')
                ->limit(4)
                ->get($channel['columns'])
                ->each(function ($conversation) use ($items, $channel) {
                    $items->push([
                        'channel' => $channel['channel'],
                        'key' => $channel['key'],
                        'title' => $channel['name']($conversation) ?: 'Conversation',
                        'subtitle' => $conversation->last_message_preview ?: ($conversation->unread_count.' unread'),
                        'at' => $conversation->last_message_at,
                        'route' => $channel['route'],
                    ]);
                });
        }

        return $items
            ->sortByDesc(fn ($item) => $item['at']?->timestamp ?? 0)
            ->take(8)
            ->values();
    }

    /**
     * @return array{
     *     period_label: string,
     *     leads: array<string, int|float>,
     *     pipeline: list<array{slug: string, label: string, count: int}>,
     *     sources: list<array{label: string, count: int}>,
     *     recentLeads: Collection<int, Lead>,
     *     recentActivity: Collection<int, array<string, mixed>>,
     *     channels: list<array<string, mixed>>,
     *     attention: Collection<int, array<string, mixed>>
     * }
     */
    protected function emptyPayload(string $periodLabel): array
    {
        $emptyMetric = fn (string $primary, string $secondary, string $tertiary, ?string $extra = null) => [
            'primary' => ['value' => 0, 'label' => $primary],
            'secondary' => ['value' => 0, 'label' => $secondary],
            'tertiary' => ['value' => 0, 'label' => $tertiary],
            'extra' => $extra,
        ];

        return [
            'period_label' => $periodLabel,
            'leads' => [
                'total' => 0,
                'new' => 0,
                'contacted' => 0,
                'qualified' => 0,
                'converted' => 0,
                'lost' => 0,
                'snoozed' => 0,
                'unassigned' => 0,
                'this_month' => 0,
                'last_month' => 0,
                'month_change' => 0,
                'conversion_rate' => 0.0,
            ],
            'pipeline' => [],
            'sources' => [],
            'recentLeads' => collect(),
            'recentActivity' => collect(),
            'channels' => [
                $this->channel('phone', 'Phone System', 'view_phone_system', 'phone-system', 'twilio.call', ...array_values($emptyMetric('calls this month', 'missed', 'inbound', '0 outbound'))),
                $this->channel('inbox', 'Inbox', 'view_inbox', 'inbox', 'inbox', ...array_values($emptyMetric('open threads', 'unread', 'active today', '0 unassigned · 0 linked to leads'))),
                $this->channel('viber', 'Viber', 'view_viber', 'viber', 'viber', ...array_values($emptyMetric('conversations', 'unread', 'active today'))),
                $this->channel('facebook', 'Facebook', 'view_facebook', 'facebook', 'facebook', ...array_values($emptyMetric('conversations', 'unread', 'active today'))),
                $this->channel('sms', 'SMS', 'view_sms', 'sms', 'sms', ...array_values($emptyMetric('conversations', 'unread', 'active today'))),
                $this->channel('whatsapp', 'WhatsApp', 'view_whatsapp', 'whatsapp', 'whatsapp', ...array_values($emptyMetric('conversations', 'unread', 'active today'))),
            ],
            'attention' => collect(),
        ];
    }
}
