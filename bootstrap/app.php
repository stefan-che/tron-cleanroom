<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\Handler as MiddlewareHandler;
use App\Console\Handler as ScheduleHandler;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__ . '/../app/Console/Commands'])
    ->withSchedule(new ScheduleHandler())
    ->withMiddleware(new MiddlewareHandler())
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
