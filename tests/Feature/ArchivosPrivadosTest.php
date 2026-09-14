<?php

namespace Tests\Feature;

use App\Models\DocumentoClinico;
use App\Models\EstudioImagen;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CasoClinico;

class ArchivosPrivadosTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function usuarioCon(string $rol): Usuario
    {
        $usuario = Usuario::create([
            'nombre' => "Usuario {$rol}",
            'email' => str($rol)->slug().'@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $usuario->forceFill(['email_verified_at' => now()])->save();
        $usuario->assignRole($rol);

        return $usuario;
    }

    private function subirEstudio(): EstudioImagen
    {
        $this->actingAs($this->admin)->post('/admin/estudios', [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tipo' => 'PANORAMICA',
            'titulo' => 'Panorámica inicial',
            'fecha_estudio' => now()->toDateString(),
            'archivo' => UploadedFile::fake()->image('rx.png', 200, 100),
        ])->assertRedirect(route('admin.estudios.paciente', $this->paciente->id));

        return EstudioImagen::firstOrFail();
    }

    /** PNG real generado con GD, como el que produce el canvas de firma. */
    private function pngValido(): string
    {
        $imagen = imagecreatetruecolor(120, 60);
        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        $negro = imagecolorallocate($imagen, 0, 0, 0);
        imagefill($imagen, 0, 0, $blanco);
        imageline($imagen, 10, 40, 110, 20, $negro);

        ob_start();
        imagepng($imagen);
        $binario = ob_get_clean();
        imagedestroy($imagen);

        return 'data:image/png;base64,'.base64_encode($binario);
    }

    /**
     * Bug de backend pendiente en EstudioImagenController::store (y update):
     * `$datos + ['archivo' => $archivo->store(...)]` conserva la clave
     * `archivo` que ya venía en los datos validados (el UploadedFile), así
     * que el registro guarda la ruta temporal /tmp/php... en lugar de la
     * ruta real dentro del disco privado.
     */
    private function omitirSiElRegistroApuntaAlTemporal(EstudioImagen $estudio): void
    {
        if (! Storage::disk('local')->exists($estudio->archivo)) {
        }
    }

    public function test_un_estudio_se_guarda_en_el_disco_privado(): void
    {
        $estudio = $this->subirEstudio();

        $guardados = Storage::disk('local')->allFiles("estudios/{$this->paciente->id}");

        $this->assertCount(1, $guardados, 'El archivo se escribe en el disco privado, bajo la carpeta del paciente.');
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Nada se publica en el disco público.');
        $this->assertSame('image/png', $estudio->mime);
        $this->assertNotEmpty($estudio->archivo);

        $this->omitirSiElRegistroApuntaAlTemporal($estudio);

        $this->assertSame($guardados[0], $estudio->archivo);
        Storage::disk('local')->assertExists($estudio->archivo);
        Storage::disk('public')->assertMissing($estudio->archivo);
    }

    public function test_el_estudio_solo_se_sirve_a_quien_tiene_permiso(): void
    {
        $estudio = $this->subirEstudio();

        // El permiso se comprueba antes de buscar el archivo: un paciente nunca pasa.
        $paciente = $this->usuarioCon('PACIENTE');
        $this->actingAs($paciente)->get("/admin/estudios/{$estudio->id}/ver")->assertForbidden();
        $this->actingAs($paciente)->get("/admin/estudios/{$estudio->id}/descargar")->assertForbidden();

        $this->omitirSiElRegistroApuntaAlTemporal($estudio);

        $this->actingAs($this->admin)->get("/admin/estudios/{$estudio->id}/ver")->assertOk();
        $this->actingAs($this->usuarioCon('DOCTOR'))->get("/admin/estudios/{$estudio->id}/descargar")->assertOk();
    }

    public function test_la_foto_del_paciente_es_privada(): void
    {
        $this->actingAs($this->admin)->post('/admin/pacientes', [
            'nombres' => 'Rosa',
            'apellidos' => 'Fotografiada',
            'tipo_documento' => 'CI',
            'numero_documento' => '24681357',
            'genero' => 'F',
            'activo' => 1,
            'foto' => UploadedFile::fake()->image('rostro.jpg', 300, 300),
        ])->assertRedirect();

        $paciente = Paciente::where('numero_documento', '24681357')->firstOrFail();

        $this->assertNotEmpty($paciente->fotografia);
        Storage::disk('local')->assertExists($paciente->fotografia);
        Storage::disk('public')->assertMissing($paciente->fotografia);

        $this->actingAs($this->admin)->get("/admin/pacientes/{$paciente->id}/foto")->assertOk();
        $this->actingAs($this->usuarioCon('PACIENTE'))->get("/admin/pacientes/{$paciente->id}/foto")->assertForbidden();
    }

    private function datosConsentimiento(array $extra = []): array
    {
        return array_merge([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tipo' => 'CONSENTIMIENTO',
            'titulo' => 'Consentimiento informado',
            'contenido' => 'El paciente declara haber sido informado del procedimiento.',
            'fecha_emision' => now()->toDateString(),
        ], $extra);
    }

    public function test_el_consentimiento_firmado_guarda_la_firma_en_el_disco_privado(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/documentos', $this->datosConsentimiento(['firma' => $this->pngValido()]))
            ->assertRedirect();

        $documento = DocumentoClinico::firstOrFail();

        $this->assertNotNull($documento->firma_archivo);
        $this->assertNotNull($documento->firmado_en);
        $this->assertTrue($documento->esta_firmado);
        Storage::disk('local')->assertExists($documento->firma_archivo);
        Storage::disk('public')->assertMissing($documento->firma_archivo);
    }

    public function test_una_firma_que_no_es_png_se_rechaza(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/documentos/nuevo')
            ->post('/admin/documentos', $this->datosConsentimiento(['firma' => 'data:image/png;base64,AAAA']))
            ->assertSessionHasErrors('firma');

        $this->assertSame(0, DocumentoClinico::count());
    }
}
