<?php

namespace App\Console\Commands;

use App\Models\Ajuste;
use App\Models\Cita;
use App\Services\Mensajeria\ResultadoEnvio;
use App\Services\MensajeriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class EnviarRecordatoriosCitas extends Command
{
    protected $signature = 'citas:recordar
                            {--horas= : Horas de anticipación (por defecto, el valor configurado en Ajustes)}
                            {--canal= : Forzar el canal: correo, whatsapp, sms o correo_whatsapp (por defecto, el de Ajustes)}';

    protected $description = 'Envía el recordatorio de las citas próximas que aún no lo recibieron (correo, WhatsApp o SMS)';

    public function handle(MensajeriaService $mensajeria): int
    {
        $ajuste = Ajuste::actual();
        $horas = (int) ($this->option('horas') ?: $ajuste->horas_recordatorio ?: 24);
        $canal = (string) ($this->option('canal') ?: $ajuste->recordatorio_canal ?: 'correo');

        if (! in_array($canal, MensajeriaService::CANALES, true)) {
            $this->error("Canal no válido: {$canal}. Usa uno de: ".implode(', ', MensajeriaService::CANALES).'.');

            return self::INVALID;
        }

        $medios = MensajeriaService::canalesDe($canal);
        $desde = Carbon::now();
        $hasta = $desde->copy()->addHours($horas);

        $citas = Cita::query()
            ->with(['paciente', 'doctor'])
            ->whereIn('estado', ['PENDIENTE', 'CONFIRMADA'])
            ->whereNull('recordatorio_enviado_en')
            ->whereHas('paciente', function ($q) use ($medios) {
                $q->where(function ($q) use ($medios) {
                    if (in_array('correo', $medios, true)) {
                        $q->orWhere(fn ($q) => $q->whereNotNull('email')->where('email', '<>', ''));
                    }
                    if (array_intersect(['whatsapp', 'sms'], $medios) !== []) {
                        $q->orWhere(fn ($q) => $q->whereNotNull('telefono')->where('telefono', '<>', ''));
                    }
                });
            })
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
                $resultados = $mensajeria->recordatorioCita($cita, esRecordatorio: true, canal: $canal);
            } catch (Throwable $e) {
                report($e);
                $this->error("No se pudo enviar el recordatorio de la cita {$cita->token}: {$e->getMessage()}");

                continue;
            }

            $exitosos = array_filter($resultados, fn (ResultadoEnvio $r) => $r->exito);

            foreach ($resultados as $medio => $resultado) {
                if (! $resultado->exito) {
                    $this->warn("Cita {$cita->token} [{$medio}]: {$resultado->error}");
                }
            }

            if ($exitosos === []) {
                $this->error("No se pudo enviar el recordatorio de la cita {$cita->token} por ningún canal.");

                continue;
            }

            $cita->forceFill(['recordatorio_enviado_en' => now()])->save();
            $enviados++;
        }

        $this->info("Recordatorios enviados: {$enviados} (ventana de {$horas} horas, canal {$canal}).");

        return self::SUCCESS;
    }
}
