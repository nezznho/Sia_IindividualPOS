<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

/**
 * Authenticates API requests with a key sent as either:
 *   X-API-KEY: gc_xxxxx
 *   Authorization: Bearer gc_xxxxx
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->header('X-API-KEY') ?: $request->bearerToken();

        $key = $plain ? ApiKey::findActive($plain) : null;

        if (! $key) {
            return response()->json([
                'message' => 'Invalid or missing API key. Send it in the X-API-KEY header.',
            ], 401);
        }

        // Avoid a DB write on every request: update at most once a minute
        if (! $key->last_used_at || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('api_key', $key);

        return $next($request);
    }
}
