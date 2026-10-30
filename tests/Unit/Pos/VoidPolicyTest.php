<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Enum\SaleStatus;
use App\Pos\Policy\VoidPolicy;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * T9 - RF-32 (motivo obligatorio), RF-35 (anulación única).
 */
final class VoidPolicyTest extends TestCase
{
    private VoidPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new VoidPolicy();
    }

    public function test_a_confirmed_sale_with_a_reason_can_be_voided(): void
    {
        $this->policy->assertCanVoid(SaleStatus::confirmed, 'Cliente desiste de la compra');

        $this->expectNotToPerformAssertions();
    }

    public function test_an_already_voided_sale_cannot_be_voided_again(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La venta ya está anulada.');

        $this->policy->assertCanVoid(SaleStatus::voided, 'Cliente desiste de la compra');
    }

    public function test_the_reason_cannot_be_empty(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El motivo de la anulación es obligatorio.');

        $this->policy->assertCanVoid(SaleStatus::confirmed, '');
    }

    public function test_the_reason_cannot_be_only_whitespace(): void
    {
        $this->expectException(DomainException::class);

        $this->policy->assertCanVoid(SaleStatus::confirmed, "   \t\n");
    }
}
