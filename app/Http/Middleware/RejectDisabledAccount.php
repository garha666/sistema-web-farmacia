<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RejectDisabledAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->disabled_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson() ? response()->json([], 401) : redirect('/login');
        }

        return $next($request);
    }
}
