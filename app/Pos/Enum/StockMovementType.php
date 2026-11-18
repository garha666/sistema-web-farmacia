<?php

declare(strict_types=1);

namespace App\Pos\Enum;

/**
 * Tipo de movimiento de stock (RF-65, RF-73, RF-74).
 *
 * `sale` y `void` son los valores históricos del POS; `in` y `out` son los
 * ajustes manuales del inventario. El signo se aplica a la cantidad y la
 * etiqueta se resuelve en la capa de presentación.
 */
enum StockMovementType: string
{
    case sale = 'sale';
    case void = 'void';
    case in = 'in';
    case out = 'out';

    public function sign(): int
    {
        return match ($this) {
            self::sale, self::out => -1,
            self::void, self::in => 1,
        };
    }

    public function label(): string
    {
        return __('pos.inventory.movement_type.'.$this->value);
    }
}
