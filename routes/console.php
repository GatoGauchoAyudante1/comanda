<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Abono mensual del sistema. A las 08:00 el local todavía está cerrado: el
// cargo queda hecho antes de que alguien entre a operar. Ver App\Services\LicenseService.
Schedule::command('licencia:aviso')->dailyAt('08:00');
