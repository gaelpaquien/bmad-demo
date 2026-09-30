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

it('centres the best excerpt on the zone with the most distinct words', function () {
    $text = 'contrat seul'.str_repeat(' x', 200).' contrat et résiliation ensemble'.str_repeat(' y', 200);

    $excerpt = TextExcerpt::aroundBestMatch([$text], ['contrat', 'résiliation'], radius: 60);

    expect($excerpt)->toContain('résiliation ensemble')->not->toContain('contrat seul');
});

it('finds words ignoring case and accents for the best excerpt', function () {
    expect(TextExcerpt::aroundBestMatch(['Résiliation du CONTRAT'], ['resiliation', 'contrat']))->toBe('Résiliation du CONTRAT');
});

it('starts the best excerpt from the first non-blank text when no word appears', function () {
    expect(TextExcerpt::aroundBestMatch([null, '  ', 'Début du texte.'], ['absent']))->toBe('Début du texte.');
});
