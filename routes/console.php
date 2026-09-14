<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de citas: revisa cada hora las citas próximas sin aviso enviado.
Schedule::command('citas:recordar')->hourly()->withoutOverlapping();

// Limpia los trabajos fallidos de la cola con más de una semana.
Schedule::command('queue:prune-failed --hours=168')->daily();

// Copia de seguridad diaria (base de datos + archivos privados) a las 02:00.
Schedule::command('sistema:respaldar')->dailyAt('02:00')->withoutOverlapping();
