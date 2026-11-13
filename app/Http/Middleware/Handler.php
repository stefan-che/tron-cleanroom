<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Configuration\Middleware as BaseMiddleware;

class Handler
{
    protected array $aliases = [
        'api.key' => ApiKeyMiddleware::class,
        'api.key.readonly' => ApiKeyReadOnlyMiddleware::class,
        'api.access_ip' => AccessApiIpMiddleware::class,
    ];

    public function __invoke(BaseMiddleware $middleware): BaseMiddleware
    {
        $middleware->append(LogRequests::class);

        if ($this->aliases) {
            $middleware->alias($this->aliases);
        }

        return $middleware;
    }
}
