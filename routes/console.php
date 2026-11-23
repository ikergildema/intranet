<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('intranet:sincronizar')
    ->everyFourHours()
    ->withoutOverlapping()
    ->onOneServer();
