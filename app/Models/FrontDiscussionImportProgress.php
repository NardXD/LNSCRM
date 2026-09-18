<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontDiscussionImportProgress extends Model
{
    protected $table = 'front_discussion_import_progress';

    protected $fillable = [
        'company_id',
        'next_page_url',
        'conversations_done',
    ];

    protected $casts = [
        'conversations_done' => 'integer',
    ];
}
