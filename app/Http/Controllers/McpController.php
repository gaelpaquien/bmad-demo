<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class McpController extends Controller
{
    /**
     * Documentation page for the local MCP server. `projectPath` lets the
     * page show a copy-paste-ready client config with the real absolute path
     * of this installation.
     */
    public function index(): Response
    {
        return Inertia::render('Documents/Mcp', [
            'projectPath' => base_path(),
        ]);
    }
}
