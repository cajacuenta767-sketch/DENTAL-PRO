<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\EstudioImagen;
use App\Models\Paciente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CasoClinico;

/**
 * Imagenología: carga del archivo, sustitución, descarga con el nombre
 * original y borrado sin dejar huérfanos en el disco.
 */
class EstudioImagenTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs($this->admin);
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tipo' => array_key_first(EstudioImagen::TIPOS),
            'titulo' => 'Panorámica de control',
            'fecha_estudio' => now()->toDateString(),
            'archivo' => UploadedFile::fake()->image('panoramica.jpg'),
        ];
    }

    public function test_un_estudio_se_carga_con_su_archivo_y_metadatos(): void
    {
        $this->post('/admin/estudios', $this->datos())
            ->assertRedirect("/admin/pacientes/{$this->paciente->id}/panoramicas");

        $estudio = EstudioImagen::sole();

        $this->assertSame('panoramica.jpg', $estudio->nombre_original);
        $this->assertGreaterThan(0, $estudio->tamano);
        Storage::disk('public')->assertExists($estudio->archivo);
    }

    public function test_un_estudio_sin_archivo_no_se_crea(): void
    {
        $datos = $this->datos();
        unset($datos['archivo']);

        $this->post('/admin/estudios', $datos)->assertSessionHasErrors('archivo');

        $this->assertSame(0, EstudioImagen::count());
    }

    public function test_un_formato_no_permitido_se_rechaza(): void
    {
        $this->post('/admin/estudios', $this->datos([
            'archivo' => UploadedFile::fake()->create('estudio.exe', 40),
        ]))->assertSessionHasErrors('archivo');
    }

    public function test_reemplazar_el_archivo_borra_el_anterior(): void
    {
        $this->post('/admin/estudios', $this->datos());

        $estudio = EstudioImagen::sole();
        $primero = $estudio->archivo;

        $this->put("/admin/estudios/{$estudio->id}", $this->datos([
            'archivo' => UploadedFile::fake()->image('nueva.jpg'),
        ]));

        $estudio->refresh();

        $this->assertNotSame($primero, $estudio->archivo);
        Storage::disk('public')->assertMissing($primero);
        Storage::disk('public')->assertExists($estudio->archivo);
    }

    public function test_el_estudio_se_descarga_con_su_nombre_original(): void
    {
        $this->post('/admin/estudios', $this->datos());

        $estudio = EstudioImagen::sole();

        $this->get("/admin/estudios/{$estudio->id}/descargar")
            ->assertOk()
            ->assertDownload('panoramica.jpg');
    }

    public function test_eliminar_el_estudio_tambien_borra_el_archivo(): void
    {
        $this->post('/admin/estudios', $this->datos());

        $estudio = EstudioImagen::sole();
        $archivo = $estudio->archivo;

        $this->delete("/admin/estudios/{$estudio->id}")
            ->assertRedirect("/admin/pacientes/{$this->paciente->id}/panoramicas");

        $this->assertModelMissing($estudio);
        Storage::disk('public')->assertMissing($archivo);
    }

    public function test_no_se_enlaza_un_estudio_con_la_cita_de_otro_paciente(): void
    {
        $otro = Paciente::create([
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'tipo_documento' => 'CI',
            'numero_documento' => '1112223',
            'genero' => 'F',
            'activo' => true,
        ]);

        $ajena = Cita::create([
            'paciente_id' => $otro->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'token' => 'CIT-OTRA-002',
            'fecha' => $this->proximaFecha(),
            'hora' => '08:00',
            'duracion' => 30,
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ]);

        $this->post('/admin/estudios', $this->datos(['cita_id' => $ajena->id]))
            ->assertSessionHasErrors('cita_id');

        $this->assertSame(0, EstudioImagen::count());
    }
}
