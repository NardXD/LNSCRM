<?php

namespace App\Services;

use App\Models\FacebookMessage;
use App\Models\InboxMessage;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadStatus;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\ViberMessage;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadReportService
{
    /** @var array<int, string> */
    protected array $labelNameById = [];

    /** @var array<int, string> */
    protected array $userNameById = [];

    public function __construct(
        protected LeadConnectedThreadService $connectedThreads,
        protected LeadFollowUpDayService $followUpDays
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

        $this->followUpDays->applyToQuery($query, $companyId, $filters);
        if ($this->followUpDays->shouldExcludeClosed($filters)) {
            $this->followUpDays->excludeClosed($query);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>|Request  $filters
     * @return array{days: list<int>, plus_min: int, labels: list<array{day: int, id: int, name: string, color: string|null}>, counts: array<string, int>}
     */
    public function followUpCounts(int $companyId, array|Request $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $filters['follow_up_day'] = 0;
        $filters['follow_up_day_min'] = 0;
        $filters['follow_up_counts'] = true;

        $base = $this->filteredQuery($companyId, $filters);
        $config = $this->followUpDays->configForCompany($companyId);
        $counts = [];

        foreach ($config['days'] as $day) {
            $counts[(string) $day] = (clone $base)
                ->tap(fn ($query) => $this->followUpDays->applyToQuery($query, $companyId, [
                    'follow_up_day' => $day,
                ]))
                ->count();
        }

        return [
            'days' => $config['days'],
            'plus_min' => $config['plus_min'],
            'labels' => $config['labels'],
            'counts' => $counts,
        ];
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

        $statusNames = LeadStatus::forCompany($companyId)->pluck('name', 'slug');
        $byStatus = (clone $base)
            ->select([
                'leads.status',
                DB::raw('COUNT(*) as aggregate'),
            ])
            ->groupBy('leads.status')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row) => [
                'label' => (string) ($statusNames[$row->status] ?? $row->status),
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
    public function exportWorkbook(int $companyId, array|Request $filters, string $type = 'leads'): array
    {
        $type = in_array($type, ['leads', 'activities', 'conversations'], true) ? $type : 'leads';

        $this->labelNameById = LeadLabel::query()
            ->where('company_id', $companyId)
            ->pluck('name', 'id')
            ->all();

        $with = ['identities', 'assignedUser:id,name', 'labels'];
        if ($type === 'leads' || $type === 'activities') {
            $with['activities'] = fn ($q) => $q->with('user:id,name')->reorder('created_at')->orderBy('id');
        }
        if ($type === 'leads') {
            $with[] = 'leadNotes.user:id,name';
        }

        $leads = $this->filteredQuery($companyId, $filters)
            ->with($with)
            ->orderByDesc('created_at')
            ->get();

        if ($type === 'leads' || $type === 'activities') {
            $this->userNameById = $this->loadUserNamesFromActivities($leads);
        }

        $leadRows = collect();
        $activityRows = collect();
        $conversationRows = collect();

        $threadsByLead = [];
        $firstMessages = [];
        if ($type === 'leads' || $type === 'conversations') {
            $threadsByLead = $this->connectedThreads->allThreadsForLeads($companyId, $leads);
            $firstMessages = $this->loadFirstMessagesBatch($this->collectConversationIdsByChannel($threadsByLead));
        }

        foreach ($leads as $lead) {
            $leadId = (int) $lead->id;
            $threads = collect($threadsByLead[$leadId] ?? []);
            $activities = ($type === 'leads' || $type === 'activities')
                ? $this->activityRowsForLead($lead)
                : collect();

            if ($type === 'activities') {
                $activityRows = $activityRows->merge($activities);

                continue;
            }

            $conversationExportRows = ($type === 'leads' || $type === 'conversations')
                ? $this->conversationRowsFromThreads($lead, $threads, $firstMessages)
                : collect();

            $firstConversation = $conversationExportRows
                ->filter(fn (array $row) => ($row['started_at'] ?? '') !== '')
                ->sortBy('started_at')
                ->first();

            if ($type === 'leads') {
                $leadRows->push($this->exportLeadRow($lead, $activities, $conversationExportRows, $firstConversation));
            }

            if ($type === 'conversations') {
                $conversationRows = $conversationRows->merge($conversationExportRows);
            }
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
        ), fn ($s) => $s !== '')));

        $followUpDay = (int) ($filters['follow_up_day'] ?? 0);
        $followUpDayMin = (int) ($filters['follow_up_day_min'] ?? 0);

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
            'follow_up_day' => $followUpDay > 0 ? $followUpDay : 0,
            'follow_up_day_min' => $followUpDayMin > 0 ? $followUpDayMin : 0,
            'follow_up_counts' => (bool) ($filters['follow_up_counts'] ?? false),
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
        $labelsOnLead = [];
        $statusHistory = [];
        $assigneeHistory = [];
        $userHistory = [];
        $detailsHistory = [];

        $activities = $lead->activities
            ->sortBy(fn (LeadActivity $activity) => [
                $activity->created_at?->timestamp ?? 0,
                $activity->id,
            ])
            ->values();

        return $activities->map(function (LeadActivity $activity) use ($lead, $leadName, &$labelsOnLead, &$statusHistory, &$assigneeHistory, &$userHistory, &$detailsHistory) {
            $meta = is_array($activity->meta) ? $activity->meta : [];
            $action = (string) $activity->action;
            $activityLabels = $this->resolveActivityLabels($meta);

            if ($action === LeadActivity::LABEL_ADDED) {
                foreach ($activityLabels as $activityLabel) {
                    $this->appendUnique($labelsOnLead, $activityLabel);
                }
            } elseif ($action === LeadActivity::LABEL_REMOVED) {
                foreach ($activityLabels as $activityLabel) {
                    $labelsOnLead = array_values(array_filter(
                        $labelsOnLead,
                        fn (string $name) => strcasecmp($name, $activityLabel) !== 0
                    ));
                }
            }

            if ($action === LeadActivity::STATUS_CHANGED) {
                $toStatus = $this->humanStatus((string) ($meta['to'] ?? ''), (int) $lead->company_id);
                if ($toStatus !== '') {
                    $this->appendUnique($statusHistory, $toStatus);
                }
            }

            if (in_array($action, [LeadActivity::ASSIGNED, LeadActivity::REASSIGNED, LeadActivity::UNASSIGNED], true)) {
                $fromUser = $this->resolveActivityUserName($meta, 'from');
                $toUser = $this->resolveActivityUserName($meta, 'to');

                if ($fromUser !== '' && in_array($action, [LeadActivity::REASSIGNED, LeadActivity::UNASSIGNED], true)) {
                    $this->appendUnique($userHistory, $fromUser);
                }
                if ($toUser !== '' && in_array($action, [LeadActivity::ASSIGNED, LeadActivity::REASSIGNED], true)) {
                    $this->appendUnique($assigneeHistory, $toUser);
                }
            }

            if ($action === LeadActivity::CREATED) {
                $assignee = $this->resolveActivityUserName($meta, 'assigned');
                if ($assignee !== '') {
                    $this->appendUnique($assigneeHistory, $assignee);
                }
            }

            $detail = $this->formatActivityDetails($meta, $action);
            $addedDetail = $detail !== '';
            if ($addedDetail) {
                $detailsHistory[] = $detail;
            }

            return [
                'lead_id' => $lead->id,
                'lead_name' => $leadName,
                'occurred_at' => $activity->created_at?->format('Y-m-d H:i:s') ?? '',
                'actor' => $activity->user?->name ?: 'System',
                'action' => $this->humanAction($action),
                'summary' => (string) ($activity->summary ?? ''),
                'label' => $action === LeadActivity::LABEL_ADDED
                    ? implode(', ', $labelsOnLead)
                    : '',
                'user' => $this->isAssignmentAction($action)
                    ? implode(', ', $userHistory)
                    : '',
                'status' => $action === LeadActivity::STATUS_CHANGED
                    ? implode(', ', $statusHistory)
                    : '',
                'assignee' => $this->isAssigneeAction($action)
                    ? implode(', ', $assigneeHistory)
                    : '',
                'details' => $addedDetail
                    ? implode('; ', $detailsHistory)
                    : '',
            ];
        })->values();
    }

    /**
     * @param  list<string>  $list
     */
    protected function appendUnique(array &$list, string $value): void
    {
        if ($value === '' || in_array($value, $list, true)) {
            return;
        }

        $list[] = $value;
    }

    protected function isAssignmentAction(string $action): bool
    {
        return in_array($action, [LeadActivity::ASSIGNED, LeadActivity::REASSIGNED, LeadActivity::UNASSIGNED], true);
    }

    protected function isAssigneeAction(string $action): bool
    {
        return $action === LeadActivity::CREATED
            || in_array($action, [LeadActivity::ASSIGNED, LeadActivity::REASSIGNED], true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveActivityUserName(array $meta, string $which): string
    {
        return match ($which) {
            'from' => $this->resolveNamedUser($meta, 'from_user_name', 'from_user_id'),
            'to' => $this->resolveNamedUser($meta, 'to_user_name', 'to_user_id'),
            'assigned' => $this->resolveNamedUser($meta, null, 'assigned_to'),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveNamedUser(array $meta, ?string $nameKey, string $idKey): string
    {
        if ($nameKey !== null) {
            $name = trim((string) ($meta[$nameKey] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        if (! empty($meta[$idKey])) {
            return (string) ($this->userNameById[(int) $meta[$idKey]] ?? '');
        }

        return '';
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @return array<int, string>
     */
    protected function loadUserNamesFromActivities(Collection $leads): array
    {
        $userIds = [];
        foreach ($leads as $lead) {
            foreach ($lead->activities as $activity) {
                $meta = is_array($activity->meta) ? $activity->meta : [];
                foreach (['from_user_id', 'to_user_id', 'assigned_to'] as $key) {
                    if (! empty($meta[$key])) {
                        $userIds[] = (int) $meta[$key];
                    }
                }
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    protected function resolveActivityLabels(array $meta): array
    {
        $labels = [];

        if (isset($meta['labels']) && is_array($meta['labels'])) {
            foreach ($meta['labels'] as $label) {
                if (is_array($label)) {
                    $name = trim((string) ($label['name'] ?? ''));
                    if ($name === '' && ! empty($label['label_id'])) {
                        $name = (string) ($this->labelNameById[(int) $label['label_id']] ?? '');
                    }
                    if ($name === '' && ! empty($label['id'])) {
                        $name = (string) ($this->labelNameById[(int) $label['id']] ?? '');
                    }
                } else {
                    $name = trim((string) $label);
                }

                if ($name !== '') {
                    $labels[] = $name;
                }
            }
        }

        if ($labels === []) {
            $single = $this->resolveActivityLabel($meta);
            if ($single !== '') {
                $labels[] = $single;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function resolveActivityLabel(array $meta): string
    {
        $label = trim((string) ($meta['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $labelId = (int) ($meta['label_id'] ?? 0);
        if ($labelId <= 0) {
            return '';
        }

        return (string) ($this->labelNameById[$labelId] ?? '');
    }

    protected function humanStatus(string $status, ?int $companyId = null): string
    {
        $status = trim($status);
        if ($status === '') {
            return '';
        }
        if ($companyId) {
            return LeadStatus::nameFor($companyId, $status);
        }

        return ucfirst($status);
    }

    /**
     * @param  array<int, list<array{channel: string, label: string, conversation_id: int, title: string, preview: string, deep_link: string, last_at: ?string}>>  $threadsByLead
     * @return array<string, list<int>>
     */
    protected function collectConversationIdsByChannel(array $threadsByLead): array
    {
        $idsByChannel = [
            'whatsapp' => [],
            'viber' => [],
            'sms' => [],
            'inbox' => [],
            'facebook' => [],
            'instagram' => [],
        ];

        foreach ($threadsByLead as $threads) {
            foreach ($threads as $thread) {
                $channel = (string) ($thread['channel'] ?? '');
                $conversationId = (int) ($thread['conversation_id'] ?? 0);
                if ($conversationId <= 0 || ! isset($idsByChannel[$channel])) {
                    continue;
                }
                $idsByChannel[$channel][] = $conversationId;
            }
        }

        foreach ($idsByChannel as $channel => $ids) {
            $idsByChannel[$channel] = array_values(array_unique($ids));
        }

        return $idsByChannel;
    }

    /**
     * @param  array<string, list<int>>  $idsByChannel
     * @return array<string, array{at: string, direction: string, preview: string}>
     */
    protected function loadFirstMessagesBatch(array $idsByChannel): array
    {
        $firstMessages = [];

        if ($idsByChannel['whatsapp'] !== []) {
            foreach ($this->loadWhatsAppFirstMessages($idsByChannel['whatsapp']) as $conversationId => $message) {
                $firstMessages['whatsapp:'.$conversationId] = $message;
            }
        }
        if ($idsByChannel['viber'] !== []) {
            foreach ($this->loadViberFirstMessages($idsByChannel['viber']) as $conversationId => $message) {
                $firstMessages['viber:'.$conversationId] = $message;
            }
        }
        if ($idsByChannel['sms'] !== []) {
            foreach ($this->loadSmsFirstMessages($idsByChannel['sms']) as $conversationId => $message) {
                $firstMessages['sms:'.$conversationId] = $message;
            }
        }
        if ($idsByChannel['inbox'] !== []) {
            foreach ($this->loadInboxFirstMessages($idsByChannel['inbox']) as $conversationId => $message) {
                $firstMessages['inbox:'.$conversationId] = $message;
            }
        }
        $facebookIds = array_values(array_unique(array_merge(
            $idsByChannel['facebook'],
            $idsByChannel['instagram']
        )));
        if ($facebookIds !== []) {
            foreach ($this->loadFacebookFirstMessages($facebookIds) as $conversationId => $message) {
                $firstMessages['facebook:'.$conversationId] = $message;
                $firstMessages['instagram:'.$conversationId] = $message;
            }
        }

        return $firstMessages;
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, array{at: string, direction: string, preview: string}>
     */
    protected function loadWhatsAppFirstMessages(array $conversationIds): array
    {
        return $this->loadFirstMessagesByConversation(
            WhatsAppMessage::query(),
            'whatsapp_conversation_id',
            $conversationIds,
            fn (WhatsAppMessage $m) => [
                'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                'direction' => (string) ($m->direction ?? ''),
                'preview' => (string) ($m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : '')),
            ]
        );
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, array{at: string, direction: string, preview: string}>
     */
    protected function loadViberFirstMessages(array $conversationIds): array
    {
        return $this->loadFirstMessagesByConversation(
            ViberMessage::query(),
            'viber_conversation_id',
            $conversationIds,
            fn (ViberMessage $m) => [
                'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                'direction' => (string) ($m->direction ?? ''),
                'preview' => (string) ($m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : '')),
            ]
        );
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, array{at: string, direction: string, preview: string}>
     */
    protected function loadSmsFirstMessages(array $conversationIds): array
    {
        return $this->loadFirstMessagesByConversation(
            SmsMessage::query(),
            'sms_conversation_id',
            $conversationIds,
            fn (SmsMessage $m) => [
                'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                'direction' => (string) ($m->direction ?? ''),
                'preview' => (string) ($m->body ?? ''),
            ]
        );
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, array{at: string, direction: string, preview: string}>
     */
    protected function loadInboxFirstMessages(array $conversationIds): array
    {
        return $this->loadFirstMessagesByConversation(
            InboxMessage::query(),
            'inbox_conversation_id',
            $conversationIds,
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
        );
    }

    /**
     * @param  list<int>  $conversationIds
     * @return array<int, array{at: string, direction: string, preview: string}>
     */
    protected function loadFacebookFirstMessages(array $conversationIds): array
    {
        return $this->loadFirstMessagesByConversation(
            FacebookMessage::query(),
            'facebook_conversation_id',
            $conversationIds,
            fn (FacebookMessage $m) => [
                'at' => ($m->sent_at ?? $m->created_at)?->format('Y-m-d H:i:s') ?? '',
                'direction' => (string) ($m->direction ?? ''),
                'preview' => (string) ($m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : '')),
            ]
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<int>  $conversationIds
     * @param  callable(object): array{at: string, direction: string, preview: string}  $mapper
     * @return array<int, array{at: string, direction: string, preview: string}>
     */
    protected function loadFirstMessagesByConversation($query, string $conversationColumn, array $conversationIds, callable $mapper): array
    {
        if ($conversationIds === []) {
            return [];
        }

        $table = $query->getModel()->getTable();
        $sub = DB::table($table)
            ->select($conversationColumn, DB::raw('MIN(COALESCE(sent_at, created_at)) as first_at'))
            ->whereIn($conversationColumn, $conversationIds)
            ->groupBy($conversationColumn);

        $messages = (clone $query)
            ->joinSub($sub, 'first_msg', function ($join) use ($table, $conversationColumn) {
                $join->on($table.'.'.$conversationColumn, '=', 'first_msg.'.$conversationColumn)
                    ->whereRaw('COALESCE('.$table.'.sent_at, '.$table.'.created_at) = first_msg.first_at');
            })
            ->orderBy($table.'.id')
            ->get();

        $first = [];
        foreach ($messages as $message) {
            $conversationId = (int) $message->{$conversationColumn};
            if (isset($first[$conversationId])) {
                continue;
            }
            $first[$conversationId] = $mapper($message);
        }

        return $first;
    }

    /**
     * @param  Collection<int, array{channel: string, label: string, conversation_id: int, title: string, preview: string, deep_link: string, last_at: ?string}>  $threads
     * @param  array<string, array{at: string, direction: string, preview: string}>  $firstMessages
     * @return Collection<int, array<string, mixed>>
     */
    protected function conversationRowsFromThreads(Lead $lead, Collection $threads, array $firstMessages): Collection
    {
        $leadName = $this->displayName($lead);

        return $threads->map(function (array $thread) use ($lead, $leadName, $firstMessages) {
            $channel = (string) ($thread['channel'] ?? '');
            $conversationId = (int) ($thread['conversation_id'] ?? 0);
            $first = $firstMessages[$channel.':'.$conversationId] ?? null;

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
     * @param  array<string, mixed>  $meta
     */
    protected function formatActivityDetails(array $meta, string $action = ''): string
    {
        if ($meta === []) {
            return '';
        }

        $skip = [
            'label',
            'label_id',
            'from_user_id',
            'from_user_name',
            'to_user_id',
            'to_user_name',
            'assigned_to',
        ];
        if ($action === LeadActivity::STATUS_CHANGED) {
            $skip[] = 'from';
            $skip[] = 'to';
        }

        $parts = [];
        foreach ($meta as $key => $value) {
            if (in_array($key, $skip, true)) {
                continue;
            }

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
            LeadActivity::FOLLOW_UP_DAY => 'Follow-up day',
            LeadActivity::TEMPLATE_SENT => 'Template sent',
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
