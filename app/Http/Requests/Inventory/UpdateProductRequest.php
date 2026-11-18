<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Pos\Enum\SaleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de forma de la edición de producto (RF-70).
 *
 * La existencia no se edita aquí: se ajusta con movimientos auditados (T17).
 */
final class UpdateProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'active_ingredient' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:32'],
            'price' => ['required', 'integer', 'min:1'],
            'sale_category' => ['required', Rule::enum(SaleCategory::class)],
            'min_stock' => ['required', 'integer', 'min:0'],
            'sellable' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('pos.inventory.products.validation.name_required'),
            'price.required' => __('pos.inventory.products.validation.price_required'),
            'price.integer' => __('pos.inventory.products.validation.price_integer'),
            'price.min' => __('pos.inventory.products.validation.price_min'),
            'sale_category.required' => __('pos.inventory.products.validation.category_required'),
            'sale_category.enum' => __('pos.inventory.products.validation.category_invalid'),
            'min_stock.required' => __('pos.inventory.products.validation.min_stock_required'),
            'min_stock.integer' => __('pos.inventory.products.validation.min_stock_integer'),
            'min_stock.min' => __('pos.inventory.products.validation.min_stock_min'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('pos.inventory.products.form_name'),
            'active_ingredient' => __('pos.inventory.products.form_active_ingredient'),
            'barcode' => __('pos.inventory.products.form_barcode'),
            'price' => __('pos.inventory.products.form_price'),
            'sale_category' => __('pos.inventory.products.form_category'),
            'min_stock' => __('pos.inventory.products.form_min_stock'),
        ];
    }
}
