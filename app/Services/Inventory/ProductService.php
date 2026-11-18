<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Product;
use App\Pos\Policy\BarcodePolicy;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Catálogo de productos (RF-68 a RF-72).
 *
 * La búsqueda y la persistencia viven aquí; el controlador sólo orquesta. El
 * código de barras se valida como EAN-13 y se comprueba su unicidad antes de
 * guardar, y la baja de catálogo desactiva en vez de borrar.
 */
final class ProductService
{
    public function __construct(
        private readonly BarcodePolicy $barcodes,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function search(string $term = ''): LengthAwarePaginator
    {
        $term = trim($term);

        return Product::query()
            ->when($term !== '', function (Builder $query) use ($term): void {
                $like = '%'.mb_strtolower($term).'%';

                $query->where(function (Builder $builder) use ($like): void {
                    $builder
                        ->whereRaw('lower(name) like ?', [$like])
                        ->orWhereRaw('lower(active_ingredient) like ?', [$like])
                        ->orWhereRaw('lower(barcode) like ?', [$like]);
                });
            })
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product
    {
        return Product::query()->create([
            'name' => $data['name'],
            'active_ingredient' => $data['active_ingredient'] ?? null,
            'barcode' => $this->barcode($data['barcode'] ?? null),
            'price' => $data['price'],
            'sale_category' => $data['sale_category'],
            'stock' => $data['stock'],
            'min_stock' => $data['min_stock'],
            'sellable' => true,
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update([
            'name' => $data['name'],
            'active_ingredient' => $data['active_ingredient'] ?? null,
            'barcode' => $this->barcode($data['barcode'] ?? null, $product),
            'price' => $data['price'],
            'sale_category' => $data['sale_category'],
            'min_stock' => $data['min_stock'],
            'sellable' => (bool) ($data['sellable'] ?? $product->sellable),
            'active' => (bool) ($data['active'] ?? $product->active),
        ]);

        return $product;
    }

    /**
     * Normaliza y valida el código: vacío es nulo, y un código repetido o con
     * verificador incorrecto se rechaza nombrando el motivo (RF-71).
     */
    private function barcode(?string $raw, ?Product $current = null): ?string
    {
        $barcode = trim((string) $raw);
        $barcode = $barcode === '' ? null : $barcode;

        if ($barcode === null) {
            return null;
        }

        if (! $this->barcodes->isValid($barcode)) {
            throw new DomainException(__('pos.inventory.products.error.barcode_invalid'));
        }

        $duplicate = Product::query()
            ->where('barcode', $barcode)
            ->when($current !== null, fn (Builder $query): Builder => $query->whereKeyNot($current->getKey()))
            ->exists();

        if ($duplicate) {
            throw new DomainException(__('pos.inventory.products.error.barcode_duplicate'));
        }

        return $barcode;
    }
}
