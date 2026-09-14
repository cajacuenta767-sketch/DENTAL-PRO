<?php

namespace Database\Seeders;

use App\Models\Auditoria;
use App\Models\Sucursal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Los datos de demostración no dejan rastro en la auditoría.
        Auditoria::$activa = false;

        $this->call([
            AjusteSeeder::class,
            SucursalSeeder::class,
            RolPermisoSeeder::class,
            UsuarioSeeder::class,
            CatalogoSeeder::class,
            EquipoSeeder::class,
            DemoClinicaSeeder::class,
            AseguradoraSeeder::class,
            InventarioSeeder::class,
            ClinicaAvanzadaSeeder::class,
        ]);

        $this->etiquetarConSedePrincipal();

        Auditoria::$activa = true;

        $this->command->newLine();
        $this->command->info('OdontoSuite listo. Cuentas de acceso (se pide cambiar la contraseña al primer ingreso):');
        $this->command->table(
            ['Rol', 'Correo', 'Contraseña'],
            [
                ['SUPER ADMINISTRADOR', 'admin@admin.com', 'admin123'],
                ['ADMINISTRADOR', 'admin@clinica.com', 'admin123'],
                ['RECEPCION', 'secretaria@clinica.com', 'recepcion123'],
                ['DOCTOR', 'sofia.arancibia@clinica.com', 'doctor123'],
            ]
        );
    }

    /**
     * Los datos de demostración nacen sin sede; se etiquetan con la principal
     * para que el selector del navbar y los filtros por sede sean coherentes.
     */
    private function etiquetarConSedePrincipal(): void
    {
        $principal = Sucursal::principal();

        if (! $principal) {
            return;
        }

        foreach (['horarios', 'citas', 'pagos', 'insumos', 'lista_espera'] as $tabla) {
            DB::table($tabla)->whereNull('sucursal_id')->update(['sucursal_id' => $principal->id]);
        }
    }
}
