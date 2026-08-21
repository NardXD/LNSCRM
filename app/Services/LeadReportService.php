<?php

namespace App\Services;

use App\Models\FacebookMessage;
use App\Models\InboxMessage;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\SmsMessage;
use App\Models\ViberMessage;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadReportService
{
    public function __construct(
        protected ContactConversationHistoryService $conversationHistory
    ) {}

    /**
     * Apply shared lead filters (list + reports).
     *
     * @param  array<string, mixed>|Request  $filters
     */
    public function filteredQuery(int $companyId, array|Request $filters): Builder
    {
        $filters = $this->normalizeFilters($filters);

        $query = Lead::query()->where('leads.company_id', $companyId);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('leads.name', 'like', "%{$search}%")
                    ->orWhere('leads.first_name', 'like', "%{$search}%")
                    ->orWhere('leads.last_name', 'like', "%{$search}%")
                    ->orWhere('leads.alt_first_name', 'like', "%{$search}%")
                    ->orWhere('leads.alt_last_name', 'like', "%{$search}%")
                    ->orWhere('leads.company_name', 'like', "%{$search}%")
                    ->orWhere('leads.city', 'like', "%{$search}%")
                    ->orWhere('leads.address', 'like', "%{$search}%")
                    ->orWhereHas('identities', function ($identity) use ($search) {
                        $identity->where('value', 'like', "%{$search}%")
                            ->orWhere('normalized_value', 'like', "%{$search}%");
                    })
                    ->orWhereHas('labels', function ($label) use ($search) {
                        $label->where('lead_labels.name', 'like', "%{$search}%");
                    });
            });
        }

        $statuses = $filters['statuses'] ?? [];
        $status = (string) ($filters['status'] ?? '');
        if ($statuses !== []) {
            $query->whereIn('leads.status', $statuses);
        } elseif ($status !== '' && $status !== 'all') {
            $query->where('leads.status', $status);
        } else {
            $query->where('leads.status', '!=', Lead::STATUS_ARCHIVED);
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source === '__none__') {
            $query->where(function ($q) {
                $q->whereNull('leads.source')->orWhere('leads.source', '');
            });
        } elseif ($source !== '') {
            $query->where('leads.source', $source);
        }

        foreach ($filters['label_ids'] as $labelId) {
            $query->whereHas('labels', fn ($label) => $label->where('lead_labels.id', $labelId));
        }

        $assignedTo = trim((string) ($filters['assigned_to'] ?? ''));
        if ($assignedTo === '__none__') {
            $query->whereNull('leads.assigned_to');
        } elseif ($assignedTo !== '' && ctype_digit($assignedTo)) {
            $query->where('leads.assigned_to', (int) $assignedTo);
        }

        $customerType = trim((string) ($filters['customer_type'] ?? ''));
        if ($customerType !== '' && $customerType !== 'all') {
            $query->where('leads.customer_type', $customerType);
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $query->whereDate('leads.created_at', '>=', $dateFrom);
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $query->whereDate('leads.created_at', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>|Request  $filters
     * @return array{
     *     totals: array{total: int, converted: int, lost: int, conversion_rate: float},
     *     by_status: list<array{label: string, count: int}>,
     *     by_source: list<array{label: string, count: int}>,
     *     by_label: list<array{label: string, color: string|null, count: int}>,
     *     by_assignee: list<array{label: string, count: int}>,
     *     preview: list<array<string, mixed>>,
     *     sources: list<string>
     * }
     */
    public function summary(int $companyId, array|Request $filters, int $previewLimit = 50): array
    {
        $base = $this->filteredQuery($companyId, $filters);

        $total = (clone $base)->count();
        $converted = (clone $base)->where('leads.status', 'converted')->count();
        $lost = (clone $base)->where('leads.status', 'lost')->count();
        $conversionRate = $total > 0 ? round(($converted / $total) * 100, 1) : 0.0;

        $byStatus = (clone $base)
            ->select([
                'leads.status',
                DB::raw('COUNT(*) as aggregate'),
            ])
            ->groupBy('leads.status')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->status,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $bySource = (clone $base)
            ->select([
                DB::raw("COALESCE(NULLIF(TRIM(leads.source), ''), 'Unspecified') as source_label"),
                DB::raw('COUNT(*) as aggregate'),
            ])
            ->groupBy('source_label')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->source_label,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $byLabel = (clone $base)
            ->join('lead_lead_label', 'leads.id', '=', 'lead_lead_label.lead_id')
            ->join('lead_labels', 'lead_labels.id', '=', 'lead_lead_label.lead_label_id')
            ->where('lead_labels.company_id', $companyId)
            ->select([
                'lead_labels.id',
                'lead_labels.name',
                'lead_labels.color',
                DB::raw('COUNT(DISTINCT leads.id) as aggregate'),
            ])
            ->groupBy('lead_labels.id', 'lead_labels.name', 'lead_labels.color')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->name,
                'color' => $row->color ? (string) $row->color : null,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $byAssignee = (clone $base)
            ->leftJoin('users', 'users.id', '=', 'leads.assigned_to')
            ->select([
                DB::raw("COALESCE(users.name, 'Unassigned') as assignee_label"),
                DB::raw('COUNT(*) as aggregate'),
            ])
            ->groupBy('assignee_label')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->assignee_label,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        $previewLeads = (clone $base)
            ->with(['identities', 'assignedUser:id,name', 'labels'])
            ->orderByDesc('updated_at')
            ->limit(max(1, min(100, $previewLimit)))
            ->get();

        return [
            'totals' => [
                'total' => $total,
                'converted' => $converted,
                'lost' => $lost,
                'conversion_rate' => $conversionRate,
            ],
            'by_status' => $byStatus,
            'by_source' => $bySource,
            'by_label' => $byLabel,
            'by_assignee' => $byAssignee,
            'preview' => $previewLeads->map(fn (Lead $lead) => $this->previewRow($lead))->all(),
            'sources' => Lead::query()
                ->where('company_id', $companyId)
                ->whereNotNull('source')
                ->where('source', '!=', '')
                ->distinct()
                ->orderBy('source')
                ->pluck('source')
                ->values()
                ->all(),
        ];
    }

    /**
     * Build detailed multi-sheet export data (leads, activity log, conversations).
     *
     * @param  array<string, mixed>|Request  $filters
     * @return array{
     *     leads: Collection<int, array<string, mixed>>,
     *     activities: Collection<int, array<string, mixed>>,
     *     conversations: Collection<int, array<string, mixed>>
     * }
     */
    public function exportWorkbook(int $companyId, array|Request $filters): array
    {
        $leads = $this->filteredQuery($companyId, $filters)
            ->with([
                'identities',
                'assignedUser:id,name',
                'labels',
                'leadNotes.user:id,name',
                'activities' => fn ($q) => $q->with('user:id,name')->reorder('created_at')->orderBy('id'),
            ])
            ->orderByDesc('created_at')
            ->get();

        $leadRows = collect();
        $activityRows = collect();
        $conversationRows = collect();

        foreach ($leads as $lead) {
            $conversations = $this->conversationRowsForLead($companyId, $lead);
            $activities = $this->activityRowsForLead($lead);

            $firstConversation = $conversations
                ->filter(fn (array $row) => ($row['started_at'] ?? '') !== '')
                ->sortBy('started_at')
                ->first();

            $leadRows->push($this->exportLeadRow($lead, $activities, $conversations, $firstConversation));
            $activityRows = $activityRows->merge($activities);
            $conversationRows = $conversationRows->merge($conversations);
        }

        return [
            'leads' => $leadRows->values(),
            'activities' => $activityRows->values(),
            'conversations' => $conversationRows->values(),
        ];
    }

    /**
     * @deprecated Use exportWorkbook() for detailed multi-sheet exports.
     *
     * @param  array<string, mixed>|Request  $filters
     */
    public function exportRows(int $companyId, array|Request $filters): Collection
    {
        return $this->exportWorkbook($companyId, $filters)['leads'];
    }

    /**
     * @param  array<string, mixed>|Request  $filters
     * @return array<string, mixed>
     */
    public function normalizeFilters(array|Request $filters): array
    {
        if ($filters instanceof Request) {
            $filters = $filters->all();
        }

        $labelIds = $filters['label_ids'] ?? $filters['label_id'] ?? [];
        if (! is_array($labelIds)) {
            $labelIds = $labelIds !== null && $labelIds !== ''
                ? preg_split('/\s*,\s*/', (string) $labelIds)
                : [];
        }
        $labelIds = array_values(array_unique(array_filter(array_map('intval', $labelIds))));

        $statuses = $filters['statuses'] ?? [];
        if (! is_array($statuses)) {
            $statuses = $statuses !== null && $statuses !== ''
                ? preg_split('/\s*,\s*/', (string) $statuses)
                : [];
        }
        $statuses = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $statuses
        ), fn ($s) => $s !== '' && in_array($s, Lead::STATUSES, true))));

        return [
            'search' => $filters['search'] ?? '',
            'status' => $filters['status'] ?? 'all',
            'statuses' => $statuses,
            'source' => $filters['source'] ?? '',
            'label_ids' => $labelIds,
            'assigned_to' => $filters['assigned_to'] ?? '',
            'customer_type' => $filters['customer_type'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function previewRow(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $this->displayName($lead),
            'company_name' => $lead->company_name,
            'status' => $lead->status,
            'source' => $lead->source,
            'customer_type' => $lead->customer_type,
            'city' => $lead->city,
            'assigned_user' => $lead->assignedUser ? [
                'id' => $lead->assignedUser->id,
                'name' => $lead->assignedUser->name,
            ] : null,
            'labels' => $lead->labels->map(fn (LeadLabel $label) => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
            ])->values()->all(),
            'phone' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_PHONE)?->value,
            'email' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_EMAIL)?->value,
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $activities
     * @param  Collection<int, array<string, mixed>>  $conversations
     * @param  array<string, mixed>|null  $firstConversation
     * @return array<string, mixed>
     */
    protected function exportLeadRow(
        Lead $lead,
        Collection $activities,
        Collection $conversations,
        ?array $firstConversation
    ): array {
        $phones = $lead->identities
            ->where('type', LeadIdentity::TYPE_PHONE)
            ->pluck('value')
            ->filter()
            ->values()
            ->implode(', ');
        $emails = $lead->identities
            ->where('type', LeadIdentity::TYPE_EMAIL)
            ->pluck('value')
            ->filter()
            ->values()
            ->implode(', ');
        $labels = $lead->labels->pluck('name')->filter()->values()->implode(', ');
        $notes = $lead->leadNotes
            ->map(function ($note) {
                $who = $note->user?->name ?: 'System';
                $when = $note->created_at?->format('Y-m-d H:i') ?? '';

                return trim('['.$when.'] '.$who.': '.trim((string) $note->note));
            })
            ->filter()
            ->values()
            ->implode("\n");

        $customerType = $lead->customer_type
            ? (Lead::CUSTOMER_TYPES[$lead->customer_type] ?? $lead->customer_type)
            : '';

        $timeline = $activities
            ->map(fn (array $row) => trim(
                '['.($row['occurred_at'] ?? '').'] '
                .($row['actor'] ?? 'System').' — '
                .($row['action'] ?? '').': '
                .($row['summary'] ?? '')
            ))
            ->filter()
            ->values()
            ->implode("\n");

        return [
            'id' => $lead->id,
            'name' => $this->displayName($lead),
            'company_name' => $lead->company_name ?? '',
            'status' => $lead->status,
            'source' => $lead->source ?: 'Unspecified',
            'labels' => $labels,
            'assignee' => $lead->assignedUser?->name ?? 'Unassigned',
            'customer_type' => $customerType,
            'city' => $lead->city ?? '',
            'address' => $lead->address ?? '',
            'phones' => $phones,
            'emails' => $emails,
            'facebook' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_FACEBOOK)?->value ?? '',
            'instagram' => $lead->identities->firstWhere('type', LeadIdentity::TYPE_INSTAGRAM)?->value ?? '',
            'created_at' => $lead->created_at?->format('Y-m-d H:i') ?? '',
            'updated_at' => $lead->updated_at?->format('Y-m-d H:i') ?? '',
            'first_conversation_at' => $firstConversation['started_at'] ?? '',
            'first_conversation_channel' => $firstConversation['channel'] ?? '',
            'first_conversation_message' => $firstConversation['first_message'] ?? '',
            'conversation_channels' => $conversations->pluck('channel')->unique()->filter()->values()->implode(', '),
            'activity_count' => $activities->count(),
            'activity_timeline' => $timeline,
            'notes' => $notes,
            'crm_url' => url('/leads?lead='.$lead->id),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function activityRowsForLead(Lead $lead): Collection
    {
        $leadName = $this->displayName($lead);

        return $lead->activities->map(function (LeadActivity $activity) use ($lead, $leadName) {
            return [
                'lead_id' => $lead->id,
                'lead_name' => $leadName,
                'occurred_at' => $activity->created_at?->format('Y-m-d H:i:s') ?? '',
                'actor' => $activity->user?->name ?: 'System',
                'action' => $this->humanAction((string) $activity->action),
                'summary' => (string) ($activity->summary ?? ''),
                'details' => $this->formatActivityDetails($activity->meta ?? []),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function conversationRowsForLead(int $companyId, Lead $lead): Collection
    {
        $history = $this->conversationHistory->history(
            $companyId,
            null,
            null,
            50,
            null,
            (int) $lead->id
        );

        $threads = collect($history['threads'] ?? []);
        $leadName = $this->displayName($lead);

        return $threads->map(function (array $thread) use ($lead, $leadName) {
            $channel = (string) ($thread['channel'] ?? '');
            $conversationId = (int) ($thread['conversation_id'] ?? 0);
            $first = $conversationId > 0
                ? $this->firstMessageForThread($channel, $conversationId)
                : null;

            return [
                'lead_id' => $lead->id,
                'lead_name' => $leadName,
                'channel' => $thread['label'] ?? ucfirst($channel),
                'thread_title' => (string) ($thread['title'] ?? ''),
                'started_at' => $first['at'] ?? '',
                'direction' => $first['direction'] ?? '',
                'first_message' => $first['preview'] ?? '',
                'last_preview' => (string) ($thread['preview'] ?? ''),
                'deep_link' => (string) ($thread['deep_link'] ?? ''),
            ];
        })->values();
    }

    /**
     * @return array{at: string, direction: string, preview: string}|null
     */
    protected function firstMessageForThread(string $channel, int $conversationId): ?array
    {
        return match ($channel) {
            'whatsapp' => $this->mapFirstMessage(
                WhatsAppMessage::query()
                    ->where('whatsapp_conversation_id', $conversationId)
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->first(),
                fn (WhatsAppMessage $m) => [
                    'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                    'direction' => (string) ($m->direction ?? ''),
                    'preview' => (string) ($m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : '')),
                ]
            ),
            'viber' => $this->mapFirstMessage(
                ViberMessage::query()
                    ->where('viber_conversation_id', $conversationId)
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->first(),
                fn (ViberMessage $m) => [
                    'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                    'direction' => (string) ($m->direction ?? ''),
                    'preview' => (string) ($m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : '')),
                ]
            ),
            'sms' => $this->mapFirstMessage(
                SmsMessage::query()
                    ->where('sms_conversation_id', $conversationId)
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->first(),
                fn (SmsMessage $m) => [
                    'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                    'direction' => (string) ($m->direction ?? ''),
                    'preview' => (string) ($m->body ?? ''),
                ]
            ),
            'inbox' => $this->mapFirstMessage(
                InboxMessage::query()
                    ->where('inbox_conversation_id', $conversationId)
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->first(),
                function (InboxMessage $m) {
                    $body = trim((string) ($m->body_text ?: ''));
                    if ($body === '') {
                        $body = trim(html_entity_decode(strip_tags((string) ($m->body_html ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    }
                    $preview = trim(($m->subject ? $m->subject.' — ' : '').$body);

                    return [
                        'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                        'direction' => (string) ($m->direction ?? ''),
                        'preview' => mb_substr(preg_replace('/\s+/u', ' ', $preview) ?? $preview, 0, 500),
                    ];
                }
            ),
            'facebook' => $this->mapFirstMessage(
                FacebookMessage::query()
                    ->where('facebook_conversation_id', $conversationId)
                    ->orderBy('sent_at')
                    ->orderBy('id')
                    ->first(),
                fn (FacebookMessage $m) => [
                    'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                    'direction' => (string) ($m->direction ?? ''),
                    'preview' => (string) ($m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : '')),
                ]
            ),
            default => null,
        };
    }

    /**
     * @template T of object
     *
     * @param  T|null  $message
     * @param  callable(T): array{at: string, direction: string, preview: string}  $mapper
     * @return array{at: string, direction: string, preview: string}|null
     */
    protected function mapFirstMessage(?object $message, callable $mapper): ?array
    {
        if (! $message) {
            return null;
        }

        return $mapper($message);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function formatActivityDetails(array $meta): string
    {
        if ($meta === []) {
            return '';
        }

        $parts = [];
        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif ($value === null) {
                continue;
            }
            $parts[] = $key.': '.$value;
        }

        return implode('; ', $parts);
    }

    protected function humanAction(string $action): string
    {
        return match ($action) {
            LeadActivity::CREATED => 'Created',
            LeadActivity::ASSIGNED => 'Assigned',
            LeadActivity::UNASSIGNED => 'Unassigned',
            LeadActivity::REASSIGNED => 'Reassigned',
            LeadActivity::STATUS_CHANGED => 'Status changed',
            LeadActivity::UPDATED => 'Updated',
            LeadActivity::IDENTITY_ADDED => 'Contact added',
            LeadActivity::IDENTITY_REMOVED => 'Contact removed',
            LeadActivity::LABEL_ADDED => 'Label added',
            LeadActivity::LABEL_REMOVED => 'Label removed',
            LeadActivity::NOTE_ADDED => 'Note added',
            LeadActivity::NOTE_REMOVED => 'Note removed',
            LeadActivity::SNOOZED => 'Snoozed',
            LeadActivity::REOPENED => 'Reopened',
            LeadActivity::INBOX_ATTACHED => 'Inbox attached',
            LeadActivity::INBOX_DETACHED => 'Inbox detached',
            default => str_replace('_', ' ', ucfirst($action)),
        };
    }

    protected function displayName(Lead $lead): string
    {
        $parts = trim(implode(' ', array_filter([
            $lead->title,
            $lead->first_name,
            $lead->last_name,
        ])));

        if ($parts !== '') {
            return $parts;
        }

        return (string) ($lead->name ?: 'Untitled lead');
    }
}
