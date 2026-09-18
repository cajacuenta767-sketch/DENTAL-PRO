<?php

namespace Tests\Feature;

use App\Models\CicloEsterilizacion;
use App\Models\Cita;
use App\Models\ImplantePaciente;
use Tests\CasoClinico;

class BioseguridadImplantesYTurneroTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->admin);
    }

    public function test_autoclave_ciclo_puede_ser_creado_con_qr_y_etiquetas(): void
    {
        // Listado
        $response = $this->get(route('admin.esterilizacion.index'));
        $response->assertOk();

        // Formulario
        $response = $this->get(route('admin.esterilizacion.create'));
        $response->assertOk();

        // Crear ciclo
        $response = $this->post(route('admin.esterilizacion.store'), [
            'autoclave_nombre' => 'Autoclave Clase B - Sala Quirúrgica',
            'numero_ciclo' => 1,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '08:30',
            'hora_fin' => '09:15',
            'temperatura' => 134.0,
            'presion' => 2.10,
            'tiempo_esterilizacion' => 18,
            'tipo_carga' => 'INSTRUMENTAL_QUIRURGICO',
            'indicador_quimico' => 'CONFORME',
            'indicador_biologico' => 'NEGATIVO',
            'resultado' => 'APROBADO',
            'paquetes_esterilizados' => 8,
            'fecha_caducidad_paquetes' => now()->addDays(30)->toDateString(),
            'observaciones' => 'Ciclo de instrumental para cirugía de implantes',
        ]);

        $response->assertRedirect(route('admin.esterilizacion.index'));
        $this->assertDatabaseHas('ciclos_esterilizacion', [
            'autoclave_nombre' => 'Autoclave Clase B - Sala Quirúrgica',
            'numero_ciclo' => 1,
            'resultado' => 'APROBADO',
            'paquetes_esterilizados' => 8,
        ]);

        $ciclo = CicloEsterilizacion::first();
        $this->assertNotEmpty($ciclo->qr_token);

        // Generación de etiquetas PDF con QR
        $responsePdf = $this->get(route('admin.esterilizacion.etiquetas', $ciclo));
        $responsePdf->assertOk();
        $this->assertEquals('application/pdf', $responsePdf->headers->get('content-type'));

        // Verificación de autenticidad
        $responseVerificar = $this->get(route('esterilizacion.verificar-publica', $ciclo->qr_token));
        $responseVerificar->assertOk();
        $responseVerificar->assertSee('INSTRUMENTAL ESTÉRIL Y CONFORME');
    }

    public function test_implante_paciente_puede_ser_registrado_y_genera_pasaporte_pdf(): void
    {
        // Listado por paciente
        $response = $this->get(route('admin.implantes.paciente', $this->paciente));
        $response->assertOk();

        // Guardar implante
        $response = $this->post(route('admin.implantes.store', $this->paciente), [
            'posicion_fdi' => 16,
            'marca' => 'Straumann',
            'modelo' => 'BLX Roxolid SLActive',
            'numero_lote' => 'LOT-ST998822',
            'numero_serie' => 'SN-882211',
            'diametro_mm' => 4.10,
            'longitud_mm' => 10.00,
            'tipo_conexion' => 'CONO_MORSE',
            'torque_insercion_ncm' => 45.0,
            'isq_estabilidad' => 78,
            'injerto_oseo' => 'Bio-Oss 0.5g',
            'membrana' => 'Bio-Gide 25x25',
            'fecha_colocacion' => now()->toDateString(),
            'doctor_id' => $this->doctor->id,
            'estado' => 'COLOCADO',
            'observaciones' => 'Colocación sin complicaciones con estabilidad primaria óptima.',
        ]);

        $response->assertRedirect(route('admin.implantes.paciente', $this->paciente));
        $this->assertDatabaseHas('implantes_paciente', [
            'paciente_id' => $this->paciente->id,
            'posicion_fdi' => 16,
            'marca' => 'Straumann',
            'numero_lote' => 'LOT-ST998822',
        ]);

        $implante = ImplantePaciente::where('paciente_id', $this->paciente->id)->first();
        $this->assertNotEmpty($implante->qr_pasaporte_token);

        // Descarga de Pasaporte Digital de Implante en PDF
        $responsePdf = $this->get(route('admin.implantes.pasaporte', $implante));
        $responsePdf->assertOk();
        $this->assertEquals('application/pdf', $responsePdf->headers->get('content-type'));

        // Actualizar a Rehabilitado
        $responseUpdate = $this->put(route('admin.implantes.update', $implante), [
            'posicion_fdi' => 16,
            'marca' => 'Straumann',
            'modelo' => 'BLX Roxolid SLActive',
            'numero_lote' => 'LOT-ST998822',
            'diametro_mm' => 4.10,
            'longitud_mm' => 10.00,
            'tipo_conexion' => 'CONO_MORSE',
            'fecha_colocacion' => now()->toDateString(),
            'fecha_rehabilitacion' => now()->addMonths(4)->toDateString(),
            'doctor_id' => $this->doctor->id,
            'estado' => 'REHABILITADO',
        ]);

        $responseUpdate->assertRedirect(route('admin.implantes.paciente', $this->paciente));
        $this->assertDatabaseHas('implantes_paciente', [
            'id' => $implante->id,
            'estado' => 'REHABILITADO',
        ]);
    }

    public function test_turnero_digital_y_llamado_de_pacientes(): void
    {
        $cita = Cita::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => now()->toDateString(),
            'hora' => '10:00',
            'estado' => 'PENDIENTE',
            'token' => 'CITA-TURNERO-1',
        ]);

        // Pantalla TV
        $responseTv = $this->get(route('admin.turnero.pantalla'));
        $responseTv->assertOk();

        // Llamar paciente desde recepción o consultorio
        $responseLlamar = $this->post(route('admin.turnero.llamar', $cita), [
            'consultorio' => 'Consultorio 3 · Sillón B',
        ]);

        $responseLlamar->assertRedirect();
        $cita->refresh();
        $this->assertNotNull($cita->llamado_en);
        $this->assertEquals('Consultorio 3 · Sillón B', $cita->consultorio);
        $this->assertEquals('EN_CURSO', $cita->estado);

        // Consultar API de datos del turnero
        $responseDatos = $this->getJson(route('admin.turnero.datos'));
        $responseDatos->assertOk();
        $responseDatos->assertJson([
            'actual' => [
                'id' => $cita->id,
                'paciente' => $this->paciente->nombre_completo,
                'consultorio' => 'Consultorio 3 · Sillón B',
            ],
        ]);
    }

    public function test_crm_postop_seguimiento_pacientes(): void
    {
        $cita = Cita::create([
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tratamiento_id' => $this->tratamiento->id,
            'fecha' => now()->subDay()->toDateString(),
            'hora' => '15:00',
            'estado' => 'COMPLETADA',
            'token' => 'CITA-POSTOP-1',
        ]);

        // Ver tablero CRM Post-Op
        $responseCrm = $this->get(route('admin.crm-postop.index'));
        $responseCrm->assertOk();
        $responseCrm->assertSee($this->paciente->nombre_completo);

        // Registrar contacto post-operatorio
        $responseContactar = $this->post(route('admin.crm-postop.contactar', $cita), [
            'postop_estado' => 'CONTACTADO',
        ]);

        $responseContactar->assertRedirect();
        $cita->refresh();
        $this->assertEquals('CONTACTADO', $cita->postop_estado);
        $this->assertNotNull($cita->postop_contactado_en);
    }
}
