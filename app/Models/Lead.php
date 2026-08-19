<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    public const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'lost', 'snoozed', 'archived'];

    public const STATUS_SNOOZED = 'snoozed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id',
        'assigned_to',
        'client_id',
        'name',
        'company_name',
        'status',
        'source',
        'notes',
        'reopen_at',
        'reopen_status',
    ];

    protected $casts = [
        'reopen_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(LeadIdentity::class);
    }

    public function leadNotes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->orderByDesc('created_at');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(LeadLabel::class, 'lead_lead_label')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('created_at');
    }

    public function getInitialsAttribute(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[count($words) - 1], 0, 1));
        }

        return strtoupper(substr($this->name, 0, 2));
    }

    /**
     * Replace identities with the given list.
     *
     * @param  list<array{type: string, value: string, label?: ?string, is_primary?: bool}>  $items
     */
    public function syncIdentities(array $items): void
    {
        $keepIds = [];

        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            $value = trim((string) ($item['value'] ?? ''));
            if ($value === '' || ! in_array($type, LeadIdentity::TYPES, true)) {
                continue;
            }

            $normalized = LeadIdentity::normalize($type, $value);
            if ($normalized === '') {
                continue;
            }

            $identity = $this->identities()->updateOrCreate(
                [
                    'type' => $type,
                    'normalized_value' => $normalized,
                ],
                [
                    'value' => $value,
                    'label' => isset($item['label']) && $item['label'] !== '' ? (string) $item['label'] : null,
                    'is_primary' => (bool) ($item['is_primary'] ?? false),
                ]
            );

            $keepIds[] = $identity->id;
        }

        $query = $this->identities();
        if ($keepIds !== []) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();
    }

    public function addIdentity(string $type, string $value, ?string $label = null): ?LeadIdentity
    {
        $value = trim($value);
        if ($value === '' || ! in_array($type, LeadIdentity::TYPES, true)) {
            return null;
        }

        $normalized = LeadIdentity::normalize($type, $value);
        if ($normalized === '') {
            return null;
        }

        $existing = $this->identities()
            ->where('type', $type)
            ->where('normalized_value', $normalized)
            ->first();

        if ($existing) {
            return $existing;
        }

        $hasType = $this->identities()->where('type', $type)->exists();

        return $this->identities()->create([
            'type' => $type,
            'value' => $value,
            'normalized_value' => $normalized,
            'label' => $label,
            'is_primary' => ! $hasType,
        ]);
    }

    /**
     * @return list<string>
     */
    public function phoneValues(): array
    {
        return $this->identities
            ->where('type', LeadIdentity::TYPE_PHONE)
            ->pluck('value')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function emailValues(): array
    {
        return $this->identities
            ->where('type', LeadIdentity::TYPE_EMAIL)
            ->pluck('value')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function socialNames(): array
    {
        return $this->identities
            ->whereIn('type', [LeadIdentity::TYPE_FACEBOOK, LeadIdentity::TYPE_INSTAGRAM])
            ->pluck('value')
            ->merge([$this->name])
            ->filter()
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter(fn ($name) => $name !== '' && ! FacebookConversation::isPlaceholderName($name))
            ->unique()
            ->values()
            ->all();
    }
}
