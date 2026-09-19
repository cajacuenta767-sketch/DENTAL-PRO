<?php

namespace Tests\Feature;

use Tests\CasoClinico;

/**
 * La aplicación corre en español (APP_LOCALE=es). Si faltan los archivos de
 * `lang/es`, Laravel no falla: muestra la clave cruda («validation.required»,
 * «pagination.next», «passwords.user») en pantalla. Estas pruebas cierran ese
 * agujero, porque comprobar solo `assertSessionHasErrors()` no lo detecta.
 */
class IdiomaTest extends CasoClinico
{
    public function test_los_errores_de_validacion_se_muestran_en_espanol(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/pacientes/create')
            ->post('/admin/pacientes', ['nombres' => '']);

        $errores = session('errors')->getBag('default');

        $this->assertNotEmpty($errores->all());

        foreach ($errores->all() as $mensaje) {
            $this->assertStringNotContainsString('validation.', $mensaje);
        }

        $this->assertSame(
            'El campo nombres es obligatorio.',
            $errores->first('nombres')
        );
    }

    public function test_los_mensajes_de_contrasena_se_muestran_en_espanol(): void
    {
        $this->assertSame('No encontramos ninguna cuenta con ese correo electrónico.', __('passwords.user'));
        $this->assertSame('El enlace de restablecimiento no es válido o ya venció.', __('passwords.token'));
        $this->assertSame('Tu contraseña fue restablecida.', __('passwords.reset'));
    }

    public function test_la_paginacion_se_muestra_en_espanol(): void
    {
        $this->assertSame('&laquo; Anterior', trans('pagination.previous'));
        $this->assertSame('Siguiente &raquo;', trans('pagination.next'));
    }

    public function test_el_aviso_de_credenciales_invalidas_se_muestra_en_espanol(): void
    {
        $mensaje = trans('auth.failed');

        $this->assertStringNotContainsString('auth.', $mensaje);
        $this->assertSame(
            'Las credenciales ingresadas no coinciden con nuestros registros.',
            $mensaje
        );
    }
}
