<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenAIIntegration extends Model
{
    protected $table = 'openai_integrations';

    protected $fillable = [
        'company_id',
        'api_key',
        'is_active',
        'uses_main_ai',
        'token_limit',
        'tokens_used',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'uses_main_ai' => 'boolean',
        'token_limit' => 'integer',
        'tokens_used' => 'integer',
    ];

    /**
     * Determine whether the company has remaining token allowance.
     */
    public function hasTokensRemaining(): bool
    {
        if (empty($this->token_limit)) {
            return true;
        }

        return $this->tokens_used < $this->token_limit;
    }

    /**
     * Get the company that owns the OpenAI integration.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
