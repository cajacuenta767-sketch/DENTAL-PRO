<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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

        $this->command->newLine();
        $this->command->info('OdontoSuite listo. Cuentas de acceso:');
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
