<?php

use App\Http\Controllers\CambiarIdiomaController;
use Illuminate\Support\Facades\Route;

/*
 * The Filament panel is served at the root path (intranet.dema.local/).
 */

/*
 * Interface language switch, also from the login page without a session.
 */
Route::get('idioma/{idioma}', CambiarIdiomaController::class)->name('idioma');
