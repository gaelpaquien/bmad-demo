<?php

use App\Support\TextExcerpt;

it('centres the excerpt on the first matching word and marks the cut ends', function () {
    $text = str_repeat('avant ', 50).'facture'.str_repeat(' après', 50);

    $excerpt = TextExcerpt::around($text, ['facture'], radius: 20);

    expect($excerpt)->toStartWith('…')->toEndWith('…')->toContain('facture');
});

it('matches ignoring case and picks the earliest of several words', function () {
    $excerpt = TextExcerpt::around('Le budget puis la FACTURE.', ['facture', 'budget'], radius: 100);

    expect($excerpt)->toBe('Le budget puis la FACTURE.');
});

it('starts from the beginning of the text when no word matches', function () {
    $excerpt = TextExcerpt::around(str_repeat('mot ', 100), ['absent'], radius: 10);

    expect($excerpt)->toStartWith('mot mot')->toEndWith('…')->not->toStartWith('…');
});

it('returns an empty string for a blank text', function () {
    expect(TextExcerpt::around("  \n ", ['mot']))->toBe('');
});

it('collapses whitespace runs in the excerpt', function () {
    expect(TextExcerpt::around("un\n\n  deux\ttrois", ['deux']))->toBe('un deux trois');
});

it('takes the excerpt from the first text that contains a word, skipping blank ones', function () {
    $excerpt = TextExcerpt::aroundFirstMatch([null, 'Texte sans rapport.', 'Le contrat est signé.'], ['contrat']);

    expect($excerpt)->toBe('Le contrat est signé.');
});

it('falls back to the start of the first non-blank text when no text contains a word', function () {
    $excerpt = TextExcerpt::aroundFirstMatch(['', 'Premier texte.', 'Second texte.'], ['absent']);

    expect($excerpt)->toBe('Premier texte.');
});
