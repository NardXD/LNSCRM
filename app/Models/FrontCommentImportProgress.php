<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrontCommentImportProgress extends Model
{
    protected $table = 'front_comment_import_progress';

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
