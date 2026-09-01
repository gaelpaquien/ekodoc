<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A request rejected by PHP's own post_max_size before Laravel's own
        // 20MB validation rule runs — surface the same friendly message
        // instead of an uncaught exception (AD-9 convention: no raw errors).
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            return back()->withErrors([
                'file' => 'Fichier trop volumineux (20 Mo maximum). Formats acceptés : PDF, Word (.docx), Excel (.xlsx).',
            ]);
        });
    })->create();
