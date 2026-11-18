<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Pos\Enum\SaleCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación de forma del alta de producto (RF-69).
 *
 * El EAN-13 y la unicidad del código se delegan en `ProductService`; aquí sólo
 * se comprueba la forma y que el precio sea positivo.
 */
final class StoreProductRequest extends FormRequest
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
            'stock' => ['required', 'integer', 'min:0'],
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
            'stock.required' => __('pos.inventory.products.validation.stock_required'),
            'stock.integer' => __('pos.inventory.products.validation.stock_integer'),
            'stock.min' => __('pos.inventory.products.validation.stock_min'),
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
            'stock' => __('pos.inventory.products.form_stock'),
        ];
    }
}
