<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Volume fixtures for manual testing of pagination and search: 500 created +
 * 500 imported documents, 10 tags, split exactly 30 % untagged / 40 % one tag
 * / 30 % several tags (2 to 4). Imported documents get no file on disk, so
 * their preview/download answer "source missing".
 */
class DocumentSeeder extends Seeder
{
    public const CREATED_COUNT = 500;

    public const IMPORTED_COUNT = 500;

    private const UNTAGGED_SHARE = 0.3;

    private const SINGLE_TAG_SHARE = 0.4;

    private const TAG_NAMES = [
        'Finance',
        'Ressources humaines',
        'Juridique',
        'Technique',
        'Marketing',
        'Commercial',
        'Qualité',
        'Sécurité',
        'Formation',
        'Direction',
    ];

    /**
     * Seed the documents, tags and their assignments.
     */
    public function run(): void
    {
        $tagIds = collect(self::TAG_NAMES)
            ->map(fn (string $name): int => Tag::create(['name' => $name])->id);

        $documentIds = Document::factory()->count(self::CREATED_COUNT)->created()->create()
            ->concat(Document::factory()->count(self::IMPORTED_COUNT)->create())
            ->pluck('id')
            ->shuffle();

        $untaggedCount = (int) round($documentIds->count() * self::UNTAGGED_SHARE);
        $singleTagCount = (int) round($documentIds->count() * self::SINGLE_TAG_SHARE);

        $rows = [];

        foreach ($documentIds->slice($untaggedCount)->values() as $index => $documentId) {
            $tagCount = $index < $singleTagCount ? 1 : fake()->numberBetween(2, 4);

            foreach ($tagIds->random($tagCount) as $tagId) {
                $rows[] = ['document_id' => $documentId, 'tag_id' => $tagId];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('document_tag')->insert($chunk);
        }
    }
}
