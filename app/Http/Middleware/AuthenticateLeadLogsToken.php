<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the inbound leads-log API with a shared bearer token from
 * `services.lead_logs.token`. Fails closed: if no token is configured,
 * every request is rejected rather than the endpoint being left open.
 */
class AuthenticateLeadLogsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.lead_logs.token');
        $provided = (string) $request->bearerToken();

        abort_if($expected === '' || ! hash_equals($expected, $provided), 401, 'Unauthenticated.');

        return $next($request);
    }
}
