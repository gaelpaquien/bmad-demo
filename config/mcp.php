<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MCP Access Token
    |--------------------------------------------------------------------------
    |
    | Static bearer token an AI client must send to reach the MCP HTTP
    | endpoint (`/mcp`). When empty, every request to that endpoint is
    | refused. The local (stdio) server does not use it.
    |
    */

    'access_token' => env('MCP_ACCESS_TOKEN'),

];
