<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'client_id',
        'user_id',
        'contract_number',
        'title',
        'content',
        'status',
        'effective_date',
        'expiry_date',
        'sent_at',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'expiry_date' => 'date',
            'sent_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function signers(): HasMany
    {
        return $this->hasMany(ContractSigner::class)->orderBy('signing_order');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ContractStatusHistory::class)->orderByDesc('created_at');
    }

    public function allSignersSigned(): bool
    {
        return $this->signers()->where('status', '!=', 'signed')->doesntExist();
    }

    public function hasPendingSigners(): bool
    {
        return $this->signers()->where('status', 'pending')->exists();
    }

    public static function generateContractNumber(int $companyId): string
    {
        $year = now()->year;
        $lastContract = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        if ($lastContract) {
            $lastNumber = (int) substr($lastContract->contract_number, -3);
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }

        return "CT-{$year}-{$nextNumber}";
    }
}
