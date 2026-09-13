<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    /** [nombre, email, contraseña, rol] */
    private const CUENTAS = [
        ['Administrador General', 'admin@admin.com', 'admin123', 'SUPER ADMINISTRADOR'],
        ['María López', 'admin@clinica.com', 'admin123', 'ADMINISTRADOR'],
        ['Ana Torres', 'secretaria@clinica.com', 'recepcion123', 'RECEPCION'],
        ['Pedro Salazar', 'caja@clinica.com', 'recepcion123', 'RECEPCION'],
    ];

    public function run(): void
    {
        foreach (self::CUENTAS as [$nombre, $email, $password, $rol]) {
            $usuario = Usuario::updateOrCreate(
                ['email' => $email],
                [
                    'nombre' => $nombre,
                    'password' => $password,
                    'estado' => 'activo',
                    'email_verified_at' => now(),
                ]
            );

            $usuario->syncRoles([$rol]);
        }

        $this->command->info('Cuentas de acceso: '.count(self::CUENTAS));
    }
}
