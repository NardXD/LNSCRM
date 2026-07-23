<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyHistory extends Model
{
    const ACTION_CREATED = 'created';

    const ACTION_STATUS_CHANGED = 'status_changed';

    const ACTION_MODULES_UPDATED = 'modules_updated';

    const ACTION_ADMIN_LOGIN_AS = 'admin_login_as';

    const ACTION_LIVE_VIEW_STARTED = 'live_view_started';

    const ACTION_LIVE_VIEW_ENDED = 'live_view_ended';

    protected $fillable = [
        'company_id',
        'action',
        'old_value',
        'new_value',
        'description',
        'changed_by',
    ];

    protected $casts = [
        'old_value' => 'array',
        'new_value' => 'array',
    ];

    /**
     * Get the company for this history entry.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who made the change (admin).
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Log a history entry for a company.
     *
     * @param  array<string, mixed>|null  $oldValue
     * @param  array<string, mixed>|null  $newValue
     */
    public static function log(
        Company $company,
        string $action,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $description = null,
        ?int $changedBy = null
    ): self {
        return self::create([
            'company_id' => $company->id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'changed_by' => $changedBy ?? auth()->id(),
        ]);
    }

    /**
     * Get a human-readable summary for display.
     */
    public function getSummaryAttribute(): string
    {
        return match ($this->action) {
            self::ACTION_CREATED => 'Company created',
            self::ACTION_STATUS_CHANGED => 'Status changed from '.ucfirst($this->old_value['status'] ?? '—').' to '.ucfirst($this->new_value['status'] ?? '—'),
            self::ACTION_MODULES_UPDATED => $this->getModulesUpdateSummary(),
            self::ACTION_ADMIN_LOGIN_AS => $this->description ?? 'Platform admin logged in as company admin',
            self::ACTION_LIVE_VIEW_STARTED => $this->description ?? 'Live screen viewing started',
            self::ACTION_LIVE_VIEW_ENDED => $this->description ?? 'Live screen viewing ended',
            default => $this->description ?? 'Updated',
        };
    }

    private function getModulesUpdateSummary(): string
    {
        $old = $this->old_value['modules'] ?? [];
        $new = $this->new_value['modules'] ?? [];
        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);

        $parts = [];
        if (! empty($added)) {
            $parts[] = 'Added: '.implode(', ', $added);
        }
        if (! empty($removed)) {
            $parts[] = 'Removed: '.implode(', ', $removed);
        }

        return $parts ? implode('; ', $parts) : 'Module access updated';
    }
}
