<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de forma de un ajuste de stock (RF-73).
 *
 * Sólo comprueba la forma: la regla de la salida que no deje existencia
 * negativa vive en `StockAdjustmentPolicy` y se resuelve dentro de la
 * transacción del servicio.
 */
final class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => __('pos.panel.bodeguero.validation.product_required'),
            'product_id.exists' => __('pos.panel.bodeguero.validation.product_missing'),
            'type.required' => __('pos.panel.bodeguero.validation.type_required'),
            'type.in' => __('pos.panel.bodeguero.validation.type_invalid'),
            'quantity.required' => __('pos.panel.bodeguero.validation.quantity_required'),
            'quantity.integer' => __('pos.panel.bodeguero.validation.quantity_integer'),
            'quantity.min' => __('pos.panel.bodeguero.validation.quantity_min'),
            'reason.required' => __('pos.panel.bodeguero.validation.reason_required'),
            'reason.max' => __('pos.panel.bodeguero.validation.reason_max'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => __('pos.panel.bodeguero.form_product'),
            'type' => __('pos.panel.bodeguero.form_type'),
            'quantity' => __('pos.panel.bodeguero.form_quantity'),
            'reason' => __('pos.panel.bodeguero.form_reason'),
        ];
    }
}
