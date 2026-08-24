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

    public const TITLES = ['Mr', 'Ms'];

    public const CUSTOMER_TYPE_RESIDENTIAL = 'residential';

    public const CUSTOMER_TYPE_BUSINESS = 'business';

    public const CUSTOMER_TYPES = [
        self::CUSTOMER_TYPE_RESIDENTIAL => 'Residential',
        self::CUSTOMER_TYPE_BUSINESS => 'Business',
    ];

    public const RESIDENTIAL_TYPES = ['House', 'Condominium', 'Apartment'];

    public const BUSINESS_INDUSTRIES = [
        'I.T. Solutions',
        'Hotels',
        'Online Retailer',
        'Insurance',
        'Distributor',
        'NGO',
        'Real Estate',
        'Government Agencies',
        'BPO',
        'Travel/Leisure',
        'Legal/Consultancy',
        'Food',
        'Construction/Engineering',
        'Freight Forwarding',
        'Banking/Finance',
        'Religious Entity',
        'Manpower/Recruitment Agencies',
        'Education',
        'Manufacturing',
        'Automotive',
        'Medical',
        'Telecommunications',
        'Event/Film Production',
        'Other',
    ];

    public const STORAGE_REASONS = [
        'Excess stuff',
        'Downsizing home',
        'Renovating',
        'Moving',
        'Other',
    ];

    public const SOURCES = [
        'Email',
        'Brochure',
        'Magazine',
        'Online Articles',
        'Twitter',
        'Yelp',
        'Newspaper',
        'Web Ads',
        'Events',
        'Tv Feature',
        'Web Search',
        'Facebook',
        'Facebook Leads',
        'Facebook Message',
        'Instagram',
        'Instagram Leads',
        'Instagram Message',
        'Tiktok',
        'SMS/Text',
        'Referral',
        'Website',
        'Street Signage',
        'Yellow Pages',
    ];

    protected $fillable = [
        'company_id',
        'assigned_to',
        'client_id',
        'name',
        'title',
        'first_name',
        'last_name',
        'address',
        'city',
        'postal_code',
        'date_of_birth',
        'company_name',
        'alt_title',
        'alt_first_name',
        'alt_last_name',
        'alt_address',
        'alt_city',
        'alt_postal_code',
        'status',
        'source',
        'customer_type',
        'residential_type',
        'business_industry',
        'business_industry_other',
        'storage_reason',
        'storage_reason_other',
        'notes',
        'reopen_at',
        'reopen_status',
    ];

    protected $casts = [
        'reopen_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    /**
     * @return array{titles: list<string>, sources: list<string>, customer_types: array<string, string>, residential_types: list<string>, business_industries: list<string>, storage_reasons: list<string>}
     */
    public static function formOptions(): array
    {
        return [
            'titles' => self::TITLES,
            'sources' => self::SOURCES,
            'customer_types' => self::CUSTOMER_TYPES,
            'residential_types' => self::RESIDENTIAL_TYPES,
            'business_industries' => self::BUSINESS_INDUSTRIES,
            'storage_reasons' => self::STORAGE_REASONS,
        ];
    }

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

    public function inboxConversations(): HasMany
    {
        return $this->hasMany(InboxConversation::class);
    }

    public function getInitialsAttribute(): string
    {
        $display = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));
        if ($display === '') {
            $display = (string) $this->name;
        }
        $words = preg_split('/\s+/', $display) ?: [];
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1).substr($words[count($words) - 1], 0, 1));
        }

        return strtoupper(substr($display, 0, 2));
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

    public function removeIdentity(LeadIdentity $identity): void
    {
        if ((int) $identity->lead_id !== (int) $this->id) {
            return;
        }

        $type = $identity->type;
        $wasPrimary = (bool) $identity->is_primary;
        $identity->delete();

        if (! $wasPrimary) {
            return;
        }

        $next = $this->identities()->where('type', $type)->orderBy('id')->first();
        if ($next && ! $next->is_primary) {
            $next->update(['is_primary' => true]);
        }
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
