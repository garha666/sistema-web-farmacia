<?php

declare(strict_types=1);

namespace App\Pos\Exception;

use DomainException;

/**
 * Producto descontinuado o marcado como no vendible (RF-2).
 */
final class ProductNotSellable extends DomainException
{
}
