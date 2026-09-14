<?php

namespace Tests\Feature;

use App\Mail\CodigoAccesoMail;
use App\Models\Usuario;
use Illuminate\Support\Facades\Mail;
use Tests\CasoClinico;

class DobleFactorTest extends CasoClinico
{
    private Usuario $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->usuario = Usuario::create([
            'nombre' => 'Recepción Protegida',
            'email' => 'protegida@pruebas.test',
            'password' => 'secreto123',
            'estado' => 'activo',
            'dos_factores' => true,
        ]);
        $this->usuario->forceFill(['email_verified_at' => now()])->save();
        $this->usuario->assignRole('RECEPCION');
    }

    /** Inicia sesión con la contraseña y devuelve el código que se envió por correo. */
    private function primerFactor(): string
    {
        $this->post('/login', [
            'email' => $this->usuario->email,
            'password' => 'secreto123',
        ])->assertRedirect(route('login.verificar'));

        $this->assertGuest();

        $codigo = null;

        Mail::assertSent(CodigoAccesoMail::class, function (CodigoAccesoMail $mail) use (&$codigo) {
            $codigo = $mail->codigo;

            return $mail->hasTo($this->usuario->email);
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $codigo);

        return $codigo;
    }

    public function test_la_contrasena_sola_no_abre_sesion_y_envia_el_codigo(): void
    {
        $this->primerFactor();

        Mail::assertSent(CodigoAccesoMail::class, 1);
    }

    public function test_un_codigo_incorrecto_no_autentica(): void
    {
        $codigo = $this->primerFactor();
        $incorrecto = $codigo === '000000' ? '111111' : '000000';

        $this->from(route('login.verificar'))
            ->post('/login/verificar', ['codigo' => $incorrecto])
            ->assertSessionHasErrors('codigo');

        $this->assertGuest();
    }

    public function test_el_codigo_correcto_completa_el_acceso(): void
    {
        $codigo = $this->primerFactor();

        $this->post('/login/verificar', ['codigo' => $codigo])
            ->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($this->usuario);
    }

    public function test_el_codigo_es_de_un_solo_uso(): void
    {
        $codigo = $this->primerFactor();

        $this->post('/login/verificar', ['codigo' => $codigo])->assertRedirect(route('admin.home'));

        $usuario = $this->usuario->fresh();

        $this->assertNull($usuario->codigo_2fa, 'El código se consume al usarlo.');
        $this->assertFalse($usuario->verificarCodigo2fa($codigo));
    }
}
