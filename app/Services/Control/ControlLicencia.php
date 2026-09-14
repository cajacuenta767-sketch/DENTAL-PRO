<?php

namespace App\Services\Control;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * SDK de licencias CONTROL para DENTAL-PRO.
 *
 * Mismo protocolo que sdk/php/ControlLicencia.php de CONTROL: activar, latido,
 * verificar (firma Ed25519 con sodium), código de emergencia y resumen para la
 * pantalla estándar "Licencia". Las llamadas HTTP usan el cliente de Laravel
 * para poder simularlas con Http::fake() en las pruebas.
 *
 * El archivo `storage/app/control/licencia.json` guarda {clave, token,
 * guardado_en, info, ultimo_error}: la clave puede venir de ahí (registrada
 * desde la pantalla o el instalador) o de CONTROL_LICENCIA en el .env.
 */
class ControlLicencia
{
    public const FORMATO_CLAVE = '/^CTL-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/';

    /** Códigos con los que CONTROL rechaza una activación o un latido. */
    public const BLOQUEADAS = ['pendiente_pago', 'suspendida', 'vencida', 'revocada'];

    private array $estado = ['valido' => false, 'payload' => null, 'motivo' => 'sin iniciar', 'codigo' => null];

    private array $info = [];

    private ?array $ultimoError = null;

    private ?string $clave;

    public function __construct(
        private string $url,
        ?string $clave,
        private string $producto,
        private ?string $clavePublicaBase64,
        private string $archivo,
        private ?string $huella = null,
        private ?string $version = null,
    ) {
        $this->url = rtrim($url, '/');
        $this->huella = $huella ?: substr(hash('sha256', gethostname() ?: 'php'), 0, 32);

        $this->claveConfigurada = self::normalizar($clave);
        $this->recargar();
    }

    private ?string $claveConfigurada;

    /** Relee el archivo: la clave registrada ahí manda sobre CONTROL_LICENCIA. */
    private function recargar(): void
    {
        $archivo = $this->leerArchivo();
        $this->clave = self::normalizar($archivo['clave'] ?? null) ?: $this->claveConfigurada;
        $this->info = is_array($archivo['info'] ?? null) ? $archivo['info'] : [];
        $this->ultimoError = is_array($archivo['ultimo_error'] ?? null) ? $archivo['ultimo_error'] : null;
    }

    public function tieneClave(): bool
    {
        return $this->clave !== null && $this->clave !== '';
    }

    public function clave(): ?string
    {
        return $this->clave;
    }

    public function huella(): string
    {
        return $this->huella;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public static function normalizar(?string $clave): ?string
    {
        $clave = strtoupper(trim((string) $clave));

        return $clave === '' ? null : $clave;
    }

    public static function formatoValido(?string $clave): bool
    {
        return (bool) preg_match(self::FORMATO_CLAVE, (string) self::normalizar($clave));
    }

    /**
     * Registra (o cambia) la clave de licencia en el archivo. Al cambiar de
     * clave se descarta el token anterior porque ya no le corresponde.
     */
    public function guardarClave(string $clave): string
    {
        $clave = self::normalizar($clave);

        if (! self::formatoValido($clave)) {
            throw new InvalidArgumentException('La clave debe tener el formato CTL-XXXX-XXXX-XXXX-XXXX.');
        }

        $archivo = $this->leerArchivo();
        if (($archivo['clave'] ?? null) !== $clave) {
            $archivo = ['token' => null, 'info' => [], 'ultimo_error' => null];
            $this->info = [];
            $this->ultimoError = null;
            $this->estado = ['valido' => false, 'payload' => null, 'motivo' => 'Clave registrada, falta activar', 'codigo' => null];
        }

        $this->clave = $clave;
        $this->escribirArchivo(['clave' => $clave] + $archivo);

        return $clave;
    }

    /** Verifica firma Ed25519 y coherencia del payload. Devuelve el payload o null. */
    public function verificar(?string $token): ?array
    {
        if (! $token || ! str_contains($token, '.') || ! $this->clavePublicaBase64) {
            return null;
        }

        [$cuerpo, $firma] = explode('.', trim($token), 2);
        $publica = base64_decode($this->clavePublicaBase64, true);
        $firmaBin = self::b64urlDecode($firma);

        if ($publica === false || strlen($publica) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($firmaBin) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        if (! sodium_crypto_sign_verify_detached($firmaBin, $cuerpo, $publica)) {
            return null;
        }

        $p = json_decode(self::b64urlDecode($cuerpo), true);
        if (! is_array($p)) {
            return null;
        }

        if (($p['clave'] ?? null) !== $this->clave || ($p['producto'] ?? null) !== $this->producto || ($p['huella'] ?? null) !== $this->huella) {
            return null;
        }

        if (strtotime($p['expira_en'] ?? '1970-01-01') < time()) {
            return null;
        }

        return $p;
    }

    /**
     * Carga el token guardado y lo verifica localmente (sin red). Devuelve el
     * estado resultante; si el token no sirve, el motivo explica por qué.
     */
    public function cargar(): array
    {
        $this->recargar();

        if (! $this->tieneClave()) {
            return $this->estado = ['valido' => false, 'payload' => null, 'motivo' => 'No hay clave de licencia registrada', 'codigo' => 'sin_clave'];
        }

        $token = $this->tokenGuardado();
        $p = $token ? $this->verificar($token) : null;

        if ($p) {
            return $this->estado = ['valido' => true, 'payload' => $p, 'motivo' => null, 'codigo' => $p['estado'] ?? null];
        }

        if ($this->ultimoError) {
            return $this->estado = ['valido' => false, 'payload' => null, 'motivo' => $this->ultimoError['motivo'] ?? 'Licencia no válida', 'codigo' => $this->ultimoError['codigo'] ?? null];
        }

        $motivo = $token ? 'El token de licencia venció o no corresponde a este equipo; hace falta conexión con CONTROL' : 'La licencia aún no se activó en este equipo';

        return $this->estado = ['valido' => false, 'payload' => null, 'motivo' => $motivo, 'codigo' => $token ? 'token_vencido' : 'sin_activar'];
    }

    /** @throws RuntimeException sin conexión con CONTROL */
    public function activar(): array
    {
        return $this->procesar($this->llamar('activar', [
            'clave' => $this->clave,
            'producto' => $this->producto,
            'huella' => $this->huella,
            'dominio' => $this->huella,
            'nombre_equipo' => gethostname() ?: null,
            'version' => $this->version,
        ]));
    }

    /** @throws RuntimeException sin conexión con CONTROL */
    public function latido(): array
    {
        return $this->procesar($this->llamar('latido', [
            'clave' => $this->clave,
            'huella' => $this->huella,
            'version' => $this->version,
        ]));
    }

    /** Token guardado si es válido; si no, activa. Sin red, vale hasta expira_en. */
    public function iniciar(): array
    {
        $this->cargar();
        $valido = $this->estado['valido'];

        try {
            $valido ? $this->latido() : $this->activar();
        } catch (RuntimeException $e) {
            if (! $valido) {
                $this->estado = ['valido' => false, 'payload' => null, 'motivo' => 'Sin conexión con CONTROL: '.$e->getMessage(), 'codigo' => 'sin_conexion'];
            }
        }

        return $this->estado;
    }

    public function estado(): array
    {
        return $this->estado;
    }

    public function payload(): ?array
    {
        return $this->estado['payload'];
    }

    public function tokenGuardado(): ?string
    {
        $token = $this->leerArchivo()['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /** Código de 72 h emitido desde CONTROL para este equipo; se verifica sin red. */
    public function aplicarCodigoEmergencia(string $codigo): array
    {
        $codigo = trim($codigo);
        $p = $this->verificar($codigo);

        if (! $p || empty($p['emergencia'])) {
            return ['ok' => false, 'motivo' => $p ? 'Ese código no es de emergencia' : 'Código inválido, vencido o de otro equipo'];
        }

        $this->ultimoError = null;
        $this->escribirArchivo(['clave' => $this->clave, 'token' => $codigo, 'info' => $this->info, 'ultimo_error' => null]);
        $this->estado = ['valido' => true, 'payload' => $p, 'motivo' => null, 'codigo' => $p['estado'] ?? 'activa'];

        return ['ok' => true, 'expira_en' => $p['expira_en']];
    }

    /** Datos para la pantalla estándar "Licencia" (sdk/pantalla-licencia de CONTROL). */
    public function resumen(): array
    {
        $p = $this->estado['payload'] ?? [];
        $vigente = ($this->estado['valido'] ?? false) || (! empty($p['expira_en']) && strtotime($p['expira_en']) > time());

        return [
            'valido' => $vigente,
            'estado' => $p['estado'] ?? $this->estado['codigo'] ?? 'desconocido',
            'motivo' => $this->estado['motivo'] ?? null,
            'clave' => $this->clave,
            'producto' => $this->producto,
            'plan' => $p['plan'] ?? $this->info['plan'] ?? null,
            'etiqueta' => $p['etiqueta'] ?? $this->info['etiqueta'] ?? null,
            'huella' => $this->huella,
            'vence_en' => $p['vence_en'] ?? $this->info['vence_en'] ?? null,
            'soporte_hasta' => $p['soporte_hasta'] ?? $this->info['soporte_hasta'] ?? null,
            'sin_conexion_hasta' => $p['expira_en'] ?? null,
            'emergencia' => ! empty($p['emergencia']),
            'version' => $this->version,
            'version_actual' => $this->info['version_actual'] ?? null,
            'desactualizada' => ! empty($this->info['desactualizada']),
            'ultimo_error' => $this->ultimoError,
        ];
    }

    private function procesar(array $r): array
    {
        if (! empty($r['licencia']) && is_array($r['licencia'])) {
            $this->info = $r['licencia'];
        }

        if (($r['ok'] ?? false) && ! empty($r['token'])) {
            $p = $this->verificar($r['token']);
            if ($p) {
                $this->ultimoError = null;
                $this->escribirArchivo(['clave' => $this->clave, 'token' => $r['token'], 'info' => $this->info, 'ultimo_error' => null]);

                return $this->estado = ['valido' => true, 'payload' => $p, 'motivo' => null, 'codigo' => $p['estado'] ?? null];
            }

            return $this->estado = ['valido' => false, 'payload' => null, 'motivo' => 'CONTROL devolvió un token que no verifica con la clave pública configurada', 'codigo' => 'token_invalido'];
        }

        $status = (int) ($r['status'] ?? 0);
        $codigo = $r['codigo'] ?? null;
        $motivo = $r['error'] ?? $r['message'] ?? $codigo ?? 'Licencia inválida';

        if (in_array($status, [403, 404], true) || in_array($codigo, self::BLOQUEADAS, true)) {
            // Un rechazo explícito manda sobre cualquier token guardado.
            $this->ultimoError = ['codigo' => $codigo, 'motivo' => $motivo, 'en' => date('c')];
            $this->escribirArchivo(['clave' => $this->clave, 'token' => null, 'info' => $this->info, 'ultimo_error' => $this->ultimoError]);

            return $this->estado = ['valido' => false, 'payload' => null, 'motivo' => $motivo, 'codigo' => $codigo];
        }

        // Otro error (500, 422…): se conserva lo que ya había.
        if (! $this->estado['valido']) {
            $this->estado = ['valido' => false, 'payload' => null, 'motivo' => "CONTROL respondió {$status}: {$motivo}", 'codigo' => $codigo ?? 'error_remoto'];
        }

        return $this->estado;
    }

    /** @throws RuntimeException cuando no hay conexión */
    private function llamar(string $ruta, array $cuerpo): array
    {
        try {
            $respuesta = Http::timeout(10)
                ->acceptJson()
                ->post("{$this->url}/api/v1/licencias/{$ruta}", array_filter($cuerpo, fn ($v) => $v !== null));
        } catch (ConnectionException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $datos = $respuesta->json();

        return ['ok' => $respuesta->successful(), 'status' => $respuesta->status()] + (is_array($datos) ? $datos : []);
    }

    private function leerArchivo(): array
    {
        if (! is_file($this->archivo)) {
            return [];
        }

        $datos = json_decode((string) @file_get_contents($this->archivo), true);

        return is_array($datos) ? $datos : [];
    }

    private function escribirArchivo(array $datos): void
    {
        @mkdir(dirname($this->archivo), 0775, true);
        file_put_contents($this->archivo, json_encode(array_merge($datos, ['guardado_en' => date('c')]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private static function b64urlDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
