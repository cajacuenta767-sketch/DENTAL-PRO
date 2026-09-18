<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\LaboratorioDental;
use App\Models\MedicamentoVademecum;
use App\Models\OrdenLaboratorio;
use App\Models\Paciente;
use Database\Seeders\VademecumSeeder;
use Tests\CasoClinico;

class LaboratorioYVademecumTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VademecumSeeder::class);
        $this->actingAs($this->admin);

        $this->paciente->update([
            'alergias' => 'Alérgica a Penicilina y al Látex',
            'enfermedades' => 'Diabetes Mellitus Tipo 2 e Hipertensión arterial',
            'medicamentos' => 'Aspirina 100mg y Warfarina anticoagulante',
        ]);
    }

    public function test_usuario_con_permiso_puede_ver_listado_de_laboratorio(): void
    {
        $response = $this->get(route('admin.laboratorio.index'));
        $response->assertOk();
        $response->assertSee('Laboratorio Dental y Prótesis');
    }

    public function test_puede_registrar_un_laboratorio_asociado_en_catalogo(): void
    {
        $response = $this->post(route('admin.laboratorio.catalogo.store'), [
            'nombre' => 'Laboratorio Dental BioEsthetic',
            'contacto' => 'TPD. Roberto Gómez',
            'telefono' => '+54 11 4455-6677',
            'email' => 'bioesthetic@lab.com',
            'direccion' => 'Av. Corrientes 1234',
            'especialidades' => 'Zirconia, E.max, Prótesis sobre Implantes',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('laboratorios_dentales', [
            'nombre' => 'Laboratorio Dental BioEsthetic',
            'contacto' => 'TPD. Roberto Gómez',
        ]);
    }

    public function test_puede_crear_y_gestionar_una_orden_de_laboratorio(): void
    {
        $lab = LaboratorioDental::create([
            'nombre' => 'CeramiDent Dental Lab',
            'contacto' => 'Juan Pérez',
            'activo' => true,
        ]);

        $response = $this->post(route('admin.laboratorio.store'), [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'laboratorio_id' => $lab->id,
            'tipo_trabajo' => 'Corona Zirconia Monolítica',
            'color_vita' => 'A2',
            'piezas_dentales' => '11, 21',
            'fecha_envio' => now()->toDateString(),
            'fecha_prometida' => now()->addDays(5)->toDateString(),
            'costo_laboratorio' => 120.50,
            'precio_paciente' => 350.00,
            'estado' => 'ENVIADO',
            'notas_tecnicas' => 'Chamfer subgingival 0.5mm, punto de contacto ajustado',
        ]);

        $response->assertRedirect();
        $orden = OrdenLaboratorio::where('tipo_trabajo', 'Corona Zirconia Monolítica')->first();
        $this->assertNotNull($orden);
        $this->assertEquals('A2', $orden->color_guia);
        $this->assertEquals(['11', '21'], $orden->dientes_array);
        $this->assertEquals('ENVIADO', $orden->estado);

        // Cambiar estado a EN_PROCESO
        $patchResponse = $this->patch(route('admin.laboratorio.estado', $orden), [
            'estado' => 'EN_PROCESO',
        ]);
        $patchResponse->assertRedirect();
        $this->assertEquals('EN_PROCESO', $orden->fresh()->estado);

        // Generar ticket PDF
        $pdfResponse = $this->get(route('admin.laboratorio.pdf', $orden));
        $pdfResponse->assertOk();
    }

    public function test_vademecum_busqueda_y_gestion(): void
    {
        // El seeder ya precargó medicamentos (Amoxicilina, Ibuprofeno, etc.)
        $response = $this->get(route('admin.vademecum.buscar', ['q' => 'amoxi']));
        $response->assertOk();
        $response->assertJsonFragment(['principio_activo' => 'Amoxicilina']);

        // Registrar un nuevo medicamento en el vademécum
        $storeResponse = $this->post(route('admin.vademecum.store'), [
            'principio_activo' => 'Azitromicina',
            'nombre_comercial' => 'Zitromax',
            'presentacion' => 'Comprimidos recubiertos',
            'concentracion' => '500 mg',
            'familia' => 'MACROLIDO',
            'posologia_adulto' => '500 mg una vez al día durante 3 días',
            'posologia_pediatrica' => '10 mg/kg/día',
            'contraindicaciones' => 'Insuficiencia hepática severa',
        ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('medicamentos_vademecum', [
            'principio_activo' => 'Azitromicina',
            'familia' => 'MACROLIDO',
        ]);
    }

    public function test_alertas_medicas_estructuradas_en_paciente(): void
    {
        $this->assertTrue($this->paciente->tiene_alertas_medicas);
        $alertas = $this->paciente->lista_alertas;

        $tipos = array_column($alertas, 'tipo');
        $this->assertContains('ALERGIA_PENICILINA', $tipos);
        $this->assertContains('ALERGIA_LATEX', $tipos);
        $this->assertContains('DIABETES', $tipos);
        $this->assertContains('HIPERTENSION', $tipos);
        $this->assertContains('ANTICOAGULANTE', $tipos);
    }
}
