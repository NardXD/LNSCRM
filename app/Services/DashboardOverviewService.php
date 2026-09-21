<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadStatus;
use App\Models\PhoneCallLog;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Models\ViberConversation;
use App\Models\ViberMessage;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardOverviewService
{
    /**
     * @return array{
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
        if (! $companyId) {
            return $this->emptyPayload();
        }

        $today = Carbon::today();
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $activeLeads = Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED);

        $total = (clone $activeLeads)->count();
        $converted = (clone $activeLeads)->where('status', 'converted')->count();
        $thisMonth = (clone $activeLeads)->where('created_at', '>=', $thisMonthStart)->count();
        $lastMonth = Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        $monthChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : ($thisMonth > 0 ? 100 : 0);

        $leads = [
            'total' => $total,
            'new' => (clone $activeLeads)->where('status', 'new')->count(),
            'contacted' => (clone $activeLeads)->where('status', 'contacted')->count(),
            'qualified' => (clone $activeLeads)->where('status', 'qualified')->count(),
            'converted' => $converted,
            'lost' => (clone $activeLeads)->where('status', 'lost')->count(),
            'snoozed' => (clone $activeLeads)->where('status', Lead::STATUS_SNOOZED)->count(),
            'unassigned' => (clone $activeLeads)->whereNull('assigned_to')->count(),
            'this_month' => $thisMonth,
            'month_change' => $monthChange,
            'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0.0,
        ];

        return [
            'leads' => $leads,
            'pipeline' => $this->pipeline($companyId),
            'sources' => $this->topSources($companyId),
            'recentLeads' => $this->recentLeads($companyId),
            'recentActivity' => $this->recentActivity($companyId),
            'channels' => $this->channels($companyId, $today),
            'attention' => $this->attention($companyId),
        ];
    }

    /**
     * @return list<array{slug: string, label: string, count: int}>
     */
    protected function pipeline(int $companyId): array
    {
        $statusNames = LeadStatus::forCompany($companyId)->pluck('name', 'slug');
        $counts = Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->select(['status', DB::raw('COUNT(*) as aggregate')])
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $rows = [];
        foreach ($statusNames as $slug => $name) {
            if ($slug === Lead::STATUS_ARCHIVED) {
                continue;
            }
            $rows[] = [
                'slug' => (string) $slug,
                'label' => (string) $name,
                'count' => (int) ($counts[$slug] ?? 0),
            ];
        }

        foreach ($counts as $slug => $count) {
            if ($slug === Lead::STATUS_ARCHIVED || $statusNames->has($slug)) {
                continue;
            }
            $rows[] = [
                'slug' => (string) $slug,
                'label' => ucfirst((string) $slug),
                'count' => (int) $count,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    protected function topSources(int $companyId): array
    {
        return Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
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
    protected function recentLeads(int $companyId): Collection
    {
        return Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->with('assignedUser:id,name')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function recentActivity(int $companyId): Collection
    {
        return LeadActivity::query()
            ->whereHas('lead', fn ($lead) => $lead->where('company_id', $companyId))
            ->with(['lead:id,name,first_name,last_name,status', 'user:id,name'])
            ->orderByDesc('created_at')
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
    protected function channels(int $companyId, Carbon $today): array
    {
        $callsToday = PhoneCallLog::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', $today);
        $missedToday = (clone $callsToday)
            ->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled'])
            ->count();
        $inboundToday = (clone $callsToday)
            ->where('direction', 'like', '%inbound%')
            ->count();
        $outboundToday = (clone $callsToday)
            ->where('direction', 'like', '%outbound%')
            ->count();

        $inboxBase = InboxConversation::query()
            ->where('company_id', $companyId)
            ->whereNull('merged_into_id');
        $inboxOpen = (clone $inboxBase)->where('folder', 'inbox')->where('status', 'open');
        $inboxUnread = (clone $inboxOpen)->where('is_read', false)->count();
        $inboxUnassigned = (clone $inboxOpen)->whereNull('assigned_to')->count();
        $inboxLinked = (clone $inboxOpen)->whereNotNull('lead_id')->count();
        $inboxToday = InboxMessage::query()
            ->whereHas('conversation', function ($conversation) use ($companyId) {
                $conversation->where('company_id', $companyId)->whereNull('merged_into_id');
            })
            ->where(function ($query) use ($today) {
                $query->whereDate('sent_at', $today)->orWhere(function ($fallback) use ($today) {
                    $fallback->whereNull('sent_at')->whereDate('created_at', $today);
                });
            })
            ->count();

        return [
            $this->channel(
                key: 'phone',
                label: 'Phone System',
                permission: 'view_phone_system',
                moduleSlug: 'phone-system',
                route: 'twilio.call',
                primary: ['value' => (clone $callsToday)->count(), 'label' => 'calls today'],
                secondary: ['value' => $missedToday, 'label' => 'missed'],
                tertiary: ['value' => $inboundToday, 'label' => 'inbound'],
                extra: $outboundToday.' outbound',
            ),
            $this->channel(
                key: 'inbox',
                label: 'Inbox',
                permission: 'view_inbox',
                moduleSlug: 'inbox',
                route: 'inbox',
                primary: ['value' => (clone $inboxOpen)->count(), 'label' => 'open threads'],
                secondary: ['value' => $inboxUnread, 'label' => 'unread'],
                tertiary: ['value' => $inboxToday, 'label' => 'messages today'],
                extra: $inboxUnassigned.' unassigned · '.$inboxLinked.' linked to leads',
            ),
            $this->messagingChannel(
                companyId: $companyId,
                today: $today,
                key: 'viber',
                label: 'Viber',
                permission: 'view_viber',
                moduleSlug: 'viber',
                route: 'viber',
                conversation: ViberConversation::query(),
                message: ViberMessage::query(),
            ),
            $this->messagingChannel(
                companyId: $companyId,
                today: $today,
                key: 'facebook',
                label: 'Facebook',
                permission: 'view_facebook',
                moduleSlug: 'facebook',
                route: 'facebook',
                conversation: FacebookConversation::query(),
                message: FacebookMessage::query(),
            ),
            $this->messagingChannel(
                companyId: $companyId,
                today: $today,
                key: 'sms',
                label: 'SMS',
                permission: 'view_sms',
                moduleSlug: 'sms',
                route: 'sms',
                conversation: SmsConversation::query(),
                message: SmsMessage::query(),
            ),
            $this->messagingChannel(
                companyId: $companyId,
                today: $today,
                key: 'whatsapp',
                label: 'WhatsApp',
                permission: 'view_whatsapp',
                moduleSlug: 'whatsapp',
                route: 'whatsapp',
                conversation: WhatsAppConversation::query(),
                message: WhatsAppMessage::query(),
            ),
        ];
    }

    /**
     * @param  Builder<Model>  $conversation
     * @param  Builder<Model>  $message
     * @return array<string, mixed>
     */
    protected function messagingChannel(
        int $companyId,
        Carbon $today,
        string $key,
        string $label,
        string $permission,
        string $moduleSlug,
        string $route,
        $conversation,
        $message,
    ): array {
        $conversations = (clone $conversation)->where('company_id', $companyId);
        $unread = (int) (clone $conversations)->sum('unread_count');
        $todayCount = (clone $message)
            ->where('company_id', $companyId)
            ->where(function ($query) use ($today) {
                $query->whereDate('sent_at', $today)->orWhere(function ($fallback) use ($today) {
                    $fallback->whereNull('sent_at')->whereDate('created_at', $today);
                });
            })
            ->count();

        return $this->channel(
            key: $key,
            label: $label,
            permission: $permission,
            moduleSlug: $moduleSlug,
            route: $route,
            primary: ['value' => (clone $conversations)->count(), 'label' => 'conversations'],
            secondary: ['value' => $unread, 'label' => 'unread'],
            tertiary: ['value' => $todayCount, 'label' => 'messages today'],
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
    protected function attention(int $companyId): Collection
    {
        $items = collect();

        InboxConversation::query()
            ->where('company_id', $companyId)
            ->whereNull('merged_into_id')
            ->where('folder', 'inbox')
            ->where('status', 'open')
            ->where('is_read', false)
            ->orderByDesc('last_message_at')
            ->limit(6)
            ->get()
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
            ->whereIn('status', ['no-answer', 'busy', 'failed', 'canceled'])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
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
            ['model' => ViberConversation::class, 'channel' => 'Viber', 'key' => 'viber', 'route' => 'viber', 'name' => fn ($c) => $c->name ?: $c->phone],
            ['model' => FacebookConversation::class, 'channel' => 'Facebook', 'key' => 'facebook', 'route' => 'facebook', 'name' => fn ($c) => $c->name ?: $c->username],
            ['model' => SmsConversation::class, 'channel' => 'SMS', 'key' => 'sms', 'route' => 'sms', 'name' => fn ($c) => $c->name ?: $c->peer_phone],
            ['model' => WhatsAppConversation::class, 'channel' => 'WhatsApp', 'key' => 'whatsapp', 'route' => 'whatsapp', 'name' => fn ($c) => $c->profile_name ?: $c->name ?: $c->phone],
        ] as $channel) {
            $channel['model']::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->orderByDesc('last_message_at')
                ->limit(4)
                ->get()
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
     *     leads: array<string, int|float>,
     *     pipeline: list<array{slug: string, label: string, count: int}>,
     *     sources: list<array{label: string, count: int}>,
     *     recentLeads: Collection<int, Lead>,
     *     recentActivity: Collection<int, array<string, mixed>>,
     *     channels: list<array<string, mixed>>,
     *     attention: Collection<int, array<string, mixed>>
     * }
     */
    protected function emptyPayload(): array
    {
        $today = Carbon::today();

        return [
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
                'month_change' => 0,
                'conversion_rate' => 0.0,
            ],
            'pipeline' => [],
            'sources' => [],
            'recentLeads' => collect(),
            'recentActivity' => collect(),
            'channels' => $this->channels(0, $today),
            'attention' => collect(),
        ];
    }
}
