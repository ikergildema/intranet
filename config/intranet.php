<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Program applications
    |--------------------------------------------------------------------------
    |
    | Base URLs of the applications the intranet reads from, used to link each
    | participation to its original record.
    |
    */

    'programas' => [
        'abian' => ['url' => env('ABIAN_URL', 'http://abianv2.dema.local')],
        'anezka' => ['url' => env('ANEZKA_URL', 'http://anezka.dema.local')],
        'enpresari' => ['url' => env('ENPRESARI_URL', 'http://enpresari.dema.local')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Abian migrant rule
    |--------------------------------------------------------------------------
    |
    | Same fixed rule as abianv2 (config/abian.php there): from this date, a
    | person is migrant when they have a country of origin; before it, when
    | their document is not a Spanish NIF. Decided by the recepción date.
    |
    */

    'abian_migrante_por_pais_desde' => env('ABIAN_MIGRANTE_POR_PAIS_DESDE', '2025-09-19'),

];
