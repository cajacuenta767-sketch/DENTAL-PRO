<?php

use App\Models\EstudioImagen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los estudios ya no son solo radiografías y fotos: también informes,
 * resultados de laboratorio y documentos externos (PDF, Word, DICOM…).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->redefinirTipos(array_keys(EstudioImagen::TIPOS));
    }

    public function down(): void
    {
        DB::table('estudios_imagen')->whereIn('tipo', ['INFORME', 'DOCUMENTO'])->update(['tipo' => 'OTRO']);

        $this->redefinirTipos(array_values(array_diff(array_keys(EstudioImagen::TIPOS), ['INFORME', 'DOCUMENTO'])));
    }

    private function redefinirTipos(array $tipos): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $lista = implode(', ', array_map(fn ($t) => "'".$t."'", $tipos));

        DB::statement('ALTER TABLE estudios_imagen DROP CONSTRAINT IF EXISTS estudios_imagen_tipo_check');
        DB::statement("ALTER TABLE estudios_imagen ADD CONSTRAINT estudios_imagen_tipo_check CHECK (tipo::text = ANY (ARRAY[$lista]::text[]))");
    }
};
