<?php

use App\Enums\DocumentSource;
use App\Models\Document;
use App\Models\Tag;
use Database\Seeders\DocumentSeeder;

it('seeds the volume fixtures with the expected tag distribution', function () {
    $this->seed(DocumentSeeder::class);

    expect(Document::where('source', DocumentSource::Created)->count())->toBe(500)
        ->and(Document::where('source', DocumentSource::Imported)->count())->toBe(500)
        ->and(Tag::count())->toBe(10);

    $tagCounts = Document::withCount('tags')->pluck('tags_count');

    expect($tagCounts->filter(fn (int $count): bool => $count === 0)->count())->toBe(300)
        ->and($tagCounts->filter(fn (int $count): bool => $count === 1)->count())->toBe(400)
        ->and($tagCounts->filter(fn (int $count): bool => $count >= 2)->count())->toBe(300);
});
