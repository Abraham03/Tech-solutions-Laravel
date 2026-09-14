<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// NUESTRO TRABAJADOR INVISIBLE
// Le decimos que ejecute el escaneo todos los días exactamente a la medianoche.
Schedule::command('services:check-expiring')->dailyAt('17:00');

// Antes del escaneo: deja a BasketPro al día con lo que se cobró aquí (plan, vigencia, estado).
Schedule::command('basketpro:sync-subscriptions')->dailyAt('16:30');
