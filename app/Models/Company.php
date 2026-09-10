<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subdomain',
        'quotation_prefix',
        'logo',
        'email',
        'phone',
        'address',
        'website',
        'default_wise_payment_url',
        'timezone',
        'status',
        'trial_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * The company this single-tenant install should use (no subdomain lookup).
     */
    public static function current(): ?self
    {
        $query = static::query()->orderBy('id');
        $id = config('company.id');

        if ($id) {
            return $query->where('id', $id)->first();
        }

        return $query->first();
    }

    /**
     * Get the users for the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the primary admin user for this company.
     */
    public function adminUser(): ?User
    {
        $adminUser = $this->users()
            ->where('status', 'active')
            ->whereHas('role', function ($query) {
                $query->where('slug', 'admin')
                    ->where('company_id', $this->id);
            })
            ->orderBy('id')
            ->first();

        if ($adminUser) {
            return $adminUser;
        }

        return $this->users()
            ->where('status', 'active')
            ->where('email', $this->email)
            ->orderBy('id')
            ->first();
    }

    /**
     * External sales representatives (not tied to employee accounts).
     */
    public function salesReps(): HasMany
    {
        return $this->hasMany(SalesRep::class);
    }

    /**
     * Get the subscriptions for the company.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the active subscription for the company.
     */
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    /**
     * Get the payments for the company.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the modules that the company has access to.
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'company_modules')
            ->withPivot('is_enabled', 'granted_at')
            ->withTimestamps();
    }

    /**
     * Check if company has access to a specific module.
     */
    public function hasModuleAccess(string $moduleSlug): bool
    {
        return $this->modules()
            ->where('slug', $moduleSlug)
            ->wherePivot('is_enabled', true)
            ->exists();
    }

    /**
     * Get the roles for the company.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get the history/audit log for the company.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(CompanyHistory::class)->orderByDesc('created_at');
    }

    /**
     * Get the permissions for the company.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Get the Twilio integration (account credentials) for the company.
     */
    public function twilioFlexIntegration()
    {
        return $this->hasOne(TwilioFlexIntegration::class);
    }

    /**
     * Alias for callers that historically used twilioIntegration().
     */
    public function twilioIntegration()
    {
        return $this->twilioFlexIntegration();
    }

    /**
     * Get the OpenAI integration for the company.
     */
    public function openaiIntegration(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OpenAIIntegration::class);
    }

    /**
     * Get the knowledge base articles for the company.
     */
    public function knowledgeBaseArticles(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticle::class, 'company_id');
    }

    /**
     * Get the knowledge base FAQs for the company.
     */
    public function knowledgeBaseFaqs(): HasMany
    {
        return $this->hasMany(KnowledgeBaseFaq::class, 'company_id');
    }

    /**
     * Get the knowledge base guides for the company.
     */
    public function knowledgeBaseGuides(): HasMany
    {
        return $this->hasMany(KnowledgeBaseGuide::class, 'company_id');
    }

    /**
     * Get the knowledge base categories for the company.
     */
    public function knowledgeBaseCategories(): HasMany
    {
        return $this->hasMany(KnowledgeBaseCategory::class, 'company_id');
    }

    /**
     * Get the conversations for the company.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'company_id');
    }

    /**
     * Get or generate quotation prefix for the company.
     */
    public function getQuotationPrefixAttribute($value): string
    {
        if (empty($value)) {
            $value = static::generateQuotationPrefix($this->name);
            $this->quotation_prefix = $value;
            $this->saveQuietly();
        }

        return $value;
    }

    /**
     * Generate a unique quotation prefix from company name.
     */
    public static function generateQuotationPrefix(string $companyName): string
    {
        // Remove common words and get initials
        $words = preg_split('/\s+/', strtoupper($companyName));
        $words = array_filter($words, function ($word) {
            $commonWords = ['CORP', 'CORPORATION', 'INC', 'INCORPORATED', 'LLC', 'LTD', 'LIMITED', 'CO', 'COMPANY', 'GROUP', 'HOLDINGS', 'THE'];

            return ! in_array($word, $commonWords) && strlen($word) > 0;
        });

        // Take first 2-3 meaningful words and create initials
        $words = array_slice(array_values($words), 0, 3);
        $prefix = '';

        if (count($words) >= 2) {
            // Take first 2-3 letters from first word and 1-2 from second
            $prefix = substr($words[0], 0, min(3, strlen($words[0])));
            if (isset($words[1])) {
                $prefix .= substr($words[1], 0, min(2, strlen($words[1])));
            }
        } elseif (count($words) === 1) {
            // Single word: take first 3-4 letters
            $prefix = substr($words[0], 0, min(4, strlen($words[0])));
        } else {
            // Fallback: use first 3 letters of company name
            $prefix = strtoupper(substr(preg_replace('/\s+/', '', $companyName), 0, 3));
        }

        // Ensure minimum length of 2 and maximum of 5
        $prefix = strtoupper(preg_replace('/[^A-Z]/', '', $prefix));
        $prefix = substr($prefix, 0, 5);
        $prefix = str_pad($prefix, 2, 'X', STR_PAD_RIGHT);

        return $prefix;
    }

    /**
     * Public URL for the company logo (served by Laravel, not the storage symlink).
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($this->logo)) {
            return public_media_url($this->logo);
        }

        return null;
    }

    /**
     * Absolute filesystem path for embedding a logo in DomPDF.
     */
    public function pdfLogoPath(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $candidates = [
            storage_path('app/public/'.$this->logo),
            public_path('storage/'.$this->logo),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
                return $path;
            }
        }

        return null;
    }
}
