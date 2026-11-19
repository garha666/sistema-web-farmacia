<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * T26 — RNF-27: cabeceras de seguridad, proxies de confianza y HTTPS.
 *
 * S4 y S5 del informe de seguridad: hoy las respuestas no llevan ninguna
 * cabecera defensiva ni política de contenido, así que un sitio externo puede
 * enmarcar la aplicación (clickjacking) y cualquier guion inyectado se ejecuta
 * sin restricción.
 */
final class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_the_defensive_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_the_content_security_policy_allows_only_the_site_and_a_nonce(): void
    {
        $response = $this->get(route('login'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]+'/", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode(';', $csp)[1] ?? '');
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
    }

    public function test_the_nonce_changes_on_every_request(): void
    {
        $first = $this->nonceOf($this->get(route('login')));
        $second = $this->nonceOf($this->get(route('login')));

        $this->assertNotSame('', $first);
        $this->assertNotSame($first, $second, 'El nonce debe ser distinto en cada petición.');
    }

    public function test_the_page_scripts_carry_the_nonce_of_the_header(): void
    {
        $cashier = User::factory()->create(['role' => 'tecnico']);
        $this->actingAs($cashier);
        $this->openCashRegister($cashier);

        $product = Product::factory()->create(['price' => 1990]);
        $this->post('/pos/cart', ['product_id' => $product->id]);

        $response = $this->get('/pos/checkout');
        $response->assertOk();

        $nonce = $this->nonceOf($response);
        $this->assertNotSame('', $nonce);

        $html = (string) $response->getContent();
        $this->assertStringContainsString('nonce="'.$nonce.'"', $html);
        // RNF-27: ningún manejador de eventos en línea.
        $this->assertDoesNotMatchRegularExpression('/\son(click|change|submit|input)\s*=/i', $html);
    }

    private function nonceOf(TestResponse $response): string
    {
        $csp = (string) $response->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([^']+)'/", $csp, $matches);

        return $matches[1] ?? '';
    }
}
