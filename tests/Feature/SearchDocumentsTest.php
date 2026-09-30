<?php

use App\Models\Document;
use App\Models\Tag;

it('filters documents whose extracted text contains the search term', function () {
    $matching = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);
    $other = Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    $response = $this->get('/recherche?search=facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $matching->id)
    );

    expect($response)->not->toBeNull();
    expect($other)->not->toBeNull();
});

// I/O matrix "Recherche vide au chargement" — AC2: Recherche never shows
// the whole library, unlike the old Index.vue behavior.
it('returns an empty result set, never the whole library, when the search term is empty', function () {
    Document::factory()->count(2)->create();

    $response = $this->get('/recherche?search=');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', '')
        ->has('documents.data', 0)
    );
});

// Same rule as an empty `search=`, when the parameter is absent entirely.
it('returns an empty result set, never the whole library, when the search term is absent', function () {
    Document::factory()->count(2)->create();

    $response = $this->get('/recherche');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', '')
        ->has('documents.data', 0)
    );
});

// I/O matrix "Tag seul" (spec-recherche-aide-et-filtre-tag-seul): a blank
// term with a tag lists that tag's documents, newest first.
it('lists the documents carrying the selected tag, newest first, when the search term is empty', function () {
    $tag = Tag::factory()->create();
    $older = Document::factory()->create(['created_at' => now()->subDays(2)]);
    $newer = Document::factory()->create(['created_at' => now()]);
    $older->tags()->sync([$tag->id]);
    $newer->tags()->sync([$tag->id]);
    Document::factory()->create();

    $response = $this->get("/recherche?tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', '')
        ->where('tagFilters', [$tag->id])
        ->has('documents.data', 2)
        ->where('documents.data.0.id', $newer->id)
        ->where('documents.data.1.id', $older->id)
    );
});

// Documents imported in the same second keep a stable order: the most
// recently inserted one first.
it('breaks a created_at tie by id so tag-only listing order is stable', function () {
    $tag = Tag::factory()->create();
    $createdAt = now()->startOfSecond();
    $first = Document::factory()->create(['created_at' => $createdAt]);
    $second = Document::factory()->create(['created_at' => $createdAt]);
    $first->tags()->sync([$tag->id]);
    $second->tags()->sync([$tag->id]);

    $response = $this->get("/recherche?tag_id[]={$tag->id}");

    $response->assertInertia(fn ($page) => $page
        ->where('documents.data.0.id', $second->id)
        ->where('documents.data.1.id', $first->id)
    );
});

// I/O matrix "Plusieurs tags seuls": OU within the tag group, each document once.
it('lists documents carrying any of several selected tags only once when the search term is empty', function () {
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $inBoth = Document::factory()->create(['created_at' => now()]);
    $inBoth->tags()->sync([$tagA->id, $tagB->id]);
    $inB = Document::factory()->create(['created_at' => now()->subDay()]);
    $inB->tags()->sync([$tagB->id]);
    Document::factory()->create();

    $response = $this->get("/recherche?tag_id[]={$tagA->id}&tag_id[]={$tagB->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 2)
        ->where('documents.data.0.id', $inBoth->id)
        ->where('documents.data.1.id', $inB->id)
    );
});

// I/O matrix "Espaces seuls + tag": whitespace counts as a blank term.
it('treats a whitespace-only term with a tag like a tag-only search', function () {
    $tag = Tag::factory()->create();
    $document = Document::factory()->create();
    $document->tags()->sync([$tag->id]);
    Document::factory()->create();

    $response = $this->get("/recherche?search=%20%20&tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('search', '')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

// I/O matrix "Exclusion seule + tag": a term without any positive keyword
// still matches nothing, even with a tag.
it('returns nothing for an exclusion-only term even with a tag selected', function () {
    $tag = Tag::factory()->create();
    $document = Document::factory()->create(['extracted_text' => 'Contenu sans le mot exclu.']);
    $document->tags()->sync([$tag->id]);

    $response = $this->get("/recherche?search=-speed&tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('search', '-speed')
        ->has('documents.data', 0)
    );
});

it('returns an empty list without failing when no document matches the search term', function () {
    Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    $response = $this->get('/recherche?search=zzz-introuvable-zzz');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'zzz-introuvable-zzz')
        ->has('documents.data', 0)
    );
});

it('matches a document by its title even when its extracted text does not contain the term', function () {
    $document = Document::factory()->create([
        'title' => 'Facture janvier.pdf',
        'extracted_text' => 'Contenu sans rapport avec le titre.',
    ]);

    $response = $this->get('/recherche?search=Facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

it('ranks title matches before content matches, even over documents matching more keywords', function () {
    $contentWithBothKeywords = Document::factory()->create([
        'title' => 'Compte-rendu.pdf',
        'extracted_text' => 'Le cubiscan et son speed.',
        'created_at' => now(),
    ]);
    $titleWithOneKeyword = Document::factory()->create([
        'title' => 'Notice Cubiscan.pdf',
        'extracted_text' => 'Contenu sans rapport.',
        'created_at' => now()->subDays(2),
    ]);
    $titleWithBothKeywords = Document::factory()->create([
        'title' => 'Cubiscan Speed.pdf',
        'extracted_text' => 'Contenu sans rapport.',
        'created_at' => now()->subDays(3),
    ]);

    $response = $this->get('/recherche?search='.urlencode('cubiscan speed'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 3)
        ->where('documents.data.0.id', $titleWithBothKeywords->id)
        ->where('documents.data.1.id', $titleWithOneKeyword->id)
        ->where('documents.data.2.id', $contentWithBothKeywords->id)
    );
});

it('never matches a document by its tag name, only by extracted text (tags are a filter, never a search term)', function () {
    $tag = Tag::factory()->create(['name' => 'Facture']);
    $document = Document::factory()->create([
        'extracted_text' => 'Contenu sans rapport avec le nom du tag.',
    ]);
    $document->tags()->sync([$tag->id]);

    $response = $this->get('/recherche?search=Facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents.data', 0)
    );
});

it('finds a document even when the search term only matches its extracted text, not its title', function () {
    $document = Document::factory()->create([
        'title' => 'Rapport annuel.pdf',
        'extracted_text' => 'Ce document mentionne une facture impayée.',
    ]);

    $response = $this->get('/recherche?search=impayée');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

it('trims leading and trailing whitespace from the search term before matching and echoing it back', function () {
    $document = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);

    $response = $this->get('/recherche?search=%20facture%20');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

it('orders search results most-recent-first, same as the unfiltered list', function () {
    $oldest = Document::factory()->create([
        'extracted_text' => 'Facture de janvier.',
        'created_at' => now()->subDays(2),
    ]);
    $newest = Document::factory()->create([
        'extracted_text' => 'Facture de février.',
        'created_at' => now(),
    ]);

    $response = $this->get('/recherche?search=facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->has('documents.data', 2)
        ->where('documents.data.0.id', $newest->id)
        ->where('documents.data.1.id', $oldest->id)
    );
});

it('combines a tag filter with an active search term through the same query entry point', function () {
    $tag = Tag::factory()->create();
    $otherTag = Tag::factory()->create();

    $matching = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de janvier.']);
    $matching->tags()->sync([$tag->id]);

    $wrongTag = Document::factory()->create(['extracted_text' => 'Voici la facture du mois de février.']);
    $wrongTag->tags()->sync([$otherTag->id]);

    $wrongSearch = Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);
    $wrongSearch->tags()->sync([$tag->id]);

    $response = $this->get("/recherche?search=facture&tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'facture')
        ->where('tagFilters', [$tag->id])
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $matching->id)
    );

    expect($wrongTag)->not->toBeNull();
    expect($wrongSearch)->not->toBeNull();
});

// Ported from the now-deleted tests/Feature/FilterDocumentsTest.php (spec-
// nettoyage-sidebar-et-page-documents removed index()'s own filtering, which
// left applyFilters()/tagIdsFromQuery()'s multi-tag handling with no
// coverage) — adapted to search() (`/recherche`), the only surviving caller:
// a shared 'facture' search term matches every document below regardless of
// its tags, isolating these assertions to the tag-filter logic alone.
it('ORs multiple selected tags within the tag group', function () {
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $tagC = Tag::factory()->create();

    $inA = Document::factory()->create(['extracted_text' => 'Facture A']);
    $inA->tags()->sync([$tagA->id]);
    $inB = Document::factory()->create(['extracted_text' => 'Facture B']);
    $inB->tags()->sync([$tagB->id]);
    $inC = Document::factory()->create(['extracted_text' => 'Facture C']);
    $inC->tags()->sync([$tagC->id]);

    $response = $this->get("/recherche?search=facture&tag_id[]={$tagA->id}&tag_id[]={$tagB->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents.data', 2)
        ->where('documents.data', fn ($documents) => collect($documents)->pluck('id')->sort()->values()->all()
            === collect([$inA->id, $inB->id])->sort()->values()->all())
    );

    expect($inC)->not->toBeNull();
});

it('matches a document tagged with several of the selected tags only once', function () {
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();

    $document = Document::factory()->create(['extracted_text' => 'Facture unique']);
    $document->tags()->sync([$tagA->id, $tagB->id]);

    $response = $this->get("/recherche?search=facture&tag_id[]={$tagA->id}&tag_id[]={$tagB->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

it('drops a malformed tag_id value instead of erroring', function () {
    $document = Document::factory()->create(['extracted_text' => 'Facture de test']);

    $response = $this->get('/recherche?search=facture&tag_id[]=abc');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('tagFilters', [])
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $document->id)
    );
});

it('matches a document containing any one of several keywords, in any order', function () {
    $withBoth = Document::factory()->create(['extracted_text' => 'Le Speed mesure le colis, puis le CubiScan pèse.']);
    $withFirstOnly = Document::factory()->create(['extracted_text' => 'Notice du cubiscan 150.']);
    $withSecondOnly = Document::factory()->create(['extracted_text' => 'Speed test du réseau.']);
    $withNeither = Document::factory()->create(['extracted_text' => 'Compte-rendu de réunion hebdomadaire.']);

    $response = $this->get('/recherche?search=cubiscan%20speed');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('search', 'cubiscan speed')
        ->has('documents.data', 3)
        ->where('documents.data', fn ($documents) => collect($documents)->pluck('id')->sort()->values()->all()
            === collect([$withBoth->id, $withFirstOnly->id, $withSecondOnly->id])->sort()->values()->all())
    );

    expect($withNeither)->not->toBeNull();
});

it('ranks documents matching more keywords first, most-recent-first among equals', function () {
    $oneKeywordNewest = Document::factory()->create([
        'extracted_text' => 'Notice du cubiscan.',
        'created_at' => now(),
    ]);
    $oneKeywordOlder = Document::factory()->create([
        'extracted_text' => 'Speed test du réseau.',
        'created_at' => now()->subDay(),
    ]);
    $bothKeywordsOldest = Document::factory()->create([
        'extracted_text' => 'Cubiscan et speed dans le même document.',
        'created_at' => now()->subDays(2),
    ]);

    $response = $this->get('/recherche?search=cubiscan%20speed');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 3)
        ->where('documents.data.0.id', $bothKeywordsOldest->id)
        ->where('documents.data.1.id', $oneKeywordNewest->id)
        ->where('documents.data.2.id', $oneKeywordOlder->id)
    );
});

it('counts a keyword found only in an attachment towards the ranking', function () {
    $splitAcrossDocumentAndAttachment = Document::factory()->create([
        'extracted_text' => 'Notice du cubiscan.',
        'attachments_extracted_text' => 'Réglages du speed.',
        'created_at' => now()->subDay(),
    ]);
    $singleKeyword = Document::factory()->create([
        'extracted_text' => 'Autre notice du cubiscan.',
        'created_at' => now(),
    ]);

    $response = $this->get('/recherche?search=cubiscan%20speed');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 2)
        ->where('documents.data.0.id', $splitAcrossDocumentAndAttachment->id)
        ->where('documents.data.1.id', $singleKeyword->id)
    );
});

it('treats a quoted phrase as a single exact keyword', function () {
    $exactPhrase = Document::factory()->create(['extracted_text' => 'Le cubiscan speed est calibré.']);
    $wordsApart = Document::factory()->create(['extracted_text' => 'Le speed et le cubiscan.']);

    $response = $this->get('/recherche?search=%22cubiscan%20speed%22');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $exactPhrase->id)
    );

    expect($wordsApart)->not->toBeNull();
});

it('matches LIKE wildcard characters in a keyword literally', function () {
    $literal = Document::factory()->create(['extracted_text' => 'Remise de 50% accordée.']);
    $wildcardOnly = Document::factory()->create(['extracted_text' => 'Remise de 500 euros.']);

    $response = $this->get('/recherche?search=50%25');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $literal->id)
    );

    expect($wildcardOnly)->not->toBeNull();
});

it('matches the _ wildcard and the ! escape character in a keyword literally', function (string $term, string $literalText, string $lookalikeText) {
    $literal = Document::factory()->create(['extracted_text' => $literalText]);
    Document::factory()->create(['extracted_text' => $lookalikeText]);

    $response = $this->get('/recherche?search='.urlencode($term));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $literal->id)
    );
})->with([
    'underscore is not a single-character wildcard' => ['a_b', 'Code a_b du lot.', 'Code axb du lot.'],
    'escape character is not swallowed' => ['a!b', 'Code a!b du lot.', 'Code ab du lot.'],
]);

// The production collation ignores accents, so "ete" and "été" are one
// keyword: merged, the exclusion wins and nothing is left to find.
it('merges keywords that only differ by accents or case into a single keyword', function () {
    Document::factory()->create(['extracted_text' => 'Planning ete sans accent.']);

    $response = $this->get('/recherche?search='.urlencode('ete -ÉTÉ'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 0)
    );
});

it('reads only the first sign of a keyword as an operator', function () {
    $withSign = Document::factory()->create(['extracted_text' => 'Option +speed activée.']);
    Document::factory()->create(['extracted_text' => 'Option speed activée.']);

    $response = $this->get('/recherche?search='.urlencode('++speed'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $withSign->id)
    );
});

it('searches a quoted term starting with an operator literally instead of excluding it', function () {
    $matching = Document::factory()->create(['extracted_text' => 'Température de -5 degrés.']);
    Document::factory()->create(['extracted_text' => 'Température de 5 degrés.']);

    $response = $this->get('/recherche?search='.urlencode('"-5"'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $matching->id)
    );
});

it('applies the tag filter to every document of a multi-keyword OR search', function () {
    $tag = Tag::factory()->create();
    $otherTag = Tag::factory()->create();

    $matching = Document::factory()->create(['extracted_text' => 'Notice du cubiscan.']);
    $matching->tags()->sync([$tag->id]);

    $wrongTag = Document::factory()->create(['extracted_text' => 'Speed test du réseau.']);
    $wrongTag->tags()->sync([$otherTag->id]);

    $response = $this->get('/recherche?search='.urlencode('cubiscan speed')."&tag_id[]={$tag->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $matching->id)
    );
});

it('keeps the strictest operator when the same keyword is repeated, whatever the word order', function (string $term, array $expectedTexts) {
    $documents = collect([
        'Notice du cubiscan.',
        'Le cubiscan et son speed.',
        'Speed test du réseau.',
    ])->mapWithKeys(fn (string $text) => [$text => Document::factory()->create(['extracted_text' => $text])]);

    $response = $this->get('/recherche?search='.urlencode($term));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('documents.data', fn ($results) => collect($results)->pluck('id')->all()
            === collect($expectedTexts)->map(fn (string $text) => $documents[$text]->id)->all())
    );
})->with([
    'required after optional' => ['cubiscan speed +speed', ['Le cubiscan et son speed.', 'Speed test du réseau.']],
    'required before optional' => ['+speed cubiscan speed', ['Le cubiscan et son speed.', 'Speed test du réseau.']],
    'excluded after optional' => ['cubiscan speed -speed', ['Notice du cubiscan.']],
    'excluded before optional' => ['cubiscan -speed speed', ['Notice du cubiscan.']],
    'excluded against required' => ['cubiscan +speed -speed', ['Notice du cubiscan.']],
    'only the excluded keyword left' => ['-speed speed', []],
]);

it('requires every keyword prefixed with + to be present', function () {
    $withBoth = Document::factory()->create(['extracted_text' => 'Le speed et le cubiscan.']);
    $withFirstOnly = Document::factory()->create(['extracted_text' => 'Notice du cubiscan.']);

    $response = $this->get('/recherche?search='.urlencode('+cubiscan +speed'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('search', '+cubiscan +speed')
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $withBoth->id)
    );

    expect($withFirstOnly)->not->toBeNull();
});

it('uses unprefixed keywords only to rank results once a + keyword is present', function () {
    $requiredAndOptional = Document::factory()->create([
        'extracted_text' => 'Le cubiscan et son speed.',
        'created_at' => now()->subDay(),
    ]);
    $requiredOnly = Document::factory()->create([
        'extracted_text' => 'Notice du cubiscan.',
        'created_at' => now(),
    ]);
    $optionalOnly = Document::factory()->create(['extracted_text' => 'Speed test du réseau.']);

    $response = $this->get('/recherche?search='.urlencode('+cubiscan speed'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 2)
        ->where('documents.data.0.id', $requiredAndOptional->id)
        ->where('documents.data.1.id', $requiredOnly->id)
    );

    expect($optionalOnly)->not->toBeNull();
});

it('excludes documents containing a keyword prefixed with -, including in their attachments', function () {
    $kept = Document::factory()->create(['extracted_text' => 'Notice du cubiscan.']);
    $excludedByContent = Document::factory()->create(['extracted_text' => 'Le cubiscan et son speed.']);
    $excludedByAttachment = Document::factory()->create([
        'extracted_text' => 'Autre notice du cubiscan.',
        'attachments_extracted_text' => 'Réglages du speed.',
    ]);

    $response = $this->get('/recherche?search='.urlencode('cubiscan -speed'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $kept->id)
    );

    expect($excludedByContent)->not->toBeNull();
    expect($excludedByAttachment)->not->toBeNull();
});

// AC2: a term made only of exclusions (or of a lone +/- typed before the
// word) would otherwise list almost the whole
// library.
it('returns an empty result set when the search term only contains excluded keywords or lone operators', function (string $term) {
    Document::factory()->create(['extracted_text' => 'Notice du cubiscan.']);

    $response = $this->get('/recherche?search='.urlencode($term));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 0)
    );
})->with(['-speed', '+', '- +']);

// I/O matrix "Page 1" (spec-recherche-bornes-pagination): 20 per page (as the Documents list), the
// total counting every match.
it('paginates search results 20 per page (as the Documents list) with the total count of matches', function () {
    Document::factory()->count(45)->create(['extracted_text' => 'Facture du mois.']);

    $response = $this->get('/recherche?search=facture');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->has('documents.data', 20)
        ->where('documents.total', 45)
        ->where('documents.current_page', 1)
        ->where('documents.last_page', 3)
    );
});

// I/O matrix "Page 2": results 21–40, page links keep `search` and
// `tag_id[]`, never Scout's own `query` parameter.
it('serves the second page of results with page links keeping the search criteria', function () {
    $tag = Tag::factory()->create();
    $documents = Document::factory()->count(45)->sequence(
        fn ($sequence) => ['created_at' => now()->subMinutes(45 - $sequence->index)],
    )->create(['extracted_text' => 'Facture du mois.']);
    $documents->each(fn (Document $document) => $document->tags()->sync([$tag->id]));

    $response = $this->get("/recherche?search=facture&tag_id[]={$tag->id}&page=2");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 20)
        ->where('documents.current_page', 2)
        // Newest first: page 2 holds positions 21–40, i.e. indexes 24 down to 5.
        ->where('documents.data.0.id', $documents->get(24)->id)
        ->where('documents.data.19.id', $documents->get(5)->id)
        ->where('documents.links', function ($links) use ($tag) {
            $urls = collect($links)->pluck('url')->filter();

            return $urls->isNotEmpty() && $urls->every(fn (string $url) => str_contains($url, 'search=facture')
                && str_contains(urldecode($url), "tag_id[0]={$tag->id}")
                && ! str_contains($url, 'query='));
        })
    );
});

// I/O matrix "Terme trop long": cut to 255 characters (not bytes), then
// re-trimmed, searched and echoed back cut.
it('truncates a search term longer than 255 characters instead of refusing it', function () {
    $kept = str_repeat('é', 254);
    $term = $kept.' facture '.str_repeat('z', 37);
    $withKeptTerm = Document::factory()->create(['extracted_text' => "Texte {$kept} fin."]);
    Document::factory()->create(['extracted_text' => 'Facture du mois.']);

    expect(mb_strlen($term))->toBe(300);

    $response = $this->get('/recherche?search='.urlencode($term));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('search', $kept)
        ->where('keywordLimitReached', false)
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $withKeptTerm->id)
    );
});

// I/O matrix "21 mots-clés": only the first 20 distinct keywords apply.
it('ignores keywords beyond the first 20 distinct ones and flags it', function () {
    $keywords = collect(range(1, 21))->map(fn (int $index) => sprintf('mot%02d', $index));
    $withFirstKeyword = Document::factory()->create(['extracted_text' => 'Contient mot01.']);
    Document::factory()->create(['extracted_text' => 'Contient mot21.']);

    $response = $this->get('/recherche?search='.urlencode($keywords->implode(' ')));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('keywordLimitReached', true)
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $withFirstKeyword->id)
    );
});

// I/O matrix "Doublons": repeated keywords are merged before the limit.
it('counts repeated keywords once towards the 20-keyword limit', function () {
    $keywords = collect(range(1, 20))->map(fn (int $index) => sprintf('mot%02d', $index));
    $term = $keywords->implode(' ').' MOT01 +mot02 Mot20';
    $withLastKeyword = Document::factory()->create(['extracted_text' => 'Contient mot20 et mot02.']);

    $response = $this->get('/recherche?search='.urlencode($term));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('keywordLimitReached', false)
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $withLastKeyword->id)
    );
});

// I/O matrix "Tags en trop": only the first 20 valid tags filter, silently.
it('keeps only the first 20 tag filters', function () {
    $tags = Tag::factory()->count(25)->create();
    $inFirstTag = Document::factory()->create();
    $inFirstTag->tags()->sync([$tags->first()->id]);
    $inLastTag = Document::factory()->create();
    $inLastTag->tags()->sync([$tags->last()->id]);

    $query = $tags->map(fn (Tag $tag) => "tag_id[]={$tag->id}")->implode('&');

    $response = $this->get("/recherche?{$query}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tagFilters', $tags->take(20)->pluck('id')->all())
        ->has('documents.data', 1)
        ->where('documents.data.0.id', $inFirstTag->id)
    );
});

// I/O matrix "Critères vides": same paginator shape, nothing in it.
it('returns an empty paginator when neither a term nor a tag is given', function () {
    Document::factory()->count(2)->create();

    $response = $this->get('/recherche');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 0)
        ->where('documents.total', 0)
        ->where('keywordLimitReached', false)
    );
});

// Source filter (spec-ajustements-recherche-spinner-source): alone it lists
// that source's documents, newest first, like a tag alone.
it('lists only the documents of the selected source when no term or tag is given', function () {
    $created = Document::factory()->created()->create();
    Document::factory()->count(2)->create();

    $response = $this->get('/recherche?source=created');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Documents/Search')
        ->where('sourceFilter', 'created')
        ->where('documents.total', 1)
        ->where('documents.data.0.id', $created->id)
    );
});

it('narrows a keyword search to the selected source', function () {
    $imported = Document::factory()->create(['extracted_text' => 'facture importée']);
    Document::factory()->created()->create(['extracted_text' => 'facture créée']);

    $response = $this->get('/recherche?search=facture&source=imported');

    $response->assertInertia(fn ($page) => $page
        ->where('documents.total', 1)
        ->where('documents.data.0.id', $imported->id)
    );
});

it('ignores an unknown source value instead of erroring', function () {
    Document::factory()->count(2)->create();

    $response = $this->get('/recherche?source=bogus');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('sourceFilter', null)
        ->has('documents.data', 0)
    );
});

// Ranking tie-break: equal on distinct keywords, the document mentioning the
// keyword more often comes first, ahead of the more recent one.
it('breaks a tie on distinct keywords by the total number of occurrences before the date', function () {
    $often = Document::factory()->create(['extracted_text' => 'facture, facture, facture.', 'created_at' => now()->subDays(3)]);
    $once = Document::factory()->create(['extracted_text' => 'Une facture.', 'created_at' => now()]);

    $this->get('/recherche?search=facture')->assertInertia(fn ($page) => $page
        ->where('documents.data.0.id', $often->id)
        ->where('documents.data.1.id', $once->id)
    );
});

it('still ranks more distinct keywords ahead of more occurrences of one keyword', function () {
    $many = Document::factory()->create(['extracted_text' => 'facture facture facture facture']);
    $both = Document::factory()->create(['extracted_text' => 'facture et devis']);

    $this->get('/recherche?search=facture+devis')->assertInertia(fn ($page) => $page
        ->where('documents.data.0.id', $both->id)
        ->where('documents.data.1.id', $many->id)
    );
});
