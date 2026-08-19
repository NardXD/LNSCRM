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
     * @return array{phones: list<string>, emails: list<string>, names: list<string>}
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

        $fromInbound = $this->fromTexts(array_reverse($inbound), (string) $conversation->peer_id);
        $fromOutbound = $this->fromTexts($outbound, (string) $conversation->peer_id);

        $names = $fromInbound['names'];
        if ($names === []) {
            $names = $fromOutbound['names'];
        }

        return [
            'phones' => array_values(array_unique(array_merge($fromInbound['phones'], $fromOutbound['phones']))),
            'emails' => array_values(array_unique(array_merge($fromInbound['emails'], $fromOutbound['emails']))),
            'names' => $names,
        ];
    }

    /**
     * @return array{phones: list<string>, emails: list<string>, names: list<string>}
     */
    public function applyToConversation(FacebookConversation $conversation): array
    {
        $extracted = $this->fromFacebookConversation($conversation);
        $name = $extracted['names'][0] ?? null;
        if ($name && FacebookConversation::isPlaceholderName($conversation->name)) {
            $conversation->name = $name;
            $conversation->save();
        }

        return $extracted;
    }

    /**
     * @param  list<string>  $texts
     * @return array{phones: list<string>, emails: list<string>, names: list<string>}
     */
    public function fromTexts(array $texts, ?string $ignoreId = null): array
    {
        $phones = [];
        $emails = [];
        $names = [];

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
            foreach ($this->namesIn($text) as $name) {
                $names[$name] = $name;
            }
        }

        if ($names === [] && $emails !== []) {
            $fromEmail = $this->nameFromEmail((string) array_key_first($emails));
            if ($fromEmail) {
                $names[$fromEmail] = $fromEmail;
            }
        }

        return [
            'phones' => array_values($phones),
            'emails' => array_values($emails),
            'names' => array_values($names),
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

    /**
     * @return list<string>
     */
    protected function namesIn(string $text): array
    {
        $patterns = [
            '/\b(?:my name is|i am|i[\x{2019}\']m|this is|name\s*[:\-]|i go by)\s+([A-Za-z][A-Za-z.\'\-]*(?:\s+[A-Za-z][A-Za-z.\'\-]*){0,3})/iu',
            '/\b(?:ako si|pangalan ko(?:\s+ay)?|ang pangalan ko(?:\s+ay)?)\s+([A-Za-z][A-Za-z.\'\-]*(?:\s+[A-Za-z][A-Za-z.\'\-]*){0,3})/iu',
        ];

        $out = [];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }
            foreach ($matches[1] as $raw) {
                $name = $this->cleanNameCandidate((string) $raw);
                if ($name) {
                    $out[$name] = $name;
                }
            }
        }

        return array_values($out);
    }

    protected function cleanNameCandidate(string $raw): ?string
    {
        $stop = [
            'po', 'pala', 'and', 'from', 'of', 'here', 'interested', 'looking', 'thanks', 'thank',
            'sir', 'maam', "ma'am", 'hello', 'hi', 'hey', 'good', 'morning', 'afternoon', 'evening',
            'yes', 'yeah', 'ok', 'okay', 'please', 'help', 'inquiry', 'about', 'regarding', 'lang',
            'naman', 'kasi', 'because', 'for', 'to', 'my', 'number', 'email', 'phone', 'contact',
            'ng', 'sa', 'ay', 'the', 'a', 'an', 'your', 'our', 'in', 'on', 'with', 'following',
            'following-up', 'follow', 'up', 'just', 'wanted', 'would', 'like', 'need', 'asking',
        ];

        $words = preg_split('/\s+/', trim($raw)) ?: [];
        $kept = [];
        foreach ($words as $word) {
            $bare = strtolower((string) preg_replace("/[^a-z']/i", '', $word));
            if ($bare === '' || in_array($bare, $stop, true) || preg_match('/\d/', $word)) {
                break;
            }
            $kept[] = $word;
            if (count($kept) >= 4) {
                break;
            }
        }

        if ($kept === []) {
            return null;
        }

        $name = mb_convert_case(implode(' ', $kept), MB_CASE_TITLE, 'UTF-8');
        if (FacebookConversation::isPlaceholderName($name)) {
            return null;
        }

        $letters = preg_replace('/[^a-z]/i', '', $name) ?? '';
        if (strlen($letters) < 3 || strlen($name) > 80) {
            return null;
        }

        return $name;
    }

    protected function nameFromEmail(string $email): ?string
    {
        $local = strtolower(explode('@', $email)[0] ?? '');
        $local = (string) preg_replace('/[0-9]+/', '', $local);
        $blocked = ['admin', 'info', 'sales', 'hello', 'contact', 'support', 'user', 'mail', 'noreply', 'no-reply'];
        if ($local === '' || in_array($local, $blocked, true)) {
            return null;
        }

        $parts = preg_split('/[._\-]+/', $local) ?: [];
        $parts = array_values(array_filter($parts, fn ($part) => strlen((string) $part) >= 2));
        if ($parts === []) {
            return null;
        }

        return $this->cleanNameCandidate(implode(' ', array_slice($parts, 0, 4)));
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
