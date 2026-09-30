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

        return self::slice($text, $position ?? 0, $radius);
    }

    /**
     * Excerpt of the zone holding the most distinct words (ties: the
     * earliest, in the first of the texts), rather than of the first
     * occurrence. Falls back to the start of the first non-blank text when no
     * word appears. Words are compared ignoring case and accents.
     *
     * @param  array<int, string|null>  $texts
     * @param  array<int, string>  $words
     */
    public static function aroundBestMatch(array $texts, array $words, int $radius = 125): string
    {
        $texts = array_values(array_filter(
            array_map(fn (?string $text): string => trim((string) preg_replace('/\s+/u', ' ', (string) $text)), $texts),
            fn (string $text): bool => $text !== '',
        ));

        $foldedWords = array_values(array_filter(array_map(KeywordDatabaseEngine::foldForComparison(...), $words), fn (string $word): bool => $word !== ''));

        $best = null;

        foreach ($texts as $text) {
            $folded = KeywordDatabaseEngine::foldForComparison($text);

            // Accent folding can change the length (ligatures…): positions
            // would then drift, so fall back to plain lower-casing.
            if (mb_strlen($folded) !== mb_strlen($text)) {
                $folded = mb_strtolower($text);
            }

            foreach (self::candidatePositions($folded, $foldedWords) as $position) {
                $window = mb_substr($folded, max(0, $position - $radius), $radius * 2);
                $score = count(array_filter($foldedWords, fn (string $word): bool => mb_strpos($window, $word) !== false));

                if ($best === null || $score > $best['score']) {
                    $best = ['score' => $score, 'text' => $text, 'position' => $position];
                }
            }
        }

        if ($best === null) {
            return self::around($texts[0] ?? '', $words, $radius);
        }

        return self::slice($best['text'], $best['position'], $radius);
    }

    /**
     * Where each word starts in the text, capped so a very frequent word in a
     * long text stays cheap to scan.
     *
     * @param  array<int, string>  $foldedWords
     * @return array<int, int>
     */
    private static function candidatePositions(string $folded, array $foldedWords, int $limitPerWord = 40): array
    {
        $positions = [];

        foreach ($foldedWords as $word) {
            $offset = 0;

            for ($count = 0; $count < $limitPerWord; $count++) {
                $found = mb_strpos($folded, $word, $offset);

                if ($found === false) {
                    break;
                }

                $positions[] = $found;
                $offset = $found + 1;
            }
        }

        sort($positions);

        return array_slice($positions, 0, 200);
    }

    private static function slice(string $text, int $position, int $radius): string
    {
        $start = max(0, $position - $radius);
        $excerpt = mb_substr($text, $start, $radius * 2);

        return ($start > 0 ? '…' : '').$excerpt.($start + $radius * 2 < mb_strlen($text) ? '…' : '');
    }

    private function __construct() {}
}
