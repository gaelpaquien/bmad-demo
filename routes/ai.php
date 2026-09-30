<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\KnowledgeBaseServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) server: `php artisan mcp:start bmad-demo`, launched by the AI
// client itself (Claude Desktop, Claude Code, Cursor...). No token needed.
Mcp::local('bmad-demo', KnowledgeBaseServer::class);

// HTTP endpoint for remote clients once exposed over HTTPS; refused unless
// the request carries MCP_ACCESS_TOKEN as a bearer.
Mcp::web('/mcp', KnowledgeBaseServer::class)
    ->middleware(AuthenticateMcpToken::class);
