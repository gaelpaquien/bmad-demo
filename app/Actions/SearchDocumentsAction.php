<?php

namespace App\Actions;

use App\DataTransferObjects\SearchDocumentsData;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Sole search entry point shared by the Recherche page and the MCP tools
 * (AD-8): keyword splitting, ranking and LIKE escaping stay in
 * KeywordDatabaseEngine (`Document::searchableUsing()`), while the bounds,
 * the tag filter and the result shape live here so both surfaces never
 * diverge.
 *
 * Every bound truncates, never refuses. A blank (post-trim) term with no tag
 * and no source filter short-circuits to an empty paginator — the whole
 * library is never listed. A blank term with at least one tag, or a source
 * filter, lists the matching documents, newest first, through the same
 * `Document::search()` path.
 */
class SearchDocumentsAction
{
    public const MAX_SEARCH_LENGTH = 255;

    public const MAX_TAG_FILTERS = 20;

    /**
     * Default page size (MCP tool); the Recherche page passes its own via
     * SearchDocumentsData::$perPage.
     */
    public const PER_PAGE = 10;

    /**
     * The term as the search applies it: trimmed, then cut to
     * MAX_SEARCH_LENGTH characters.
     */
    public static function boundedTerm(string $term): string
    {
        return trim(mb_substr(trim($term), 0, self::MAX_SEARCH_LENGTH));
    }

    public function __invoke(SearchDocumentsData $data): LengthAwarePaginator
    {
        $term = self::boundedTerm($data->term);
        $tagIds = array_slice($data->tagIds, 0, self::MAX_TAG_FILTERS);

        if ($term === '' && $tagIds === [] && $data->source === null) {
            return new LengthAwarePaginator([], 0, $data->perPage, options: ['path' => $data->path ?? LengthAwarePaginator::resolveCurrentPath()]);
        }

        return Document::search($term)
            ->query(function (Builder $query) use ($tagIds, $data) {
                if ($tagIds !== []) {
                    $query->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereIn('tags.id', $tagIds));
                }

                if ($data->source !== null) {
                    $query->where('source', $data->source);
                }

                return $query
                    ->select(['id', 'title', 'source', 'mime_type', 'created_at'])
                    ->with('tags:id,name')
                    ->latest()
                    ->orderByDesc('id');
            })
            ->paginate($data->perPage, 'page', $data->page);
    }
}
