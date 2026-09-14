<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\DocumentoClinico;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Support\Facades\Storage;
use Tests\CasoClinico;

class PortalFirmaTest extends CasoClinico
{
    private Usuario $cuentaPaciente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(DocumentoClinico::DISCO_FIRMAS);

        $this->cuentaPaciente = Usuario::create([
            'nombre' => 'Cuenta PACIENTE',
            'email' => 'paciente@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
        ]);
        $this->cuentaPaciente->assignRole('PACIENTE');
        $this->cuentaPaciente->forceFill(['email_verified_at' => now()])->save();

        $this->paciente->update(['usuario_id' => $this->cuentaPaciente->id]);
    }

    private function consentimiento(?Paciente $paciente = null, string $tipo = 'CONSENTIMIENTO'): DocumentoClinico
    {
        return DocumentoClinico::create([
            'folio' => DocumentoClinico::siguienteFolio($tipo),
            'paciente_id' => ($paciente ?? $this->paciente)->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'tipo' => $tipo,
            'titulo' => DocumentoClinico::TIPOS[$tipo],
            'contenido' => 'El paciente declara haber sido informado sobre el procedimiento de profilaxis dental.',
            'fecha_emision' => now()->toDateString(),
            'estado' => 'EMITIDO',
        ]);
    }

    /** PNG real generado con GD, como el que produce el canvas del navegador. */
    private function firmaPng(): string
    {
        $imagen = imagecreatetruecolor(300, 100);
        imagesavealpha($imagen, true);
        imagefill($imagen, 0, 0, imagecolorallocatealpha($imagen, 0, 0, 0, 127));
        imageline($imagen, 10, 80, 290, 20, imagecolorallocate($imagen, 26, 26, 26));

        ob_start();
        imagepng($imagen);
        $binario = ob_get_clean();
        imagedestroy($imagen);

        return 'data:image/png;base64,'.base64_encode($binario);
    }

    public function test_el_paciente_ve_el_consentimiento_pendiente_y_lo_firma(): void
    {
        $documento = $this->consentimiento();

        $this->actingAs($this->cuentaPaciente);

        $this->get(route('portal.documentos'))
            ->assertOk()
            ->assertSee(route('portal.documentos.firmar', $documento), false);

        $this->get(route('portal.documentos.firmar', $documento))
            ->assertOk()
            ->assertSee('firma-canvas', false)
            ->assertSee('profilaxis dental', false);

        $this->post(route('portal.documentos.firmar.guardar', $documento), [
            'firma' => $this->firmaPng(),
            'acepto' => '1',
        ])->assertRedirect(route('portal.documentos'))->assertSessionHas('exito');

        $documento->refresh();

        $this->assertTrue($documento->esta_firmado);
        $this->assertNotNull($documento->firmado_en);
        $this->assertStringStartsWith("firmas/{$this->paciente->id}/{$documento->folio}-", $documento->firma_archivo);
        Storage::disk(DocumentoClinico::DISCO_FIRMAS)->assertExists($documento->firma_archivo);
        $this->assertStringStartsWith("\x89PNG", Storage::disk(DocumentoClinico::DISCO_FIRMAS)->get($documento->firma_archivo));

        $this->assertTrue(
            Auditoria::where('modelo', 'DocumentoClinico')->where('modelo_id', $documento->id)
                ->where('descripcion', 'like', 'El paciente firmó%')->exists()
        );

        // Ya firmado: el botón desaparece y el formulario avisa sin cambiar nada.
        $this->get(route('portal.documentos'))->assertOk()->assertDontSee(route('portal.documentos.firmar', $documento), false);
    }

    public function test_una_firma_que_no_es_png_se_rechaza(): void
    {
        $documento = $this->consentimiento();

        $this->actingAs($this->cuentaPaciente)
            ->from(route('portal.documentos.firmar', $documento))
            ->post(route('portal.documentos.firmar.guardar', $documento), [
                'firma' => 'data:image/png;base64,'.base64_encode(str_repeat('no soy un png ', 20)),
                'acepto' => '1',
            ])
            ->assertRedirect(route('portal.documentos.firmar', $documento))
            ->assertSessionHasErrors('firma');

        $this->assertFalse($documento->fresh()->esta_firmado);
    }

    public function test_un_consentimiento_ya_firmado_no_se_vuelve_a_firmar(): void
    {
        $documento = $this->consentimiento();
        Storage::disk(DocumentoClinico::DISCO_FIRMAS)->put('firmas/previa.png', "\x89PNGprevia");
        $documento->update(['firma_archivo' => 'firmas/previa.png', 'firmado_en' => now()->subDay()]);
        $firmadoEn = $documento->fresh()->firmado_en;

        $this->actingAs($this->cuentaPaciente);

        $this->get(route('portal.documentos.firmar', $documento))
            ->assertRedirect(route('portal.documentos'))
            ->assertSessionHas('aviso');

        $this->post(route('portal.documentos.firmar.guardar', $documento), [
            'firma' => $this->firmaPng(),
            'acepto' => '1',
        ])->assertRedirect(route('portal.documentos'))->assertSessionHas('aviso');

        $documento->refresh();
        $this->assertSame('firmas/previa.png', $documento->firma_archivo);
        $this->assertTrue($firmadoEn->equalTo($documento->firmado_en));
    }

    public function test_un_documento_ajeno_o_que_no_es_consentimiento_no_existe_para_el_portal(): void
    {
        $otro = Paciente::create([
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'tipo_documento' => 'CI',
            'numero_documento' => '99998888',
            'genero' => 'F',
            'activo' => true,
        ]);
        $ajeno = $this->consentimiento($otro);
        $receta = $this->consentimiento(tipo: 'RECETA');

        $this->actingAs($this->cuentaPaciente);

        $this->get(route('portal.documentos.firmar', $ajeno))->assertNotFound();
        $this->post(route('portal.documentos.firmar.guardar', $ajeno), ['firma' => $this->firmaPng(), 'acepto' => '1'])->assertNotFound();
        $this->get(route('portal.documentos.firmar', $receta))->assertNotFound();

        $this->assertFalse($ajeno->fresh()->esta_firmado);
        $this->assertFalse($receta->fresh()->esta_firmado);
    }
}
