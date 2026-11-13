<?php

namespace App\Http\Middleware\Telegram;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\Response;

class TgOperationBotSecretTokenCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {

            $secretToken = config('telegram.bots.operations.secret_token');
            if (empty($secretToken)) {
                return $next($request);
            }

            $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

            if (empty($receivedToken)) {
                throw new Exception('Invalid Secret-Token!');
            }

            if ($receivedToken !== $secretToken) {
                throw new Exception('Invalid Secret-Token!');
            }

            return $next($request);
        } catch (\Exception $e) {

            Log::channel('security')->error('🚨 Secret token is missing', [
                'error' => $e->getMessage(),
                'bot' => 'operations',
            ]);

            return response()->json([
                'message' => 'Access is forbidden',
                'error' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }
}
