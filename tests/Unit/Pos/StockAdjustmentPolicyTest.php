<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Enum\StockMovementType;
use App\Pos\Policy\StockAdjustmentPolicy;
use App\Pos\Value\StockAdjustment;
use PHPUnit\Framework\TestCase;

/**
 * T17 - RF-73, RF-74: cantidad entera positiva, motivo obligatorio y salida que
 * no deje la existencia en negativo; la existencia resultante se calcula con el
 * signo del tipo.
 */
final class StockAdjustmentPolicyTest extends TestCase
{
    private StockAdjustmentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new StockAdjustmentPolicy;
    }

    public function test_an_inbound_movement_increases_the_stock(): void
    {
        $result = $this->policy->evaluate(10, 5, StockMovementType::in, 'Compra a proveedor');

        $this->assertTrue($result->accepted());
        $this->assertSame(15, $result->resultingStock());
        $this->assertNull($result->rejection());
    }

    public function test_an_outbound_movement_decreases_the_stock(): void
    {
        $result = $this->policy->evaluate(10, 4, StockMovementType::out, 'Merma por vencimiento');

        $this->assertTrue($result->accepted());
        $this->assertSame(6, $result->resultingStock());
    }

    public function test_an_outbound_movement_equal_to_the_stock_leaves_zero(): void
    {
        $result = $this->policy->evaluate(7, 7, StockMovementType::out, 'Salida total autorizada');

        $this->assertTrue($result->accepted());
        $this->assertSame(0, $result->resultingStock());
    }

    public function test_a_non_positive_quantity_is_rejected(): void
    {
        $zero = $this->policy->evaluate(10, 0, StockMovementType::in, 'Compra');
        $negative = $this->policy->evaluate(10, -3, StockMovementType::out, 'Merma');

        $this->assertFalse($zero->accepted());
        $this->assertSame(StockAdjustment::INVALID_QUANTITY, $zero->rejection());
        $this->assertFalse($negative->accepted());
        $this->assertSame(StockAdjustment::INVALID_QUANTITY, $negative->rejection());
    }

    public function test_a_missing_reason_is_rejected(): void
    {
        $result = $this->policy->evaluate(10, 1, StockMovementType::in, '   ');

        $this->assertFalse($result->accepted());
        $this->assertSame(StockAdjustment::REASON_REQUIRED, $result->rejection());
    }

    public function test_an_outbound_movement_beyond_the_stock_is_rejected_with_the_available_stock(): void
    {
        $result = $this->policy->evaluate(3, 5, StockMovementType::out, 'Merma');

        $this->assertFalse($result->accepted());
        $this->assertSame(StockAdjustment::INSUFFICIENT_STOCK, $result->rejection());
        $this->assertSame(3, $result->available());
    }

    public function test_a_rejection_keeps_the_current_stock_as_result(): void
    {
        $result = $this->policy->evaluate(3, 5, StockMovementType::out, 'Merma');

        $this->assertSame(3, $result->resultingStock());
    }
}
