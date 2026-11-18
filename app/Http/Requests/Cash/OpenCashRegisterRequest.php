<?php

declare(strict_types=1);

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación de forma de la apertura de caja (RF-58).
 *
 * Sólo comprueba el tipo y el rango: la regla de una sola caja abierta vive en
 * el servicio y en el índice único parcial.
 */
final class OpenCashRegisterRequest extends FormRequest
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
            'opening_amount' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_amount.required' => __('pos.cash_register.validation.opening_required'),
            'opening_amount.integer' => __('pos.cash_register.validation.opening_integer'),
            'opening_amount.min' => __('pos.cash_register.validation.opening_min'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'opening_amount' => __('pos.cash_register.opening_amount'),
        ];
    }
}
