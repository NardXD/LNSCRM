<?php

namespace App\Support;

class EmailQuotedHistory
{
    public static function snippet(?string $html, ?string $text = null, int $limit = 500): string
    {
        $fromHtml = self::collapseWhitespace(self::stripPlain(self::plainFromHtml((string) $html)));
        if ($fromHtml !== '') {
            return mb_substr($fromHtml, 0, $limit);
        }

        $fromText = self::collapseWhitespace(self::stripPlain((string) $text));

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
            .'|-----Forwarded message-----'
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

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function collapseWhitespace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
