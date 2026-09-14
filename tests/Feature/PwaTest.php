<?php

namespace Tests\Feature;

use Tests\CasoClinico;

/**
 * Comprueba que la aplicación web es instalable como PWA: manifiesto,
 * service worker, página sin conexión, iconos y etiquetas en los layouts.
 */
class PwaTest extends CasoClinico
{
    public function test_el_manifiesto_responde_con_su_content_type(): void
    {
        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');

        $manifiesto = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('DENTAL-PRO', $manifiesto['name']);
        $this->assertSame('/admin/home', $manifiesto['start_url']);
        $this->assertSame('standalone', $manifiesto['display']);
        $this->assertSame('#0d9488', $manifiesto['theme_color']);
        $this->assertNotEmpty($manifiesto['background_color']);

        $propositos = array_column($manifiesto['icons'], 'purpose');
        $this->assertContains('any', $propositos);
        $this->assertContains('maskable', $propositos);

        foreach ($manifiesto['icons'] as $icono) {
            $this->assertFileExists(public_path($icono['src']), "Falta el icono {$icono['src']}");
            $this->assertMatchesRegularExpression('/^(192|512)x\1$/', $icono['sizes']);
        }
    }

    public function test_el_service_worker_responde_como_javascript(): void
    {
        $this->get('/sw.js')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8')
            ->assertHeader('Service-Worker-Allowed', '/');

        $codigo = (string) file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("addEventListener('install'", $codigo);
        $this->assertStringContainsString("addEventListener('fetch'", $codigo);
        $this->assertStringContainsString('/offline.html', $codigo);
        $this->assertStringContainsString("request.method !== 'GET'", $codigo);
    }

    public function test_la_pagina_sin_conexion_responde_como_html(): void
    {
        $this->get('/offline.html')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

        $html = (string) file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('Sin conexión', $html);
        $this->assertStringContainsString('location.reload()', $html);
    }

    public function test_los_iconos_de_la_pwa_son_png_del_tamano_declarado(): void
    {
        foreach (['icon-192' => 192, 'icon-512' => 512, 'maskable-192' => 192, 'maskable-512' => 512, 'apple-touch-icon' => 180] as $nombre => $lado) {
            $ruta = public_path("iconos/{$nombre}.png");
            $this->assertFileExists($ruta);

            $info = getimagesize($ruta);
            $this->assertNotFalse($info, "{$nombre}.png no es una imagen válida");
            $this->assertSame([$lado, $lado, 'image/png'], [$info[0], $info[1], $info['mime']]);
        }
    }

    public function test_los_layouts_incluyen_el_manifiesto(): void
    {
        // Layout de invitado (inicio de sesión).
        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('manifest.webmanifest')
            ->assertSee('rel="apple-touch-icon"', false)
            ->assertSee('name="theme-color" content="#0d9488"', false);

        // Layout del panel de administración.
        $this->actingAs($this->admin)
            ->get('/admin/home')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('manifest.webmanifest')
            ->assertSee('rel="apple-touch-icon"', false);
    }

    public function test_el_script_principal_registra_el_service_worker(): void
    {
        $this->assertStringContainsString(
            "navigator.serviceWorker.register('/sw.js'",
            (string) file_get_contents(resource_path('js/app.js')),
        );
    }
}
