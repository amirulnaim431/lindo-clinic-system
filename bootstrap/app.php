<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'staff_or_admin' => \App\Http\Middleware\EnsureStaffOrAdmin::class,
            'app_permission' => \App\Http\Middleware\EnsureAppPermission::class,
        ]);
    })
    ->withExceptions(function ($exceptions) {
        //
    })
    ->create();
