<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    public function run(): void
    {
        Sucursal::updateOrCreate(['codigo' => 'CENTRAL'], [
            'nombre' => 'Sede Central',
            'direccion' => 'Av. Ballivián #1234, Zona Central · La Paz',
            'telefono' => '+591 2 2456789',
            'email' => 'central@saludyestetica.bo',
            'color' => '#0d9488',
            'principal' => true,
            'activo' => true,
        ]);

        Sucursal::updateOrCreate(['codigo' => 'SUR'], [
            'nombre' => 'Sede Sur',
            'direccion' => 'Calle 21 de Calacoto #8050, Zona Sur · La Paz',
            'telefono' => '+591 2 2791234',
            'email' => 'sur@saludyestetica.bo',
            'color' => '#6366f1',
            'principal' => false,
            'activo' => true,
        ]);
    }
}
