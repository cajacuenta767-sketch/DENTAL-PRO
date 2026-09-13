<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolPermisoSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permission:cache-reset');

        // Un permiso por acción declarada en config/odontosuite.php.
        $permisos = collect(config('odontosuite.modulos'))
            ->flatMap(fn ($modulo, $clave) => collect($modulo['acciones'])->map(fn ($a) => "{$clave}.{$a}"))
            ->values();

        foreach ($permisos as $nombre) {
            Permission::findOrCreate($nombre, 'web');
        }

        foreach (config('odontosuite.roles') as $nombre => $definicion) {
            $rol = Role::findOrCreate($nombre, 'web');

            $rol->syncPermissions(
                $definicion['permisos'] === '*'
                    ? Permission::all()
                    : $this->expandir($definicion['permisos'], $permisos)
            );
        }

        $this->command->info("Permisos: {$permisos->count()} · Roles: ".count(config('odontosuite.roles')));
    }

    /** Convierte comodines como "pacientes.*" en la lista concreta de permisos. */
    private function expandir(array $patrones, $todos): array
    {
        return collect($patrones)
            ->flatMap(function (string $patron) use ($todos) {
                if (! str_ends_with($patron, '.*')) {
                    return [$patron];
                }

                $modulo = str_replace('.*', '', $patron);

                return $todos->filter(fn ($p) => str_starts_with($p, "{$modulo}."))->values();
            })
            ->unique()
            ->filter(fn ($p) => $todos->contains($p))
            ->values()
            ->all();
    }
}
