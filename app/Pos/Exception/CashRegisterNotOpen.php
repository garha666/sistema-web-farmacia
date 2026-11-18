<?php

declare(strict_types=1);

namespace App\Pos\Exception;

use DomainException;

/**
 * No hay una caja abierta que cerrar (RF-59, caso límite 4).
 */
final class CashRegisterNotOpen extends DomainException {}
