<?php

use App\Actions\SearchDocumentsAction;
use App\Mcp\Servers\KnowledgeBaseServer;
use App\Mcp\Tools\SearchDocumentsTool;
use App\Models\Document;
use App\Models\Tag;

function searchDocumentsTool(array $arguments)
{
    return KnowledgeBaseServer::tool(SearchDocumentsTool::class, $arguments);
}

it('returns the matching documents with their metadata and an excerpt around the match', function () {
    $tag = Tag::factory()->create(['name' => 'Finance']);
    $document = Document::factory()->create([
        'title' => 'Rapport annuel.pdf',
        'mime_type' => 'application/pdf',
        'extracted_text' => str_repeat('Introduction sans intérêt. ', 20).'La facture de janvier est en retard.'.str_repeat(' Conclusion.', 30),
    ]);
    $document->tags()->attach($tag);
    Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    searchDocumentsTool(['query' => 'facture'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total', 1)
            ->where('page', 1)
            ->where('results.0.id', $document->id)
            ->where('results.0.title', 'Rapport annuel.pdf')
            ->where('results.0.type', 'PDF')
            ->where('results.0.tags', ['Finance'])
            ->where('results.0.excerpt', fn ($excerpt) => str_contains($excerpt, 'La facture de janvier') && str_starts_with($excerpt, '…'))
            ->etc()
        );
});

it('ranks results the same way as the Recherche page', function () {
    $inContent = Document::factory()->create(['title' => 'Compte-rendu.pdf', 'extracted_text' => 'Le budget a été voté.']);
    $inTitle = Document::factory()->create(['title' => 'Budget 2026.pdf', 'extracted_text' => 'Rien à signaler.']);

    $this->get('/recherche?search=budget')->assertInertia(fn ($page) => $page
        ->where('documents.data.0.id', $inTitle->id)
        ->where('documents.data.1.id', $inContent->id)
    );

    searchDocumentsTool(['query' => 'budget'])->assertStructuredContent(fn ($json) => $json
        ->where('results.0.id', $inTitle->id)
        ->where('results.1.id', $inContent->id)
        ->etc()
    );
});

it('supports required, excluded and quoted keywords', function () {
    $wanted = Document::factory()->create(['extracted_text' => 'La facture client de mars, réglée.']);
    Document::factory()->create(['extracted_text' => 'La facture fournisseur de mars.']);
    Document::factory()->create(['extracted_text' => 'Un devis sans rapport.']);

    searchDocumentsTool(['query' => '+facture -fournisseur'])->assertStructuredContent(fn ($json) => $json
        ->where('total', 1)
        ->where('results.0.id', $wanted->id)
        ->etc()
    );

    searchDocumentsTool(['query' => '"facture client"'])->assertStructuredContent(fn ($json) => $json
        ->where('total', 1)
        ->where('results.0.id', $wanted->id)
        ->etc()
    );
});

it('lists the documents of a tag when the query is empty', function () {
    $tag = Tag::factory()->create();
    $tagged = Document::factory()->create();
    $tagged->tags()->attach($tag);
    Document::factory()->create();

    searchDocumentsTool(['tag_ids' => [$tag->id]])->assertStructuredContent(fn ($json) => $json
        ->where('total', 1)
        ->where('results.0.id', $tagged->id)
        ->etc()
    );
});

it('narrows a keyword search to the requested tag', function () {
    $tag = Tag::factory()->create();
    $tagged = Document::factory()->create(['extracted_text' => 'contrat de maintenance']);
    $tagged->tags()->attach($tag);
    Document::factory()->create(['extracted_text' => 'contrat de location']);

    searchDocumentsTool(['query' => 'contrat', 'tag_ids' => [$tag->id]])->assertStructuredContent(fn ($json) => $json
        ->where('total', 1)
        ->where('results.0.id', $tagged->id)
        ->etc()
    );
});

it('keeps only the first 20 tag filters, like the Recherche page', function () {
    $tags = Tag::factory()->count(25)->create();
    $onlyPastTheLimit = Document::factory()->create();
    $onlyPastTheLimit->tags()->attach($tags[24]);
    $withinTheLimit = Document::factory()->create();
    $withinTheLimit->tags()->attach($tags[0]);

    searchDocumentsTool(['tag_ids' => $tags->pluck('id')->all()])->assertStructuredContent(fn ($json) => $json
        ->where('total', 1)
        ->where('results.0.id', $withinTheLimit->id)
        ->etc()
    );
});

it('shows an excerpt from the attachments when the match is only in an attachment', function () {
    $document = Document::factory()->create([
        'extracted_text' => 'Texte principal sans rapport avec la recherche.',
        'attachments_extracted_text' => 'Annexe : la clause de résiliation est précisée ici.',
    ]);

    searchDocumentsTool(['query' => 'résiliation'])->assertStructuredContent(fn ($json) => $json
        ->where('results.0.id', $document->id)
        ->where('results.0.excerpt', fn ($excerpt) => str_contains($excerpt, 'clause de résiliation'))
        ->etc()
    );
});

it('never lists the whole library when there is neither a keyword nor a tag', function () {
    Document::factory()->count(2)->create();

    searchDocumentsTool(['query' => '   '])
        ->assertHasErrors(['Indique au moins un mot-clé (`query`) ou un tag (`tag_ids`) : la bibliothèque entière n\'est jamais listée.']);

    searchDocumentsTool([])->assertHasErrors();
});

it('returns an empty result for a term nothing matches', function () {
    Document::factory()->create(['extracted_text' => 'Compte-rendu.']);

    searchDocumentsTool(['query' => 'introuvable'])->assertStructuredContent(fn ($json) => $json
        ->where('total', 0)
        ->where('results', [])
        ->etc()
    );
});

it('returns results ten per page and serves the requested page', function () {
    Document::factory()->count(12)->create(['extracted_text' => 'rapport trimestriel']);

    searchDocumentsTool(['query' => 'rapport'])->assertStructuredContent(fn ($json) => $json
        ->where('total', 12)
        ->where('last_page', 2)
        ->has('results', 10)
        ->etc()
    );

    searchDocumentsTool(['query' => 'rapport', 'page' => 2])->assertStructuredContent(fn ($json) => $json
        ->where('page', 2)
        ->has('results', 2)
        ->etc()
    );
});

it('tells the client when keywords beyond the limit were ignored', function () {
    Document::factory()->create(['extracted_text' => 'mot1']);
    $manyWords = implode(' ', array_map(fn ($n) => "mot{$n}", range(1, 25)));

    searchDocumentsTool(['query' => $manyWords])->assertStructuredContent(fn ($json) => $json
        ->where('ignored_keywords', fn ($message) => str_contains($message, '20'))
        ->etc()
    );
});

it('rejects malformed arguments with an actionable message', function () {
    searchDocumentsTool(['query' => 'facture', 'page' => 0])->assertHasErrors(['`page` commence à 1.']);
    searchDocumentsTool(['query' => 'facture', 'tag_ids' => ['abc']])->assertHasErrors();
});

it('never exposes the path of the original file', function () {
    Document::factory()->create(['file_path' => 'documents/secret/dossier/original.pdf', 'extracted_text' => 'facture']);

    searchDocumentsTool(['query' => 'facture'])->assertDontSee('documents/secret');
});

it('tells how many keywords each result contains and where', function () {
    $document = Document::factory()->create([
        'title' => 'Contrat de maintenance.pdf',
        'extracted_text' => 'Le préavis est de trois mois.',
        'attachments_extracted_text' => 'Annexe : conditions de résiliation.',
    ]);

    searchDocumentsTool(['query' => 'contrat préavis résiliation absent'])->assertStructuredContent(fn ($json) => $json
        ->where('results.0.id', $document->id)
        ->where('results.0.keywords_matched', 3)
        ->where('results.0.keywords_total', 4)
        ->where('results.0.matched_in', ['titre', 'contenu', 'pièce jointe'])
        ->etc()
    );
});

it('centres the excerpt on the zone holding the most keywords, not the first occurrence', function () {
    $document = Document::factory()->create([
        'extracted_text' => 'Le contrat est mentionné ici seul.'.str_repeat(' Bla bla bla.', 60).' Contrat de maintenance : la clause de résiliation est précisée.'.str_repeat(' Fin.', 60),
    ]);

    searchDocumentsTool(['query' => 'contrat résiliation'])->assertStructuredContent(fn ($json) => $json
        ->where('results.0.id', $document->id)
        ->where('results.0.excerpt', fn ($excerpt) => str_contains($excerpt, 'clause de résiliation') && ! str_contains($excerpt, 'mentionné ici seul'))
        ->etc()
    );
});

it('keeps only the documents of the requested source', function () {
    $created = Document::factory()->created()->create(['extracted_text' => 'Facture de janvier.']);
    Document::factory()->create(['extracted_text' => 'Facture de février.']);

    searchDocumentsTool(['query' => 'facture', 'source' => 'created'])->assertStructuredContent(fn ($json) => $json
        ->where('total', 1)
        ->where('results.0.id', $created->id)
        ->etc()
    );
});

it('still refuses a source given without any keyword or tag, and an unknown source', function () {
    Document::factory()->count(2)->create();

    searchDocumentsTool(['source' => 'created'])->assertHasErrors();
    searchDocumentsTool(['query' => 'facture', 'source' => 'bogus'])->assertHasErrors();
});

it('advises on how to search in its description', function () {
    $description = (new SearchDocumentsTool(app(SearchDocumentsAction::class)))->description();

    expect($description)->toContain('2 à 4 mots')->toContain('`+`')->toContain('affine ta requête');
});
