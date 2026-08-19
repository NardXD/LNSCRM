<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\SharedInbox;
use App\Models\TwilioPhoneNumber;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class LeadAutoCreateService
{
    public function __construct(
        protected FlexCrmLookupService $crmLookup,
        protected TwilioCompanyService $twilioCompany,
        protected LeadActivityService $leadActivity
    ) {}

    public function fromSharedInbox(SharedInbox $inbox, ?string $fromName, ?string $fromEmail): ?Lead
    {
        if ($inbox->isPersonal()) {
            $email = $this->cleanEmail((int) $inbox->company_id, $fromEmail);

            return $email
                ? $this->findExisting((int) $inbox->company_id, null, $email, null, null)
                : null;
        }

        return $this->ensure(
            (int) $inbox->company_id,
            'inbox',
            $fromName,
            null,
            $fromEmail
        );
    }

    public function fromInboxConversation(InboxConversation $conversation): ?Lead
    {
        $conversation->loadMissing('inbox');
        if (! $conversation->inbox) {
            return null;
        }

        return $this->fromSharedInbox(
            $conversation->inbox,
            $conversation->from_name,
            $conversation->from_email
        );
    }

    /**
     * @param  array{phones?: list<string>, emails?: list<string>, names?: list<string>}|null  $extracted
     */
    public function fromFacebookConversation(FacebookConversation $conversation, ?array $extracted = null): ?Lead
    {
        $extracted = $extracted ?? ['phones' => [], 'emails' => [], 'names' => []];
        $isIg = $conversation->channel === 'instagram';
        $extractedName = trim((string) ($extracted['names'][0] ?? ''));
        $extractedName = $extractedName !== '' ? $extractedName : null;
        $facebookName = $isIg ? null : $conversation->name;
        if (! $isIg && FacebookConversation::isPlaceholderName($facebookName)) {
            $facebookName = $extractedName;
        }

        $lead = $this->ensure(
            (int) $conversation->company_id,
            'facebook',
            $extractedName ?: $conversation->name,
            $extracted['phones'][0] ?? null,
            $extracted['emails'][0] ?? null,
            $facebookName,
            $isIg ? ($conversation->username ?: $conversation->name) : null
        );

        if (
            $lead
            && $extractedName
            && ! $this->isPlaceholderName($extractedName)
            && (
                $this->isPlaceholderName($lead->name)
                || strcasecmp(trim((string) $lead->name), trim((string) $conversation->name)) === 0
            )
            && strcasecmp(trim((string) $lead->name), $extractedName) !== 0
        ) {
            $lead->name = $extractedName;
            $lead->save();
        }

        return $lead?->fresh('identities') ?? $lead;
    }

    /**
     * @param  string|array<int, string>  $triggers
     * @param  array{contact_name?: ?string, phone?: ?string, email?: ?string, subject?: ?string, message?: ?string}  $context
     */
    public function applyRules(?Lead $lead, string $channel, string|array $triggers, array $context = []): ?Lead
    {
        if ($lead) {
            app(LeadRuleEngine::class)->apply($lead, $channel, $triggers, $context);
        }

        return $lead;
    }

    public function fromPhoneChannel(
        int $companyId,
        string $source,
        ?string $phone,
        ?string $name = null
    ): ?Lead {
        return $this->ensure($companyId, $source, $name, $phone, null);
    }

    /**
     * Create/find a lead from a voice call. Uses the customer number (not the company/Twilio number).
     */
    public function fromCallLegs(
        int $companyId,
        ?string $from,
        ?string $to,
        ?string $direction = null
    ): ?Lead {
        $direction = strtolower((string) $direction);
        $primary = str_contains($direction, 'outbound') ? $to : $from;
        $secondary = $primary === $from ? $to : $from;

        return $this->fromPhoneChannel($companyId, 'phone', $primary)
            ?: $this->fromPhoneChannel($companyId, 'phone', $secondary);
    }

    /**
     * Find/create the call's lead and assign it to the agent who answered or placed the call.
     */
    public function assignFromCall(
        int $companyId,
        int $userId,
        ?string $from,
        ?string $to,
        ?string $direction = null,
        bool $onlyIfUnassigned = false
    ): ?Lead {
        $lead = $this->fromCallLegs($companyId, $from, $to, $direction);
        if (! $lead) {
            return null;
        }

        return $this->assignToUser($lead, $userId, $onlyIfUnassigned, $this->callReason($direction));
    }

    public function assignToUser(Lead $lead, int $userId, bool $onlyIfUnassigned = false, ?string $reason = null): Lead
    {
        if ($userId <= 0) {
            return $lead;
        }

        if ($onlyIfUnassigned && $lead->assigned_to) {
            return $lead;
        }

        if ((int) $lead->assigned_to === $userId) {
            return $lead;
        }

        $exists = User::query()
            ->where('company_id', $lead->company_id)
            ->where('id', $userId)
            ->exists();

        if (! $exists) {
            return $lead;
        }

        $fromId = $lead->assigned_to;
        $lead->assigned_to = $userId;
        $lead->save();
        $this->leadActivity->recordAssignment($lead, $fromId, $userId, reason: $reason);

        return $lead;
    }

    /**
     * Find or create a lead from channel contact details. Existing leads are not overwritten;
     * new phones/emails are appended so agents can fill in the rest.
     */
    public function ensure(
        int $companyId,
        string $source,
        ?string $name = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $facebookName = null,
        ?string $instagramUsername = null
    ): ?Lead {
        $phone = $this->cleanPhone($companyId, $phone);
        $email = $this->cleanEmail($companyId, $email);
        $facebookName = $this->cleanSocial($facebookName);
        $instagramUsername = $this->cleanSocial($instagramUsername);
        $displayName = $this->displayName($name, $email, $phone, $facebookName, $instagramUsername);

        if (! $phone && ! $email && ! $facebookName && ! $instagramUsername) {
            return null;
        }

        try {
            $lead = $this->findExisting($companyId, $phone, $email, $facebookName, $instagramUsername);

            if ($lead) {
                $this->attachMissingIdentities($lead, $phone, $email, $facebookName, $instagramUsername);
                if ($this->isPlaceholderName($lead->name) && $displayName && ! $this->isPlaceholderName($displayName)) {
                    $lead->name = $displayName;
                    $lead->save();
                }

                return $lead->fresh('identities');
            }

            $lead = Lead::create([
                'company_id' => $companyId,
                'name' => $displayName,
                'status' => 'new',
                'source' => $source,
            ]);

            $this->attachMissingIdentities($lead, $phone, $email, $facebookName, $instagramUsername);
            $this->leadActivity->recordCreated($lead, $source);

            return $lead->fresh('identities');
        } catch (\Throwable $e) {
            Log::warning('Auto-create lead failed', [
                'company_id' => $companyId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function findExisting(
        int $companyId,
        ?string $phone,
        ?string $email,
        ?string $facebookName,
        ?string $instagramUsername
    ): ?Lead {
        if ($phone) {
            $normalized = $this->twilioCompany->normalizePhone($phone);
            $digits = preg_replace('/\D+/', '', $normalized) ?? '';
            $lead = $this->crmLookup->findLeadByPhone($companyId, $normalized, $digits);
            if ($lead) {
                return $lead;
            }
        }

        if ($email) {
            $lead = $this->crmLookup->findLeadByEmail($companyId, $email);
            if ($lead) {
                return $lead;
            }
        }

        if ($facebookName) {
            $lead = $this->findByIdentity($companyId, LeadIdentity::TYPE_FACEBOOK, $facebookName);
            if ($lead) {
                return $lead;
            }
        }

        if ($instagramUsername) {
            return $this->findByIdentity($companyId, LeadIdentity::TYPE_INSTAGRAM, $instagramUsername);
        }

        return null;
    }

    protected function findByIdentity(int $companyId, string $type, string $value): ?Lead
    {
        $normalized = LeadIdentity::normalize($type, $value);
        if ($normalized === '') {
            return null;
        }

        return LeadIdentity::query()
            ->where('type', $type)
            ->where('normalized_value', $normalized)
            ->whereHas('lead', fn ($q) => $q->where('company_id', $companyId))
            ->with('lead.identities')
            ->first()
            ?->lead;
    }

    protected function attachMissingIdentities(
        Lead $lead,
        ?string $phone,
        ?string $email,
        ?string $facebookName,
        ?string $instagramUsername
    ): void {
        if ($phone && ! $this->identityOwnedByOtherLead($lead, LeadIdentity::TYPE_PHONE, $phone)) {
            $lead->addIdentity(LeadIdentity::TYPE_PHONE, $phone);
        }
        if ($email && ! $this->identityOwnedByOtherLead($lead, LeadIdentity::TYPE_EMAIL, $email)) {
            $lead->addIdentity(LeadIdentity::TYPE_EMAIL, $email);
        }
        if ($facebookName && ! $this->identityOwnedByOtherLead($lead, LeadIdentity::TYPE_FACEBOOK, $facebookName)) {
            $lead->addIdentity(LeadIdentity::TYPE_FACEBOOK, $facebookName);
        }
        if ($instagramUsername && ! $this->identityOwnedByOtherLead($lead, LeadIdentity::TYPE_INSTAGRAM, $instagramUsername)) {
            $lead->addIdentity(LeadIdentity::TYPE_INSTAGRAM, $instagramUsername);
        }
    }

    protected function identityOwnedByOtherLead(Lead $lead, string $type, string $value): bool
    {
        $normalized = LeadIdentity::normalize($type, $value);
        if ($normalized === '') {
            return false;
        }

        return LeadIdentity::query()
            ->where('type', $type)
            ->where('normalized_value', $normalized)
            ->where('lead_id', '!=', $lead->id)
            ->whereHas('lead', fn ($q) => $q->where('company_id', $lead->company_id))
            ->exists();
    }

    protected function cleanPhone(int $companyId, ?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '' || str_starts_with($phone, 'client:')) {
            return null;
        }

        $normalized = $this->twilioCompany->normalizePhone($phone);
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        if (strlen($digits) < 7) {
            return null;
        }

        if ($this->isCompanyPhone($companyId, $normalized, $digits)) {
            return null;
        }

        return $normalized;
    }

    protected function cleanEmail(int $companyId, ?string $email): ?string
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $local = explode('@', $email)[0] ?? '';
        if (preg_match('/^(no-?reply|mailer-daemon|postmaster|notifications?|bounce|donotreply)/i', $local)) {
            return null;
        }

        $owned = SharedInbox::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->exists();
        if ($owned) {
            return null;
        }

        $userOwned = User::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->exists();
        if ($userOwned) {
            return null;
        }

        return $email;
    }

    protected function cleanSocial(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (FacebookConversation::isPlaceholderName($value)) {
            return null;
        }

        return $value;
    }

    protected function displayName(
        ?string $name,
        ?string $email,
        ?string $phone,
        ?string $facebookName,
        ?string $instagramUsername
    ): string {
        foreach ([$name, $facebookName, $instagramUsername] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! $this->isPlaceholderName($candidate)) {
                return $candidate;
            }
        }

        if ($email && str_contains($email, '@')) {
            $local = str_replace(['.', '_', '-'], ' ', explode('@', $email)[0]);

            return ucwords($local);
        }

        return $phone ?: 'New lead';
    }

    protected function isPlaceholderName(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || strcasecmp($name, 'New lead') === 0) {
            return true;
        }
        if (str_contains($name, '@')) {
            return true;
        }

        $digits = preg_replace('/\D+/', '', $name) ?? '';

        return strlen($digits) >= 7 && strlen($digits) >= (int) (strlen($name) * 0.6);
    }

    protected function isCompanyPhone(int $companyId, string $normalized, string $digits): bool
    {
        $candidates = TwilioPhoneNumber::query()
            ->where('company_id', $companyId)
            ->pluck('phone_number')
            ->merge(
                User::query()
                    ->where('company_id', $companyId)
                    ->where(function ($q) {
                        $q->whereNotNull('twilio_number')
                            ->orWhereNotNull('twilio_sms_number');
                    })
                    ->get(['twilio_number', 'twilio_sms_number'])
                    ->flatMap(fn (User $user) => [$user->twilio_number, $user->twilio_sms_number])
            )
            ->filter();

        foreach ($candidates as $candidate) {
            $candNorm = $this->twilioCompany->normalizePhone((string) $candidate);
            if ($candNorm === $normalized) {
                return true;
            }
            $candDigits = preg_replace('/\D+/', '', $candNorm) ?? '';
            if ($candDigits !== '' && $digits !== '' && (str_ends_with($digits, substr($candDigits, -8)) || str_ends_with($candDigits, substr($digits, -8)))) {
                return true;
            }
        }

        return false;
    }

    protected function callReason(?string $direction): string
    {
        return str_contains(strtolower((string) $direction), 'outbound')
            ? 'outbound call'
            : 'inbound call';
    }
}
