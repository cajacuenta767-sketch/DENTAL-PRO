<?php

namespace Tests\Feature;

use App\Models\EstudioImagen;
use App\Models\Paciente;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CasoClinico;

class ImagenologiaAvanzadaTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs($this->admin);
    }

    /** Crea un estudio de imagen con su PNG en el disco privado simulado. */
    private function crearEstudio(Paciente $paciente, string $titulo = 'Panorámica'): EstudioImagen
    {
        $archivo = UploadedFile::fake()->image('rx.png', 200, 100);
        $ruta = $archivo->store('estudios/'.$paciente->id, 'local');

        return EstudioImagen::create([
            'paciente_id' => $paciente->id,
            'doctor_id' => $this->doctor->id,
            'usuario_id' => $this->admin->id,
            'tipo' => 'PANORAMICA',
            'titulo' => $titulo,
            'archivo' => $ruta,
            'nombre_original' => 'rx.png',
            'mime' => 'image/png',
            'tamano' => 1234,
            'fecha_estudio' => now()->toDateString(),
        ]);
    }

    private function otroPaciente(): Paciente
    {
        return Paciente::create([
            'nombres' => 'Ana',
            'apellidos' => 'Quispe',
            'tipo_documento' => 'CI',
            'numero_documento' => '9988776',
            'genero' => 'F',
            'activo' => true,
        ]);
    }

    public function test_guarda_las_anotaciones_en_coordenadas_relativas(): void
    {
        $estudio = $this->crearEstudio($this->paciente);

        $anotaciones = [
            ['tipo' => 'lapiz', 'color' => '#D63939', 'grosor' => 3, 'puntos' => [[0.1, 0.2], [0.15, 0.25], [0.2, 0.3]]],
            ['tipo' => 'flecha', 'color' => '#206bc4', 'grosor' => 2, 'desde' => ['x' => 0.1, 'y' => 0.1], 'hasta' => ['x' => 0.5, 'y' => 0.5]],
            ['tipo' => 'circulo', 'color' => '#2fb344', 'grosor' => 4, 'centro' => ['x' => 0.5, 'y' => 0.5], 'radio' => 0.1],
            ['tipo' => 'texto', 'color' => '#f59f00', 'grosor' => 3, 'texto' => 'Lesión apical', 'x' => 0.3, 'y' => 0.7],
        ];

        $this->putJson("/admin/estudios/{$estudio->id}/anotaciones", ['anotaciones' => $anotaciones])
            ->assertOk()
            ->assertJson(['ok' => true, 'total' => 4]);

        $guardadas = $estudio->fresh()->anotaciones;

        $this->assertCount(4, $guardadas);
        $this->assertSame('lapiz', $guardadas[0]['tipo']);
        $this->assertSame('#d63939', $guardadas[0]['color'], 'El color se normaliza a minúsculas.');
        $this->assertCount(3, $guardadas[0]['puntos']);
        $this->assertSame(0.5, $guardadas[1]['hasta']['x']);
        $this->assertSame(0.1, $guardadas[2]['radio']);
        $this->assertSame('Lesión apical', $guardadas[3]['texto']);
        $this->assertSame(0.7, $guardadas[3]['y']);
    }

    public function test_rechaza_anotaciones_con_tipo_o_coordenadas_invalidas(): void
    {
        $estudio = $this->crearEstudio($this->paciente);

        $this->putJson("/admin/estudios/{$estudio->id}/anotaciones", ['anotaciones' => [
            ['tipo' => 'poligono', 'color' => '#d63939', 'grosor' => 3, 'puntos' => [[0.1, 0.2]]],
        ]])->assertStatus(422)->assertJsonValidationErrors(['anotaciones.0.tipo']);

        $this->putJson("/admin/estudios/{$estudio->id}/anotaciones", ['anotaciones' => [
            ['tipo' => 'texto', 'color' => 'rojo', 'grosor' => 3, 'texto' => 'x', 'x' => 'no', 'y' => 0.1],
        ]])->assertStatus(422)->assertJsonValidationErrors(['anotaciones.0.color', 'anotaciones.0.x']);

        $this->assertNull($estudio->fresh()->anotaciones);
    }

    public function test_un_arreglo_vacio_borra_las_anotaciones(): void
    {
        $estudio = $this->crearEstudio($this->paciente);
        $estudio->update(['anotaciones' => [['tipo' => 'texto', 'color' => '#d63939', 'grosor' => 3, 'texto' => 'a', 'x' => 0.1, 'y' => 0.1]]]);

        $this->putJson("/admin/estudios/{$estudio->id}/anotaciones", ['anotaciones' => []])->assertOk();

        $this->assertSame([], $estudio->fresh()->anotaciones);
    }

    public function test_compara_dos_estudios_del_mismo_paciente(): void
    {
        $a = $this->crearEstudio($this->paciente, 'Panorámica inicial');
        $b = $this->crearEstudio($this->paciente, 'Panorámica de control');

        $this->get("/admin/estudios/comparar?a={$a->id}&b={$b->id}")
            ->assertOk()
            ->assertSee('Comparar estudios')
            ->assertSee('Panorámica inicial')
            ->assertSee('Panorámica de control')
            ->assertSee('Sincronizar zoom')
            ->assertSee('Superponer B sobre A');
    }

    public function test_no_compara_estudios_de_pacientes_distintos(): void
    {
        $a = $this->crearEstudio($this->paciente);
        $b = $this->crearEstudio($this->otroPaciente());

        $this->get("/admin/estudios/comparar?a={$a->id}&b={$b->id}")->assertNotFound();
        $this->get("/admin/estudios/comparar?a={$a->id}&b=999999")->assertNotFound();
    }

    public function test_la_galeria_muestra_el_badge_de_anotado_y_el_enlace_para_comparar(): void
    {
        $a = $this->crearEstudio($this->paciente, 'Con anotaciones');
        $this->crearEstudio($this->paciente, 'Sin anotaciones');
        $a->update(['anotaciones' => [['tipo' => 'texto', 'color' => '#d63939', 'grosor' => 3, 'texto' => 'a', 'x' => 0.1, 'y' => 0.1]]]);

        $this->get("/admin/pacientes/{$this->paciente->id}/panoramicas")
            ->assertOk()
            ->assertSee('Anotado')
            ->assertSee('data-comparar-check', false)
            ->assertSee(route('admin.estudios.comparar'));
    }
}
