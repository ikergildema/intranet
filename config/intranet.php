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

];
