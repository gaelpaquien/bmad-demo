<?php

namespace App\Mcp\Tools;

use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Liste les tags qui classent les documents, avec le nombre de documents de chacun. Utile pour filtrer `search_documents` par tag.')]
#[Name('list_tags')]
#[IsReadOnly]
class ListTagsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $tags = Tag::query()
            ->withCount('documents')
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'documents_count' => $tag->documents_count,
            ])
            ->all();

        return Response::structured(['tags' => $tags]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
