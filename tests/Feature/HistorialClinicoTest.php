<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\HistorialClinico;
use App\Models\Paciente;
use Tests\CasoClinico;

/**
 * Historia clínica: registro de consultas, su PDF y la coherencia entre
 * la consulta y la cita que la origina.
 */
class HistorialClinicoTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'doctor_id' => $this->doctor->id,
            'fecha' => now()->toDateString(),
            'motivo_consulta' => 'Dolor en molar inferior derecho',
            'diagnostico' => 'Caries profunda en pieza 46',
            'tratamiento_realizado' => 'Obturación provisional',
        ];
    }

    private function citaDeOtroPaciente(): Cita
    {
        $otro = Paciente::create([
            'nombres' => 'Otra',
            'apellidos' => 'Persona',
            'tipo_documento' => 'CI',
            'numero_documento' => '1112223',
            'genero' => 'F',
            'activo' => true,
        ]);

        return Cita::create([
            'paciente_id' => $otro->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'token' => 'CIT-OTRA-001',
            'fecha' => $this->proximaFecha(),
            'hora' => '08:00',
            'duracion' => 30,
            'estado' => 'CONFIRMADA',
            'origen' => 'RECEPCION',
        ]);
    }

    public function test_una_consulta_queda_registrada_en_la_historia_del_paciente(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/historial", $this->datos())
            ->assertRedirect("/admin/pacientes/{$this->paciente->id}/historial");

        $this->assertSame(1, $this->paciente->historiales()->count());
    }

    public function test_una_consulta_con_fecha_futura_se_rechaza(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/historial", $this->datos([
            'fecha' => now()->addWeek()->toDateString(),
        ]))->assertSessionHasErrors('fecha');
    }

    public function test_no_se_enlaza_una_consulta_con_la_cita_de_otro_paciente(): void
    {
        $ajena = $this->citaDeOtroPaciente();

        $this->post("/admin/pacientes/{$this->paciente->id}/historial", $this->datos([
            'cita_id' => $ajena->id,
        ]))->assertSessionHasErrors('cita_id');

        $this->assertSame(0, HistorialClinico::where('cita_id', $ajena->id)->count());
    }

    public function test_la_historia_clinica_se_descarga_en_pdf(): void
    {
        $this->post("/admin/pacientes/{$this->paciente->id}/historial", $this->datos());

        $historial = HistorialClinico::sole();

        $respuesta = $this->get("/admin/historiales/{$historial->id}/pdf");

        $respuesta->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
    }
}
