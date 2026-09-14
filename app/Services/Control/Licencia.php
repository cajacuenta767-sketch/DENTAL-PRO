<?php

namespace App\Services\Control;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Fachada de la licencia CONTROL para toda la aplicación (singleton).
 *
 * En cada petición solo corre la verificación local del token (sodium). El
 * latido remoto se dispara cuando el token guardado expira en menos de
 * `control.renovar_dias` o el estado es `mora`, y como máximo una vez cada
 * `control.latido_cada` segundos (Cache). Sin red se sigue funcionando
 * hasta `expira_en` del token.
 */
class Licencia
{
    private ?array $estado = null;

    public function __construct(
        private ControlLicencia $sdk,
        private array $config,
    ) {}

    public static function desdeConfig(array $config): self
    {
        return new self(new ControlLicencia(
            url: $config['url'] ?? '',
            clave: $config['licencia'] ?? null,
            producto: $config['producto'] ?? 'dental-pro',
            clavePublicaBase64: $config['clave_publica'] ?? null,
            archivo: $config['archivo'] ?? storage_path('app/control/licencia.json'),
            huella: self::huellaDesde($config),
            version: $config['version'] ?? null,
        ), $config);
    }

    /**
     * Huella del equipo: la configurada; si no, en localhost (escritorio) un
     * hash del nombre del equipo, y en un servidor el dominio de APP_URL.
     */
    public static function huellaDesde(array $config): string
    {
        if (! empty($config['huella'])) {
            return trim((string) $config['huella']);
        }

        $host = strtolower((string) parse_url((string) ($config['app_url'] ?? ''), PHP_URL_HOST));

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return substr(hash('sha256', gethostname() ?: 'dental-pro'), 0, 32);
        }

        return $host;
    }

    public function sdk(): ControlLicencia
    {
        return $this->sdk;
    }

    public function activo(): bool
    {
        return (bool) ($this->config['activo'] ?? false);
    }

    public function tieneClave(): bool
    {
        return $this->sdk->tieneClave();
    }

    public function huella(): string
    {
        return $this->sdk->huella();
    }

    /** Olvida el estado calculado (se llama al inicio de cada petición). */
    public function olvidar(): void
    {
        $this->estado = null;
    }

    /**
     * Estado de la licencia, en caché durante la petición.
     *
     * @return array{valido: bool, payload: ?array, motivo: ?string, codigo: ?string}
     */
    public function estado(): array
    {
        if ($this->estado !== null) {
            return $this->estado;
        }

        $estado = $this->sdk->cargar();

        if ($this->necesitaRed($estado) && $this->tieneClave() && $this->turnoDeLatido()) {
            $estado = $this->remoto($estado['valido'] ? 'latido' : 'activar') ?? $this->sdk->cargar();
        }

        return $this->estado = $estado;
    }

    public function valida(): bool
    {
        return $this->estado()['valido'];
    }

    public function enMora(): bool
    {
        return $this->valida() && ($this->estado()['payload']['estado'] ?? null) === 'mora';
    }

    public function motivo(): ?string
    {
        return $this->estado()['motivo'];
    }

    public function codigo(): ?string
    {
        return $this->estado()['codigo'];
    }

    /** Botón "Reactivar / verificar ahora" y `licencia:activar`: activa sin esperar el turno. */
    public function verificarAhora(): array
    {
        $this->olvidar();
        $this->sdk->cargar();
        $this->marcarLatido();

        try {
            return $this->estado = $this->sdk->activar();
        } catch (RuntimeException $e) {
            $estado = $this->sdk->estado();

            return $this->estado = $estado['valido']
                ? $estado
                : ['valido' => false, 'payload' => null, 'motivo' => 'Sin conexión con CONTROL: '.$e->getMessage(), 'codigo' => 'sin_conexion'];
        }
    }

    /** Latido programado (`licencia:latido`): renueva el token; si el equipo no está activado, activa. */
    public function latido(): array
    {
        $this->olvidar();
        $this->sdk->cargar();
        $this->marcarLatido();

        try {
            $estado = $this->sdk->latido();
            if (! $estado['valido'] && ($estado['codigo'] ?? null) === 'no_activada') {
                $estado = $this->sdk->activar();
            }

            return $this->estado = $estado;
        } catch (RuntimeException $e) {
            $estado = $this->sdk->estado();

            return $this->estado = $estado['valido']
                ? $estado
                : ['valido' => false, 'payload' => null, 'motivo' => 'Sin conexión con CONTROL: '.$e->getMessage(), 'codigo' => 'sin_conexion'];
        }
    }

    public function guardarClave(string $clave): string
    {
        $this->olvidar();
        Cache::forget($this->claveCache());

        return $this->sdk->guardarClave($clave);
    }

    public function aplicarCodigoEmergencia(string $codigo): array
    {
        $this->olvidar();

        return $this->sdk->aplicarCodigoEmergencia($codigo);
    }

    /** Datos de la pantalla "Licencia" (calcula el estado si aún no se hizo). */
    public function resumen(): array
    {
        $this->estado();

        return $this->sdk->resumen() + ['activo' => $this->activo()];
    }

    private function necesitaRed(array $estado): bool
    {
        if (! $estado['valido']) {
            return true;
        }

        $p = $estado['payload'] ?? [];
        if (($p['estado'] ?? null) === 'mora') {
            return true;
        }

        $dias = (int) ($this->config['renovar_dias'] ?? 2);
        $expira = strtotime($p['expira_en'] ?? '1970-01-01');

        return $expira < time() + $dias * 86400;
    }

    private function remoto(string $accion): ?array
    {
        try {
            return $accion === 'latido' ? $this->sdk->latido() : $this->sdk->activar();
        } catch (RuntimeException $e) {
            Log::notice('CONTROL sin conexión: '.$e->getMessage());

            return null;
        }
    }

    /** true solo una vez por ventana de `latido_cada` segundos. */
    private function turnoDeLatido(): bool
    {
        $segundos = max(60, (int) ($this->config['latido_cada'] ?? 3600));

        return Cache::add($this->claveCache(), now()->toIso8601String(), $segundos);
    }

    private function marcarLatido(): void
    {
        $segundos = max(60, (int) ($this->config['latido_cada'] ?? 3600));
        Cache::put($this->claveCache(), now()->toIso8601String(), $segundos);
    }

    private function claveCache(): string
    {
        return 'control:latido:'.$this->sdk->huella();
    }
}
