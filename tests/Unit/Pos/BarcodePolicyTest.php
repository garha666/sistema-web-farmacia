<?php

declare(strict_types=1);

namespace Tests\Unit\Pos;

use App\Pos\Policy\BarcodePolicy;
use PHPUnit\Framework\TestCase;

/**
 * T16 - RF-71: EAN-13 con trece dígitos y dígito verificador correcto; un
 * producto puede quedar sin código de barras.
 */
final class BarcodePolicyTest extends TestCase
{
    private BarcodePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new BarcodePolicy;
    }

    public function test_a_valid_ean13_code_is_accepted(): void
    {
        $this->assertTrue($this->policy->isValid('4006381333931'));
        $this->assertTrue($this->policy->isValid('7801234567894'));
    }

    public function test_a_code_with_a_wrong_check_digit_is_rejected(): void
    {
        $this->assertFalse($this->policy->isValid('4006381333932'));
        $this->assertFalse($this->policy->isValid('7801234567890'));
    }

    public function test_a_product_may_have_no_barcode(): void
    {
        $this->assertTrue($this->policy->isValid(null));
        $this->assertTrue($this->policy->isValid(''));
        $this->assertTrue($this->policy->isValid('   '));
    }

    public function test_codes_with_another_length_or_non_digits_are_rejected(): void
    {
        $this->assertFalse($this->policy->isValid('400638133393'));
        $this->assertFalse($this->policy->isValid('78012345678901'));
        $this->assertFalse($this->policy->isValid('400638133393A'));
        $this->assertFalse($this->policy->isValid('400638133393 '));
    }

    public function test_the_check_digit_is_computed_from_the_first_twelve_digits(): void
    {
        $this->assertSame(1, $this->policy->checkDigit('400638133393'));
        $this->assertSame(4, $this->policy->checkDigit('780123456789'));
    }
}
