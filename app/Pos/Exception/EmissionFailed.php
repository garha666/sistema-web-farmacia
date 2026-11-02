<?php

declare(strict_types=1);

namespace App\Pos\Exception;

use RuntimeException;

/**
 * Fallo esperado de la emisión simulada (RF-24).
 *
 * Es el único fallo que CheckoutService convierte en documento pendiente. Un
 * error de base de datos o de integridad no se captura como emisión fallida:
 * debe permanecer visible.
 */
final class EmissionFailed extends RuntimeException
{
}
