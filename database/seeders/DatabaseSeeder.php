<?php

namespace Database\Seeders;

use App\Models\Auditoria;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Los datos de demostración no dejan rastro en la auditoría.
        Auditoria::$activa = false;

        $this->call([
            AjusteSeeder::class,
            RolPermisoSeeder::class,
            UsuarioSeeder::class,
            CatalogoSeeder::class,
            EquipoSeeder::class,
            DemoClinicaSeeder::class,
            AseguradoraSeeder::class,
            InventarioSeeder::class,
            ClinicaAvanzadaSeeder::class,
        ]);

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
}
