<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiKeyReadOnlyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $fullAccessKeysConfig = config('app.global_our_api_key');
        $readOnlyKeysConfig = config('app.global_readonly_api_key');

        if (!$fullAccessKeysConfig && !$readOnlyKeysConfig) {
            return $next($request);
        }

        $apiKeys = array_filter(array_map('trim', explode(',', $fullAccessKeysConfig . ',' . $readOnlyKeysConfig)));

        $headerKey = $request->header('X-API-KEY');

        if (!in_array($headerKey, $apiKeys, true)) {
            Log::channel('security')->error('🚨 API KEY mismatch (read-only)', [
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
