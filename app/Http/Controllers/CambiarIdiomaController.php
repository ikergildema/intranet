<?php

namespace App\Http\Controllers;

use App\Enums\Idioma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Changes the interface language and remembers it in the user.
 */
class CambiarIdiomaController
{
    public function __invoke(Request $request, string $idioma): RedirectResponse
    {
        abort_unless(in_array($idioma, Idioma::valores(), true), 404);

        $request->session()->put('locale', $idioma);
        $request->user()?->update(['locale' => $idioma]);

        return redirect()->back(fallback: '/');
    }
}
