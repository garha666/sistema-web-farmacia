<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Raíz del sitio (F0.2): la caja es la pantalla de entrada del sistema, así que
 * `GET /` no sirve una página propia sino que redirige al POS.
 */
final class ExampleTest extends TestCase
{
    public function test_root_redirects_to_the_cash_screen(): void
    {
        $this->get('/')->assertRedirect(route('pos.index'));

        $this->assertSame('/pos', route('pos.index', absolute: false));
    }
}
