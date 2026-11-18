<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Services\Inventory\ProductService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de productos (RF-68 a RF-72).
 *
 * El Administrador consulta, crea y edita; el Bodeguero sólo consulta. La
 * validación de forma vive en los FormRequest y la de negocio en el servicio.
 */
final class ProductController extends Controller
{
    public function index(Request $request, ProductService $products): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('inventory.products.index', [
            'navGroup' => $request->user()->role === 'bodeguero' ? 'bodeguero' : 'admin',
            'products' => $products->search($term),
            'term' => $term,
        ]);
    }

    public function create(): View
    {
        return view('inventory.products.create', ['navGroup' => 'admin']);
    }

    public function store(StoreProductRequest $request, ProductService $products): RedirectResponse
    {
        try {
            $products->create($request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['barcode' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.products.index')
            ->with('success', __('pos.inventory.products.created'));
    }

    public function edit(Product $product): View
    {
        return view('inventory.products.edit', [
            'navGroup' => 'admin',
            'product' => $product,
        ]);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        ProductService $products,
    ): RedirectResponse {
        try {
            $products->update($product, $request->validated());
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['barcode' => $exception->getMessage()]);
        }

        return redirect()
            ->route('inventory.products.index')
            ->with('success', __('pos.inventory.products.updated'));
    }
}
