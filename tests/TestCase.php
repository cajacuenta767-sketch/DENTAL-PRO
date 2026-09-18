<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas no deben depender de haber corrido «npm run build»:
        // si no hay manifiesto de Vite, se omiten las etiquetas de assets.
        // Con el manifiesto presente sí se renderizan, para no perder de
        // vista un build roto.
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->withoutVite();
        }
    }
}
