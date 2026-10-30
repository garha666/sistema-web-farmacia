<?php

declare(strict_types=1);

namespace App\Pos\Exception;

use DomainException;

/**
 * Cantidad inválida en el carrito (RF-3).
 */
final class InvalidQuantity extends DomainException
{
}
