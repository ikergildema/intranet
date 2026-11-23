<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('intranet:sincronizar')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer();
