<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedLocales = ['bg', 'en', 'de', 'fr', 'es', 'ro', 'tr'];

        $locale = session('locale', config('app.locale'));

        if (!in_array($locale, $allowedLocales)) {
            $locale = 'bg';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}