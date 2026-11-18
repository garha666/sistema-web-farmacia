<?php

declare(strict_types=1);

namespace App\Pos\Enum;

/**
 * Sentido de la diferencia de caja (RF-59).
 *
 * La diferencia se guarda como `declarado − esperado`: positiva es sobrante,
 * negativa es faltante y cero es «cuadra». La etiqueta se resuelve en la capa
 * de presentación.
 */
enum CashDifference: string
{
    case balanced = 'balanced';
    case over = 'over';
    case short = 'short';

    public function label(): string
    {
        return __('pos.cash_register.kind.'.$this->value);
    }
}
