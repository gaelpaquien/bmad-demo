<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListTagsTool;
use App\Mcp\Tools\ReadDocumentTool;
use App\Mcp\Tools\SearchDocumentsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('bmad-demo — base de connaissance')]
#[Version('1.0.0')]
#[Instructions("Base de connaissance de documents internes (PDF, Word, Excel importés, documents rédigés dans l'outil, pièces jointes incluses), en lecture seule. Commence par `search_documents` avec des mots-clés pour trouver les documents pertinents, puis lis leur texte avec `read_document` (utilise `next_offset` pour lire la suite d'un long document). `list_tags` donne les tags disponibles pour filtrer la recherche.")]
class KnowledgeBaseServer extends Server
{
    protected array $tools = [
        SearchDocumentsTool::class,
        ReadDocumentTool::class,
        ListTagsTool::class,
    ];
}
