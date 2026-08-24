<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadStatus extends Model
{
    protected $fillable = [
        'company_id',
        'slug',
        'name',
        'sort_order',
        'is_locked',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return list<array{slug: string, name: string, is_locked: bool}>
     */
    public static function defaults(): array
    {
        return [
            ['slug' => 'new', 'name' => 'New', 'is_locked' => false],
            ['slug' => 'contacted', 'name' => 'Contacted', 'is_locked' => false],
            ['slug' => 'qualified', 'name' => 'Qualified', 'is_locked' => false],
            ['slug' => 'converted', 'name' => 'Converted', 'is_locked' => false],
            ['slug' => 'lost', 'name' => 'Lost', 'is_locked' => false],
            ['slug' => Lead::STATUS_SNOOZED, 'name' => 'Snoozed', 'is_locked' => true],
            ['slug' => Lead::STATUS_ARCHIVED, 'name' => 'Archived', 'is_locked' => false],
        ];
    }

    public static function ensureForCompany(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }

        if (static::query()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (static::defaults() as $index => $row) {
            static::create([
                'company_id' => $companyId,
                'slug' => $row['slug'],
                'name' => $row['name'],
                'sort_order' => $index + 1,
                'is_locked' => $row['is_locked'],
            ]);
        }
    }

    /**
     * @return Collection<int, self>
     */
    public static function forCompany(int $companyId): Collection
    {
        static::ensureForCompany($companyId);

        return static::query()
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public static function slugsForCompany(int $companyId): array
    {
        if ($companyId < 1) {
            return Lead::STATUSES;
        }

        return static::forCompany($companyId)->pluck('slug')->all();
    }

    public static function fallbackSlug(int $companyId, ?string $preferred = null): string
    {
        $slugs = static::slugsForCompany($companyId);
        $preferred = trim((string) $preferred);
        if ($preferred !== '' && $preferred !== Lead::STATUS_SNOOZED && in_array($preferred, $slugs, true)) {
            return $preferred;
        }
        if (in_array('new', $slugs, true)) {
            return 'new';
        }
        foreach ($slugs as $slug) {
            if ($slug !== Lead::STATUS_SNOOZED) {
                return $slug;
            }
        }

        return $slugs[0] ?? 'new';
    }

    public static function isValid(int $companyId, string $slug): bool
    {
        return in_array($slug, static::slugsForCompany($companyId), true);
    }

    public static function nameFor(int $companyId, string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return 'none';
        }

        $row = static::forCompany($companyId)->firstWhere('slug', $slug);

        return $row?->name ?: ucfirst($slug);
    }

    public static function uniqueSlug(int $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'status';
        if ($base === 'all') {
            $base = 'status';
        }
        $base = mb_substr($base, 0, 40);
        $slug = $base;
        $i = 2;
        while ($slug === 'all' || static::query()->where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = mb_substr($base, 0, 36).'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
};
