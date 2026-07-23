<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeTrackingEditHistory extends Model
{
    use HasFactory;

    protected $table = 'time_tracking_edit_history';

    protected $fillable = [
        'time_tracking_record_id',
        'edited_by',
        'old_time_in',
        'new_time_in',
        'old_time_out',
        'new_time_out',
        'old_hours_worked',
        'new_hours_worked',
        'reason',
    ];

    /**
     * Get the time tracking record that was edited.
     */
    public function timeTrackingRecord()
    {
        return $this->belongsTo(TimeTracking::class, 'time_tracking_record_id');
    }

    /**
     * Get the user who made the edit.
     */
    public function editedByUser()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
