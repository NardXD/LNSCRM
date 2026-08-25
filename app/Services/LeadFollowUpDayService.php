<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadLabel;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class LeadFollowUpDayService
{
    public const PLUS_BUCKET = 5;

    /** @var list<int> */
    public const DEFAULT_DAYS = [4, 10, 30, 90];

    /** @var list<int> */
    public const LEGACY_DEFAULT_DAYS = [1, 2, 3, 4];

    public const MAX_CONFIGURED_DAYS = 20;

    public const INQUIRY_LABEL = 'Inquiry';

    public const MOVE_IN_LABEL = 'Move in';

    public const NOT_INTERESTED_LABEL = 'Not Interested';

    /** @var list<string> */
    public const OUTCOME_LABELS = [self::MOVE_IN_LABEL, self::NOT_INTERESTED_LABEL];

    /** @var list<string> */
    public const CLOSED_STATUSES = ['converted', 'lost', Lead::STATUS_ARCHIVED];

    /** @var list<string> */
    public const FU_LABEL_COLORS = ['#7c3aed', '#2563eb', '#0891b2', '#c2410c', '#db2777', '#059669'];

    /**
     * @var array<int, string>
     */
    protected array $timezoneByCompany = [];

    /**
     * @var array<int, list<int>>
     */
    protected array $daysByCompany = [];

    /**
     * @var array<int, list<array{day: int, id: int, name: string, color: string|null}>>
     */
    protected array $labelsByCompany = [];

    public function timezone(?Company $company): string
    {
        $tz = trim((string) ($company?->timezone ?? ''));

        return $tz !== '' ? $tz : (string) config('app.timezone', 'UTC');
    }

    public function timezoneForCompanyId(int $companyId): string
    {
        if ($companyId < 1) {
            return (string) config('app.timezone', 'UTC');
        }

        if (! isset($this->timezoneByCompany[$companyId])) {
            $company = Company::query()->find($companyId);
            $this->timezoneByCompany[$companyId] = $this->timezone($company);
        }

        return $this->timezoneByCompany[$companyId];
    }

    public function today(int $companyId): CarbonInterface
    {
        return now($this->timezoneForCompanyId($companyId))->startOfDay();
    }

    public function dayFor(Lead $lead, ?CarbonInterface $today = null): int
    {
        $today ??= $this->today((int) $lead->company_id);

        return $this->dayFromCreatedAt($lead->created_at, $today);
    }

    public function dayFromCreatedAt(mixed $createdAt, CarbonInterface $today): int
    {
        if (! $createdAt) {
            return 0;
        }

        $created = Carbon::parse($createdAt)
            ->timezone($today->getTimezone())
            ->startOfDay();
        $today = $today->copy()->startOfDay();

        if ($created->greaterThanOrEqualTo($today)) {
            return 0;
        }

        return max(0, (int) $created->diffInDays($today));
    }

    public function ordinalDayLabel(int $day): string
    {
        $day = max(1, $day);
        $mod100 = $day % 100;
        $mod10 = $day % 10;
        $suffix = match (true) {
            $mod100 >= 11 && $mod100 <= 13 => 'th',
            $mod10 === 1 => 'st',
            $mod10 === 2 => 'nd',
            $mod10 === 3 => 'rd',
            default => 'th',
        };

        return $day.$suffix.' Day FU';
    }

    /**
     * @return array{start: CarbonInterface, end: CarbonInterface}
     */
    public function utcRangeForDay(int $companyId, int $day): array
    {
        $day = max(1, $day);
        $target = $this->today($companyId)->subDays($day);

        return [
            'start' => $target->copy()->startOfDay()->utc(),
            'end' => $target->copy()->endOfDay()->utc(),
        ];
    }

    public function utcEndForMinDay(int $companyId, int $minDay): CarbonInterface
    {
        $minDay = max(1, $minDay);

        return $this->today($companyId)->subDays($minDay)->endOfDay()->utc();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyToQuery(Builder $query, int $companyId, array $filters): void
    {
        $day = (int) ($filters['follow_up_day'] ?? 0);
        $min = (int) ($filters['follow_up_day_min'] ?? 0);

        if ($day >= 1) {
            $labelId = $this->labelIdForDay($companyId, $day);
            if ($labelId < 1) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereHas('labels', fn ($labels) => $labels->where('lead_labels.id', $labelId));

            return;
        }

        if ($min >= 1) {
            $query->where('leads.created_at', '<=', $this->utcEndForMinDay($companyId, $min));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function shouldExcludeClosed(array $filters): bool
    {
        $day = (int) ($filters['follow_up_day'] ?? 0);
        $min = (int) ($filters['follow_up_day_min'] ?? 0);
        if ($day < 1 && $min < 1 && empty($filters['follow_up_counts'])) {
            return false;
        }

        $statuses = $filters['statuses'] ?? [];
        $status = (string) ($filters['status'] ?? '');

        return $statuses === [] && ($status === '' || $status === 'all');
    }

    public function excludeClosed(Builder $query): void
    {
        $query->whereNotIn('leads.status', self::CLOSED_STATUSES);
    }

    /**
     * @return list<int>
     */
    public function configuredDays(int $companyId): array
    {
        if ($companyId < 1) {
            return self::DEFAULT_DAYS;
        }

        if (! isset($this->daysByCompany[$companyId])) {
            $this->ensureForCompany($companyId);
        }

        return $this->daysByCompany[$companyId] ?? self::DEFAULT_DAYS;
    }

    public function plusMin(int $companyId): int
    {
        $days = $this->configuredDays($companyId);

        return ($days === [] ? self::PLUS_BUCKET : max($days) + 1);
    }

    /**
     * @return array{days: list<int>, plus_min: int, labels: list<array{day: int, id: int, name: string, color: string|null}>}
     */
    public function configForCompany(int $companyId): array
    {
        $this->ensureForCompany($companyId);

        return [
            'days' => $this->configuredDays($companyId),
            'plus_min' => $this->plusMin($companyId),
            'labels' => $this->labelsForCompany($companyId),
        ];
    }

    /**
     * @return list<array{day: int, id: int, name: string, color: string|null}>
     */
    public function labelsForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return [];
        }

        $this->ensureForCompany($companyId);

        return $this->labelsByCompany[$companyId] ?? [];
    }

    public function labelIdForDay(int $companyId, int $day): int
    {
        foreach ($this->labelsForCompany($companyId) as $label) {
            if ((int) $label['day'] === $day) {
                return (int) $label['id'];
            }
        }

        return 0;
    }

    public function latestDueDay(int $companyId, int $currentDay): ?int
    {
        $due = null;
        foreach ($this->configuredDays($companyId) as $day) {
            if ($day <= $currentDay) {
                $due = $day;
            }
        }

        return $due;
    }

    public function hasNamedLabel(Lead $lead, string $name): bool
    {
        $lead->loadMissing('labels');
        $wanted = mb_strtolower($name);

        return $lead->labels->contains(
            fn (LeadLabel $label) => mb_strtolower($label->name) === $wanted
        );
    }

    public function hasOutcomeLabel(Lead $lead): bool
    {
        $lead->loadMissing('labels');

        return $lead->labels->contains(
            fn (LeadLabel $label) => in_array(mb_strtolower($label->name), array_map(
                fn (string $name) => mb_strtolower($name),
                self::OUTCOME_LABELS
            ), true)
        );
    }

    public function dueLabelToApply(Lead $lead, int $currentDay): ?LeadLabel
    {
        if (in_array((string) $lead->status, self::CLOSED_STATUSES, true)) {
            return null;
        }

        $companyId = (int) $lead->company_id;
        $dueDay = $this->latestDueDay($companyId, $currentDay);
        if ($dueDay === null) {
            return null;
        }

        $lead->loadMissing('labels');
        if ($this->hasOutcomeLabel($lead) || ! $this->hasNamedLabel($lead, self::INQUIRY_LABEL)) {
            return null;
        }

        $blockingDays = array_values(array_filter(
            $this->configuredDays($companyId),
            fn (int $day) => $day >= $dueDay
        ));
        $labelIds = [];
        $dueLabelId = 0;
        foreach ($this->labelsForCompany($companyId) as $row) {
            if (in_array((int) $row['day'], $blockingDays, true)) {
                $labelIds[] = (int) $row['id'];
            }
            if ((int) $row['day'] === $dueDay) {
                $dueLabelId = (int) $row['id'];
            }
        }

        if ($dueLabelId < 1) {
            return null;
        }

        if ($lead->labels->contains(fn (LeadLabel $label) => in_array((int) $label->id, $labelIds, true))) {
            return null;
        }

        return LeadLabel::query()->find($dueLabelId);
    }

    public function dayFromLabelName(string $name): ?int
    {
        if (! preg_match('/^(\d+)(?:st|nd|rd|th) Day FU$/iu', trim($name), $matches)) {
            return null;
        }

        $day = (int) $matches[1];

        return $day >= 1 && $day <= 365 ? $day : null;
    }

    public function detachFollowUpDay(int $companyId, int $day): void
    {
        $company = Company::query()->find($companyId);
        if (! $company) {
            return;
        }

        $stored = $company->lead_follow_up_days;
        $days = $stored === null
            ? self::DEFAULT_DAYS
            : $this->normalizeDays($stored, false);
        $days = array_values(array_filter($days, fn (int $value) => $value !== $day));
        $company->lead_follow_up_days = $days;
        $company->save();
        $this->daysByCompany[$companyId] = $days;
        unset($this->labelsByCompany[$companyId]);
    }

    public function ensureForCompany(int $companyId, bool $createDayLabels = false, bool $createSupportingLabels = false): void
    {
        if ($companyId < 1) {
            return;
        }

        if (
            ! $createDayLabels
            && ! $createSupportingLabels
            && isset($this->daysByCompany[$companyId], $this->labelsByCompany[$companyId])
        ) {
            return;
        }

        $company = Company::query()->find($companyId);
        if (! $company) {
            $this->daysByCompany[$companyId] = self::DEFAULT_DAYS;
            $this->labelsByCompany[$companyId] = [];

            return;
        }

        $stored = $company->lead_follow_up_days;
        if ($stored === null) {
            $days = self::DEFAULT_DAYS;
        } else {
            $normalizedStored = $this->normalizeDays($stored, false);
            if ($normalizedStored === self::LEGACY_DEFAULT_DAYS) {
                $days = self::DEFAULT_DAYS;
                if ($company->lead_follow_up_days !== $days) {
                    $company->lead_follow_up_days = $days;
                    $company->save();
                }
            } else {
                $days = $normalizedStored;
            }
        }

        $this->daysByCompany[$companyId] = $days;
        if ($createSupportingLabels) {
            $this->ensureSupportingLabels($companyId);
        }
        $this->labelsByCompany[$companyId] = $this->resolveDayLabels($companyId, $days, $createDayLabels);
    }

    /**
     * @param  mixed  $days
     * @return list<int>
     */
    public function normalizeDays(mixed $days, bool $emptyUsesDefault = true): array
    {
        $values = is_array($days) ? $days : [];
        $normalized = [];
        foreach ($values as $value) {
            $day = (int) $value;
            if ($day >= 1 && $day <= 365) {
                $normalized[$day] = $day;
            }
        }
        $normalized = array_values($normalized);
        sort($normalized);

        if ($normalized === [] && $emptyUsesDefault) {
            return self::DEFAULT_DAYS;
        }

        return array_slice($normalized, 0, self::MAX_CONFIGURED_DAYS);
    }

    public function rememberDays(int $companyId, array $days, bool $emptyUsesDefault = true): void
    {
        $this->daysByCompany[$companyId] = $this->normalizeDays($days, $emptyUsesDefault);
        unset($this->labelsByCompany[$companyId]);
    }

    public function isPlusValue(mixed $value): bool
    {
        $value = strtolower(trim((string) $value));

        return $value === 'plus' || $value === '5plus' || (bool) preg_match('/^\d+plus$/', $value);
    }

    /**
     * @param  list<int>  $days
     * @return list<array{day: int, id: int, name: string, color: string|null}>
     */
    protected function resolveDayLabels(int $companyId, array $days, bool $create): array
    {
        $labels = [];
        foreach (array_values($days) as $index => $day) {
            $name = $this->ordinalDayLabel($day);
            $color = self::FU_LABEL_COLORS[$index % count(self::FU_LABEL_COLORS)];
            $query = LeadLabel::query()
                ->where('company_id', $companyId)
                ->where('name', $name);
            $label = $create
                ? LeadLabel::query()->firstOrCreate(
                    ['company_id' => $companyId, 'name' => $name],
                    ['color' => $color]
                )
                : $query->first();
            if (! $label) {
                continue;
            }
            if ($create && ! $label->color) {
                $label->color = $color;
                $label->save();
            }
            $labels[] = [
                'day' => $day,
                'id' => (int) $label->id,
                'name' => $label->name,
                'color' => $label->color ? (string) $label->color : $color,
            ];
        }

        return $labels;
    }

    protected function ensureSupportingLabels(int $companyId): void
    {
        foreach ([
            self::INQUIRY_LABEL => '#9333ea',
            self::MOVE_IN_LABEL => '#16a34a',
            self::NOT_INTERESTED_LABEL => '#dc2626',
        ] as $name => $color) {
            LeadLabel::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                ['color' => $color]
            );
        }
    }
}
