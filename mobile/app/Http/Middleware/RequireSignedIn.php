<?php

namespace App\Http\Middleware;

use App\Services\TokenStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Layar dalam hanya boleh dibuka kalau ponsel ini sudah pernah berhasil masuk.
 */
class RequireSignedIn
{
    public function __construct(private TokenStore $tokenStore) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tokenStore->isSignedIn()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
