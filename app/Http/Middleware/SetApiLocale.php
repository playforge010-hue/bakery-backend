<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Select a supported API locale from the client header or bakery default. */
class SetApiLocale
{
    /** Apply Gujarati by default and accept English or Hindi through X-Locale. */
    public function handle(Request $request, Closure $next): Response
    {
        $requestedLocale = strtolower((string) $request->header('X-Locale', config('app.locale', 'gu')));
        $locale = in_array($requestedLocale, ['gu', 'en', 'hi'], true) ? $requestedLocale : 'gu';

        App::setLocale($locale);

        return $next($request);
    }
}
