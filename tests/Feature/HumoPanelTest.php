<?php

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Aseguradora;
use App\Models\Cita;
use App\Models\Doctor;
use App\Models\DocumentoClinico;
use App\Models\DocumentoFiscal;
use App\Models\Especialidad;
use App\Models\EstudioImagen;
use App\Models\HistorialClinico;
use App\Models\Horario;
use App\Models\Insumo;
use App\Models\Odontograma;
use App\Models\Paciente;
use App\Models\Pago;
use App\Models\Presupuesto;
use App\Models\Tratamiento;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Prueba de humo del panel: recorre todas las rutas GET con la clínica
 * demo cargada y un super administrador, y exige que ninguna reviente.
 * Es la red que detecta vistas rotas, relaciones ausentes o consultas
 * inválidas en cualquier módulo, sin tener que abrirlos uno por uno.
 */
class HumoPanelTest extends TestCase
{
    use RefreshDatabase;

    /** Rutas que no se pueden visitar a ciegas (redirecciones externas, firmas, descargas de archivo). */
    private const EXCLUIDAS = [
        'social.redirect',
        'social.callback',
        'verification.verify',
        'password.reset',
        'estudios.descargar',
        'api.v1.agenda.horas',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_todas_las_rutas_get_del_panel_responden(): void
    {
        $this->actingAs($this->superAdmin());

        $fallos = [];

        foreach ($this->rutasVisitables() as $nombre => $url) {
            $respuesta = $this->get($url);
            $codigo = $respuesta->getStatusCode();

            if ($codigo >= 400) {
                $fallos[] = sprintf('%s [%s] → %d', $nombre, $url, $codigo);

                continue;
            }

            // Un super administrador no debe acabar en el login ni en una
            // pantalla de permiso denegado: eso delata un middleware mal puesto.
            if ($codigo === 302 && str_contains((string) $respuesta->headers->get('Location'), '/login')) {
                $fallos[] = sprintf('%s [%s] → redirige al login', $nombre, $url);
            }
        }

        $this->assertSame([], $fallos, "Rutas del panel que no responden:\n".implode("\n", $fallos));
    }

    private function superAdmin(): Usuario
    {
        return Usuario::role(Role::findByName('SUPER ADMINISTRADOR', 'web')->name)->firstOrFail();
    }

    /** Construye la lista de URLs visitables resolviendo cada parámetro con un registro real. */
    private function rutasVisitables(): array
    {
        $urls = [];

        foreach (Route::getRoutes() as $ruta) {
            if (! in_array('GET', $ruta->methods(), true)) {
                continue;
            }

            $nombre = $ruta->getName();

            if ($nombre === null || in_array($nombre, self::EXCLUIDAS, true)) {
                continue;
            }

            $parametros = $this->parametrosPara($ruta->parameterNames(), $nombre);

            if ($parametros === null) {
                continue;
            }

            $urls[$nombre] = route($nombre, $parametros, false);
        }

        return $urls;
    }

    /** @return array<string,mixed>|null null si falta algún dato para armar la URL */
    private function parametrosPara(array $nombres, string $ruta): ?array
    {
        $parametros = [];

        foreach ($nombres as $nombre) {
            $valor = $this->valorDeParametro($nombre, $ruta);

            if ($valor === null) {
                return null;
            }

            $parametros[$nombre] = $valor;
        }

        return $parametros;
    }

    private function valorDeParametro(string $nombre, string $ruta): mixed
    {
        return match ($nombre) {
            'paciente' => Paciente::value('id'),
            'doctor' => Doctor::value('id'),
            'especialidad' => Especialidad::value('id'),
            'tratamiento' => Tratamiento::value('id'),
            'horario' => Horario::value('id'),
            'cita' => Cita::value('id'),
            'historial' => HistorialClinico::value('id'),
            'odontograma' => Odontograma::value('id'),
            'pago' => Pago::value('id'),
            'presupuesto' => Presupuesto::value('id'),
            'documento' => $this->documentoSegunContexto($ruta),
            'estudio' => EstudioImagen::value('id'),
            'insumo' => Insumo::value('id'),
            'aseguradora' => Aseguradora::value('id'),
            'usuario' => Usuario::value('id'),
            'role' => Role::value('id'),
            'seccion' => 'financiero',
            'token' => Ajuste::value('reservas_token'),
            default => null,
        };
    }

    /**
     * «documento» nombra dos recursos distintos: el clínico en el módulo de
     * recetas y el fiscal en facturación. Se resuelven por separado.
     */
    private function documentoSegunContexto(string $ruta): mixed
    {
        return str_starts_with($ruta, 'admin.facturacion.')
            ? DocumentoFiscal::value('id')
            : DocumentoClinico::value('id');
    }
}
