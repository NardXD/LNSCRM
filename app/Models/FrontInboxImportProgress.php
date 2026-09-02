<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontInboxImportProgress extends Model
{
    protected $fillable = [
        'company_id',
        'front_inbox_id',
        'shared_inbox_id',
        'next_page_url',
        'conversations_done',
    ];

    protected $casts = [
        'conversations_done' => 'integer',
    ];
}
