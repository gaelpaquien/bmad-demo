<?php

namespace App\Mcp\Tools;

use App\Actions\SearchDocumentsAction;
use App\DataTransferObjects\SearchDocumentsData;
use App\Enums\DocumentSource;
use App\Mcp\DocumentPresenter;
use App\Models\Document;
use App\Support\KeywordDatabaseEngine;
use App\Support\KeywordMatchReport;
use App\Support\TextExcerpt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
Recherche des documents par mots-clés dans leur titre et leur contenu (pièces jointes comprises), avec des filtres optionnels par tag et par type (importé ou créé). Renvoie 10 résultats par page, les plus pertinents d'abord : ceux dont le titre contient le plus de mots-clés, puis ceux qui en contiennent le plus, puis ceux où ils reviennent le plus souvent, puis les plus récents.

Comment bien chercher :
- Donne 2 à 4 mots distinctifs (un nom, une référence, un terme précis), jamais une phrase entière : les mots vides (le, de, pour…) ramèneraient presque tout, car un seul mot suffit par défaut.
- Préfixe par `+` les mots qui doivent absolument être présents (`+contrat +résiliation`), par `-` ceux à exclure, et mets entre guillemets une expression exacte (`"préavis de trois mois"`).
- Chaque résultat indique `keywords_matched` sur `keywords_total` (combien de tes mots-clés il contient), `matched_in` (titre, contenu, pièce jointe) et un extrait centré sur la zone la plus riche en mots-clés.
- Regarde `total` : s'il est grand (plus de 20), affine ta requête (ajoute un `+mot`, un tag ou `source`) au lieu de parcourir les pages ; ne passe à la page suivante que si les premiers résultats sont pertinents mais insuffisants.
- Lis ensuite le document retenu avec `read_document`.
TXT)]
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
            'source' => ['nullable', Rule::enum(DocumentSource::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'query.string' => '`query` doit être un texte : des mots-clés séparés par des espaces.',
            'tag_ids.*.integer' => '`tag_ids` ne contient que des identifiants de tags (nombres) ; `list_tags` les donne.',
            'source.enum' => "`source` vaut `imported` (documents importés) ou `created` (documents créés dans l'éditeur).",
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
            source: isset($validated['source']) ? DocumentSource::from($validated['source']) : null,
        ));

        $texts = Document::query()
            ->whereIn('id', $documents->pluck('id'))
            ->get(['id', 'title', 'extracted_text', 'attachments_extracted_text'])
            ->keyBy('id');

        $words = KeywordDatabaseEngine::matchableWordsFrom($term);

        return Response::structured([
            'total' => $documents->total(),
            'page' => $documents->currentPage(),
            'last_page' => $documents->lastPage(),
            'ignored_keywords' => KeywordDatabaseEngine::exceedsKeywordLimit($term)
                ? 'Seuls les '.KeywordDatabaseEngine::MAX_KEYWORDS.' premiers mots-clés distincts ont été utilisés.'
                : null,
            'results' => $documents->getCollection()->map(function (Document $document) use ($texts, $words): array {
                $text = $texts[$document->id] ?? null;
                $report = KeywordMatchReport::for($words, [
                    'titre' => $text?->title,
                    'contenu' => $text?->extracted_text,
                    'pièce jointe' => $text?->attachments_extracted_text,
                ]);

                return [
                    ...DocumentPresenter::summary($document),
                    'keywords_matched' => $report['matched'],
                    'keywords_total' => $report['total'],
                    'matched_in' => $report['found_in'],
                    'excerpt' => TextExcerpt::aroundBestMatch([
                        $text?->extracted_text,
                        $text?->attachments_extracted_text,
                    ], $words),
                ];
            })->values()->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description("2 à 4 mots-clés distinctifs séparés par des espaces (au moins un doit être présent), pas une phrase. `+mot` rend un mot obligatoire, `-mot` l'exclut, `\"une expression\"` cherche l'expression exacte. Peut être vide si `tag_ids` est fourni."),
            'tag_ids' => $schema->array()
                ->items($schema->integer())
                ->description("Identifiants de tags (voir `list_tags`) : ne garde que les documents portant l'un d'eux."),
            'source' => $schema->string()
                ->enum(['imported', 'created'])
                ->description("Ne garde que les documents importés depuis un fichier (`imported`) ou rédigés dans l'éditeur (`created`). Ne suffit pas seul : indique aussi `query` ou `tag_ids`."),
            'page' => $schema->integer()
                ->description('Numéro de page des résultats, à partir de 1.')
                ->default(1),
        ];
    }
}
