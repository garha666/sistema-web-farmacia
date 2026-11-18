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
            'declared_amount' => ['required', 'integer', 'min:0'],
        ];
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
        ];
    }
}
