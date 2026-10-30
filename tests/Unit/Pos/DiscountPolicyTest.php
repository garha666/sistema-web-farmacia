<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Enum\DiscountStatus;
use App\Pos\Money;
use App\Pos\Policy\DiscountPolicy;
use App\Pos\Value\ConventionSnapshot;
use App\Pos\Value\Discount;
use PHPUnit\Framework\TestCase;

/**
 * T3 - RF-13 (aplicar descuento), RF-14 (tope 10%), RF-15 (convenio),
 * RF-16 (autorización sobre el tope), RF-17 (motivo),
 * RF-18 (total no negativo), RF-39 (catálogo), RF-40 (aplicación del convenio).
 *
 * Toda fecha entra por parámetro: la política no consulta el reloj.
 */
final class DiscountPolicyTest extends TestCase
{
    private DiscountPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DiscountPolicy();
    }

    public function test_discount_under_the_cap_is_applied_without_supervisor(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(5, 'Promoción de mostrador'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isApplied());
        $this->assertFalse($decision->requiresSupervisor());
        $this->assertSame(500, $decision->amount()->pesos());
    }

    public function test_discount_of_exactly_the_cap_is_applied(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(10, 'Tope exacto'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isApplied());
        $this->assertSame(1_000, $decision->amount()->pesos());
    }

    public function test_discount_above_the_cap_requires_supervisor(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(10.01, 'Sobre el tope'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->requiresSupervisor());
        $this->assertFalse($decision->isApplied());
    }

    public function test_amount_discount_above_the_cap_also_requires_supervisor(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::amount(Money::fromPesos(1_500), 'Monto sobre el tope'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->requiresSupervisor());
    }

    public function test_manual_discount_without_reason_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(5, '   '),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
        $this->assertSame('reason_required', $decision->reason());
    }

    public function test_discount_that_leaves_total_at_zero_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 1_000,
            discount: Discount::percent(100, 'Todo gratis'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
        $this->assertSame('total_not_positive', $decision->reason());
    }

    public function test_discount_that_leaves_total_negative_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 1_000,
            discount: Discount::amount(Money::fromPesos(5_000), 'Exceso'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
        $this->assertSame('total_not_positive', $decision->reason());
    }

    public function test_active_convention_applies_its_own_percentage_without_supervisor(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(0, 'sin uso'),
            convention: $this->convention(percent: 20),
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isApplied());
        $this->assertFalse($decision->requiresSupervisor());
        $this->assertSame(2_000, $decision->amount()->pesos());
    }

    public function test_inactive_convention_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(0, 'sin uso'),
            convention: $this->convention(percent: 20, active: false),
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
        $this->assertSame('convention_not_applicable', $decision->reason());
    }

    public function test_convention_expired_yesterday_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(0, 'sin uso'),
            convention: $this->convention(
                percent: 20,
                validUntil: $this->today()->modify('-1 day'),
            ),
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
    }

    public function test_convention_starting_tomorrow_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(0, 'sin uso'),
            convention: $this->convention(
                percent: 20,
                validFrom: $this->today()->modify('+1 day'),
            ),
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
    }

    public function test_convention_is_valid_on_its_last_day(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(0, 'sin uso'),
            convention: $this->convention(percent: 20, validUntil: $this->today()),
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isApplied());
    }

    public function test_convention_that_leaves_total_at_zero_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(0, 'sin uso'),
            convention: $this->convention(percent: 100),
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
        $this->assertSame('total_not_positive', $decision->reason());
    }

    public function test_convention_percentage_wins_over_any_manual_discount(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(50, 'Intento del cajero'),
            convention: $this->convention(percent: 20),
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isApplied());
        $this->assertSame(2_000, $decision->amount()->pesos());
    }

    public function test_zero_subtotal_is_rejected(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 0,
            discount: Discount::percent(5, 'Sin base'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertTrue($decision->isRejected());
        $this->assertSame('total_not_positive', $decision->reason());
    }

    public function test_status_enum_is_exposed(): void
    {
        $decision = $this->policy->evaluate(
            subtotal: 10_000,
            discount: Discount::percent(5, 'Normal'),
            convention: null,
            generalCapPercent: 10,
            today: $this->today(),
        );

        $this->assertSame(DiscountStatus::applied, $decision->status());
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-30 12:00:00');
    }

    private function convention(
        float $percent = 20,
        bool $active = true,
        ?\DateTimeImmutable $validFrom = null,
        ?\DateTimeImmutable $validUntil = null,
    ): ConventionSnapshot {
        return new ConventionSnapshot(
            id: 1,
            name: 'Isapre Banmédica',
            percent: $percent,
            active: $active,
            validFrom: $validFrom ?? new \DateTimeImmutable('2026-01-01'),
            validUntil: $validUntil ?? new \DateTimeImmutable('2026-12-31'),
        );
    }
}
