<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * T29 — P6 / RNF-4: los textos de interfaz viven en `lang/es/pos.php`.
 *
 * S8 del informe de seguridad: quedaban mensajes en español escritos dentro
 * de `app/`, fuera del archivo de idioma. Se traducen todos y se deja un
 * guardián para que no vuelvan.
 */
final class HardcodedTextTest extends TestCase
{
    /**
     * Mensajes que estaban escritos dentro de `app/` (S8).
     *
     * @var array<int, string>
     */
    private const HARDCODED = [
        'No tienes permiso para realizar esta acción.',
        'La venta ya está anulada.',
        'El motivo de la anulación es obligatorio.',
        'El carrito está vacío.',
        'La factura exige los datos tributarios del cliente.',
        'El convenio seleccionado no existe.',
        'El convenio seleccionado está desactivado.',
        'El convenio seleccionado está fuera de vigencia.',
        'Sólo se pueden reintentar documentos pendientes o rechazados.',
        'No quedan folios disponibles para este tipo de documento.',
    ];

    /**
     * Claves nuevas que deben existir en `lang/es/pos.php`.
     *
     * @var array<int, string>
     */
    private const KEYS = [
        'pos.errors.forbidden',
        'pos.errors.sale_already_voided',
        'pos.errors.void_reason_required',
        'pos.errors.cart_empty',
        'pos.errors.invoice_customer_required',
        'pos.errors.convention_missing',
        'pos.errors.convention_inactive',
        'pos.errors.convention_expired',
        'pos.errors.document_not_retryable',
        'pos.errors.folios_exhausted',
    ];

    public function test_application_code_has_no_hardcoded_ui_strings(): void
    {
        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach (self::HARDCODED as $text) {
                $this->assertStringNotContainsString(
                    $text,
                    $source,
                    $file->getRelativePathname()." todavía contiene el texto hardcodeado «{$text}».",
                );
            }
        }
    }

    public function test_the_messages_are_translated_in_the_language_file(): void
    {
        foreach (self::KEYS as $key) {
            $translated = __($key);

            $this->assertNotSame($key, $translated, "Falta la traducción de {$key}.");
            $this->assertNotSame('', trim($translated), "La traducción de {$key} está vacía.");
        }
    }
}
