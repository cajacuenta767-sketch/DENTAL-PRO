<?php

namespace Database\Seeders;

use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Datos mínimos para una instalación real (instalador de escritorio, servidor
 * de una clínica): ajustes neutros, una sola sede, roles y permisos, el
 * catálogo base de especialidades y tratamientos y un único SUPER ADMINISTRADOR.
 *
 * No crea pacientes, citas, doctores ni ningún dato de demostración. Es
 * idempotente: puede ejecutarse en cada arranque sin pisar lo que la clínica ya
 * configuró (los ajustes, la sede y la contraseña del administrador solo se
 * crean si no existen; roles, permisos y catálogo se sincronizan).
 *
 *   php artisan db:seed --class=ProduccionSeeder --force
 */
class ProduccionSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@admin.com';

    public const ADMIN_PASSWORD = 'admin123';

    public function run(): void
    {
        Auditoria::$activa = false;

        $this->ajustes();
        $this->sedePrincipal();
        $this->call([RolPermisoSeeder::class, CatalogoSeeder::class]);
        $this->administrador();

        Auditoria::$activa = true;

        $this->command?->newLine();
        $this->command?->info('Instalación lista. Entra con '.self::ADMIN_EMAIL.' / '.self::ADMIN_PASSWORD.' (se pedirá cambiar la contraseña).');
    }

    /** Ajustes generales neutros; la clínica los completa desde Configuración. */
    private function ajustes(): void
    {
        Ajuste::firstOrCreate(['id' => 1], [
            'nombre' => 'Mi Clínica Dental',
            'descripcion' => 'Atención odontológica integral',
            'divisa' => env('CLINICA_MONEDA', 'BOB'),
            'simbolo_divisa' => 'Bs',
            'minutos_intervalo_cita' => 30,
            'horas_recordatorio' => 24,
            'terminos_recibo' => 'Este comprobante no constituye factura fiscal. '
                .'Conserve el documento para cualquier reclamo dentro de los 30 días posteriores a la atención.',
        ]);
    }

    /** Una única sede marcada como principal (el sistema exige al menos una). */
    private function sedePrincipal(): void
    {
        if (Sucursal::query()->exists()) {
            return;
        }

        Sucursal::create([
            'codigo' => 'PRINCIPAL',
            'nombre' => 'Sede Principal',
            'color' => '#0d9488',
            'principal' => true,
            'activo' => true,
        ]);
    }

    /** SUPER ADMINISTRADOR inicial; si ya existe no se toca su contraseña. */
    private function administrador(): void
    {
        $usuario = Usuario::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'nombre' => 'Administrador',
                'password' => self::ADMIN_PASSWORD,
                'estado' => 'activo',
                'email_verified_at' => now(),
                // Clave inicial conocida: el sistema exige cambiarla al primer ingreso.
                'debe_cambiar_password' => true,
            ]
        );

        $usuario->syncRoles(['SUPER ADMINISTRADOR']);
    }
}
