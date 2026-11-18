<?php

declare(strict_types=1);

namespace App\Pos\Exception;

use DomainException;

/**
 * El cajero ya tiene una caja abierta (RF-58, caso límite 8).
 */
final class CashRegisterAlreadyOpen extends DomainException {}
