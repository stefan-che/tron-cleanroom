<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogViewerBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $username = env('LOG_VIEWER_USERNAME');
        $password = env('LOG_VIEWER_PASSWORD');

        if (empty($username) || empty($password)) {
            return $next($request);
        }

        if ($request->getUser() !== $username || $request->getPassword() !== $password) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Log Viewer"',
            ]);
        }

        return $next($request);
    }
}

