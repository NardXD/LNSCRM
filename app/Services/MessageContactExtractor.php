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
            ->get(['text', 'direction'])
            ->reverse()
            ->values();

        $inbound = [];
        $outbound = [];
        $promptNames = [];
        $awaitingName = false;

        foreach ($messages as $message) {
            $text = (string) $message->text;
            $isInbound = strtolower((string) $message->direction) === 'inbound';

            if ($awaitingName && $isInbound && ! $this->isNamePrompt($text)) {
                foreach ($this->bareNamesIn($text) as $name) {
                    $promptNames[$name] = $name;
                }
            }

            $awaitingName = $this->isNamePrompt($text);

            if ($isInbound) {
                $inbound[] = $text;
            } else {
                $outbound[] = $text;
            }
        }

        $fromInbound = $this->fromTexts($inbound, (string) $conversation->peer_id);
        $fromOutbound = $this->fromTexts($outbound, (string) $conversation->peer_id);

        $names = array_values(array_unique(array_merge(
            array_values($promptNames),
            $fromInbound['names']
        )));
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
     * Pull name, phone, and email from labeled lines in a message or email body.
     * Keywords are matched case-insensitively; comma-separated aliases are allowed.
     *
     * @param  array{name?: string, phone?: string, email?: string, name_keyword?: string, phone_keyword?: string, email_keyword?: string}  $keywords
     * @return array{name: ?string, phone: ?string, email: ?string}
     */
    public function fromKeywords(string $text, array $keywords): array
    {
        $nameKeys = $this->keywordList($keywords['name'] ?? $keywords['name_keyword'] ?? '');
        $phoneKeys = $this->keywordList($keywords['phone'] ?? $keywords['phone_keyword'] ?? '');
        $emailKeys = $this->keywordList($keywords['email'] ?? $keywords['email_keyword'] ?? '');
        $stops = array_values(array_unique(array_merge($nameKeys, $phoneKeys, $emailKeys)));

        $nameRaw = $this->valueAfterKeyword($text, $nameKeys, $stops);
        $phoneRaw = $this->valueAfterKeyword($text, $phoneKeys, $stops);
        $emailRaw = $this->valueAfterKeyword($text, $emailKeys, $stops);

        $phone = null;
        if ($phoneRaw) {
            $phone = $this->phonesIn($phoneRaw)[0] ?? $this->normalizeExtractedPhone($phoneRaw);
        }

        $email = null;
        if ($emailRaw) {
            $email = $this->emailsIn($emailRaw)[0] ?? null;
            $fallback = strtolower(trim($emailRaw));
            if (! $email && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                $email = $fallback;
            }
        }

        return [
            'name' => $nameRaw ? $this->labeledName($nameRaw) : null,
            'phone' => $phone,
            'email' => $email,
        ];
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
    protected function keywordList(mixed $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/\s*,\s*/', $raw) ?: [])
            ->map(fn ($keyword) => trim((string) $keyword, " \t:-–—."))
            ->filter()
            ->unique(fn ($keyword) => mb_strtolower($keyword))
            ->sortByDesc(fn ($keyword) => mb_strlen($keyword))
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $stopLabels
     */
    protected function valueAfterKeyword(string $text, array $labels, array $stopLabels): ?string
    {
        if ($labels === []) {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($normalized) === '') {
            return null;
        }

        foreach ($labels as $label) {
            $quoted = preg_quote($label, '/');
            if (! preg_match('/(?:^|[\n\r]|[\s\*])'.$quoted.'\s*[:：\-–—.]*\s*(.+)/iu', $normalized, $match)) {
                continue;
            }

            $value = (string) (preg_split('/\R/u', (string) ($match[1] ?? ''))[0] ?? '');
            foreach ($stopLabels as $stop) {
                if (strcasecmp($stop, $label) === 0) {
                    continue;
                }
                $stopQuoted = preg_quote($stop, '/');
                $value = preg_replace('/\s+'.$stopQuoted.'\s*[:：\-–—.].*$/iu', '', $value) ?? $value;
            }

            $value = trim($value, " \t\"'*:：\-–—.");
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function labeledName(string $raw): ?string
    {
        $raw = trim((string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '', $raw));
        $cleaned = $this->cleanNameCandidate($raw);
        if ($cleaned) {
            return $cleaned;
        }

        $raw = trim($raw, " \t:-–—.");
        if ($raw === '' || mb_strlen($raw) > 80) {
            return null;
        }

        return mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
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
        $name = '([A-Za-zÑñ][A-Za-zÑñ.\'\-]*(?:\s+[A-Za-zÑñ][A-Za-zÑñ.\'\-]*){0,3})';
        $patterns = [
            '/(?:^|[^\p{L}\p{N}_])(?:\*{0,2})(?:(?:full|complete|legal)\s*)?names?(?:\*{0,2})\s*[:：\-–—.]\s*(?:\*{0,2}\s*)?'.$name.'/iu',
            '/\b(?:my name is|i am|i[\x{2019}\']m|this is|i go by)\s+'.$name.'/iu',
            '/\b(?:ako si|pangalan ko(?:\s+ay)?|ang pangalan ko(?:\s+ay)?)\s+'.$name.'/iu',
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

    /**
     * True when the whole message is a name prompt such as "Full name:" or "Name:".
     */
    protected function isNamePrompt(string $text): bool
    {
        $normalized = strtolower(trim($text));
        $normalized = preg_replace('/[*_`#]+/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);
        $normalized = trim($normalized, " \t:-–—.?!。");

        return (bool) preg_match(
            '/^(?:please\s+|kindly\s+)?(?:(?:send|provide|enter|type|give)(?:\s+(?:us|me))?\s+)?(?:your\s+)?(?:full|complete|legal)?\s*names?$/',
            $normalized
        );
    }

    /**
     * @return list<string>
     */
    protected function bareNamesIn(string $text): array
    {
        $labeled = $this->namesIn($text);
        if ($labeled !== []) {
            return $labeled;
        }

        $firstLine = trim((string) (preg_split('/\R/u', $text)[0] ?? ''));
        $name = $this->cleanNameCandidate($firstLine);

        return $name ? [$name] : [];
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
