<?php

namespace App\Support;

/**
 * Tells an AI client why a result is relevant: how many of the search
 * keywords a document contains and in which of its texts. Comparison ignores
 * case and accents, like the search itself (KeywordDatabaseEngine).
 */
final class KeywordMatchReport
{
    /**
     * @param  array<int, string>  $words  matchable keywords (never the excluded ones)
     * @param  array<string, string|null>  $locations  label => text, e.g. ['titre' => …, 'contenu' => …]
     * @return array{matched: int, total: int, found_in: array<int, string>}
     */
    public static function for(array $words, array $locations): array
    {
        $foldedWords = array_map(KeywordDatabaseEngine::foldForComparison(...), $words);
        $foundWords = [];
        $foundIn = [];

        foreach ($locations as $label => $text) {
            if (blank($text)) {
                continue;
            }

            $folded = KeywordDatabaseEngine::foldForComparison($text);

            foreach ($foldedWords as $index => $word) {
                if (mb_strpos($folded, $word) !== false) {
                    $foundWords[$index] = true;
                    $foundIn[$label] = true;
                }
            }
        }

        return [
            'matched' => count($foundWords),
            'total' => count($words),
            'found_in' => array_keys($foundIn),
        ];
    }

    private function __construct() {}
}
