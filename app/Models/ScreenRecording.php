<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'date',
        'screen_recording_path',
        'screen_recording_duration',
        'status',
        'upload_id',
        'device_id',
        'device_platform',
        'sync_status',
        'upload_checksum',
        'retry_count',
        'file_size',
        'queued_at',
        'uploaded_at',
        'last_retry_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'metadata' => 'array',
            'queued_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'last_retry_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the screen recording.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the company that owns the screen recording.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Format duration in a readable format.
     */
    public function getDurationFormattedAttribute(): string
    {
        if (! $this->screen_recording_duration) {
            return '0s';
        }

        $seconds = $this->screen_recording_duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        }

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        return sprintf('%ds', $secs);
    }
}
