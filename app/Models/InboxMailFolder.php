<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxMailFolder extends Model
{
    protected $fillable = [
        'shared_inbox_id',
        'local_key',
        'graph_folder_id',
        'display_name',
        'parent_local_key',
        'well_known_name',
        'status_default',
        'direction_default',
        'graph_total_count',
        'last_synced_at',
    ];

    protected $casts = [
        'graph_total_count' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'shared_inbox_id');
    }
}
