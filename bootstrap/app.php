<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminOnlyMiddleware;
use App\Http\Middleware\AuditLogMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'admin-only' => AdminOnlyMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
        $middleware->appendToGroup('web', AuditLogMiddleware::class);
        $middleware->validateCsrfTokens(except: ['payments/sslcommerz/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['api_key', 'secret_key']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
