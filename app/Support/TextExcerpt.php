<?php

namespace App\Support;

/**
 * Short readable extract of a long text, centred on the first place one of
 * the given words appears.
 */
final class TextExcerpt
{
    /**
     * Excerpt of the first text that contains one of the words, or of the
     * first non-blank text when none does.
     *
     * @param  array<int, string|null>  $texts
     * @param  array<int, string>  $words
     */
    public static function aroundFirstMatch(array $texts, array $words, int $radius = 120): string
    {
        $texts = array_values(array_filter($texts, fn (?string $text): bool => filled($text)));

        foreach ($texts as $text) {
            foreach ($words as $word) {
                if (mb_stripos($text, $word) !== false) {
                    return self::around($text, $words, $radius);
                }
            }
        }

        return self::around($texts[0] ?? '', $words, $radius);
    }

    /**
     * @param  array<int, string>  $words
     */
    public static function around(string $text, array $words, int $radius = 120): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return '';
        }

        $position = null;

        foreach ($words as $word) {
            $found = mb_stripos($text, $word);

            if ($found !== false && ($position === null || $found < $position)) {
                $position = $found;
            }
        }

        $start = max(0, ($position ?? 0) - $radius);
        $excerpt = mb_substr($text, $start, $radius * 2);

        return ($start > 0 ? '…' : '').$excerpt.($start + $radius * 2 < mb_strlen($text) ? '…' : '');
    }

    private function __construct() {}
}
