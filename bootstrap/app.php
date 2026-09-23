<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Di Coolify, request datang lewat Cloudflare Tunnel sebagai HTTP biasa. Tanpa ini Laravel mengira
         * aksesnya bukan HTTPS: URL dibangun dengan http:// dan link bertanda tangan (PDF, verifikasi email)
         * ditolak. "*" aman selama port aplikasi hanya terikat ke 127.0.0.1.
         */
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
