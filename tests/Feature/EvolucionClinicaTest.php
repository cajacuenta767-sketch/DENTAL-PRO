<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\HistorialClinico;
use Tests\CasoClinico;

class EvolucionClinicaTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'doctor_id' => $this->doctor->id,
            'fecha' => now()->toDateString(),
            'motivo_consulta' => 'Limpieza dental de rutina.',
            'diagnostico' => 'Gingivitis leve asociada a placa.',
            'tratamiento_realizado' => 'Profilaxis con ultrasonido y pulido.',
        ], $extra);
    }

    public function test_la_configuracion_trae_plantillas_y_anestesicos(): void
    {
        $plantillas = config('evolucion.plantillas');

        foreach (['control', 'profilaxis', 'obturacion', 'endodoncia', 'exodoncia', 'urgencia_dolor', 'ortodoncia_control'] as $clave) {
            $this->assertArrayHasKey($clave, $plantillas);
            $this->assertNotEmpty($plantillas[$clave]['etiqueta']);
            $this->assertNotEmpty($plantillas[$clave]['motivo_consulta']);
        }

        $this->assertContains('Lidocaína 2 % con epinefrina 1:100000', config('evolucion.anestesicos'));
        $this->assertContains('Sin anestesia', config('evolucion.anestesicos'));
    }

    public function test_registra_la_consulta_con_plantilla_anestesia_y_medicacion(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/historial", $this->datos([
            'plantilla' => 'profilaxis',
            'anestesia' => 'Lidocaína 2 % con epinefrina 1:100000',
            'anestesia_cantidad' => '1.5',
            'medicacion' => 'Flúor tópico en gel.',
            'proxima_cita_indicaciones' => 'Control en 6 meses; traer radiografía.',
        ]))->assertRedirect(route('admin.historiales.index', $this->paciente));

        $historial = HistorialClinico::firstOrFail();

        $this->assertSame('profilaxis', $historial->plantilla);
        $this->assertSame('Lidocaína 2 % con epinefrina 1:100000', $historial->anestesia);
        $this->assertSame('1.5', $historial->anestesia_cantidad);
        $this->assertSame('Flúor tópico en gel.', $historial->medicacion);
        $this->assertSame('Control en 6 meses; traer radiografía.', $historial->proxima_cita_indicaciones);

        $this->get("/admin/pacientes/{$this->paciente->id}/historial")
            ->assertOk()
            ->assertSee('Lidocaína 2 % con epinefrina 1:100000')
            ->assertSee('1.5 cartucho(s)')
            ->assertSee('Flúor tópico en gel.')
            ->assertSee('Profilaxis dental');
    }

    public function test_rechaza_una_plantilla_desconocida(): void
    {
        $this->postJson("/admin/pacientes/{$this->paciente->id}/historial", $this->datos([
            'plantilla' => 'inventada',
        ]))->assertStatus(422)->assertJsonValidationErrors(['plantilla']);

        $this->postJson("/admin/pacientes/{$this->paciente->id}/historial", $this->datos([
            'anestesia' => str_repeat('a', 121),
        ]))->assertStatus(422)->assertJsonValidationErrors(['anestesia']);

        $this->assertSame(0, HistorialClinico::count());
    }

    public function test_el_formulario_ofrece_plantillas_anestesicos_y_dictado(): void
    {
        $this->get("/admin/pacientes/{$this->paciente->id}/historial/nuevo")
            ->assertOk()
            ->assertSee('Plantilla de nota')
            ->assertSee('Profilaxis dental')
            ->assertSee('Mepivacaína 3 %')
            ->assertSee('Indicaciones para la próxima cita')
            ->assertSee('data-dictar="diagnostico"', false)
            ->assertSee('webkitSpeechRecognition', false);
    }

    public function test_el_pdf_incluye_la_anestesia(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/historial", $this->datos([
            'anestesia' => 'Articaína 4 % con epinefrina 1:100000',
            'anestesia_cantidad' => '2',
            'medicacion' => 'Ibuprofeno 400 mg administrado en consulta.',
        ]))->assertRedirect();

        $historial = HistorialClinico::firstOrFail()->load(['paciente', 'doctor.especialidad']);

        $respuesta = $this->get("/admin/historiales/{$historial->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));

        // El PDF comprime el texto, así que se verifica la plantilla que lo genera.
        $html = view('pdf.historial', ['historial' => $historial, 'clinica' => Ajuste::actual()])->render();

        $this->assertStringContainsString('Articaína 4 % con epinefrina 1:100000', $html);
        $this->assertStringContainsString('2 cartucho(s)', $html);
        $this->assertStringContainsString('Ibuprofeno 400 mg administrado en consulta.', $html);
    }
}
