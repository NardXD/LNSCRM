<?php

namespace App\Support;

class EmailQuotedHistory
{
    public static function forwardedSubject(?string $subject): string
    {
        $value = trim((string) $subject);
        if ($value === '') {
            $value = '(no subject)';
        }

        return preg_match('/^fwd:\s*/i', $value) ? $value : 'Fwd: '.$value;
    }

    /**
     * Quoted history is noise on a reply, but on a forward it is the content the reader
     * wants. When a subject is known, only strip it if the subject is clearly a reply.
     */
    public static function isReplySubject(?string $subject): bool
    {
        return (bool) preg_match('/^\s*(?:re|aw|sv|antw|res|vs|odp)\s*(?:\[\d+\])?\s*:/i', (string) $subject);
    }

    public static function snippet(?string $html, ?string $text = null, int $limit = 500, ?string $subject = null): string
    {
        $strip = $subject === null
            ? fn (string $v): string => self::stripPlain($v)
            : fn (string $v): string => self::isReplySubject($subject) ? self::stripPlain($v) : $v;

        $fromHtml = self::collapseWhitespace($strip(self::plainFromHtml((string) $html)));
        if ($fromHtml !== '') {
            return mb_substr($fromHtml, 0, $limit);
        }

        $fromText = self::collapseWhitespace($strip((string) $text));

        return mb_substr($fromText, 0, $limit);
    }

    public static function stripPlain(string $text): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", $text);
        if (trim($source) === '') {
            return $source;
        }

        $pattern = '/\n\s*(?:'
            .'-----Original Message-----'
            .'|-{2,}\s*Forwarded message\s*-{2,}'
            .'|From:\s.+\nSent:\s'
            .'|On .{8,160} wrote:\s*$'
            .'|_{8,}'
            .')/im';

        if (! preg_match($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            return $source;
        }

        $cut = $matches[0][1];
        if ($cut <= 0) {
            return $source;
        }

        $kept = trim(mb_strcut($source, 0, $cut, 'UTF-8'));

        return $kept !== '' ? $kept : $source;
    }

    public static function plainFromHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(style|script)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(br|hr)\s*/?>#i', "\n", $html) ?? $html;
        // Table cells sit side by side with no whitespace between their closing/opening
        // tags (e.g. "<td>Email</td><td>a@b.com</td>"), so without this a label and its
        // value fuse into one word ("Emaila@b.com") once tags are stripped below.
        $html = preg_replace('#</(p|div|tr|td|th|h[1-6]|li|blockquote|table|section)>#i', "\n", $html) ?? $html;
        // Any tag we didn't already turn into a line break (span/font/a/b/... — email
        // builders lay out label/value pairs as sibling inline tags with no text-node
        // space between them) still needs *some* separator, or a bare strip_tags() would
        // fuse "Email" directly onto the address that follows it.
        $html = preg_replace('/<[^>]+>/', ' ', $html) ?? $html;

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[ \t]+/', ' ', $decoded) ?? $decoded);
    }

    private static function collapseWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
