<?php

use App\Mcp\Servers\KnowledgeBaseServer;
use Laravel\Mcp\Facades\Mcp;

// Local (stdio) server: `php artisan mcp:start bmad-demo`, launched by the AI
// client itself (Claude Desktop, Claude Code, Cursor...). No token needed.
// Remote access is not exposed yet.
Mcp::local('bmad-demo', KnowledgeBaseServer::class);
