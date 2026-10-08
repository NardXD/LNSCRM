<?php

namespace App\Services;

use App\Models\PhoneCallLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PhoneReportService
{
    public const MISSED_STATUSES = ['busy', 'no-answer', 'failed', 'canceled'];

    /**
     * Mirrors PhoneCallLog::hasRecording() plus the "absent" exclusion used by call history.
     */
    protected const HAS_RECORDING_SQL = "(((recording_sid IS NOT NULL AND recording_sid <> '') OR (recording_url IS NOT NULL AND recording_url <> '')) AND (recording_status IS NULL OR recording_status <> 'absent'))";

    /**
     * Call length buckets in seconds (min inclusive, max exclusive). Zero-second calls never connected and are excluded.
     */
    protected const LENGTH_BUCKETS = [
        ['label' => 'Under 1 min', 'min' => 1, 'max' => 60],
        ['label' => '1–5 min', 'min' => 60, 'max' => 300],
        ['label' => '5–15 min', 'min' => 300, 'max' => 900],
        ['label' => '15–30 min', 'min' => 900, 'max' => 1800],
        ['label' => '30+ min', 'min' => 1800, 'max' => null],
    ];

    protected const MAX_FILLED_DAYS = 366;

    public function __construct(protected FlexCrmLookupService $crmLookup) {}

    /**
     * Same gate as call history and recording playback: number managers see every agent's calls.
     */
    public function canViewAllUsers(User $user): bool
    {
        return $user->hasPermission('manage_twilio_numbers');
    }

    /**
     * First day of the current month through today, in the company timezone.
     *
     * @return array{0: string, 1: string}
     */
    public function defaultDateRange(): array
    {
        $today = TimezoneService::today();

        return [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()];
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    public function userOptions(int $companyId): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(User $viewer, array $filters): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);
        $scope = $this->resolveUserScope($viewer, $filters);

        $totals = $this->emptyTotals();
        $perUser = [];
        $daily = [];
        $hourly = array_fill(0, 24, ['calls' => 0, 'duration' => 0]);
        $statuses = [];
        $lengths = array_fill(0, count(self::LENGTH_BUCKETS), 0);

        $rows = $this->scopedQuery((int) $viewer->company_id, $dateFrom, $dateTo, $scope)
            ->toBase()
            ->select([
                'id',
                'user_id',
                'direction',
                'status',
                'duration',
                'recording_sid',
                'recording_url',
                'recording_status',
                'recording_duration',
                'created_at',
            ])
            ->lazyById(1000, 'id');

        foreach ($rows as $row) {
            $call = $this->classifyRow($row);
            $userKey = $row->user_id !== null ? (int) $row->user_id : 0;

            $this->accumulate($totals, $call);
            $perUser[$userKey] ??= $this->emptyTotals();
            $this->accumulate($perUser[$userKey], $call);

            $createdAt = (string) $row->created_at;
            $day = substr($createdAt, 0, 10);
            $daily[$day] ??= ['calls' => 0, 'duration' => 0, 'recordings' => 0, 'missed' => 0];
            $daily[$day]['calls']++;
            $daily[$day]['duration'] += $call['duration'];
            $daily[$day]['recordings'] += $call['has_recording'] ? 1 : 0;
            $daily[$day]['missed'] += $call['outcome'] === 'missed' ? 1 : 0;

            $hour = (int) substr($createdAt, 11, 2);
            if ($hour >= 0 && $hour <= 23) {
                $hourly[$hour]['calls']++;
                $hourly[$hour]['duration'] += $call['duration'];
            }

            $statusKey = $call['status'] !== '' ? $call['status'] : 'unknown';
            $statuses[$statusKey] = ($statuses[$statusKey] ?? 0) + 1;

            if ($call['duration'] > 0) {
                foreach (self::LENGTH_BUCKETS as $index => $bucket) {
                    if ($call['duration'] >= $bucket['min'] && ($bucket['max'] === null || $call['duration'] < $bucket['max'])) {
                        $lengths[$index]++;
                        break;
                    }
                }
            }
        }

        arsort($statuses);

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'can_view_all' => $this->canViewAllUsers($viewer),
            'user_scope' => $scope['type'] === 'user' ? (string) $scope['user_id'] : $scope['type'],
            'totals' => $this->finalizeTotals($totals),
            'per_user' => $this->buildPerUserRows((int) $viewer->company_id, $perUser, $totals['total_duration']),
            'daily' => $this->buildDailySeries($dateFrom, $dateTo, $daily),
            'hourly' => array_map(
                fn (int $hour) => ['hour' => $hour, 'calls' => $hourly[$hour]['calls'], 'duration' => $hourly[$hour]['duration']],
                range(0, 23)
            ),
            'by_status' => array_map(
                fn (string $status, int $count) => ['status' => $status, 'count' => $count],
                array_keys($statuses),
                array_values($statuses)
            ),
            'length_buckets' => array_map(
                fn (array $bucket, int $index) => ['label' => $bucket['label'], 'count' => $lengths[$index]],
                self::LENGTH_BUCKETS,
                array_keys(self::LENGTH_BUCKETS)
            ),
        ];
    }

    /**
     * Paginated call list (with recordings) plus totals for the whole filtered set.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function calls(User $viewer, array $filters): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);
        $scope = $this->resolveUserScope($viewer, $filters);
        $companyId = (int) $viewer->company_id;

        $query = $this->listQuery($companyId, $dateFrom, $dateTo, $scope, $filters);

        $aggregate = (clone $query)
            ->toBase()
            ->selectRaw('COUNT(*) as calls')
            ->selectRaw('COALESCE(SUM(duration), 0) as total_duration')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::HAS_RECORDING_SQL.' THEN 1 ELSE 0 END), 0) as recordings')
            ->selectRaw('COALESCE(SUM(CASE WHEN '.self::HAS_RECORDING_SQL.' THEN COALESCE(recording_duration, 0) ELSE 0 END), 0) as recorded_duration')
            ->first();

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $page = max((int) ($filters['page'] ?? 1), 1);

        $paginator = $this->applySort($query, (string) ($filters['sort'] ?? 'newest'))
            ->with('user:id,name')
            ->paginate($perPage, ['*'], 'page', $page);

        $leadIndex = $paginator->isEmpty() ? null : $this->crmLookup->leadIndex($companyId);

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'totals' => [
                'calls' => (int) ($aggregate->calls ?? 0),
                'total_duration' => (int) ($aggregate->total_duration ?? 0),
                'recordings' => (int) ($aggregate->recordings ?? 0),
                'recorded_duration' => (int) ($aggregate->recorded_duration ?? 0),
            ],
            'data' => $paginator->getCollection()
                ->map(fn (PhoneCallLog $log) => $this->formatCall($log, $leadIndex))
                ->values()
                ->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * Rows for CSV export, using the same filters and sort as the call list.
     *
     * @param  array<string, mixed>  $filters
     * @return array{date_from: string, date_to: string, rows: iterable<int, array<int, string|int>>}
     */
    public function exportRows(User $viewer, array $filters): array
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($filters);
        $scope = $this->resolveUserScope($viewer, $filters);
        $companyId = (int) $viewer->company_id;

        $query = $this->applySort(
            $this->listQuery($companyId, $dateFrom, $dateTo, $scope, $filters),
            (string) ($filters['sort'] ?? 'newest')
        )->with('user:id,name');

        $rows = (function () use ($query, $companyId) {
            $leadIndex = $this->crmLookup->leadIndex($companyId);

            foreach ($query->lazy(500) as $log) {
                $call = $this->formatCall($log, $leadIndex);

                yield [
                    $call['occurred_at_label'],
                    $call['user']['name'] ?? 'Unassigned',
                    ucfirst($call['direction']),
                    (string) $call['from'],
                    (string) $call['to'],
                    $call['lead']['name'] ?? '',
                    (string) $call['status'],
                    $call['duration'],
                    $this->formatClock($call['duration']),
                    $call['has_recording'] ? 'Yes' : 'No',
                    $call['has_recording'] ? (int) $call['recording_duration'] : '',
                    $call['has_recording'] ? $this->formatClock((int) $call['recording_duration']) : '',
                    (string) $call['call_sid'],
                ];
            }
        })();

        return ['date_from' => $dateFrom, 'date_to' => $dateTo, 'rows' => $rows];
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return [
            'Date / Time',
            'Agent',
            'Direction',
            'From',
            'To',
            'Contact',
            'Status',
            'Duration (seconds)',
            'Duration (h:mm:ss)',
            'Recorded',
            'Recording Duration (seconds)',
            'Recording Duration (h:mm:ss)',
            'Call SID',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: string}
     */
    public function resolveDateRange(array $filters): array
    {
        [$defaultFrom, $defaultTo] = $this->defaultDateRange();

        $dateFrom = $this->parseDate($filters['date_from'] ?? null) ?? $defaultFrom;
        $dateTo = $this->parseDate($filters['date_to'] ?? null) ?? $defaultTo;

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [$dateFrom, $dateTo];
    }

    public function formatClock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    protected function parseDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return null;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $value : null;
    }

    /**
     * Agents without the "see everyone" permission are always locked to their own calls.
     *
     * @param  array<string, mixed>  $filters
     * @return array{type: 'all'|'user'|'unassigned', user_id: ?int}
     */
    protected function resolveUserScope(User $viewer, array $filters): array
    {
        if (! $this->canViewAllUsers($viewer)) {
            return ['type' => 'user', 'user_id' => (int) $viewer->id];
        }

        $raw = trim((string) ($filters['user_id'] ?? ''));

        if ($raw === 'unassigned') {
            return ['type' => 'unassigned', 'user_id' => null];
        }

        if (ctype_digit($raw) && (int) $raw > 0) {
            return ['type' => 'user', 'user_id' => (int) $raw];
        }

        return ['type' => 'all', 'user_id' => null];
    }

    /**
     * Calls are bucketed by created_at (indexed, always set) using the same naive wall-clock
     * comparison as the dashboard call stats.
     *
     * @param  array{type: string, user_id: ?int}  $scope
     * @return Builder<PhoneCallLog>
     */
    protected function scopedQuery(int $companyId, string $dateFrom, string $dateTo, array $scope): Builder
    {
        $endExclusive = Carbon::createFromFormat('!Y-m-d', $dateTo)->addDay()->format('Y-m-d H:i:s');

        $query = PhoneCallLog::query()
            ->where('company_id', $companyId)
            ->where('created_at', '>=', $dateFrom.' 00:00:00')
            ->where('created_at', '<', $endExclusive);

        if ($scope['type'] === 'user') {
            $query->where('user_id', $scope['user_id']);
        } elseif ($scope['type'] === 'unassigned') {
            $query->whereNull('user_id');
        }

        return $query;
    }

    /**
     * @param  array{type: string, user_id: ?int}  $scope
     * @param  array<string, mixed>  $filters
     * @return Builder<PhoneCallLog>
     */
    protected function listQuery(int $companyId, string $dateFrom, string $dateTo, array $scope, array $filters): Builder
    {
        $query = $this->scopedQuery($companyId, $dateFrom, $dateTo, $scope);

        $direction = (string) ($filters['direction'] ?? '');
        if ($direction === 'inbound' || $direction === 'outbound') {
            $query->where('direction', 'like', '%'.$direction.'%');
        }

        $outcome = (string) ($filters['outcome'] ?? '');
        if ($outcome === 'completed') {
            $query->where('status', 'completed');
        } elseif ($outcome === 'missed') {
            $query->whereIn('status', self::MISSED_STATUSES);
        }

        if (filter_var($filters['recordings_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereRaw(self::HAS_RECORDING_SQL);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $digits = preg_replace('/\D+/', '', $search);
            if ($digits !== '') {
                $query->where(function (Builder $inner) use ($digits) {
                    $inner->where('from_number', 'like', '%'.$digits.'%')
                        ->orWhere('to_number', 'like', '%'.$digits.'%');
                });
            } else {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $query->whereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$escaped.'%'));
            }
        }

        return $query;
    }

    /**
     * @param  Builder<PhoneCallLog>  $query
     * @return Builder<PhoneCallLog>
     */
    protected function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'longest' => $query->orderByDesc('duration')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /**
     * @return array{direction: string, outcome: string, status: string, duration: int, has_recording: bool, recorded_duration: int}
     */
    protected function classifyRow(object $row): array
    {
        $hasRecording = (filled($row->recording_sid) || filled($row->recording_url))
            && $row->recording_status !== 'absent';

        return [
            'direction' => $this->directionOf($row->direction),
            'outcome' => $this->outcomeOf($row->status),
            'status' => strtolower(trim((string) $row->status)),
            'duration' => max(0, (int) $row->duration),
            'has_recording' => $hasRecording,
            'recorded_duration' => $hasRecording ? max(0, (int) $row->recording_duration) : 0,
        ];
    }

    protected function directionOf(?string $direction): string
    {
        $direction = strtolower((string) $direction);

        if (str_contains($direction, 'inbound')) {
            return 'inbound';
        }

        return str_contains($direction, 'outbound') ? 'outbound' : 'other';
    }

    protected function outcomeOf(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        if ($status === 'completed') {
            return 'completed';
        }

        return in_array($status, self::MISSED_STATUSES, true) ? 'missed' : 'other';
    }

    /**
     * @return array<string, int>
     */
    protected function emptyTotals(): array
    {
        return [
            'calls' => 0,
            'inbound' => 0,
            'outbound' => 0,
            'completed' => 0,
            'missed' => 0,
            'total_duration' => 0,
            'connected_calls' => 0,
            'longest_duration' => 0,
            'recordings' => 0,
            'recorded_duration' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $bucket
     * @param  array{direction: string, outcome: string, status: string, duration: int, has_recording: bool, recorded_duration: int}  $call
     */
    protected function accumulate(array &$bucket, array $call): void
    {
        $bucket['calls']++;
        $bucket['inbound'] += $call['direction'] === 'inbound' ? 1 : 0;
        $bucket['outbound'] += $call['direction'] === 'outbound' ? 1 : 0;
        $bucket['completed'] += $call['outcome'] === 'completed' ? 1 : 0;
        $bucket['missed'] += $call['outcome'] === 'missed' ? 1 : 0;
        $bucket['total_duration'] += $call['duration'];
        $bucket['connected_calls'] += $call['duration'] > 0 ? 1 : 0;
        $bucket['longest_duration'] = max($bucket['longest_duration'], $call['duration']);
        $bucket['recordings'] += $call['has_recording'] ? 1 : 0;
        $bucket['recorded_duration'] += $call['recorded_duration'];
    }

    /**
     * @param  array<string, int>  $totals
     * @return array<string, int|float>
     */
    protected function finalizeTotals(array $totals): array
    {
        $totals['avg_duration'] = $totals['connected_calls'] > 0
            ? (int) round($totals['total_duration'] / $totals['connected_calls'])
            : 0;
        $totals['missed_rate'] = $totals['calls'] > 0
            ? round($totals['missed'] / $totals['calls'] * 100, 1)
            : 0.0;

        return $totals;
    }

    /**
     * @param  array<int, array<string, int>>  $perUser
     * @return list<array<string, mixed>>
     */
    protected function buildPerUserRows(int $companyId, array $perUser, int $grandDuration): array
    {
        $userIds = array_values(array_filter(array_keys($perUser)));
        $names = $userIds === []
            ? collect()
            : User::query()->where('company_id', $companyId)->whereIn('id', $userIds)->pluck('name', 'id');

        $rows = [];
        foreach ($perUser as $userId => $totals) {
            $rows[] = array_merge($this->finalizeTotals($totals), [
                'user_id' => $userId > 0 ? $userId : null,
                'name' => $userId > 0 ? (string) ($names[$userId] ?? 'Unknown user') : 'Unassigned',
                'duration_share' => $grandDuration > 0 ? round($totals['total_duration'] / $grandDuration * 100, 1) : 0.0,
            ]);
        }

        usort($rows, fn (array $a, array $b) => [$b['total_duration'], $b['calls'], $a['name']] <=> [$a['total_duration'], $a['calls'], $b['name']]);

        return $rows;
    }

    /**
     * @param  array<string, array{calls: int, duration: int, recordings: int, missed: int}>  $daily
     * @return list<array{date: string, calls: int, duration: int, recordings: int, missed: int}>
     */
    protected function buildDailySeries(string $dateFrom, string $dateTo, array $daily): array
    {
        $start = Carbon::createFromFormat('!Y-m-d', $dateFrom);
        $end = Carbon::createFromFormat('!Y-m-d', $dateTo);

        if ($start->diffInDays($end) < self::MAX_FILLED_DAYS) {
            $days = [];
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
                $days[] = $cursor->toDateString();
            }
        } else {
            // Very long ranges: only plot days that have calls to keep the payload small.
            $days = array_keys($daily);
            sort($days);
        }

        return array_map(fn (string $day) => [
            'date' => $day,
            'calls' => $daily[$day]['calls'] ?? 0,
            'duration' => $daily[$day]['duration'] ?? 0,
            'recordings' => $daily[$day]['recordings'] ?? 0,
            'missed' => $daily[$day]['missed'] ?? 0,
        ], $days);
    }

    /**
     * @param  array{by_phone: array<string, array<string, mixed>>, by_email: array<string, array<string, mixed>>, by_name: array<string, array<string, mixed>>}|null  $leadIndex
     * @return array<string, mixed>
     */
    protected function formatCall(PhoneCallLog $log, ?array $leadIndex): array
    {
        $direction = $this->directionOf($log->direction);
        $hasRecording = $log->hasRecording() && $log->recording_status !== 'absent';
        $customer = $direction === 'outbound' ? $log->to_number : $log->from_number;
        $occurredAt = $log->started_at ?? $log->created_at;

        $lead = null;
        if ($leadIndex !== null) {
            $lead = $this->crmLookup->matchAssignedLead($leadIndex, $customer)
                ?: $this->crmLookup->matchAssignedLead($leadIndex, $log->from_number)
                ?: $this->crmLookup->matchAssignedLead($leadIndex, $log->to_number);
        }

        return [
            'id' => (int) $log->id,
            'call_sid' => $log->call_sid,
            'direction' => $direction,
            'from' => $log->from_number,
            'to' => $log->to_number,
            'contact_number' => $customer,
            'status' => $log->status,
            'outcome' => $this->outcomeOf($log->status),
            'duration' => max(0, (int) $log->duration),
            'occurred_at' => $occurredAt?->toIso8601String(),
            'occurred_at_label' => $occurredAt?->format('M j, Y g:i A') ?? '',
            'user' => $log->user ? ['id' => (int) $log->user->id, 'name' => (string) $log->user->name] : null,
            'lead' => $lead ? [
                'id' => $lead['id'] ?? null,
                'name' => $lead['name'] ?? null,
                'crm_url' => $lead['crm_url'] ?? null,
            ] : null,
            'has_recording' => $hasRecording,
            'recording_duration' => $hasRecording ? (int) $log->recording_duration : null,
            'recording_url' => $hasRecording ? route('api.phone-reports.recording', $log) : null,
            'recording_download_url' => $hasRecording ? route('api.phone-reports.recording', ['phoneCallLog' => $log, 'download' => 1]) : null,
        ];
    }
}
