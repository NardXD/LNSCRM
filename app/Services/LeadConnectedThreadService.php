<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\SmsConversation;
use App\Models\ViberConversation;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Collection;

class LeadConnectedThreadService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany
    ) {}

    /**
     * Best connected channel thread per lead for the current page.
     *
     * @param  iterable<Lead>  $leads
     * @return array<int, array{channel: string, label: string, url: string, conversation_id: int}>
     */
    public function forLeads(int $companyId, iterable $leads): array
    {
        /** @var Collection<int, Lead> $leads */
        $leads = collect($leads)->filter(fn ($lead) => $lead instanceof Lead)->values();
        if ($leads->isEmpty()) {
            return [];
        }

        $leadIds = $leads->map(fn (Lead $lead) => (int) $lead->id)->all();
        $phonesByLead = [];
        $emailsByLead = [];
        $socialByLead = [];
        $allPhoneDigits = [];
        $allEmails = [];
        $allSocial = [];

        foreach ($leads as $lead) {
            $id = (int) $lead->id;
            $phonesByLead[$id] = [];
            $emailsByLead[$id] = [];
            $socialByLead[$id] = [];

            foreach ($lead->identities ?? [] as $identity) {
                $type = (string) $identity->type;
                $value = trim((string) $identity->value);
                if ($value === '') {
                    continue;
                }

                if ($type === LeadIdentity::TYPE_PHONE) {
                    $digits = preg_replace('/\D+/', '', $this->twilioCompany->normalizePhone($value)) ?? '';
                    if ($digits !== '') {
                        $phonesByLead[$id][] = $digits;
                        $allPhoneDigits[] = $digits;
                    }
                } elseif ($type === LeadIdentity::TYPE_EMAIL) {
                    $email = strtolower($value);
                    $emailsByLead[$id][] = $email;
                    $allEmails[] = $email;
                } elseif (in_array($type, [LeadIdentity::TYPE_FACEBOOK, LeadIdentity::TYPE_INSTAGRAM], true)) {
                    $name = strtolower($value);
                    if (! FacebookConversation::isPlaceholderName($name)) {
                        $socialByLead[$id][] = $name;
                        $allSocial[] = $name;
                    }
                }
            }

            foreach ($lead->socialNames() as $name) {
                $socialByLead[$id][] = $name;
                $allSocial[] = $name;
            }

            $phonesByLead[$id] = array_values(array_unique($phonesByLead[$id]));
            $emailsByLead[$id] = array_values(array_unique($emailsByLead[$id]));
            $socialByLead[$id] = array_values(array_unique($socialByLead[$id]));
        }

        $allPhoneDigits = array_values(array_unique($allPhoneDigits));
        $allEmails = array_values(array_unique($allEmails));
        $allSocial = array_values(array_unique($allSocial));

        /** @var array<int, list<array{channel: string, label: string, url: string, conversation_id: int, at: ?string, score: int}>> $candidates */
        $candidates = [];
        foreach ($leadIds as $leadId) {
            $candidates[$leadId] = [];
        }

        $this->collectInbox($companyId, $leadIds, $emailsByLead, $allEmails, $candidates);
        $this->collectPhoneChannel(
            $companyId,
            $phonesByLead,
            $allPhoneDigits,
            $candidates,
            WhatsAppConversation::class,
            'whatsapp',
            'WhatsApp',
            fn (WhatsAppConversation $c) => [(string) $c->wa_id, (string) $c->phone],
            fn (WhatsAppConversation $c) => url('/whatsapp').'?conversation='.$c->id
        );
        $this->collectPhoneChannel(
            $companyId,
            $phonesByLead,
            $allPhoneDigits,
            $candidates,
            ViberConversation::class,
            'viber',
            'Viber',
            fn (ViberConversation $c) => [(string) $c->viber_user_id, (string) $c->phone],
            fn (ViberConversation $c) => url('/viber').'?conversation='.$c->id
        );
        $this->collectPhoneChannel(
            $companyId,
            $phonesByLead,
            $allPhoneDigits,
            $candidates,
            SmsConversation::class,
            'sms',
            'SMS',
            fn (SmsConversation $c) => [(string) $c->peer_phone],
            fn (SmsConversation $c) => url('/sms').'?conversation='.$c->id
        );
        $this->collectFacebook($companyId, $socialByLead, $allSocial, $candidates);

        $preferred = [];
        foreach ($leads as $lead) {
            $preferred[(int) $lead->id] = $this->preferredChannel($lead->source);
        }

        $result = [];
        foreach ($leadIds as $leadId) {
            $best = $this->pickBest($candidates[$leadId] ?? [], $preferred[$leadId] ?? null);
            if ($best) {
                $result[$leadId] = [
                    'channel' => $best['channel'],
                    'label' => $best['label'],
                    'url' => $best['url'],
                    'conversation_id' => $best['conversation_id'],
                ];
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $leadIds
     * @param  array<int, list<string>>  $emailsByLead
     * @param  list<string>  $allEmails
     * @param  array<int, list<array{channel: string, label: string, url: string, conversation_id: int, at: ?string, score: int}>>  $candidates
     */
    protected function collectInbox(
        int $companyId,
        array $leadIds,
        array $emailsByLead,
        array $allEmails,
        array &$candidates
    ): void {
        $query = InboxConversation::query()
            ->notMerged()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($leadIds, $allEmails) {
                $q->whereIn('lead_id', $leadIds);
                if ($allEmails !== []) {
                    $q->orWhere(function ($emailQuery) use ($allEmails) {
                        foreach ($allEmails as $email) {
                            $emailQuery->orWhereRaw('LOWER(TRIM(from_email)) = ?', [$email]);
                        }
                    });
                }
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'lead_id', 'from_email', 'last_message_at']);

        foreach ($query as $conversation) {
            $url = url('/inbox').'?conversation='.$conversation->id;
            $at = $conversation->last_message_at?->toIso8601String();
            $from = strtolower(trim((string) $conversation->from_email));

            if ($conversation->lead_id && isset($candidates[(int) $conversation->lead_id])) {
                $candidates[(int) $conversation->lead_id][] = [
                    'channel' => 'inbox',
                    'label' => 'Inbox',
                    'url' => $url,
                    'conversation_id' => (int) $conversation->id,
                    'at' => $at,
                    'score' => 1000,
                ];
            }

            if ($from === '') {
                continue;
            }

            foreach ($emailsByLead as $leadId => $emails) {
                if (! in_array($from, $emails, true)) {
                    continue;
                }
                $candidates[$leadId][] = [
                    'channel' => 'inbox',
                    'label' => 'Inbox',
                    'url' => $url,
                    'conversation_id' => (int) $conversation->id,
                    'at' => $at,
                    'score' => 100,
                ];
            }
        }
    }

    /**
     * @template T of WhatsAppConversation|ViberConversation|SmsConversation
     *
     * @param  array<int, list<string>>  $phonesByLead
     * @param  list<string>  $allPhoneDigits
     * @param  array<int, list<array{channel: string, label: string, url: string, conversation_id: int, at: ?string, score: int}>>  $candidates
     * @param  class-string<T>  $model
     * @param  callable(T): list<string>  $phoneFields
     * @param  callable(T): string  $url
     */
    protected function collectPhoneChannel(
        int $companyId,
        array $phonesByLead,
        array $allPhoneDigits,
        array &$candidates,
        string $model,
        string $channel,
        string $label,
        callable $phoneFields,
        callable $url
    ): void {
        $suffixes = [];
        foreach ($allPhoneDigits as $digits) {
            if (strlen($digits) >= 7) {
                $suffixes[] = substr($digits, -8);
            }
        }
        $suffixes = array_values(array_unique(array_filter($suffixes)));
        if ($suffixes === []) {
            return;
        }

        $columns = match ($channel) {
            'whatsapp' => ['id', 'wa_id', 'phone', 'last_message_at'],
            'viber' => ['id', 'viber_user_id', 'phone', 'last_message_at'],
            default => ['id', 'peer_phone', 'last_message_at'],
        };

        $rows = $model::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($suffixes, $channel) {
                foreach ($suffixes as $suffix) {
                    if ($channel === 'whatsapp') {
                        $q->orWhere('phone', 'like', '%'.$suffix)
                            ->orWhere('wa_id', 'like', '%'.$suffix);
                    } elseif ($channel === 'viber') {
                        $q->orWhere('phone', 'like', '%'.$suffix)
                            ->orWhere('viber_user_id', 'like', '%'.$suffix);
                    } else {
                        $q->orWhere('peer_phone', 'like', '%'.$suffix);
                    }
                }
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get($columns);

        foreach ($rows as $conversation) {
            $fields = $phoneFields($conversation);
            $fieldDigits = [];
            foreach ($fields as $field) {
                if ($field === '' || $field === '0') {
                    continue;
                }
                $digits = preg_replace('/\D+/', '', $this->twilioCompany->normalizePhone($field)) ?? '';
                if ($digits !== '') {
                    $fieldDigits[] = $digits;
                }
            }
            if ($fieldDigits === []) {
                continue;
            }

            $at = $conversation->last_message_at?->toIso8601String();
            $deepLink = $url($conversation);

            foreach ($phonesByLead as $leadId => $leadPhones) {
                if (! $this->phonesMatch($leadPhones, $fieldDigits)) {
                    continue;
                }
                $candidates[$leadId][] = [
                    'channel' => $channel,
                    'label' => $label,
                    'url' => $deepLink,
                    'conversation_id' => (int) $conversation->id,
                    'at' => $at,
                    'score' => 100,
                ];
            }
        }
    }

    /**
     * @param  array<int, list<string>>  $socialByLead
     * @param  list<string>  $allSocial
     * @param  array<int, list<array{channel: string, label: string, url: string, conversation_id: int, at: ?string, score: int}>>  $candidates
     */
    protected function collectFacebook(
        int $companyId,
        array $socialByLead,
        array $allSocial,
        array &$candidates
    ): void {
        if ($allSocial === []) {
            return;
        }

        $rows = FacebookConversation::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($allSocial) {
                foreach ($allSocial as $name) {
                    $q->orWhereRaw('LOWER(TRIM(name)) = ?', [$name])
                        ->orWhereRaw('LOWER(TRIM(username)) = ?', [$name]);
                }
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'channel', 'name', 'username', 'last_message_at']);

        foreach ($rows as $conversation) {
            $names = array_values(array_filter([
                strtolower(trim((string) $conversation->name)),
                strtolower(trim((string) $conversation->username)),
            ], fn ($name) => $name !== '' && ! FacebookConversation::isPlaceholderName($name)));
            if ($names === []) {
                continue;
            }

            $isIg = $conversation->channel === 'instagram';
            $channel = $isIg ? 'instagram' : 'facebook';
            $label = $isIg ? 'Instagram' : 'Facebook';
            $url = url('/facebook').'?conversation='.$conversation->id;
            $at = $conversation->last_message_at?->toIso8601String();

            foreach ($socialByLead as $leadId => $leadNames) {
                if (! $this->namesMatch($leadNames, $names)) {
                    continue;
                }
                $candidates[$leadId][] = [
                    'channel' => $channel,
                    'label' => $label,
                    'url' => $url,
                    'conversation_id' => (int) $conversation->id,
                    'at' => $at,
                    'score' => 100,
                ];
            }
        }
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    protected function phonesMatch(array $left, array $right): bool
    {
        foreach ($left as $a) {
            foreach ($right as $b) {
                if ($a === $b) {
                    return true;
                }
                $len = min(10, strlen($a), strlen($b));
                if ($len >= 7 && substr($a, -$len) === substr($b, -$len)) {
                    return true;
                }
                if (
                    strlen($a) >= 8 && strlen($b) >= 8
                    && (str_ends_with($a, substr($b, -8)) || str_ends_with($b, substr($a, -8)))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    protected function namesMatch(array $left, array $right): bool
    {
        foreach ($left as $a) {
            if ($a === '' || FacebookConversation::isPlaceholderName($a)) {
                continue;
            }
            foreach ($right as $b) {
                if ($b === '' || FacebookConversation::isPlaceholderName($b)) {
                    continue;
                }
                if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function preferredChannel(?string $source): ?string
    {
        $source = strtolower(trim((string) $source));
        if ($source === '') {
            return null;
        }
        if (str_contains($source, 'instagram')) {
            return 'instagram';
        }
        if (str_contains($source, 'facebook')) {
            return 'facebook';
        }
        if (str_contains($source, 'whatsapp')) {
            return 'whatsapp';
        }
        if (str_contains($source, 'viber')) {
            return 'viber';
        }
        if (str_contains($source, 'sms') || str_contains($source, 'text')) {
            return 'sms';
        }
        if (str_contains($source, 'email') || $source === 'inbox') {
            return 'inbox';
        }

        return null;
    }

    /**
     * @param  list<array{channel: string, label: string, url: string, conversation_id: int, at: ?string, score: int}>  $candidates
     * @return array{channel: string, label: string, url: string, conversation_id: int, at: ?string, score: int}|null
     */
    protected function pickBest(array $candidates, ?string $preferred): ?array
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (array $a, array $b) use ($preferred) {
            $aPref = $preferred && $a['channel'] === $preferred ? 1 : 0;
            $bPref = $preferred && $b['channel'] === $preferred ? 1 : 0;
            if ($aPref !== $bPref) {
                return $bPref <=> $aPref;
            }
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? ''));
        });

        return $candidates[0];
    }
}
