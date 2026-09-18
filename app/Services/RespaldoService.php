<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Copias de seguridad completas: volcado de PostgreSQL (pg_dump -Fc) más los
 * archivos privados (estudios, fotos, firmas) en un único ZIP guardado en el
 * disco "respaldos". La restauración usa pg_restore --clean --if-exists.
 */
class RespaldoService
{
    public const DISCO = 'respaldos';

    public const PATRON_NOMBRE = '/^respaldo-\d{8}-\d{6}\.zip$/';

    /** Genera un respaldo nuevo y devuelve el nombre del archivo creado. */
    public function crear(): string
    {
        $this->comprobarHerramienta('pg_dump');

        $nombre = 'respaldo-'.now()->format('Ymd-His').'.zip';
        $temporal = $this->directorioTemporal();
        $volcado = $temporal.DIRECTORY_SEPARATOR.'base.dump';
        $zip = $temporal.DIRECTORY_SEPARATOR.$nombre;

        try {
            $this->volcarBase($volcado);
            $this->empaquetar($zip, $volcado);

            $flujo = fopen($zip, 'r');
            $this->disco()->put($nombre, $flujo);
            if (is_resource($flujo)) {
                fclose($flujo);
            }
        } finally {
            File::deleteDirectory($temporal);
        }

        $this->aplicarRetencion();

        return $nombre;
    }

    /**
     * Respaldos disponibles, del más reciente al más antiguo.
     *
     * @return Collection<int, array{nombre: string, tamano: int, fecha: Carbon}>
     */
    public function listar(): Collection
    {
        $disco = $this->disco();

        return collect($disco->files())
            ->map(fn ($ruta) => basename($ruta))
            ->filter(fn ($nombre) => preg_match(self::PATRON_NOMBRE, $nombre))
            ->sortDesc()
            ->values()
            ->map(fn ($nombre) => [
                'nombre' => $nombre,
                'tamano' => (int) $disco->size($nombre),
                'fecha' => $this->fechaDe($nombre) ?? Carbon::createFromTimestamp($disco->lastModified($nombre)),
            ]);
    }

    public function existe(string $archivo): bool
    {
        return $this->nombreValido($archivo) && $this->disco()->exists($archivo);
    }

    public function eliminar(string $archivo): bool
    {
        if (! $this->existe($archivo)) {
            return false;
        }

        return $this->disco()->delete($archivo);
    }

    /**
     * Restaura la base de datos y los archivos privados desde un respaldo.
     * Reemplaza TODO el contenido actual: quien la llama debe pedir confirmación.
     */
    public function restaurar(string $archivo): void
    {
        if (! $this->existe($archivo)) {
            throw new RuntimeException("El respaldo {$archivo} no existe.");
        }

        $this->comprobarHerramienta('pg_restore');

        $temporal = $this->directorioTemporal();

        try {
            $zipLocal = $temporal.DIRECTORY_SEPARATOR.$archivo;
            file_put_contents($zipLocal, $this->disco()->readStream($archivo));

            $zip = new ZipArchive;
            if ($zip->open($zipLocal) !== true) {
                throw new RuntimeException("No se pudo abrir el archivo {$archivo}: ¿está dañado?");
            }

            $destino = $temporal.DIRECTORY_SEPARATOR.'contenido';
            File::ensureDirectoryExists($destino);
            $zip->extractTo($destino);
            $zip->close();

            $volcado = $destino.DIRECTORY_SEPARATOR.'base.dump';
            if (! is_file($volcado)) {
                throw new RuntimeException('El respaldo no contiene el volcado base.dump.');
            }

            $this->restaurarBase($volcado);

            $privado = $destino.DIRECTORY_SEPARATOR.'privado';
            if (is_dir($privado)) {
                File::copyDirectory($privado, storage_path('app/private'));
            }
        } finally {
            File::deleteDirectory($temporal);
        }
    }

    /** Conserva sólo los N respaldos más recientes (RESPALDOS_CONSERVAR). */
    public function aplicarRetencion(): int
    {
        $conservar = max(1, (int) config('filesystems.disks.'.self::DISCO.'.conservar', 14));
        $sobrantes = $this->listar()->slice($conservar);

        foreach ($sobrantes as $respaldo) {
            $this->disco()->delete($respaldo['nombre']);
        }

        return $sobrantes->count();
    }

    /** Comprueba que pg_dump y pg_restore pueden ejecutarse; lanza RuntimeException si no. */
    public function comprobarHerramientas(): void
    {
        $this->comprobarHerramienta('pg_dump');
        $this->comprobarHerramienta('pg_restore');
    }

    public function herramientasDisponibles(): bool
    {
        try {
            $this->comprobarHerramientas();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function nombreValido(string $archivo): bool
    {
        return (bool) preg_match(self::PATRON_NOMBRE, $archivo);
    }

    public function disco(): Filesystem
    {
        return Storage::disk(self::DISCO);
    }

    // ------------------------------------------------------------------

    private function volcarBase(string $destino): void
    {
        $conexion = $this->conexion();

        $resultado = Process::env($this->entorno($conexion))
            ->timeout(900)
            ->run([
                $this->binario('pg_dump'),
                '--format=custom',
                '--no-owner',
                '--no-privileges',
                '--host='.$conexion['host'],
                '--port='.$conexion['port'],
                '--username='.$conexion['username'],
                '--dbname='.$conexion['database'],
                '--file='.$destino,
            ]);

        if ($resultado->failed() || ! is_file($destino) || filesize($destino) === 0) {
            throw new RuntimeException(
                'pg_dump no pudo generar el volcado de la base de datos: '.$this->resumenError($resultado->errorOutput())
            );
        }
    }

    private function restaurarBase(string $volcado): void
    {
        $conexion = $this->conexion();

        $resultado = Process::env($this->entorno($conexion))
            ->timeout(1800)
            ->run([
                $this->binario('pg_restore'),
                '--clean',
                '--if-exists',
                '--no-owner',
                '--no-privileges',
                '--host='.$conexion['host'],
                '--port='.$conexion['port'],
                '--username='.$conexion['username'],
                '--dbname='.$conexion['database'],
                $volcado,
            ]);

        if ($resultado->failed()) {
            throw new RuntimeException(
                'pg_restore terminó con errores: '.$this->resumenError($resultado->errorOutput())
            );
        }
    }

    private function empaquetar(string $rutaZip, string $volcado): void
    {
        $zip = new ZipArchive;

        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP del respaldo.');
        }

        $zip->addFile($volcado, 'base.dump');

        $privado = storage_path('app/private');
        $archivosPrivados = 0;

        if (is_dir($privado)) {
            $zip->addEmptyDir('privado');

            foreach (File::allFiles($privado, true) as $archivo) {
                $relativa = str_replace('\\', '/', $archivo->getRelativePathname());
                $zip->addFile($archivo->getPathname(), 'privado/'.$relativa);
                $archivosPrivados++;
            }
        }

        $zip->addFromString('manifiesto.json', json_encode($this->manifiesto($archivosPrivados), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (! $zip->close()) {
            throw new RuntimeException('No se pudo cerrar el archivo ZIP del respaldo.');
        }
    }

    private function manifiesto(int $archivosPrivados): array
    {
        $conexion = $this->conexion();

        return [
            'aplicacion' => config('app.name'),
            'version' => config('odontosuite.version'),
            'laravel' => app()->version(),
            'fecha' => now()->toIso8601String(),
            'base_de_datos' => $conexion['database'],
            'formato' => 'pg_dump custom (-Fc)',
            'tablas' => Schema::getTableListing(),
            'archivos_privados' => $archivosPrivados,
        ];
    }

    private function comprobarHerramienta(string $herramienta): void
    {
        try {
            $resultado = Process::timeout(15)->run([$this->binario($herramienta), '--version']);
        } catch (\Throwable $e) {
            $resultado = null;
        }

        if ($resultado === null || $resultado->failed()) {
            $sugerencia = config('filesystems.disks.'.self::DISCO.'.ruta_pg')
                ? 'Revisa la variable RESPALDOS_RUTA_PG.'
                : 'Instala el cliente de PostgreSQL (postgresql-client / postgresql16-client) o indica su carpeta en RESPALDOS_RUTA_PG.';

            throw new RuntimeException("{$herramienta} no está disponible en el servidor. {$sugerencia}");
        }
    }

    private function binario(string $herramienta): string
    {
        $ruta = config('filesystems.disks.'.self::DISCO.'.ruta_pg');

        return $ruta ? rtrim($ruta, '/\\').DIRECTORY_SEPARATOR.$herramienta : $herramienta;
    }

    private function conexion(): array
    {
        $conexion = config('database.connections.pgsql', []);

        return [
            'host' => $conexion['host'] ?? '127.0.0.1',
            'port' => (string) ($conexion['port'] ?? 5432),
            'database' => $conexion['database'] ?? '',
            'username' => $conexion['username'] ?? '',
            'password' => (string) ($conexion['password'] ?? ''),
        ];
    }

    private function entorno(array $conexion): array
    {
        return ['PGPASSWORD' => $conexion['password']];
    }

    private function directorioTemporal(): string
    {
        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'odontosuite-respaldo-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($ruta);

        return $ruta;
    }

    private function fechaDe(string $nombre): ?Carbon
    {
        if (! preg_match('/^respaldo-(\d{8})-(\d{6})\.zip$/', $nombre, $m)) {
            return null;
        }

        return Carbon::createFromFormat('YmdHis', $m[1].$m[2]);
    }

    private function resumenError(string $salida): string
    {
        $salida = trim($salida);

        return $salida === '' ? 'sin detalle.' : mb_substr($salida, 0, 600);
    }
}
