<?php

use App\Models\Document;
use App\Models\Tag;
use Illuminate\Testing\TestResponse;

function mcpValidToken(): string
{
    return str_repeat('a', 40);
}

function mcpToolsListRequest(?string $token): TestResponse
{
    $headers = ['Accept' => 'application/json, text/event-stream'];

    if ($token !== null) {
        $headers['Authorization'] = "Bearer {$token}";
    }

    return test()->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $headers);
}

it('serves the MCP tools over HTTP to a client presenting the configured token', function () {
    config(['mcp.access_token' => mcpValidToken()]);

    mcpToolsListRequest(mcpValidToken())
        ->assertOk()
        ->assertJsonPath('result.tools.0.name', 'search_documents');
});

it('refuses the MCP endpoint without a token', function () {
    config(['mcp.access_token' => mcpValidToken()]);

    mcpToolsListRequest(null)->assertUnauthorized();
});

it('refuses the MCP endpoint with a wrong token', function () {
    config(['mcp.access_token' => mcpValidToken()]);

    mcpToolsListRequest(str_repeat('b', 40))->assertUnauthorized();
});

it('refuses every request when no token is configured, even an empty bearer', function () {
    config(['mcp.access_token' => null]);

    mcpToolsListRequest('anything')->assertUnauthorized();
    mcpToolsListRequest('')->assertUnauthorized();
});

it('executes no tool for a refused request', function () {
    config(['mcp.access_token' => mcpValidToken()]);
    Document::factory()->create(['extracted_text' => 'facture']);

    test()->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'search_documents', 'arguments' => ['query' => 'facture']],
    ], ['Accept' => 'application/json, text/event-stream'])
        ->assertUnauthorized()
        ->assertDontSee('facture');
});

it('refuses a token shorter than the minimum length, even when the client presents it exactly', function () {
    config(['mcp.access_token' => 'short-token']);

    mcpToolsListRequest('short-token')->assertUnauthorized();
});

it('runs a tool over HTTP for a client presenting the configured token', function () {
    config(['mcp.access_token' => mcpValidToken()]);
    Tag::factory()->create(['name' => 'Finance']);

    test()->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list_tags', 'arguments' => (object) []],
    ], [
        'Accept' => 'application/json, text/event-stream',
        'Authorization' => 'Bearer '.mcpValidToken(),
    ])
        ->assertOk()
        ->assertJsonPath('result.structuredContent.tags.0.name', 'Finance');
});
