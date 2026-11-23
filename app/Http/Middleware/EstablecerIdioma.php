<?php

namespace App\Http\Middleware;

use App\Enums\Idioma;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the interface language: the one in the session or, failing that, the user's.
 */
class EstablecerIdioma
{
    public function handle(Request $request, Closure $next): Response
    {
        $idioma = $request->session()->get('locale') ?? $request->user()?->locale ?? config('app.locale');

        if (! in_array($idioma, Idioma::valores(), true)) {
            $idioma = Idioma::Castellano->value;
        }

        app()->setLocale($idioma);
        Carbon::setLocale($idioma);

        return $next($request);
    }
}
