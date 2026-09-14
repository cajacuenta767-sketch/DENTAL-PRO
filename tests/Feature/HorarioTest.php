<?php

namespace Tests\Feature;

use App\Models\Horario;
use Tests\CasoClinico;

class HorarioTest extends CasoClinico
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
            'dia_semana' => 'LUNES',
            'turno' => 'MAÑANA',
            'hora_inicio' => '10:00',
            'hora_fin' => '14:00',
            'activo' => 1,
        ], $extra);
    }

    public function test_un_turno_que_se_cruza_con_otro_activo_se_rechaza(): void
    {
        $previos = Horario::count();

        $this->from('/admin/horarios/create')
            ->post('/admin/horarios', $this->datos())
            ->assertSessionHasErrors('hora_inicio');

        $this->assertSame($previos, Horario::count());
    }

    public function test_un_turno_inactivo_no_bloquea_el_nuevo(): void
    {
        Horario::where('doctor_id', $this->doctor->id)->where('dia_semana', 'LUNES')->update(['activo' => false]);

        $this->post('/admin/horarios', $this->datos())->assertRedirect(route('admin.horarios.index'));

        $this->assertTrue(
            Horario::where('doctor_id', $this->doctor->id)->where('dia_semana', 'LUNES')
                ->where('hora_inicio', '10:00:00')->where('activo', true)->exists()
        );
    }

    public function test_un_turno_en_otra_franja_se_registra(): void
    {
        $previos = Horario::count();

        $this->post('/admin/horarios', $this->datos([
            'dia_semana' => 'MARTES',
            'turno' => 'TARDE',
            'hora_inicio' => '14:00',
            'hora_fin' => '18:00',
        ]))->assertRedirect(route('admin.horarios.index'));

        $this->assertSame($previos + 1, Horario::count());
    }
}
