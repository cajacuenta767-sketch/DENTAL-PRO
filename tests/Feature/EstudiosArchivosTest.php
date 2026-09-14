<?php

namespace Tests\Feature;

use App\Models\EstudioImagen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CasoClinico;

/** Carga de resultados: fotos, radiografías, PDF, documentos y DICOM, uno o varios a la vez. */
class EstudiosArchivosTest extends CasoClinico
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs($this->admin);
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'paciente_id' => $this->paciente->id,
            'doctor_id' => $this->doctor->id,
            'tipo' => 'INFORME',
            'titulo' => 'Resultado de laboratorio',
            'fecha_estudio' => now()->toDateString(),
        ];
    }

    public function test_acepta_documentos_word_pdf_y_dicom(): void
    {
        $archivos = [
            UploadedFile::fake()->create('informe.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('informe.pdf', 300, 'application/pdf'),
            UploadedFile::fake()->create('tomografia.dcm', 800, 'application/dicom'),
            UploadedFile::fake()->create('notas.txt', 2, 'text/plain'),
        ];

        foreach ($archivos as $archivo) {
            $this->post('/admin/estudios', $this->datos(['archivo' => $archivo]))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.estudios.paciente', $this->paciente->id));
        }

        $this->assertSame(4, EstudioImagen::count());

        $docx = EstudioImagen::where('nombre_original', 'informe.docx')->firstOrFail();
        $this->assertSame('documento', $docx->familia);
        $this->assertFalse($docx->es_visualizable);
        $this->assertFalse($docx->es_pdf);
        Storage::disk('local')->assertExists($docx->archivo);

        $pdf = EstudioImagen::where('nombre_original', 'informe.pdf')->firstOrFail();
        $this->assertTrue($pdf->es_pdf);
        $this->assertSame('pdf', $pdf->familia);

        $dicom = EstudioImagen::where('nombre_original', 'tomografia.dcm')->firstOrFail();
        $this->assertSame('dicom', $dicom->familia);
        $this->assertSame('ti-radioactive', $dicom->icono);
    }

    public function test_sube_varios_archivos_a_la_vez_como_estudios_independientes(): void
    {
        $this->post('/admin/estudios', $this->datos([
            'tipo' => 'FOTO_INTRAORAL',
            'titulo' => 'Fotos de control',
            'archivos' => [
                UploadedFile::fake()->image('frontal.jpg', 300, 200),
                UploadedFile::fake()->image('lateral.png', 300, 200),
                UploadedFile::fake()->create('informe.pdf', 50, 'application/pdf'),
            ],
        ]))->assertSessionHasNoErrors()
            ->assertSessionHas('exito', 'Se cargaron 3 archivos.');

        $estudios = EstudioImagen::orderBy('id')->get();
        $this->assertCount(3, $estudios);
        $this->assertSame(['Fotos de control (1/3)', 'Fotos de control (2/3)', 'Fotos de control (3/3)'], $estudios->pluck('titulo')->all());
        $this->assertSame([true, true, false], $estudios->pluck('es_visualizable')->all());
        $estudios->each(fn ($e) => Storage::disk('local')->assertExists($e->archivo));
    }

    public function test_rechaza_archivos_peligrosos_o_disfrazados(): void
    {
        // Ejecutables y scripts, aunque el navegador los declare como otra cosa.
        $this->post('/admin/estudios', $this->datos([
            'archivo' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('archivo');

        $this->post('/admin/estudios', $this->datos([
            'archivo' => UploadedFile::fake()->create('script.php', 10, 'text/x-php'),
        ]))->assertSessionHasErrors('archivo');

        // Extensión de imagen con contenido que no es imagen.
        $this->post('/admin/estudios', $this->datos([
            'archivo' => UploadedFile::fake()->create('foto.jpg', 10, 'application/x-msdownload'),
        ]))->assertSessionHasErrors('archivo');

        // Dentro de una carga múltiple, un archivo inválido invalida el envío.
        $this->post('/admin/estudios', $this->datos([
            'archivos' => [
                UploadedFile::fake()->image('ok.png', 100, 100),
                UploadedFile::fake()->create('malo.bat', 10, 'text/plain'),
            ],
        ]))->assertSessionHasErrors('archivos.1');

        $this->assertSame(0, EstudioImagen::count());
    }

    public function test_exige_al_menos_un_archivo_al_crear(): void
    {
        $this->post('/admin/estudios', $this->datos())
            ->assertSessionHasErrors(['archivo', 'archivos']);
    }

    public function test_reemplaza_una_imagen_por_un_documento_al_editar(): void
    {
        $this->post('/admin/estudios', $this->datos([
            'tipo' => 'PANORAMICA',
            'archivo' => UploadedFile::fake()->image('rx.png', 200, 100),
        ]))->assertSessionHasNoErrors();

        $estudio = EstudioImagen::firstOrFail();
        $rutaAnterior = $estudio->archivo;

        $this->put("/admin/estudios/{$estudio->id}", $this->datos([
            'tipo' => 'INFORME',
            'archivo' => UploadedFile::fake()->create('informe.docx', 80, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ]))->assertSessionHasNoErrors();

        $estudio->refresh();
        $this->assertSame('informe.docx', $estudio->nombre_original);
        $this->assertSame('documento', $estudio->familia);
        Storage::disk('local')->assertMissing($rutaAnterior);
        Storage::disk('local')->assertExists($estudio->archivo);

        // El visor sirve el documento en línea con su tipo real y la descarga conserva el nombre.
        $this->get("/admin/estudios/{$estudio->id}/ver")->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->get("/admin/estudios/{$estudio->id}/descargar")->assertOk()
            ->assertDownload('informe.docx');
    }
}
