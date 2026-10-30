<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Pos\Calculator\PriceCalculator;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Exception\InsufficientStock;
use App\Pos\Value\Cart;
use App\Services\Pos\CartService;
use App\Services\Pos\CheckoutService;
use App\Services\Pos\VoidSaleService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
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
        private readonly CheckoutService $checkoutService,
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
     * Pantalla de cobro (RF-20, RF-21).
     */
    public function checkout(): View|RedirectResponse
    {
        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            return redirect()
                ->route('pos.index')
                ->withErrors(['cart' => 'El carrito está vacío.']);
        }

        return view('pos.checkout', [
            'cart' => $cart,
            'totals' => $this->priceCalculator->totals($cart, (int) config('pos.iva_rate')),
            'methods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Confirma la venta (RF-22 a RF-26).
     */
    public function store(Request $request): RedirectResponse
    {
        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'El carrito está vacío.']);
        }

        $data = $request->validate([
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'received_amount' => ['nullable', 'integer', 'min:0'],
            'customer_rut' => ['nullable', 'string', 'max:20'],
        ]);

        $totals = $this->priceCalculator->totals($cart, (int) config('pos.iva_rate'));
        $method = PaymentMethod::from($data['payment_method']);
        $received = isset($data['received_amount']) ? (int) $data['received_amount'] : null;

        if ($method->requiresReceivedAmount()) {
            if ($received === null || $received < $totals->total()->pesos()) {
                throw ValidationException::withMessages([
                    'received_amount' => 'El monto recibido es menor que el total a pagar.',
                ]);
            }
        }

        try {
            $sale = $this->checkoutService->checkout(
                cart: $cart,
                method: $method,
                receivedAmount: $method->requiresReceivedAmount() ? $received : null,
                customerRut: $data['customer_rut'] ?? null,
                token: $this->cartService->token(),
                userId: $request->user()?->id,
            );
        } catch (InsufficientStock $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }

        $this->cartService->clear();

        return redirect()->route('pos.sales.receipt', $sale);
    }

    /**
     * Comprobante reimprimible (RF-25).
     */
    public function receipt(Sale $sale): View
    {
        $sale->loadMissing('lines', 'document', 'reversal');

        $voidReason = $sale->auditLogs()
            ->where('action', 'sale.void')
            ->latest()
            ->first()
            ?->reason;

        return view('pos.receipt', [
            'sale' => $sale,
            'document' => $sale->document,
            'reversal' => $sale->reversal,
            'voidReason' => $voidReason,
            'issuer' => (array) config('pos.issuer'),
        ]);
    }

    /**
     * Anula una venta confirmada con motivo obligatorio (RF-32 a RF-36).
     */
    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            app(VoidSaleService::class)->void(
                $sale,
                $data['reason'],
                $request->user()?->id,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return redirect()->route('pos.sales.receipt', $sale)
            ->with('voided', true);
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
