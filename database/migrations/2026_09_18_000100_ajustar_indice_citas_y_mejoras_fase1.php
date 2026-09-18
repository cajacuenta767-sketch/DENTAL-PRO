<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS citas_doctor_slot_unico');
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS citas_doctor_slot_activo ON citas (doctor_id, fecha, hora) WHERE estado != 'CANCELADA'");
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS citas_doctor_slot_unico');
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS citas_doctor_slot_activo ON citas (doctor_id, fecha, hora) WHERE estado != 'CANCELADA'");
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            try {
                DB::statement('ALTER TABLE citas DROP INDEX citas_doctor_slot_unico');
            } catch (\Throwable) {
                // Si el indice ya no existe
            }

            try {
                DB::statement("ALTER TABLE citas ADD COLUMN slot_activo VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN estado != 'CANCELADA' THEN CONCAT(doctor_id, '_', fecha, '_', hora) ELSE NULL END) VIRTUAL");
                DB::statement('CREATE UNIQUE INDEX citas_doctor_slot_activo ON citas (slot_activo)');
            } catch (\Throwable) {
                // Si ya fue creada la columna virtual
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS citas_doctor_slot_activo');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS citas_doctor_slot_unico ON citas (doctor_id, fecha, hora)');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS citas_doctor_slot_activo');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS citas_doctor_slot_unico ON citas (doctor_id, fecha, hora)');
        } elseif (in_array($driver, ['mysql', 'mariadb'])) {
            try {
                DB::statement('ALTER TABLE citas DROP INDEX citas_doctor_slot_activo');
                DB::statement('ALTER TABLE citas DROP COLUMN slot_activo');
                DB::statement('CREATE UNIQUE INDEX citas_doctor_slot_unico ON citas (doctor_id, fecha, hora)');
            } catch (\Throwable) {
                // Reversion
            }
        }
    }
};
