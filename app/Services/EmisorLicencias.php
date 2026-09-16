<?php

namespace App\Services;

use App\Models\LicenciaEmitida;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lado del proveedor: crea cadenas por cliente, firma códigos de activación y
 * calcula PINes de renovación. Requiere la clave privada configurada.
 */
class EmisorLicencias
{
    public function __construct(private readonly LicenciaService $licencias) {}

    public function disponible(): bool
    {
        return filled(config('licencia.clave_privada'));
    }

    private function clavePrivada(): string
    {
        if (! $this->disponible()) {
            throw new RuntimeException('Esta instalación no tiene la clave privada para emitir licencias.');
        }

        return (string) config('licencia.clave_privada');
    }

    /** Traduce "días desde hoy", "hasta fecha" o "vitalicia" al índice de día. */
    public function diaFinDesde(?int $dias = null, ?string $hasta = null, bool $vitalicia = false): int
    {
        if ($vitalicia) {
            return $this->licencias->diasMaximos();
        }

        if ($hasta) {
            $dia = $this->licencias->diaDeFecha($hasta);
        } elseif ($dias !== null) {
            $dia = $this->licencias->diaDeFecha(CarbonImmutable::today()->addDays($dias));
        } else {
            throw new InvalidArgumentException('Indica días, fecha límite o vitalicia.');
        }

        if ($dia <= $this->licencias->diaDeFecha(CarbonImmutable::today())) {
            throw new InvalidArgumentException('La fecha de vencimiento debe ser posterior a hoy.');
        }

        return $dia;
    }

    /** Registra un cliente nuevo y devuelve su código de activación. */
    public function emitir(string $cliente, int $tipo, int $diaFin, ?string $contacto = null, ?string $notas = null): LicenciaEmitida
    {
        $semilla = $this->licencias->nuevaSemilla();
        $ancla = $this->licencias->ancla($semilla);

        $emitida = LicenciaEmitida::create([
            'cliente' => $cliente,
            'contacto' => $contacto,
            'semilla' => bin2hex($semilla),
            'ancla' => bin2hex($ancla),
            'tipo' => $tipo === LicenciaService::TIPO_PRUEBA ? 'PRUEBA' : 'COMPLETA',
            'dia_fin' => $diaFin,
            'notas' => $notas,
        ]);

        $emitida->ultimo_codigo = $this->licencias->codigoActivacion($ancla, $tipo, $diaFin, $this->clavePrivada());
        $emitida->save();

        return $emitida;
    }

    /** Nuevo código de activación para un cliente ya registrado (misma cadena). */
    public function reactivar(LicenciaEmitida $emitida, int $tipo, int $diaFin): string
    {
        $codigo = $this->licencias->codigoActivacion(hex2bin($emitida->ancla), $tipo, $diaFin, $this->clavePrivada());

        $emitida->update([
            'tipo' => $tipo === LicenciaService::TIPO_PRUEBA ? 'PRUEBA' : 'COMPLETA',
            'dia_fin' => $diaFin,
            'ultimo_codigo' => $codigo,
        ]);

        return $codigo;
    }

    /** PIN corto que extiende la licencia de una instalación concreta. */
    public function renovar(LicenciaEmitida $emitida, string $codigoInstalacion, int $diaFin): string
    {
        $instalacion = $this->licencias->normalizarInstalacion($codigoInstalacion);

        if ($instalacion === null) {
            throw new InvalidArgumentException('El código de instalación debe tener 8 caracteres, por ejemplo 7K3M-9P2Q.');
        }

        if ($diaFin <= $emitida->dia_fin) {
            throw new InvalidArgumentException('La nueva fecha debe ser posterior al vencimiento ya otorgado ('.$emitida->vence_en->format('d/m/Y').').');
        }

        $pin = $this->licencias->pinRenovacion(hex2bin($emitida->semilla), $diaFin, $instalacion);

        $emitida->update([
            'tipo' => 'COMPLETA',
            'dia_fin' => $diaFin,
            'codigo_instalacion' => $instalacion,
            'ultimo_codigo' => $pin,
        ]);

        return $pin;
    }
}
