<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $apiKeysConfig = config('app.global_our_api_key');
        if (!$apiKeysConfig) {
            return $next($request);
        }

        $apiKeys = array_map('trim', explode(',', $apiKeysConfig));

        $headerKey = $request->header('X-API-KEY');

        if (!in_array($headerKey, $apiKeys, true)) {
            Log::channel('security')->error('🚨 API KEY mismatch', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'request' => $request->all(),
                'provided_api_key' => $headerKey,
            ]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
