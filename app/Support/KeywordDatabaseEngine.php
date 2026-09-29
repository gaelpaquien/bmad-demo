<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\DatabaseEngine;

/**
 * Scout's `database` driver with keyword semantics (AD-8 stays intact: the
 * search still goes through `Document::search()`, only the engine changes).
 * The stock engine matches the whole term as one `LIKE '%...%'`, so
 * "cubiscan speed" only finds that exact sequence. Here the term is split
 * into keywords (whitespace-separated, or a "quoted phrase" kept whole),
 * each optionally prefixed:
 *
 * - no prefix: optional — without any `+keyword`, a document matches when
 *   at least one optional keyword appears (OU); alongside a `+keyword`,
 *   optional keywords only boost the ranking;
 * - `+keyword`: required (ET);
 * - `-keyword`: excluded.
 *
 * A term made only of excluded keywords (or of nothing usable, e.g. a lone
 * `+` typed before the word) matches nothing, so Recherche never
 * falls back to listing (almost) the whole library (AC2, spec-3-4). Results
 * are ranked by how many required/optional keywords their priority columns
 * contain, then by how many they contain across every indexed column — the
 * caller's own `orderBy` (e.g. `latest()`) only breaks ties.
 */
class KeywordDatabaseEngine extends DatabaseEngine
{
    private const OPTIONAL = 'optional';

    private const REQUIRED = 'required';

    private const EXCLUDED = 'excluded';

    /**
     * Escape character for `LIKE` patterns, declared explicitly on every
     * clause since SQLite (tests) has no default escape character, unlike
     * MySQL's backslash.
     */
    private const LIKE_ESCAPE = '!';

    /**
     * @param  array<int, string>  $priorityColumns  indexed columns whose matches outrank every other
     *                                               column's in the ranking (e.g. the title)
     */
    public function __construct(private array $priorityColumns = [])
    {
        parent::__construct();
    }

    /**
     * @param  EloquentBuilder  $query
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $prefixColumns
     * @param  array<int, string>  $fullTextColumns
     */
    protected function addTextSearchConstraints($query, Builder $builder, array $columns, array $prefixColumns = [], array $fullTextColumns = []): EloquentBuilder
    {
        $keywords = self::keywordsFrom((string) $builder->query);

        $likeOperator = $builder->modelConnectionType() === 'pgsql' ? 'ilike' : 'like';
        $grammar = $query->getQuery()->getGrammar();

        // `coalesce()` so a NULL column (e.g. no attachments) never turns an
        // excluded keyword's `not (...)` into NULL and drops the document.
        $matchSqlFor = fn (array $matchColumns): string => '('.implode(' or ', array_map(
            fn (string $column): string => 'coalesce('.$grammar->wrap($builder->model->qualifyColumn($column)).", '') {$likeOperator} ? escape '".self::LIKE_ESCAPE."'",
            $matchColumns,
        )).')';

        $bindingsFor = fn (array $keyword, array $matchColumns): array => array_fill(0, count($matchColumns), '%'.self::escapeLike($keyword['text']).'%');

        $byOperator = fn (string $operator): array => array_values(array_filter(
            $keywords,
            fn (array $keyword): bool => $keyword['operator'] === $operator,
        ));

        $required = $byOperator(self::REQUIRED);
        $optional = $byOperator(self::OPTIONAL);
        $excluded = $byOperator(self::EXCLUDED);
        $ranked = [...$required, ...$optional];

        if ($ranked === []) {
            return $query->whereRaw('1 = 0');
        }

        $keywordMatchSql = $matchSqlFor($columns);

        foreach ($required as $keyword) {
            $query->whereRaw($keywordMatchSql, $bindingsFor($keyword, $columns));
        }

        if ($required === []) {
            $query->where(function (EloquentBuilder $optionalQuery) use ($optional, $keywordMatchSql, $bindingsFor, $columns) {
                foreach ($optional as $keyword) {
                    $optionalQuery->orWhereRaw($keywordMatchSql, $bindingsFor($keyword, $columns));
                }
            });
        }

        foreach ($excluded as $keyword) {
            $query->whereRaw("not {$keywordMatchSql}", $bindingsFor($keyword, $columns));
        }

        $orderByMatchCount = fn (array $matchColumns) => $query->orderByRaw(
            implode(' + ', array_fill(0, count($ranked), "(case when {$matchSqlFor($matchColumns)} then 1 else 0 end)")).' desc',
            array_merge(...array_map(fn (array $keyword): array => $bindingsFor($keyword, $matchColumns), $ranked)),
        );

        $priorityColumns = array_values(array_intersect($this->priorityColumns, $columns));

        if ($priorityColumns !== []) {
            $orderByMatchCount($priorityColumns);
        }

        $orderByMatchCount($columns);

        return $query;
    }

    /**
     * Splits a search term into distinct keywords: a "quoted phrase" stays a
     * single keyword, everything else splits on whitespace; a leading `+`
     * marks it required, a leading `-` excluded. Duplicates are dropped
     * case-insensitively (first occurrence wins) so a repeated word never
     * counts twice in the ranking.
     *
     * @return array<int, array{text: string, operator: string}>
     */
    private static function keywordsFrom(string $term): array
    {
        preg_match_all('/([+-]?)(?:"([^"]*)"?|(\S+))/u', $term, $matches, PREG_SET_ORDER);

        $keywords = [];

        foreach ($matches as $match) {
            $isPhrase = ($match[2] ?? '') !== '';
            $text = trim($isPhrase ? $match[2] : ($match[3] ?? ''), " \t\n\r\0\x0B\"");

            // A lone `+`/`-` (e.g. typed before the word) is not a keyword.
            if ($text === '' || (! $isPhrase && trim($text, '+-') === '')) {
                continue;
            }

            $keywords[mb_strtolower($text)] ??= [
                'text' => $text,
                'operator' => match ($match[1]) {
                    '+' => self::REQUIRED,
                    '-' => self::EXCLUDED,
                    default => self::OPTIONAL,
                },
            ];
        }

        return array_values($keywords);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(
            [self::LIKE_ESCAPE, '%', '_'],
            [self::LIKE_ESCAPE.self::LIKE_ESCAPE, self::LIKE_ESCAPE.'%', self::LIKE_ESCAPE.'_'],
            $value,
        );
    }
}
