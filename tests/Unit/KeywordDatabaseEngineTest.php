<?php

use App\Support\KeywordDatabaseEngine;

it('lists the required, optional and quoted keywords but never the excluded ones', function () {
    expect(KeywordDatabaseEngine::matchableWordsFrom('+facture budget -brouillon "compte rendu"'))
        ->toBe(['facture', 'budget', 'compte rendu']);
});

it('returns no word for a term made only of excluded keywords or of a lone operator', function () {
    expect(KeywordDatabaseEngine::matchableWordsFrom('-brouillon + -'))->toBe([]);
});
