<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Pos\Calculator\PriceCalculator;
use App\Pos\Value\Cart;
use App\Services\Pos\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Pantalla de caja del POS (RF-1 a RF-6).
 *
 * Sin lógica de negocio: delega en CartService y PriceCalculator.
 */
final class PosController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly PriceCalculator $priceCalculator,
    ) {
    }

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        return view('pos.index', [
            'term' => $term,
            'results' => $this->search($term),
            'cart' => $this->cartService->load(),
            'totals' => $this->priceCalculator->totals(
                $this->cartService->load(),
                (int) config('pos.iva_rate'),
            ),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::query()->find($data['product_id']);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => 'El producto no existe.',
            ]);
        }

        if (! $product->sellable) {
            throw ValidationException::withMessages([
                'product_id' => 'El producto no está disponible para la venta.',
            ]);
        }

        if ($product->stock < 1) {
            throw ValidationException::withMessages([
                'product_id' => 'El producto no tiene stock disponible.',
            ]);
        }

        $cart = $this->cartService->load()->add($this->cartService->snapshot($product));
        $this->cartService->persist($cart);

        return redirect()->route('pos.index');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['quantity'] > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => "Sólo hay {$product->stock} unidades disponibles.",
            ]);
        }

        $cart = $this->cartService
            ->load()
            ->changeQuantity($product->id, (int) $data['quantity']);

        $this->cartService->persist($cart);

        return redirect()->route('pos.index');
    }

    public function remove(Product $product): RedirectResponse
    {
        $cart = $this->cartService->load()->remove($product->id);
        $this->cartService->persist($cart);

        return redirect()->route('pos.index');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    private function search(string $term)
    {
        $query = Product::query()->where('sellable', true);

        if ($term !== '') {
            $like = '%'.mb_strtolower($term).'%';

            $query->where(function ($builder) use ($like): void {
                $builder
                    ->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(active_ingredient) like ?', [$like])
                    ->orWhereRaw('lower(barcode) like ?', [$like]);
            });
        }

        return $query->orderBy('name')->limit(20)->get();
    }
}
