<?php

namespace App\Services;

use App\Mail\CitaConfirmacionMail;
use App\Models\Ajuste;
use App\Models\Cita;
use App\Services\Mensajeria\LogDriver;
use App\Services\Mensajeria\MetaWhatsappDriver;
use App\Services\Mensajeria\ProveedorMensajeria;
use App\Services\Mensajeria\ResultadoEnvio;
use App\Services\Mensajeria\TwilioDriver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envío de recordatorios y confirmaciones de cita por correo, WhatsApp y SMS.
 * Nunca lanza excepciones hacia fuera: cada canal devuelve un ResultadoEnvio.
 */
class MensajeriaService
{
    /** Canales configurables en Ajustes (recordatorio_canal). */
    public const CANALES = ['correo', 'whatsapp', 'sms', 'correo_whatsapp'];

    public const ETIQUETAS_CANAL = [
        'correo' => 'Solo correo electrónico',
        'whatsapp' => 'Solo WhatsApp',
        'sms' => 'Solo SMS',
        'correo_whatsapp' => 'Correo electrónico y WhatsApp',
    ];

    public const PROVEEDORES = ['log', 'twilio', 'meta'];

    private ProveedorMensajeria $proveedor;

    public function __construct(?ProveedorMensajeria $proveedor = null)
    {
        $this->proveedor = $proveedor ?? static::crearProveedor();
    }

    /** Construye el driver indicado en config('services.mensajeria.proveedor'). */
    public static function crearProveedor(?string $nombre = null): ProveedorMensajeria
    {
        $config = (array) config('services.mensajeria', []);
        $nombre = strtolower((string) ($nombre ?: ($config['proveedor'] ?? 'log')));

        return match ($nombre) {
            'twilio' => new TwilioDriver(
                sid: $config['twilio']['sid'] ?? null,
                token: $config['twilio']['token'] ?? null,
                desdeSms: $config['twilio']['desde_sms'] ?? null,
                desdeWhatsapp: $config['twilio']['desde_whatsapp'] ?? null,
            ),
            'meta' => new MetaWhatsappDriver(
                token: $config['meta']['token'] ?? null,
                phoneId: $config['meta']['phone_id'] ?? null,
            ),
            default => new LogDriver,
        };
    }

    public static function nombreProveedor(): string
    {
        $nombre = strtolower((string) config('services.mensajeria.proveedor', 'log'));

        return in_array($nombre, self::PROVEEDORES, true) ? $nombre : 'log';
    }

    public function proveedor(): ProveedorMensajeria
    {
        return $this->proveedor;
    }

    /**
     * Deja el número en formato E.164: solo dígitos, con "+" y, si es un número
     * local de 8 dígitos, el prefijo de país configurado. Devuelve null si no es válido.
     */
    public static function normalizarNumero(?string $numero): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $numero);

        if (strlen($digitos) < 8 || strlen($digitos) > 15) {
            return null;
        }

        if (strlen($digitos) === 8) {
            $prefijo = preg_replace('/\D+/', '', (string) config('services.mensajeria.prefijo_pais', '591'));
            $digitos = $prefijo.$digitos;
        }

        return '+'.$digitos;
    }

    public function enviarWhatsapp(?string $numero, string $texto): ResultadoEnvio
    {
        return $this->enviar('whatsapp', $numero, $texto);
    }

    public function enviarSms(?string $numero, string $texto): ResultadoEnvio
    {
        return $this->enviar('sms', $numero, $texto);
    }

    private function enviar(string $canal, ?string $numero, string $texto): ResultadoEnvio
    {
        $destino = static::normalizarNumero($numero);

        if (! $destino) {
            $resultado = ResultadoEnvio::fallo('El número de teléfono "'.(string) $numero.'" no es válido.');
            Log::warning("Mensajería [{$canal}] descartado: {$resultado->error}");

            return $resultado;
        }

        try {
            $resultado = $canal === 'whatsapp'
                ? $this->proveedor->enviarWhatsapp($destino, $texto)
                : $this->proveedor->enviarSms($destino, $texto);
        } catch (Throwable $e) {
            report($e);
            $resultado = ResultadoEnvio::fallo($e->getMessage() ?: get_class($e));
        }

        Log::log(
            $resultado->exito ? 'info' : 'warning',
            "Mensajería [{$canal}] → {$destino}: ".($resultado->exito ? 'enviado' : 'falló'),
            ['proveedor' => static::nombreProveedor(), 'id_externo' => $resultado->idExterno, 'error' => $resultado->error],
        );

        return $resultado;
    }

    /** Texto corto del recordatorio (WhatsApp / SMS). */
    public function textoCita(Cita $cita, bool $esRecordatorio = true): string
    {
        $cita->loadMissing(['paciente', 'doctor']);

        $clinica = Ajuste::actual()->nombre;
        $fecha = $cita->fecha->format('d/m/Y');
        $hora = substr((string) $cita->hora, 0, 5);
        $doctor = $cita->doctor?->nombre_profesional ?? 'tu doctor';
        $accion = $esRecordatorio ? 'te recuerda tu cita' : 'registró tu cita';

        $texto = "Hola {$cita->paciente->nombres}, {$clinica} {$accion} el {$fecha} a las {$hora} con {$doctor}.";

        if ($cita->estado === 'CONFIRMADA') {
            return $texto." Tu asistencia ya está confirmada. Código: {$cita->token}.";
        }

        return $texto." Confirma aquí: {$cita->url_confirmacion}";
    }

    /**
     * Envía el recordatorio (o la confirmación inicial) por el canal configurado
     * en Ajustes, o por el indicado en $canal. Devuelve un resultado por canal.
     *
     * @return array<string, ResultadoEnvio>
     */
    public function recordatorioCita(Cita $cita, bool $esRecordatorio = true, ?string $canal = null): array
    {
        $cita->loadMissing(['paciente', 'doctor']);

        $canal = in_array($canal, self::CANALES, true) ? $canal : (Ajuste::actual()->recordatorio_canal ?: 'correo');
        $resultados = [];

        foreach (self::canalesDe($canal) as $medio) {
            $resultados[$medio] = match ($medio) {
                'correo' => $this->enviarCorreo($cita, $esRecordatorio),
                'whatsapp' => $this->enviarWhatsapp($cita->paciente?->telefono, $this->textoCita($cita, $esRecordatorio)),
                'sms' => $this->enviarSms($cita->paciente?->telefono, $this->textoCita($cita, $esRecordatorio)),
            };
        }

        $enviados = array_keys(array_filter($resultados, fn (ResultadoEnvio $r) => $r->exito));

        if ($enviados !== []) {
            $cita->forceFill(['recordatorio_canal' => implode(',', $enviados)])->save();
        }

        return $resultados;
    }

    /** Medios individuales que componen un canal configurado. */
    public static function canalesDe(string $canal): array
    {
        return match ($canal) {
            'correo_whatsapp' => ['correo', 'whatsapp'],
            'whatsapp', 'sms' => [$canal],
            default => ['correo'],
        };
    }

    private function enviarCorreo(Cita $cita, bool $esRecordatorio): ResultadoEnvio
    {
        $email = trim((string) $cita->paciente?->email);

        if ($email === '') {
            return ResultadoEnvio::fallo('El paciente no tiene correo electrónico.');
        }

        try {
            Mail::to($email)->send(new CitaConfirmacionMail($cita, esRecordatorio: $esRecordatorio));
        } catch (Throwable $e) {
            report($e);

            return ResultadoEnvio::fallo($e->getMessage() ?: get_class($e));
        }

        return ResultadoEnvio::ok();
    }
}
