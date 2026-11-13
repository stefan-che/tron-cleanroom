<?php

namespace App\Http\Middleware;

use App\Helpers\Development;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AccessApiIpMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $ips = Setting::getAccessApiIps();

        if (empty($ips)) {
            return $next($request);
        }

        $ip = Development::getRealIpUser();

        if (in_array($ip, $ips, true)) {
            return $next($request);
        }

        Log::channel('security')->error('🚨 API access from an unknown IP', [
            'ip' => $ip,
            'ip2' => $request->ip(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'request' => $request->all(),
        ]);

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
