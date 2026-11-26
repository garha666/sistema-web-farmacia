<?php

declare(strict_types=1);

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de forma del cierre de caja (RF-59).
 *
 * El monto declarado es el efectivo contado por el cajero; puede diferir del
 * esperado, y esa diferencia se registra.
 */
final class CloseCashRegisterRequest extends FormRequest
{
    public const DENOMINATIONS = [20000, 10000, 5000, 2000, 1000, 500, 100, 50, 10];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'declared_amount' => ['required_without:denominations', 'prohibits:denominations', 'integer', 'min:0', 'max:2147483647'],
            'denominations' => ['array:'.implode(',', self::DENOMINATIONS)],
            'denominations.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function declaredAmount(): int
    {
        $counts = $this->validated('denominations');

        if ($counts === null) {
            return (int) $this->validated('declared_amount');
        }

        $total = 0;
        foreach ($counts as $denomination => $quantity) {
            $total += $denomination * (int) $quantity;
        }

        return $total;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'declared_amount.required' => __('pos.cash_register.validation.declared_required'),
            'declared_amount.integer' => __('pos.cash_register.validation.declared_integer'),
            'declared_amount.min' => __('pos.cash_register.validation.declared_min'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'declared_amount' => __('pos.cash_register.declared_amount'),
            'denominations' => __('pos.cash_register.denominations'),
            'denominations.*' => __('pos.cash_register.quantity'),
            'reason' => __('pos.cash_register.close_reason'),
        ];
    }
}
