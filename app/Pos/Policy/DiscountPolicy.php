<?php

declare(strict_types=1);

namespace App\Pos\Policy;

use App\Pos\Enum\DiscountOrigin;
use App\Pos\Money;
use App\Pos\Value\ConventionSnapshot;
use App\Pos\Value\Discount;
use App\Pos\Value\DiscountDecision;
use DateTimeImmutable;

/**
 * Decide si un descuento se aplica, requiere supervisor o se rechaza.
 *
 * RF-13: descuento manual o por convenio.
 * RF-14: tope general (10% por defecto) sin autorización.
 * RF-15/RF-40: el convenio aplica su propio porcentaje, definido por el administrador.
 * RF-16: sobre el tope exige credenciales de supervisor.
 * RF-17: todo descuento manual exige motivo.
 * RF-18: ningún descuento deja el total en cero o negativo.
 */
final class DiscountPolicy
{
    public function evaluate(
        int $subtotal,
        ?Discount $discount,
        ?ConventionSnapshot $convention,
        int $generalCapPercent,
        DateTimeImmutable $today,
    ): DiscountDecision {
        if ($subtotal <= 0) {
            return DiscountDecision::rejected('total_not_positive');
        }

        if ($convention !== null) {
            return $this->evaluateConvention($subtotal, $convention, $today);
        }

        if ($discount === null) {
            return DiscountDecision::rejected('reason_required');
        }

        if (trim($discount->reason) === '') {
            return DiscountDecision::rejected('reason_required');
        }

        $amount = $discount->amountFor(Money::fromPesos($subtotal));

        if ($subtotal - $amount->pesos() <= 0) {
            return DiscountDecision::rejected('total_not_positive');
        }

        $equivalentPercent = ($amount->pesos() * 100) / $subtotal;

        if ($equivalentPercent > $generalCapPercent) {
            return DiscountDecision::forSupervisor($amount, $discount);
        }

        return DiscountDecision::applied($amount, $discount);
    }

    private function evaluateConvention(
        int $subtotal,
        ConventionSnapshot $convention,
        DateTimeImmutable $today,
    ): DiscountDecision {
        if (! $convention->isApplicableOn($today)) {
            return DiscountDecision::rejected('convention_not_applicable');
        }

        $discount = Discount::percent(
            $convention->percent,
            "Convenio {$convention->name}",
            DiscountOrigin::convention,
        );

        $amount = $discount->amountFor(Money::fromPesos($subtotal));

        if ($subtotal - $amount->pesos() <= 0) {
            return DiscountDecision::rejected('total_not_positive');
        }

        // El porcentaje del convenio lo fija el administrador: no exige supervisor.
        return DiscountDecision::applied($amount, $discount);
    }
}
