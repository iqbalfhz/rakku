<?php

namespace App\Http\Middleware;

use App\Services\TokenStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplikasi selalu menyala di layar masuk. Kalau ponsel ini sudah pernah masuk,
 * pengguna langsung dibawa ke bukunya, bukan diminta masuk lagi.
 */
class RedirectIfSignedIn
{
    public function __construct(private TokenStore $tokenStore) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tokenStore->isSignedIn()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
