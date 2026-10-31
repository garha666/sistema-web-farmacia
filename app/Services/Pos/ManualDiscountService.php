<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Pos\Enum\DiscountType;
use App\Pos\Money;
use App\Pos\Value\Discount;

/**
 * Descuento manual de la venta en curso, persistido en sesión (RF-13, RF-17).
 *
 * Igual que el carrito, sólo guarda los datos que eligió el cajero: el tope y
 * la autorización se vuelven a validar contra la política antes de cobrar.
 */
final class ManualDiscountService
{
    private const SESSION_KEY = 'pos.manual_discount';

    public function load(): ?Discount
    {
        $stored = session()->get(self::SESSION_KEY);

        if (! is_array($stored)
            || ! isset($stored['type'], $stored['value'], $stored['reason'])) {
            return null;
        }

        $type = DiscountType::tryFrom((string) $stored['type']);

        if ($type === null) {
            return null;
        }

        $reason = (string) $stored['reason'];

        return $type === DiscountType::percent
            ? Discount::percent((float) $stored['value'], $reason)
            : Discount::amount(Money::fromPesos((int) $stored['value']), $reason);
    }

    /**
     * Supervisor que autorizó el descuento sobre el tope (RF-16).
     */
    public function authorizedBy(): ?int
    {
        $stored = session()->get(self::SESSION_KEY);

        if (! is_array($stored) || ! isset($stored['authorized_by'])) {
            return null;
        }

        return (int) $stored['authorized_by'];
    }

    public function store(DiscountType $type, float $value, string $reason, ?int $authorizedBy): void
    {
        session()->put(self::SESSION_KEY, [
            'type' => $type->value,
            'value' => $value,
            'reason' => $reason,
            'authorized_by' => $authorizedBy,
        ]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
