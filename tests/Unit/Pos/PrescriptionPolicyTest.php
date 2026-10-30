<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Enum\SaleCategory;
use App\Pos\Money;
use App\Pos\Policy\PrescriptionPolicy;
use App\Pos\Value\Cart;
use App\Pos\Value\PrescriptionInput;
use App\Pos\Value\ProductSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * T4 - RF-7 (categorías), RF-8 (datos exigidos), RF-9 (vigencia 30 días),
 * RF-10 (registro de controlados), RF-11 (receta reutilizada).
 *
 * La fecha entra por parámetro: la política no consulta el reloj.
 */
final class PrescriptionPolicyTest extends TestCase
{
    private PrescriptionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PrescriptionPolicy();
    }

    public function test_free_sale_needs_no_prescription(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::free,
            input: null,
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->requiresSupervisor());
        $this->assertSame([], $result->errors());
    }

    public function test_prescription_sale_without_input_is_invalid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: null,
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isInvalid());
        $this->assertArrayHasKey('number', $result->errors());
        $this->assertArrayHasKey('prescriber_name', $result->errors());
        $this->assertArrayHasKey('prescriber_rut', $result->errors());
        $this->assertArrayHasKey('issued_at', $result->errors());
    }

    public function test_controlled_sale_without_input_is_invalid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::controlled,
            input: null,
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isInvalid());
    }

    public function test_complete_prescription_is_valid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(),
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->errors());
    }

    public function test_prescription_expired_yesterday_is_invalid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(issuedAt: $this->today()->modify('-31 days')),
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isInvalid());
        $this->assertSame('expired', $result->errors()['issued_at']);
    }

    public function test_prescription_valid_on_its_last_day(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(issuedAt: $this->today()->modify('-30 days')),
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isValid());
    }

    public function test_prescription_issued_in_the_future_is_invalid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(issuedAt: $this->today()->modify('+1 day')),
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isInvalid());
        $this->assertSame('issued_in_future', $result->errors()['issued_at']);
    }

    public function test_blank_prescription_number_is_invalid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(number: '   '),
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isInvalid());
        $this->assertSame('required', $result->errors()['number']);
    }

    public function test_missing_prescriber_name_is_invalid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(prescriberName: ''),
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isInvalid());
        $this->assertSame('required', $result->errors()['prescriber_name']);
    }

    public function test_missing_prescriber_rut_is_invalid(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(prescriberRut: ''),
            validityDays: 30,
            alreadyUsed: false,
            today: $this->today(),
        );

        $this->assertTrue($result->isInvalid());
        $this->assertSame('required', $result->errors()['prescriber_rut']);
    }

    public function test_reused_prescription_requires_supervisor(): void
    {
        $result = $this->policy->evaluate(
            category: SaleCategory::prescription,
            input: $this->input(),
            validityDays: 30,
            alreadyUsed: true,
            today: $this->today(),
        );

        $this->assertTrue($result->requiresSupervisor());
        $this->assertFalse($result->isInvalid());
        $this->assertSame('prescription_already_used', $result->errors()['number']);
    }

    public function test_controlled_sale_requires_controlled_register(): void
    {
        $this->assertTrue($this->policy->requiresControlledRegister(SaleCategory::controlled));
        $this->assertFalse($this->policy->requiresControlledRegister(SaleCategory::prescription));
        $this->assertFalse($this->policy->requiresControlledRegister(SaleCategory::free));
    }

    public function test_cart_knows_when_it_has_prescription_items(): void
    {
        $freeCart = Cart::empty()->add($this->product(1, SaleCategory::free));
        $controlledCart = Cart::empty()->add($this->product(2, SaleCategory::controlled));

        $this->assertFalse($this->policy->cartRequiresPrescription($freeCart));
        $this->assertTrue($this->policy->cartRequiresPrescription($controlledCart));
    }

    public function test_cart_with_mixed_items_uses_the_strictest_category(): void
    {
        $cart = Cart::empty()
            ->add($this->product(1, SaleCategory::free))
            ->add($this->product(2, SaleCategory::prescription))
            ->add($this->product(3, SaleCategory::controlled));

        $this->assertSame(SaleCategory::controlled, $this->policy->effectiveCategory($cart));
    }

    public function test_effective_category_of_free_cart_is_free(): void
    {
        $cart = Cart::empty()->add($this->product(1, SaleCategory::free));

        $this->assertSame(SaleCategory::free, $this->policy->effectiveCategory($cart));
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-30 12:00:00');
    }

    private function input(
        string $number = 'RX-12345',
        string $prescriberName = 'Dra. Ana Pérez',
        string $prescriberRut = '12.345.678-9',
        ?\DateTimeImmutable $issuedAt = null,
    ): PrescriptionInput {
        return new PrescriptionInput(
            number: $number,
            prescriberName: $prescriberName,
            prescriberRut: $prescriberRut,
            issuedAt: $issuedAt ?? new \DateTimeImmutable('2026-10-20'),
        );
    }

    private function product(int $id, SaleCategory $category): ProductSnapshot
    {
        return new ProductSnapshot(
            id: $id,
            name: "Product {$id}",
            unitPrice: Money::fromPesos(1000),
            category: $category,
            availableStock: 10,
        );
    }
}
