<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractSigner extends Model
{
    protected $fillable = [
        'contract_id',
        'name',
        'email',
        'role',
        'signing_order',
        'token',
        'token_expires_at',
        'status',
        'signed_at',
        'signature_path',
        'signature_ip',
        'signature_method',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function generateSigningToken(): void
    {
        $this->update([
            'token' => Str::random(64),
            'token_expires_at' => now()->addDays(30),
        ]);
    }

    public function isTokenValid(): bool
    {
        if (! $this->token || $this->status !== 'pending') {
            return false;
        }

        if ($this->token_expires_at && $this->token_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getSignatureDataUri(): ?string
    {
        if (! $this->signature_path || ! Storage::disk('local')->exists($this->signature_path)) {
            return null;
        }

        $content = Storage::disk('local')->get($this->signature_path);

        return 'data:image/png;base64,'.base64_encode($content);
    }
}
