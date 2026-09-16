<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Criptografía de las licencias.
 *
 * Dos piezas:
 *  - Código de activación: firmado con Ed25519 por el proveedor. Lleva el tipo,
 *    el día de vencimiento y el ancla de la cadena de renovación del cliente.
 *  - PIN de renovación: un eslabón de una cadena de hashes cuyo extremo (el
 *    ancla) quedó registrado al activar. Revelar el eslabón "k" autoriza hasta
 *    el día "k" y no puede fabricarse sin la semilla, que solo tiene el
 *    proveedor. El PIN va enmascarado con el código de instalación, así que
 *    no sirve en otra clínica.
 */
class LicenciaService
{
    public const VERSION = 1;

    public const TIPO_PRUEBA = 0;

    public const TIPO_COMPLETA = 1;

    /** Alfabeto Crockford: sin I, L, O ni U para evitar confusiones al dictar. */
    public const ALFABETO = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const LARGO_VALOR = 10;

    private const LARGO_PAYLOAD = 18;

    // ---------------------------------------------------------------- claves

    /** @return array{publica: string, privada: string} en base64 */
    public function generarClaves(): array
    {
        $par = sodium_crypto_sign_keypair();

        return [
            'publica' => base64_encode(sodium_crypto_sign_publickey($par)),
            'privada' => base64_encode(sodium_crypto_sign_secretkey($par)),
        ];
    }

    // -------------------------------------------------------------- calendario

    public function epoca(): CarbonImmutable
    {
        return CarbonImmutable::parse(config('licencia.epoca'))->startOfDay();
    }

    public function diasMaximos(): int
    {
        return (int) config('licencia.dias_maximos');
    }

    /** Índice de día (1..máximo) para una fecha; 0 si es anterior a la época. */
    public function diaDeFecha(Carbon|CarbonImmutable|string $fecha): int
    {
        $fecha = CarbonImmutable::parse($fecha)->startOfDay();
        $dias = (int) $this->epoca()->diffInDays($fecha, false);

        return max(0, min($this->diasMaximos(), $dias));
    }

    /** Fecha de vencimiento (último día válido) para un índice. */
    public function fechaDeDia(int $dia): CarbonImmutable
    {
        return $this->epoca()->addDays($dia);
    }

    public function esVitalicio(int $dia): bool
    {
        return $dia >= $this->diasMaximos();
    }

    // ----------------------------------------------------------- codificación

    public static function codificar(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }

        $salida = '';
        foreach (str_split($bits, 5) as $trozo) {
            $salida .= self::ALFABETO[bindec(str_pad($trozo, 5, '0'))];
        }

        return $salida;
    }

    public static function decodificar(string $texto): ?string
    {
        $texto = strtoupper((string) preg_replace('/[\s\-\.]/', '', $texto));
        $texto = strtr($texto, ['I' => '1', 'L' => '1', 'O' => '0']);

        if ($texto === '') {
            return null;
        }

        $bits = '';
        foreach (str_split($texto) as $ch) {
            $i = strpos(self::ALFABETO, $ch);
            if ($i === false) {
                return null;
            }
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $b) {
            $bytes .= chr(bindec($b));
        }

        return $bytes;
    }

    public static function agrupar(string $texto, int $tamano): string
    {
        return implode('-', str_split($texto, $tamano));
    }

    /** Código de instalación nuevo: 8 caracteres legibles, "XXXX-XXXX". */
    public function nuevoCodigoInstalacion(): string
    {
        return self::agrupar(substr(self::codificar(random_bytes(5)), 0, 8), 4);
    }

    /** Lleva cualquier forma de escribir el código de instalación a "XXXX-XXXX". */
    public function normalizarInstalacion(string $codigo): ?string
    {
        $limpio = strtoupper((string) preg_replace('/[\s\-\.]/', '', $codigo));
        $limpio = strtr($limpio, ['I' => '1', 'L' => '1', 'O' => '0']);

        if (strlen($limpio) !== 8 || preg_match('/[^'.self::ALFABETO.']/', $limpio)) {
            return null;
        }

        return self::agrupar($limpio, 4);
    }

    // ------------------------------------------------------ cadena de hashes

    private function paso(string $valor): string
    {
        return substr(hash('sha256', 'odontosuite-cadena|'.$valor, true), 0, self::LARGO_VALOR);
    }

    public function nuevaSemilla(): string
    {
        return random_bytes(self::LARGO_VALOR);
    }

    /** Eslabón que autoriza hasta el día $dia (0 = ancla). */
    public function eslabon(string $semilla, int $dia): string
    {
        $valor = $semilla;
        for ($i = 0, $n = $this->diasMaximos() - $dia; $i < $n; $i++) {
            $valor = $this->paso($valor);
        }

        return $valor;
    }

    public function ancla(string $semilla): string
    {
        return $this->eslabon($semilla, 0);
    }

    private function mascara(string $codigoInstalacion, int $dia): string
    {
        return substr(hash('sha256', 'odontosuite-pin|'.$codigoInstalacion.'|'.$dia, true), 0, self::LARGO_VALOR);
    }

    /** PIN de renovación para una instalación concreta: "XXXX-XXXX-XXXX-XXXX-XXXX". */
    public function pinRenovacion(string $semilla, int $dia, string $codigoInstalacion): string
    {
        $valor = $this->eslabon($semilla, $dia) ^ $this->mascara($codigoInstalacion, $dia);

        return self::agrupar(self::codificar(pack('n', $dia).$valor), 4);
    }

    /** Devuelve el día autorizado por el PIN, o null si no corresponde a esta instalación. */
    public function verificarPin(string $pin, string $ancla, string $codigoInstalacion): ?int
    {
        $bytes = self::decodificar($pin);

        if ($bytes === null || strlen($bytes) !== 2 + self::LARGO_VALOR) {
            return null;
        }

        $dia = unpack('n', substr($bytes, 0, 2))[1];

        if ($dia < 1 || $dia > $this->diasMaximos()) {
            return null;
        }

        $valor = substr($bytes, 2) ^ $this->mascara($codigoInstalacion, $dia);
        for ($i = 0; $i < $dia; $i++) {
            $valor = $this->paso($valor);
        }

        return hash_equals($ancla, $valor) ? $dia : null;
    }

    // ---------------------------------------------------- código de activación

    /**
     * Código firmado que activa una instalación por primera vez (o la re-ancla).
     *
     * @param  string  $clavePrivada  base64
     */
    public function codigoActivacion(string $ancla, int $tipo, int $diaFin, string $clavePrivada): string
    {
        $payload = pack('C', self::VERSION)
            .pack('C', $tipo)
            .pack('n', $diaFin)
            .pack('n', $this->diaDeFecha(now()))
            .$ancla
            .random_bytes(2);

        $firma = sodium_crypto_sign_detached($payload, base64_decode($clavePrivada));

        return self::agrupar(self::codificar($payload.$firma), 6);
    }

    /**
     * @return array{tipo: int, dia_fin: int, ancla: string, dia_emision: int}|null
     */
    public function verificarActivacion(string $codigo, ?string $clavePublica = null): ?array
    {
        $bytes = self::decodificar($codigo);

        if ($bytes === null || strlen($bytes) !== self::LARGO_PAYLOAD + SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        $payload = substr($bytes, 0, self::LARGO_PAYLOAD);
        $firma = substr($bytes, self::LARGO_PAYLOAD);
        $publica = base64_decode($clavePublica ?? (string) config('licencia.clave_publica'));

        if (strlen($publica) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || ! sodium_crypto_sign_verify_detached($firma, $payload, $publica)) {
            return null;
        }

        $datos = unpack('Cversion/Ctipo/ndia_fin/ndia_emision', substr($payload, 0, 6));

        if ($datos['version'] !== self::VERSION) {
            return null;
        }

        return [
            'tipo' => $datos['tipo'],
            'dia_fin' => $datos['dia_fin'],
            'dia_emision' => $datos['dia_emision'],
            'ancla' => substr($payload, 6, self::LARGO_VALOR),
        ];
    }

    /** ¿Parece un código de activación (largo) o un PIN de renovación (corto)? */
    public function esCodigoActivacion(string $texto): bool
    {
        $bytes = self::decodificar($texto);

        return $bytes !== null && strlen($bytes) === self::LARGO_PAYLOAD + SODIUM_CRYPTO_SIGN_BYTES;
    }
}
