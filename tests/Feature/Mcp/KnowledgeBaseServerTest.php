<?php

use App\Mcp\Servers\KnowledgeBaseServer;
use Laravel\Mcp\Facades\Mcp;

// The MCP page (resources/js/Pages/Documents/Mcp.vue) documents this handle
// and these tool names by hand: this test fails when they drift apart.
it('registers the local server under the handle documented on the MCP page', function () {
    expect(Mcp::getLocalServer('bmad-demo'))->not->toBeNull();
});

it('exposes exactly the tools documented on the MCP page', function () {
    $tools = (new ReflectionProperty(KnowledgeBaseServer::class, 'tools'))
        ->getDefaultValue();

    $names = array_map(fn (string $tool): string => app($tool)->name(), $tools);

    expect($names)->toBe(['search_documents', 'read_document', 'list_tags']);
});
