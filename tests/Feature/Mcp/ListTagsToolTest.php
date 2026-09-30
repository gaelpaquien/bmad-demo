<?php

use App\Mcp\Servers\KnowledgeBaseServer;
use App\Mcp\Tools\ListTagsTool;
use App\Models\Document;
use App\Models\Tag;

it('lists the tags by name with the number of documents of each', function () {
    $legal = Tag::factory()->create(['name' => 'Juridique']);
    $finance = Tag::factory()->create(['name' => 'Finance']);
    Document::factory()->count(2)->create()->each(fn (Document $document) => $document->tags()->attach($finance));

    KnowledgeBaseServer::tool(ListTagsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('tags.0', ['id' => $finance->id, 'name' => 'Finance', 'documents_count' => 2])
            ->where('tags.1', ['id' => $legal->id, 'name' => 'Juridique', 'documents_count' => 0])
        );
});

it('returns an empty list when there is no tag', function () {
    KnowledgeBaseServer::tool(ListTagsTool::class)
        ->assertStructuredContent(fn ($json) => $json->where('tags', []));
});
