<?php

it('renders the MCP documentation page with the project path', function () {
    test()->get('/configuration/mcp')->assertInertia(fn ($page) => $page
        ->component('Documents/Mcp')
        ->where('projectPath', base_path())
    );
});

it('no longer exposes an MCP HTTP endpoint', function () {
    test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertNotFound();
});
