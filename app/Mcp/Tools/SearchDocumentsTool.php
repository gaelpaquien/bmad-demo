<?php

namespace App\Mcp\Tools;

use App\Actions\SearchDocumentsAction;
use App\DataTransferObjects\SearchDocumentsData;
use App\Mcp\DocumentPresenter;
use App\Models\Document;
use App\Support\KeywordDatabaseEngine;
use App\Support\TextExcerpt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description("Recherche des documents par mots-clés dans leur titre et leur contenu (pièces jointes comprises), avec un filtre optionnel par tag. Renvoie 10 résultats par page, les correspondances dans le titre d'abord, chacun avec un extrait. Lis ensuite un résultat avec `read_document`.")]
#[Name('search_documents')]
#[IsReadOnly]
class SearchDocumentsTool extends Tool
{
    public function __construct(private SearchDocumentsAction $searchDocuments) {}

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'query.string' => '`query` doit être un texte : des mots-clés séparés par des espaces.',
            'tag_ids.*.integer' => '`tag_ids` ne contient que des identifiants de tags (nombres) ; `list_tags` les donne.',
            'page.min' => '`page` commence à 1.',
            'page.max' => '`page` ne dépasse pas 100000.',
        ]);

        $term = SearchDocumentsAction::boundedTerm($validated['query'] ?? '');
        $tagIds = array_values(array_unique($validated['tag_ids'] ?? []));

        if ($term === '' && $tagIds === []) {
            return Response::error("Indique au moins un mot-clé (`query`) ou un tag (`tag_ids`) : la bibliothèque entière n'est jamais listée.");
        }

        $documents = ($this->searchDocuments)(new SearchDocumentsData(
            term: $term,
            tagIds: $tagIds,
            page: $validated['page'] ?? null,
        ));

        $texts = Document::query()
            ->whereIn('id', $documents->pluck('id'))
            ->get(['id', 'extracted_text', 'attachments_extracted_text'])
            ->keyBy('id');

        $words = KeywordDatabaseEngine::matchableWordsFrom($term);

        return Response::structured([
            'total' => $documents->total(),
            'page' => $documents->currentPage(),
            'last_page' => $documents->lastPage(),
            'ignored_keywords' => KeywordDatabaseEngine::exceedsKeywordLimit($term)
                ? 'Seuls les '.KeywordDatabaseEngine::MAX_KEYWORDS.' premiers mots-clés distincts ont été utilisés.'
                : null,
            'results' => $documents->getCollection()->map(fn (Document $document): array => [
                ...DocumentPresenter::summary($document),
                'excerpt' => TextExcerpt::aroundFirstMatch([
                    $texts[$document->id]?->extracted_text,
                    $texts[$document->id]?->attachments_extracted_text,
                ], $words),
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description("Mots-clés séparés par des espaces (au moins un doit être présent). `+mot` rend un mot obligatoire, `-mot` l'exclut, `\"une expression\"` cherche l'expression exacte. Peut être vide si `tag_ids` est fourni."),
            'tag_ids' => $schema->array()
                ->items($schema->integer())
                ->description("Identifiants de tags (voir `list_tags`) : ne garde que les documents portant l'un d'eux."),
            'page' => $schema->integer()
                ->description('Numéro de page des résultats, à partir de 1.')
                ->default(1),
        ];
    }
}
