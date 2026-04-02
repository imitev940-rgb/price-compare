<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveUserMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (!$user->is_active) {
            auth()->logout();

            return redirect()->route('login')
                ->withErrors([
                    'email' => 'Your account is inactive. Please contact administrator.',
                ]);
        }

        return $next($request);
    }
}