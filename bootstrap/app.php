<?php

use App\Http\Middleware\ResolveProjectFromApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToPriorityList([
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
        ], ResolveProjectFromApiKey::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
