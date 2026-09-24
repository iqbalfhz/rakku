<?php

use App\Services\DiagnosticsReporter;
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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Error di ponsel orang lain tidak pernah sampai ke kita kalau tidak dikabarkan.
        $exceptions->report(fn (Throwable $exception) => app(DiagnosticsReporter::class)->report(
            DiagnosticsReporter::CRASH,
            $exception::class.': '.$exception->getMessage(),
        ));
    })->create();
