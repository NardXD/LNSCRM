<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\FacebookConversation;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\PhoneCallLog;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Models\ViberConversation;
use App\Models\ViberMessage;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ContactConversationHistoryService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected FlexCrmLookupService $crmLookup
    ) {}

    /**
     * Unified timeline across WhatsApp, Viber, SMS, Inbox, Calls, and Facebook (name-linked).
     *
     * @return array{
     *     query: array{phone: ?string, email: ?string, name: ?string, lead_id: ?int},
     *     contact: array<string, mixed>,
     *     threads: list<array<string, mixed>>,
     *     events: list<array<string, mixed>>,
     *     notes: list<string>
     * }
     */
    public function history(int $companyId, ?string $phone = null, ?string $email = null, int $limit = 100, ?string $name = null, ?int $leadId = null): array
    {
        $phone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;
        $email = $email !== null && trim($email) !== '' ? strtolower(trim($email)) : null;
        $name = $name !== null && trim($name) !== '' ? trim($name) : null;

        if (! $phone && ! $email && ! $name && ! $leadId) {
            return [
                'query' => ['phone' => null, 'email' => null, 'name' => null, 'lead_id' => null],
                'contact' => ['found' => false, 'display_name' => null],
                'threads' => [],
                'events' => [],
                'notes' => ['Provide a phone number, email, name, or lead to search.'],
            ];
        }

        $normalizedPhone = $phone ? $this->twilioCompany->normalizePhone($phone) : null;
        $digits = $normalizedPhone ? (preg_replace('/\D+/', '', $normalizedPhone) ?? '') : '';

        $lookup = $normalizedPhone
            ? $this->crmLookup->lookup($companyId, $normalizedPhone)
            : ['found' => false, 'client' => null, 'lead' => null, 'phone_contact' => null, 'display_name' => $name, 'recent_calls' => []];

        $lead = $leadId ? $this->crmLookup->findLeadById($companyId, $leadId) : null;
        if (! $lead && isset($lookup['lead']['id'])) {
            $lead = $this->crmLookup->findLeadById($companyId, (int) $lookup['lead']['id']);
        }
        if (! $lead && $normalizedPhone) {
            $lead = $this->crmLookup->findLeadByPhone($companyId, $normalizedPhone, $digits);
        }
        if (! $lead && $email) {
            $lead = $this->crmLookup->findLeadByEmail($companyId, $email);
        }
        if (! $lead && $name) {
            $lead = $this->crmLookup->findLeadByName($companyId, $name);
        }
        if ($lead) {
            $lead->loadMissing('identities');
        }

        // Enrich contact via email if phone lookup missed.
        $client = isset($lookup['client']) && is_array($lookup['client'])
            ? Client::query()->where('company_id', $companyId)->find($lookup['client']['id'] ?? 0)
            : null;

        if (! $client && $email) {
            $client = $this->findClientByEmail($companyId, $email);
        }

        if (! $client && $name) {
            $client = Client::query()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($name) {
                    $q->where('name', 'like', '%'.$name.'%')
                        ->orWhere('contact_person', 'like', '%'.$name.'%');
                })
                ->first();
        }

        if ($client) {
            $client->loadMissing('contacts');
        }

        // Seed lookup name for Facebook matching when only a name is known.
        if ($name && empty($lookup['phone_contact'])) {
            $lookup['phone_contact'] = ['name' => $name];
        }
        if ($name && empty($lookup['display_name'])) {
            $lookup['display_name'] = $name;
        }

        $emails = $this->collectEmails($email, $client, $lookup['phone_contact']['email'] ?? null, $lead);
        $phones = $this->collectPhones($normalizedPhone, $digits, $client, $lead);

        $threads = collect()
            ->merge($this->safeChannel('whatsapp-threads', fn () => $this->whatsappThreads($companyId, $phones)))
            ->merge($this->safeChannel('viber-threads', fn () => $this->viberThreads($companyId, $phones)))
            ->merge($this->safeChannel('sms-threads', fn () => $this->smsThreads($companyId, $phones)))
            ->merge($this->safeChannel('inbox-threads', fn () => $this->inboxThreads($companyId, $emails)))
            ->merge($this->safeChannel('facebook-threads', fn () => $this->facebookThreads($companyId, $client, $lookup, $lead)))
            ->values()
            ->all();

        $events = collect()
            ->merge($this->safeChannel('whatsapp-events', fn () => $this->whatsappEvents($companyId, $phones, 40)))
            ->merge($this->safeChannel('viber-events', fn () => $this->viberEvents($companyId, $phones, 40)))
            ->merge($this->safeChannel('sms-events', fn () => $this->smsEvents($companyId, $phones, 40)))
            ->merge($this->safeChannel('inbox-events', fn () => $this->inboxEvents($companyId, $emails, 40)))
            ->merge($this->safeChannel('call-events', fn () => $this->callEvents($companyId, $phones, 40)))
            ->merge($this->safeChannel('facebook-events', fn () => $this->facebookEvents($companyId, $threads, 40)))
            ->sortByDesc(fn (array $e) => $e['at'] ?? '')
            ->values()
            ->take(max(10, $limit))
            ->values()
            ->all();

        $notes = [];
        if ($phones === [] && $emails !== []) {
            $notes[] = 'Phone-based channels (WhatsApp, Viber, SMS, calls) need a phone number.';
        }
        if ($emails === [] && $phones !== []) {
            $notes[] = 'Inbox (email) matches need an email address.';
        }
        $notes[] = 'Facebook/Instagram threads match by CRM client name when available (Meta does not expose phone/email on messages).';

        $displayName = $lead?->name
            ?? $client?->name
            ?? ($lookup['display_name'] ?? null)
            ?? ($lookup['phone_contact']['name'] ?? null)
            ?? $normalizedPhone
            ?? ($emails[0] ?? null);

        return [
            'query' => [
                'phone' => $normalizedPhone,
                'email' => $email,
                'name' => $name,
                'lead_id' => $lead?->id,
            ],
            'contact' => [
                'found' => (bool) ($lead || $client || ($lookup['found'] ?? false) || $threads !== []),
                'display_name' => $displayName,
                'lead' => $lead ? $this->crmLookup->serializeLead($lead) : null,
                'client' => $client ? [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'crm_url' => url('/client-management?client='.$client->id),
                ] : ($lookup['client'] ?? null),
                'phone_contact' => $lookup['phone_contact'] ?? null,
                'matched_phones' => array_values(array_unique(array_filter($phones))),
                'matched_emails' => $emails,
            ],
            'threads' => $threads,
            'events' => $events,
            'notes' => $notes,
        ];
    }

    /**
     * @return list<string>
     */
    protected function collectEmails(?string $email, ?Client $client, ?string $phoneContactEmail, ?Lead $lead = null): array
    {
        $emails = [];
        foreach ([$email, $phoneContactEmail, $client?->email] as $candidate) {
            $extracted = $this->extractEmail($candidate);
            if ($extracted) {
                $emails[] = $extracted;
            }
        }
        if ($client) {
            foreach ($client->contacts as $c) {
                $extracted = $this->extractEmail($c->email);
                if ($extracted) {
                    $emails[] = $extracted;
                }
            }
        }
        if ($lead) {
            foreach ($lead->emailValues() as $candidate) {
                $extracted = $this->extractEmail($candidate);
                if ($extracted) {
                    $emails[] = $extracted;
                }
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * @return list<string>
     */
    protected function collectPhones(?string $normalizedPhone, string $digits, ?Client $client, ?Lead $lead = null): array
    {
        $phones = [];
        if ($normalizedPhone) {
            $phones[] = $normalizedPhone;
        }
        if ($digits !== '') {
            $phones[] = '+'.$digits;
            $phones[] = $digits;
        }
        if ($client?->phone) {
            $phones[] = $this->twilioCompany->normalizePhone((string) $client->phone);
        }
        if ($client) {
            $client->loadMissing('contacts');
            foreach ($client->contacts as $c) {
                if ($c->phone) {
                    $phones[] = $this->twilioCompany->normalizePhone((string) $c->phone);
                }
            }
        }
        if ($lead) {
            foreach ($lead->phoneValues() as $candidate) {
                $phones[] = $this->twilioCompany->normalizePhone((string) $candidate);
            }
        }

        return array_values(array_unique(array_filter($phones)));
    }

    protected function findClientByEmail(int $companyId, string $email): ?Client
    {
        $client = Client::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->first();
        if ($client) {
            return $client;
        }

        $contact = ClientContact::query()
            ->whereHas('client', fn ($q) => $q->where('company_id', $companyId))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->with('client')
            ->first();

        return $contact?->client;
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<int, array<string, mixed>>
     */
    protected function whatsappThreads(int $companyId, array $phones): Collection
    {
        if ($phones === []) {
            return collect();
        }

        return WhatsAppConversation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (WhatsAppConversation $c) => $this->phoneListMatch($phones, [(string) $c->wa_id, (string) $c->phone]))
            ->map(fn (WhatsAppConversation $c) => [
                'channel' => 'whatsapp',
                'label' => 'WhatsApp',
                'conversation_id' => $c->id,
                'title' => $c->name ?: $c->profile_name ?: $c->wa_id,
                'preview' => $c->last_message_preview,
                'last_at' => $c->last_message_at?->toIso8601String(),
                'unread' => (int) $c->unread_count,
                'deep_link' => url('/whatsapp').'?conversation='.$c->id,
            ]);
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<int, array<string, mixed>>
     */
    protected function viberThreads(int $companyId, array $phones): Collection
    {
        if ($phones === []) {
            return collect();
        }

        return ViberConversation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (ViberConversation $c) => $this->phoneListMatch($phones, [(string) $c->viber_user_id, (string) $c->phone]))
            ->map(fn (ViberConversation $c) => [
                'channel' => 'viber',
                'label' => 'Viber',
                'conversation_id' => $c->id,
                'title' => $c->name ?: $c->viber_user_id,
                'preview' => $c->last_message_preview,
                'last_at' => $c->last_message_at?->toIso8601String(),
                'unread' => (int) $c->unread_count,
                'deep_link' => url('/viber').'?conversation='.$c->id,
            ]);
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<int, array<string, mixed>>
     */
    protected function smsThreads(int $companyId, array $phones): Collection
    {
        if ($phones === []) {
            return collect();
        }

        return SmsConversation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (SmsConversation $c) => $this->phoneListMatch($phones, [(string) $c->peer_phone]))
            ->map(fn (SmsConversation $c) => [
                'channel' => 'sms',
                'label' => 'SMS',
                'conversation_id' => $c->id,
                'title' => $c->name ?: $c->peer_phone,
                'preview' => $c->last_message_preview,
                'last_at' => $c->last_message_at?->toIso8601String(),
                'unread' => (int) $c->unread_count,
                'deep_link' => url('/sms').'?conversation='.$c->id,
            ]);
    }

    /**
     * @param  list<string>  $emails
     * @return Collection<int, array<string, mixed>>
     */
    protected function inboxThreads(int $companyId, array $emails): Collection
    {
        if ($emails === []) {
            return collect();
        }

        return InboxConversation::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($emails) {
                $this->applyInboxEmailMatch($query, $emails);
            })
            ->with('messages')
            ->orderByDesc('last_message_at')
            ->limit(80)
            ->get()
            ->filter(fn (InboxConversation $c) => $this->inboxConversationMatchesEmails($c, $emails))
            ->map(fn (InboxConversation $c) => [
                'channel' => 'inbox',
                'label' => 'Inbox',
                'conversation_id' => $c->id,
                'title' => $c->subject ?: ($c->from_name ?: $c->from_email),
                'preview' => $c->snippet,
                'last_at' => $c->last_message_at?->toIso8601String(),
                'unread' => $c->is_read ? 0 : 1,
                'deep_link' => url('/inbox').'?conversation='.$c->id,
            ]);
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return Collection<int, array<string, mixed>>
     */
    protected function facebookThreads(int $companyId, ?Client $client, array $lookup, ?Lead $lead = null): Collection
    {
        $names = [];
        if ($client?->name) {
            $names[] = strtolower(trim($client->name));
        }
        if ($client?->contact_person) {
            $names[] = strtolower(trim($client->contact_person));
        }
        if (! empty($lookup['phone_contact']['name'])) {
            $names[] = strtolower(trim((string) $lookup['phone_contact']['name']));
        }
        if ($lead) {
            foreach ($lead->socialNames() as $socialName) {
                $names[] = $socialName;
            }
        }
        $names = array_values(array_unique(array_filter($names)));
        if ($names === []) {
            return collect();
        }

        return FacebookConversation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(function (FacebookConversation $c) use ($names) {
                $candidates = array_filter([
                    strtolower(trim((string) $c->name)),
                    strtolower(trim((string) $c->username)),
                ]);
                foreach ($candidates as $cand) {
                    foreach ($names as $name) {
                        if ($cand === $name || str_contains($cand, $name) || str_contains($name, $cand)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->map(fn (FacebookConversation $c) => [
                'channel' => 'facebook',
                'label' => $c->channel === 'instagram' ? 'Instagram' : 'Facebook',
                'conversation_id' => $c->id,
                'title' => $c->name ?: $c->username ?: $c->peer_id,
                'preview' => $c->last_message_preview,
                'last_at' => $c->last_message_at?->toIso8601String(),
                'unread' => (int) $c->unread_count,
                'deep_link' => url('/facebook').'?conversation='.$c->id,
                'match_note' => 'Matched by name (Meta does not provide phone/email)',
            ]);
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<int, array<string, mixed>>
     */
    protected function whatsappEvents(int $companyId, array $phones, int $limit): Collection
    {
        if ($phones === []) {
            return collect();
        }

        $convIds = WhatsAppConversation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (WhatsAppConversation $c) => $this->phoneListMatch($phones, [(string) $c->wa_id, (string) $c->phone]))
            ->pluck('id');

        if ($convIds->isEmpty()) {
            return collect();
        }

        return WhatsAppMessage::query()
            ->where('company_id', $companyId)
            ->whereIn('whatsapp_conversation_id', $convIds)
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsAppMessage $m) => [
                'channel' => 'whatsapp',
                'label' => 'WhatsApp',
                'direction' => $m->direction,
                'preview' => $m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : ''),
                'at' => ($m->sent_at ?? $m->created_at)?->toIso8601String(),
                'conversation_id' => $m->whatsapp_conversation_id,
                'deep_link' => url('/whatsapp').'?conversation='.$m->whatsapp_conversation_id,
            ]);
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<int, array<string, mixed>>
     */
    protected function viberEvents(int $companyId, array $phones, int $limit): Collection
    {
        if ($phones === []) {
            return collect();
        }

        $convIds = ViberConversation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (ViberConversation $c) => $this->phoneListMatch($phones, [(string) $c->viber_user_id, (string) $c->phone]))
            ->pluck('id');

        if ($convIds->isEmpty()) {
            return collect();
        }

        return ViberMessage::query()
            ->where('company_id', $companyId)
            ->whereIn('viber_conversation_id', $convIds)
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get()
            ->map(fn (ViberMessage $m) => [
                'channel' => 'viber',
                'label' => 'Viber',
                'direction' => $m->direction,
                'preview' => $m->text ?: ($m->type !== 'text' ? '['.$m->type.']' : ''),
                'at' => ($m->sent_at ?? $m->created_at)?->toIso8601String(),
                'conversation_id' => $m->viber_conversation_id,
                'deep_link' => url('/viber').'?conversation='.$m->viber_conversation_id,
            ]);
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<int, array<string, mixed>>
     */
    protected function smsEvents(int $companyId, array $phones, int $limit): Collection
    {
        if ($phones === []) {
            return collect();
        }

        $convIds = SmsConversation::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(fn (SmsConversation $c) => $this->phoneListMatch($phones, [(string) $c->peer_phone]))
            ->pluck('id');

        if ($convIds->isEmpty()) {
            return collect();
        }

        return SmsMessage::query()
            ->where('company_id', $companyId)
            ->whereIn('sms_conversation_id', $convIds)
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get()
            ->map(fn (SmsMessage $m) => [
                'channel' => 'sms',
                'label' => 'SMS',
                'direction' => $m->direction,
                'preview' => $m->body,
                'at' => ($m->sent_at ?? $m->created_at)?->toIso8601String(),
                'conversation_id' => $m->sms_conversation_id,
                'deep_link' => url('/sms').'?conversation='.$m->sms_conversation_id,
            ]);
    }

    /**
     * @param  list<string>  $emails
     * @return Collection<int, array<string, mixed>>
     */
    protected function inboxEvents(int $companyId, array $emails, int $limit): Collection
    {
        if ($emails === []) {
            return collect();
        }

        return InboxConversation::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($emails) {
                $this->applyInboxEmailMatch($query, $emails);
            })
            ->with('messages')
            ->orderByDesc('last_message_at')
            ->limit(80)
            ->get()
            ->filter(fn (InboxConversation $c) => $this->inboxConversationMatchesEmails($c, $emails))
            ->take($limit)
            ->map(fn (InboxConversation $c) => [
                'channel' => 'inbox',
                'label' => 'Inbox',
                'direction' => 'inbound',
                'preview' => ($c->subject ? $c->subject.' — ' : '').($c->snippet ?: ''),
                'at' => ($c->last_message_at ?? $c->updated_at)?->toIso8601String(),
                'conversation_id' => $c->id,
                'deep_link' => url('/inbox').'?conversation='.$c->id,
            ]);
    }

    /**
     * @param  list<string>  $phones
     * @return Collection<int, array<string, mixed>>
     */
    protected function callEvents(int $companyId, array $phones, int $limit): Collection
    {
        if ($phones === []) {
            return collect();
        }

        $primary = $phones[0] ?? null;
        if (! $primary) {
            return collect();
        }

        // Reuse fuzzy matcher via lookup recent calls + broader scan
        $digits = preg_replace('/\D+/', '', $this->twilioCompany->normalizePhone($primary)) ?? '';

        return PhoneCallLog::query()
            ->where('company_id', $companyId)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->filter(function (PhoneCallLog $log) use ($phones, $digits) {
                foreach ([$log->from_number, $log->to_number] as $n) {
                    if (! $n) {
                        continue;
                    }
                    foreach ($phones as $p) {
                        if ($this->crmLookupPhonesMatch((string) $n, $p, $digits)) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->take($limit)
            ->map(fn (PhoneCallLog $log) => [
                'channel' => 'call',
                'label' => 'Call',
                'direction' => $log->direction,
                'preview' => trim(($log->status ?? '').' · '.($log->duration ? $log->duration.'s' : '')),
                'at' => ($log->started_at ?? $log->created_at)?->toIso8601String(),
                'conversation_id' => $log->id,
                'deep_link' => url('/twilio/call'),
            ]);
    }

    /**
     * @param  list<array<string, mixed>>  $threads
     * @return Collection<int, array<string, mixed>>
     */
    protected function facebookEvents(int $companyId, array $threads, int $limit): Collection
    {
        $ids = collect($threads)
            ->where('channel', 'facebook')
            ->pluck('conversation_id')
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return FacebookConversation::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (FacebookConversation $c) => [
                'channel' => 'facebook',
                'label' => $c->channel === 'instagram' ? 'Instagram' : 'Facebook',
                'direction' => 'inbound',
                'preview' => $c->last_message_preview,
                'at' => ($c->last_message_at ?? $c->updated_at)?->toIso8601String(),
                'conversation_id' => $c->id,
                'deep_link' => url('/facebook').'?conversation='.$c->id,
            ])
            ->take($limit);
    }

    /**
     * @param  list<string>  $needles
     * @param  list<string>  $haystacks
     */
    protected function phoneListMatch(array $needles, array $haystacks): bool
    {
        foreach ($haystacks as $hay) {
            if ($hay === '' || $hay === '0') {
                continue;
            }
            $hayNorm = $this->twilioCompany->normalizePhone($hay);
            $hayDigits = preg_replace('/\D+/', '', $hayNorm) ?? '';
            foreach ($needles as $needle) {
                if ($needle === '') {
                    continue;
                }
                if ($this->crmLookupPhonesMatch($hayNorm, $needle, preg_replace('/\D+/', '', $this->twilioCompany->normalizePhone($needle)) ?? '')) {
                    return true;
                }
                // Digits-only column match (WhatsApp/Viber phone field)
                $needleDigits = preg_replace('/\D+/', '', $this->twilioCompany->normalizePhone($needle)) ?? '';
                if ($hayDigits !== '' && $needleDigits !== '' && (
                    $hayDigits === $needleDigits
                    || str_ends_with($hayDigits, substr($needleDigits, -8))
                    || str_ends_with($needleDigits, substr($hayDigits, -8))
                )) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function crmLookupPhonesMatch(string $candidate, string $normalized, string $digits): bool
    {
        // Mirror FlexCrmLookupService::phonesMatch without making it public again
        $candNorm = $this->twilioCompany->normalizePhone($candidate);
        if ($candNorm === $this->twilioCompany->normalizePhone($normalized)) {
            return true;
        }

        $candDigits = preg_replace('/\D+/', '', $candNorm) ?? '';
        $normDigits = $digits !== '' ? $digits : (preg_replace('/\D+/', '', $this->twilioCompany->normalizePhone($normalized)) ?? '');
        if ($normDigits === '' || $candDigits === '') {
            return false;
        }

        $len = min(10, strlen($normDigits), strlen($candDigits));
        if ($len < 7) {
            return $normDigits === $candDigits;
        }

        return substr($normDigits, -$len) === substr($candDigits, -$len);
    }

    /**
     * @param  callable(): Collection<int, array<string, mixed>>  $fn
     * @return Collection<int, array<string, mixed>>
     */
    protected function safeChannel(string $channel, callable $fn): Collection
    {
        try {
            $result = $fn();

            return $result instanceof Collection ? $result : collect();
        } catch (\Throwable $e) {
            Log::warning('Contact history channel failed', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    protected function extractEmail(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (preg_match('/<([^>]+@[^>]+)>/', $value, $matches)) {
            return strtolower(trim($matches[1]));
        }

        $lower = strtolower($value);

        return str_contains($lower, '@') ? $lower : null;
    }

    /**
     * @param  list<string>  $emails
     */
    protected function applyInboxEmailMatch($query, array $emails): void
    {
        foreach ($emails as $email) {
            $like = '%'.$email.'%';
            $query->orWhereRaw('LOWER(from_email) = ?', [$email])
                ->orWhereRaw('LOWER(from_email) LIKE ?', [$like])
                ->orWhereHas('messages', function ($messages) use ($email, $like) {
                    $messages->whereRaw('LOWER(from_email) = ?', [$email])
                        ->orWhereRaw('LOWER(from_email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(to_emails) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(cc_emails) LIKE ?', [$like]);
                });
        }
    }

    /**
     * @param  list<string>  $emails
     */
    protected function inboxConversationMatchesEmails(InboxConversation $conversation, array $emails): bool
    {
        $from = $this->extractEmail($conversation->from_email);
        if ($from && in_array($from, $emails, true)) {
            return true;
        }

        foreach ($conversation->messages as $message) {
            $messageFrom = $this->extractEmail($message->from_email);
            if ($messageFrom && in_array($messageFrom, $emails, true)) {
                return true;
            }
            $haystack = strtolower(trim((string) $message->to_emails).' '.(string) $message->cc_emails);
            foreach ($emails as $email) {
                if ($email !== '' && str_contains($haystack, $email)) {
                    return true;
                }
            }
        }

        return false;
    }
}
