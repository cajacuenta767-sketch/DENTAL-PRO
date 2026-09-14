<?php

namespace App\Console\Commands;

use App\Mail\CitaConfirmacionMail;
use App\Models\Ajuste;
use App\Models\Cita;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EnviarRecordatoriosCitas extends Command
{
    protected $signature = 'citas:recordar
                            {--horas= : Horas de anticipación (por defecto, el valor configurado en Ajustes)}';

    protected $description = 'Envía por correo el recordatorio de las citas próximas que aún no lo recibieron';

    public function handle(): int
    {
        $horas = (int) ($this->option('horas') ?: Ajuste::actual()->horas_recordatorio ?: 24);

        $desde = Carbon::now();
        $hasta = $desde->copy()->addHours($horas);

        $citas = Cita::query()
            ->with('paciente')
            ->whereIn('estado', ['PENDIENTE', 'CONFIRMADA'])
            ->whereNull('recordatorio_enviado_en')
            ->whereHas('paciente', fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''))
            ->whereRaw(
                '(fecha::timestamp + hora::interval) BETWEEN ? AND ?',
                [$desde->toDateTimeString(), $hasta->toDateTimeString()],
            )
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $enviados = 0;

        foreach ($citas as $cita) {
            try {
                Mail::to($cita->paciente->email)->send(new CitaConfirmacionMail($cita, esRecordatorio: true));

                $cita->forceFill(['recordatorio_enviado_en' => now()])->save();
                $enviados++;
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo enviar el recordatorio de la cita {$cita->token}: {$e->getMessage()}");
            }
        }

        $this->info("Recordatorios enviados: {$enviados} (ventana de {$horas} horas).");

        return self::SUCCESS;
    }
}
