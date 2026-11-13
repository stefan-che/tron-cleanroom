<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogRequests
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $method = $request->method();

        // Log only GET and POST requests
        if (in_array($method, ['POST'])) { // 'GET',
            $url = $request->fullUrl();
            $ip = $request->ip();
            $userAgent = $request->userAgent();

            $queryParams = $request->query();
            $postParams = $request->post();
            $allParams = $request->all();

            $logData = [
                'method' => $method,
                'url' => $url,
                'ip' => $ip,
                'user_agent' => $userAgent,
                'query_params' => $queryParams,
                'post_params' => $postParams,
                'all_params' => $allParams,
                'timestamp' => now()->toDateTimeString(),
            ];

            Log::channel('requests')->info('HTTP POST Request', $logData);
        }

        return $next($request);
    }
}
