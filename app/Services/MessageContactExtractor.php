<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\FacebookMessage;

class MessageContactExtractor
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany
    ) {}

    /**
     * @return array{phones: list<string>, emails: list<string>}
     */
    public function fromFacebookConversation(FacebookConversation $conversation): array
    {
        $messages = FacebookMessage::query()
            ->where('facebook_conversation_id', $conversation->id)
            ->whereNotNull('text')
            ->where('text', '!=', '')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get(['text', 'direction']);

        $inbound = [];
        $outbound = [];
        foreach ($messages as $message) {
            $text = (string) $message->text;
            if (strtolower((string) $message->direction) === 'inbound') {
                $inbound[] = $text;
            } else {
                $outbound[] = $text;
            }
        }

        $fromInbound = $this->fromTexts($inbound, (string) $conversation->peer_id);
        $fromOutbound = $this->fromTexts($outbound, (string) $conversation->peer_id);

        return [
            'phones' => array_values(array_unique(array_merge($fromInbound['phones'], $fromOutbound['phones']))),
            'emails' => array_values(array_unique(array_merge($fromInbound['emails'], $fromOutbound['emails']))),
        ];
    }

    /**
     * @param  list<string>  $texts
     * @return array{phones: list<string>, emails: list<string>}
     */
    public function fromTexts(array $texts, ?string $ignoreId = null): array
    {
        $phones = [];
        $emails = [];

        foreach ($texts as $text) {
            $text = (string) $text;
            if ($text === '') {
                continue;
            }

            foreach ($this->emailsIn($text) as $email) {
                $emails[$email] = $email;
            }
            foreach ($this->phonesIn($text, $ignoreId) as $phone) {
                $phones[$phone] = $phone;
            }
        }

        return [
            'phones' => array_values($phones),
            'emails' => array_values($emails),
        ];
    }

    /**
     * @return list<string>
     */
    protected function emailsIn(string $text): array
    {
        if (! preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
            return [];
        }

        $skipDomains = ['facebook.com', 'messenger.com', 'instagram.com', 'meta.com', 'fb.com', 'threads.net'];
        $out = [];

        foreach ($matches[0] as $email) {
            $email = strtolower(trim($email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
            if (in_array($domain, $skipDomains, true)) {
                continue;
            }
            if (preg_match('/^(no-?reply|mailer-daemon|postmaster|notifications?|bounce|donotreply)/i', $local)) {
                continue;
            }

            $out[] = $email;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected function phonesIn(string $text, ?string $ignoreId = null): array
    {
        $ignoreDigits = preg_replace('/\D+/', '', (string) $ignoreId) ?? '';
        $candidates = [];

        if (preg_match_all('/(?<!\d)(?:\+?63|0)?9\d(?:[\s().\-]*\d){8}(?!\d)/', $text, $matches)) {
            $candidates = array_merge($candidates, $matches[0]);
        }
        if (preg_match_all('/(?<!\d)\+\d{1,3}[\s().\-]*\d(?:[\s().\-]*\d){7,12}(?!\d)/', $text, $matches)) {
            $candidates = array_merge($candidates, $matches[0]);
        }

        $out = [];
        foreach ($candidates as $raw) {
            $normalized = $this->normalizeExtractedPhone($raw);
            if (! $normalized) {
                continue;
            }

            $digits = preg_replace('/\D+/', '', $normalized) ?? '';
            if ($ignoreDigits !== '' && ($digits === $ignoreDigits || str_ends_with($ignoreDigits, $digits) || str_ends_with($digits, $ignoreDigits))) {
                continue;
            }

            $out[] = $normalized;
        }

        return $out;
    }

    protected function normalizeExtractedPhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        if (preg_match('/^09\d{9}$/', $digits) || preg_match('/^9\d{9}$/', $digits)) {
            $digits = '63'.ltrim($digits, '0');
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return $this->twilioCompany->normalizePhone($digits);
    }
}
