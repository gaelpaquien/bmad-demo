<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the MCP HTTP endpoint with the static bearer token from
 * `config('mcp.access_token')`. An unset token, or one shorter than
 * MINIMUM_TOKEN_LENGTH, refuses every request: the endpoint is never open
 * by omission nor protected by a guessable secret.
 */
class AuthenticateMcpToken
{
    public const MINIMUM_TOKEN_LENGTH = 32;

    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('mcp.access_token');
        $provided = $request->bearerToken();

        if (! is_string($expected) || strlen($expected) < self::MINIMUM_TOKEN_LENGTH || $provided === null || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Jeton MCP absent ou invalide.'], 401);
        }

        return $next($request);
    }
}
