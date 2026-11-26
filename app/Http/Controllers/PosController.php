<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Calculator\PriceCalculator;
use App\Pos\Enum\DiscountType;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Exception\CashRegisterNotOpen;
use App\Pos\Exception\DiscountRejected;
use App\Pos\Exception\InsufficientStock;
use App\Pos\Exception\PrescriptionRejected;
use App\Pos\Money;
use App\Pos\Policy\BarcodeScanPolicy;
use App\Pos\Policy\DiscountPolicy;
use App\Pos\Policy\PrescriptionPolicy;
use App\Pos\Policy\ReceiptPolicy;
use App\Pos\Policy\StockPolicy;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\ChileanRut;
use App\Pos\Value\Discount;
use App\Pos\Value\InvoiceData;
use App\Pos\Value\PrescriptionInput;
use App\Pos\Value\ReceiptTotals;
use App\Services\Cash\CashRegisterService;
use App\Services\Inventory\LotStock;
use App\Services\Pos\CartService;
use App\Services\Pos\CheckoutService;
use App\Services\Pos\ConventionService;
use App\Services\Pos\ManualDiscountService;
use App\Services\Pos\PrescriptionReviewService;
use App\Services\Pos\VoidSaleService;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
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
    /**
     * RF-83: cinco intentos de autorización de supervisor por minuto (D-16),
     * la misma ventana que el inicio de sesión.
     */
    private const SUPERVISOR_MAX_ATTEMPTS = 5;

    private const SUPERVISOR_DECAY_SECONDS = 60;

    public function __construct(
        private readonly CartService $cartService,
        private readonly PriceCalculator $priceCalculator,
        private readonly CheckoutService $checkoutService,
        private readonly ConventionService $conventionService,
        private readonly PrescriptionPolicy $prescriptionPolicy,
        private readonly DiscountPolicy $discountPolicy,
        private readonly ManualDiscountService $manualDiscountService,
        private readonly StockPolicy $stockPolicy,
        private readonly BarcodeScanPolicy $barcodeScanPolicy,
        private readonly CashRegisterService $cashRegisters,
    ) {}

    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $scannedProductId = null;

        // B2 (RF-1, RF-2, RNF-3): el lector entrega el código completo seguido
        // de Enter. El acierto exacto agrega al carrito y vuelve a la caja sin
        // ?q; un código que no puede venderse avisa y tampoco deja el término.
        // La búsqueda al teclear (RF-86) manda scan=0 y nunca toca el carrito.
        if ($term !== '' && $request->boolean('scan', true)) {
            $scan = $this->barcodeScanPolicy->evaluate($term, $this->barcodeCandidates($term));

            if ($scan['product_id'] !== null) {
                $redirect = $this->addScannedProduct($scan['product_id']);

                if (! $request->expectsJson()) {
                    return $redirect;
                }

                $scannedProductId = $scan['product_id'];
                $term = '';
            } elseif ($scan['error'] !== null) {
                if ($request->expectsJson()) {
                    // RF-90: el motivo viaja en el 422 y el carrito no se toca.
                    throw ValidationException::withMessages([
                        'barcode' => __('pos.errors.'.$scan['error']),
                    ]);
                }

                return redirect()
                    ->route('pos.index')
                    ->withErrors(['barcode' => __('pos.errors.'.$scan['error'])]);
            }
        }

        if ($request->expectsJson()) {
            // RF-86: búsqueda asíncrona sin recargar la página.
            $results = $this->search($term);

            return response()->json(array_merge(
                $this->cartPayload($term, $results),
                [
                    'scanned_product_id' => $scannedProductId,
                    'results' => array_map(
                        fn (Product $product): array => [
                            'id' => $product->id,
                            'name' => $product->name,
                            'active_ingredient' => $product->active_ingredient,
                            'barcode' => $product->barcode,
                            'price' => $product->price,
                'stock' => (int) $product->pos_stock,
                            'active' => $product->active,
                        ],
                        $results->all(),
                    ),
                ],
            ));
        }

        $cart = $this->cartService->load();
        $manualDiscount = $this->manualDiscountService->load();

        if ($cart->isEmpty() && $manualDiscount !== null) {
            $this->manualDiscountService->clear();
            $manualDiscount = null;
        }

        return view('pos.index', [
            'term' => $term,
            'results' => $this->search($term),
            'cart' => $cart,
            'totals' => $this->priceCalculator->totals(
                $cart,
                (int) config('pos.iva_rate'),
                $manualDiscount,
            ),
            'manualDiscount' => $manualDiscount,
            'calculator' => $this->priceCalculator,
        ]);
    }

    /**
     * RF-85 / RF-86: payload JSON del carrito y los resultados de búsqueda.
     *
     * Mismo dato que recibe la vista Blade, serializado para el cliente
     * asíncrono. El servidor sigue siendo la fuente de verdad del carrito: el
     * cliente pinta el fragmento `cart` (y `results` si hubo búsqueda) tal cual
     * lo renderiza la vista, así que nada se calcula ni se duplica en JS.
     *
     * @param  Collection<int, Product>|null  $results  resultados de búsqueda; null en las operaciones del carrito
     * @return array<string, mixed>
     */
    private function cartPayload(string $term = '', ?Collection $results = null): array
    {
        $cart = $this->cartService->load();
        $manualDiscount = $this->manualDiscountService->load();

        if ($cart->isEmpty() && $manualDiscount !== null) {
            $this->manualDiscountService->clear();
            $manualDiscount = null;
        }

        $totals = $this->priceCalculator->totals(
            $cart,
            (int) config('pos.iva_rate'),
            $manualDiscount,
        );

        return [
            'cart' => [
                'isEmpty' => $cart->isEmpty(),
                'totalQuantity' => $cart->totalQuantity(),
                'lines' => array_map(
                    fn (CartLine $line): array => [
                        'product_id' => $line->productId(),
                        'name' => $line->name(),
                        'quantity' => $line->quantity(),
                        'unit_price' => $line->unitPrice()->pesos(),
                        'line_subtotal' => $this->priceCalculator->lineSubtotal($line)->pesos(),
                        'line_discount' => $this->priceCalculator->lineDiscount($line)->pesos(),
                    ],
                    $cart->lines(),
                ),
            ],
            'totals' => [
                'subtotal' => $totals->subtotal()->pesos(),
                'subtotal_after_line_discounts' => $totals->subtotalAfterLineDiscounts()->pesos(),
                'line_discounts' => $totals->lineDiscounts()->pesos(),
                'global_discount' => $totals->globalDiscount()->pesos(),
                'net' => $totals->net()->pesos(),
                'iva' => $totals->iva()->pesos(),
                'total' => $totals->total()->pesos(),
            ],
            'manual_discount' => $manualDiscount !== null,
            'fragments' => $this->fragments($term, $results, $cart, $totals, $manualDiscount),
        ];
    }

    /**
     * Fragmentos Blade `cart` y `results` de `pos.index` (mismo HTML que la
     * página completa, RF-91). Sólo se devuelve `results` cuando hubo búsqueda.
     *
     * @param  Collection<int, Product>|null  $results
     * @return array<string, string>
     */
    private function fragments(
        string $term,
        ?Collection $results,
        Cart $cart,
        ReceiptTotals $totals,
        ?Discount $manualDiscount,
    ): array {
        // Se renderiza una sola vez y se leen ambos fragmentos del mismo render.
        $fragments = view('pos.index', [
            'term' => $term,
            'results' => $results ?? new Collection,
            'cart' => $cart,
            'totals' => $totals,
            'manualDiscount' => $manualDiscount,
            'calculator' => $this->priceCalculator,
        ])->render(fn (): array => view()->getFragments());

        return $results === null
            ? ['cart' => $fragments['cart']]
            : ['cart' => $fragments['cart'], 'results' => $fragments['results']];
    }

    public function add(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::query()->withPosStock(LotStock::today())->find($data['product_id']);

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => __('pos.errors.product_missing'),
            ]);
        }

        if (! $product->sellable || ! $product->active) {
            throw ValidationException::withMessages([
                'product_id' => __('pos.errors.product_not_sellable'),
            ]);
        }

        if ($product->pos_stock < 1) {
            throw ValidationException::withMessages([
                'product_id' => __('pos.errors.product_out_of_stock'),
            ]);
        }

        $cart = $this->cartService->load()->add($this->cartService->snapshot($product));
        $this->cartService->persist($cart);

        if ($request->expectsJson()) {
            // RF-85: agregar al carrito sin recargar la página.
            return response()->json($this->cartPayload());
        }

        return redirect()->route('pos.index');
    }

    public function update(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $product = Product::query()->withPosStock(LotStock::today())->findOrFail($product->id);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['quantity'] > $product->pos_stock) {
            throw ValidationException::withMessages([
                'quantity' => __('pos.errors.quantity_available', ['stock' => $product->pos_stock]),
            ]);
        }

        $cart = $this->cartService
            ->load()
            ->changeQuantity($product->id, (int) $data['quantity']);

        $this->cartService->persist($cart);

        if ($request->expectsJson()) {
            // RF-85: cambiar cantidad sin recargar la página.
            return response()->json($this->cartPayload());
        }

        return redirect()->route('pos.index');
    }

    public function remove(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $cart = $this->cartService->load()->remove($product->id);
        $this->cartService->persist($cart);

        if ($request->expectsJson()) {
            // RF-85: quitar una línea sin recargar la página.
            return response()->json($this->cartPayload());
        }

        return redirect()->route('pos.index');
    }

    /**
     * Aplica el descuento manual de la venta en curso (RF-13, RF-16, RF-17).
     *
     * Bajo el tope se aplica directo. Sobre el tope no se guarda hasta que
     * vengan las credenciales de un supervisor; la vista abre el modal.
     */
    public function applyDiscount(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'discount_type' => ['required', new Enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'discount_reason' => ['required', 'string', 'min:3', 'max:255'],
            'supervisor_email' => ['nullable', 'email'],
            'supervisor_password' => ['nullable', 'string'],
        ], [
            'discount_reason.required' => __('pos.validation.discount_reason_required'),
        ]);

        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages([
                'discount_value' => __('pos.errors.cart_empty'),
            ]);
        }

        $type = DiscountType::from($data['discount_type']);
        $reason = trim((string) $data['discount_reason']);

        $discount = $type === DiscountType::percent
            ? Discount::percent((float) $data['discount_value'], $reason)
            : Discount::amount(
                Money::fromPesos($this->priceCalculator->roundToPeso((float) $data['discount_value'])),
                $reason,
            );

        $decision = $this->discountPolicy->evaluate(
            subtotal: $this->priceCalculator->subtotal($cart)->pesos(),
            discount: $discount,
            convention: null,
            generalCapPercent: (int) config('pos.discount.general_cap_percent'),
            today: new DateTimeImmutable('now', new DateTimeZone((string) config('pos.timezone'))),
            lineDiscountsPesos: $this->priceCalculator->lineDiscounts($cart)->pesos(),
        );

        if ($decision->isRejected()) {
            $field = $decision->reason() === 'reason_required' ? 'discount_reason' : 'discount_value';

            throw ValidationException::withMessages([
                $field => $field === 'discount_reason'
                    ? __('pos.errors.discount_manual_reason')
                    : __('pos.errors.discount_zero_total'),
            ]);
        }

        $authorizedBy = $this->supervisorId($data, errorKey: 'discount_supervisor');

        if ($authorizedBy === null) {
            throw ValidationException::withMessages([
                'discount_supervisor' => __('pos.authorization.required'),
            ]);
        }

        $this->manualDiscountService->store(
            $type,
            (float) $data['discount_value'],
            $reason,
            $authorizedBy,
        );

        if ($request->expectsJson()) {
            // RF-87: descuento global sin recargar la página.
            return response()->json($this->cartPayload());
        }

        return redirect()->route('pos.index');
    }

    /**
     * Quita el descuento manual de la venta en curso (RF-13).
     */
    public function removeDiscount(Request $request): RedirectResponse|JsonResponse
    {
        $this->manualDiscountService->clear();

        if ($request->expectsJson()) {
            // RF-87: quitar el descuento global sin recargar la página.
            return response()->json($this->cartPayload());
        }

        return redirect()->route('pos.index');
    }

    /**
     * Aplica el descuento de una línea del carrito (RF-4, RF-13 a RF-18).
     *
     * Exige motivo, no deja la línea en cero y el acumulado de la transacción
     * no supera el tope general; sobre el tope pide supervisor.
     */
    public function applyLineDiscount(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'line_type' => ['required', new Enum(DiscountType::class)],
            'line_value' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'line_reason' => ['required', 'string', 'min:3', 'max:255'],
            'line_supervisor_email' => ['nullable', 'email'],
            'line_supervisor_password' => ['nullable', 'string'],
        ]);

        $cart = $this->cartService->load();
        $line = $cart->line($product->id);

        if ($line === null) {
            throw ValidationException::withMessages([
                'line_value' => __('pos.errors.product_missing'),
            ]);
        }

        $type = DiscountType::from($data['line_type']);
        $reason = trim((string) $data['line_reason']);

        $discount = $type === DiscountType::percent
            ? Discount::percent((float) $data['line_value'], $reason)
            : Discount::amount(
                Money::fromPesos($this->priceCalculator->roundToPeso((float) $data['line_value'])),
                $reason,
            );

        $otherLinesDiscount = $this->otherLinesDiscount($cart, $product->id);

        $decision = $this->discountPolicy->evaluateLine(
            lineBase: $this->priceCalculator->lineSubtotal($line)->pesos(),
            discount: $discount,
            transactionSubtotal: $this->priceCalculator->subtotal($cart)->pesos(),
            otherLinesDiscountPesos: $otherLinesDiscount,
            generalCapPercent: (int) config('pos.discount.general_cap_percent'),
        );

        if ($decision->isRejected()) {
            throw ValidationException::withMessages(
                $decision->reason() === 'reason_required'
                    ? ['line_reason' => __('pos.errors.discount_manual_reason')]
                    : ['line_value' => __('pos.errors.discount_zero_total')],
            );
        }

        $lineAuthorizedBy = $this->supervisorId(
            $data, 'line_supervisor_email', 'line_supervisor_password', 'line_discount_supervisor',
        );

        if ($lineAuthorizedBy === null) {
            throw ValidationException::withMessages([
                'line_discount_supervisor' => __('pos.authorization.required'),
            ]);
        }

        $this->cartService->persist(
            $cart->applyLineDiscount($product->id, $discount),
        );

        // S12/RF-36: recuerda al supervisor para auditarlo al confirmar el cobro.
        $this->cartService->authorizeLineDiscount($product->id, $lineAuthorizedBy);

        if ($request->expectsJson()) {
            // RF-87: descuento de línea sin recargar la página.
            return response()->json($this->cartPayload());
        }

        return redirect()->route('pos.index');
    }

    /**
     * Quita el descuento de una línea del carrito (RF-13).
     */
    public function removeLineDiscount(Request $request, Product $product): RedirectResponse|JsonResponse
    {
        $cart = $this->cartService->load()
            ->applyLineDiscount($product->id, null);

        $this->cartService->persist($cart);

        if ($request->expectsJson()) {
            // RF-87: quitar el descuento de línea sin recargar la página.
            return response()->json($this->cartPayload());
        }

        return redirect()->route('pos.index');
    }

    /**
     * Ahorro en pesos ya aplicado en las demás líneas del carrito (RF-14).
     */
    private function otherLinesDiscount(Cart $cart, int $excludeProductId): int
    {
        $total = 0;

        foreach ($cart->lines() as $line) {
            if ($line->productId() === $excludeProductId) {
                continue;
            }

            $total += $this->priceCalculator->lineDiscount($line)->pesos();
        }

        return $total;
    }

    /**
     * RF-89: verifica que el carrito siga siendo válido justo antes de cobrar.
     *
     * Rechaza el checkout si una línea supera el stock disponible o si un
     * producto salió del catálogo, sin vaciar el carrito.
     *
     * @throws ValidationException si hay stock insuficiente o el producto dejó de estar en el catálogo.
     */
    private function assertCartStillValid(Cart $cart): void
    {
        $productIds = array_map(
            fn (CartLine $line): int => $line->productId(),
            $cart->lines(),
        );

        if ($productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->select('id', 'stock', 'active')->withPosStock(LotStock::today())->get()
            ->keyBy('id');

        $availableByProductId = [];
        $unavailable = [];

        foreach ($cart->lines() as $line) {
            $productId = $line->productId();
            $product = $products->get($productId);

            if ($product === null || ! $product->active) {
                $unavailable[] = $productId;

                continue;
            }

            $availableByProductId[$productId] = (int) $product->pos_stock;
        }

        if ($unavailable !== []) {
            throw ValidationException::withMessages([
                'cart' => __('pos.errors.cart_product_unavailable', [
                    'count' => count($unavailable),
                ]),
            ]);
        }

        if ($this->stockPolicy->hasShortages($cart, $availableByProductId)) {
            // Construye el mismo mensaje que CheckoutService::checkout() usaría
            // tras bloquear la fila, para que el cajero vea el stock real.
            foreach ($cart->lines() as $line) {
                $available = $availableByProductId[$line->productId()] ?? 0;
                if (! $this->stockPolicy->canSell($available, $line->quantity())) {
                    throw new InsufficientStock(__('pos.errors.concurrent_stock', [
                        'name' => $line->name(),
                        'available' => max(0, (int) $available),
                    ]));
                }
            }
        }
    }

    /**
     * RF-88: sondeo ligero del stock de productos del carrito.
     *
     * Devuelve el stock actual y el estado activo de cada producto
     * solicitado, para polling sin recargar la página.
     *
     * @return array<string, mixed>
     */
    public function stock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
        ]);

        $productIds = $validated['product_ids'] ?? [];

        if ($productIds === []) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->select('id', 'stock', 'active')->withPosStock(LotStock::today())->get()
            ->keyBy('id');

        $result = [];

        foreach ($productIds as $productId) {
            $product = $products->get($productId);

            if ($product === null) {
                continue;
            }

            $result[] = [
                'id' => $product->id,
                'stock' => (int) $product->pos_stock,
                'active' => (bool) $product->active,
            ];
        }

        return response()->json(['products' => $result]);
    }

    /**
     * Pantalla de cobro (RF-20, RF-21).
     */
    public function checkout(Request $request, PrescriptionReviewService $reviews): View|RedirectResponse
    {
        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            return redirect()
                ->route('pos.index')
                ->withErrors(['cart' => __('pos.errors.cart_empty')]);
        }

        $manualDiscount = $this->manualDiscountService->load();
        $benefitInput = $request->old('customer_rut', $request->old('benefit_rut'));
        $benefitRut = is_string($benefitInput) ? ChileanRut::normalize($benefitInput) : null;
        $requiresPrescription = $this->prescriptionPolicy->cartRequiresPrescription($cart);
        $openRegister = $this->cashRegisters->openFor($request->user());

        // RF-29: avisar si la existencia cambió mientras el carrito estaba armado.
        $liveStock = $this->cartService->liveStock($cart);
        $changedIds = $this->stockPolicy->detectChanges(
            $cart,
            $liveStock,
            $this->cartService->snapshottedStock(),
        );

        $conventions = $this->conventionService->forCheckout($benefitRut);
        $selected = $request->session()->get('benefit_lookup') && $benefitRut !== null && $conventions->count() === 1 ? $conventions->first() : null;
        $totals = $this->priceCalculator->totals($cart, (int) config('pos.iva_rate'), $manualDiscount);
        if ($selected !== null) {
            try {
                $totals = $this->checkoutService->totalsFor($cart, $selected->id, $manualDiscount, $this->manualDiscountService->authorizedBy(), $this->cartService->lineDiscountAuthors(), $benefitRut);
            } catch (DomainException $error) {
                return to_route('pos.checkout')->withErrors(['convention_id' => $error->getMessage()]);
            }
        }

        return view('pos.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'selected' => $selected,
            'controlledExcluded' => $selected && ! $selected->applies_to_controlled && $this->prescriptionPolicy->effectiveCategory($cart) === \App\Pos\Enum\SaleCategory::controlled,
            'calculator' => $this->priceCalculator,
            'manualDiscount' => $manualDiscount,
            'stockChanges' => $this->stockChanges($cart, $liveStock, $changedIds),
            'methods' => PaymentMethod::cases(),
            'conventions' => $conventions,
            'benefitRut' => $benefitRut,
            'membershipConventions' => $this->conventionService->applicable(now(config('pos.timezone'))->toDateTimeImmutable()),
            'chemists' => $requiresPrescription && $openRegister !== null
                ? User::query()->where('role', User::ROLE_QUIMICO)->whereNull('disabled_at')->orderBy('name')->get(['name', 'email']) : collect(),
            'prescriptionReview' => $requiresPrescription
                ? $reviews->findForCart($request->user()->id, $this->cartService->token(), $cart) : null,
            'requiresPrescription' => $requiresPrescription,
            'openRegister' => $openRegister,
        ]);
    }

    public function lookupBenefit(Request $request): RedirectResponse
    {
        abort_unless($this->cashRegisters->openFor($request->user()) !== null, 403);
        $input = $request->input('customer_rut', $request->input('benefit_rut'));
        $rut = is_string($input) && strlen($input) <= 20 ? ChileanRut::normalize($input) : null;
        $fields = $request->only(['document_type', 'payment_method', 'received_amount', 'invoice_business_name',
            'invoice_business_activity', 'invoice_address', 'invoice_commune', 'prescription_number',
            'prescription_prescriber_name', 'prescription_prescriber_rut', 'prescription_issued_at']);
        $fields = array_filter($fields, 'is_scalar');

        if ($input !== null && $rut === null) {
            return to_route('pos.checkout')->withInput($fields)->withErrors(['benefit_rut' => __('pos.checkout.benefit_invalid')]);
        }

        return to_route('pos.checkout')->withInput($fields + ['customer_rut' => $rut])->with('benefit_lookup', true);
    }

    public function registerMembership(Request $request): RedirectResponse
    {
        abort_unless($this->cashRegisters->openFor($request->user()) !== null, 403);
        $data = $request->validateWithBag('membership', [
            'membership_rut' => ['required', 'string', 'max:20'],
            'membership_name' => ['required', 'string', 'max:120'],
            'membership_convention' => ['required', 'integer', 'min:1'],
            'supervisor_email' => ['required', 'email', 'max:255'],
            'supervisor_password' => ['required', 'string', 'max:72'],
        ]);

        try {
            $rut = ChileanRut::normalize($data['membership_rut']);
            if ($rut === null) {
                throw ValidationException::withMessages(['membership_rut' => __('pos.checkout.benefit_invalid')]);
            }
            $authorizedBy = $this->supervisorId($data);
            $this->conventionService->registerMembership($rut, $data['membership_name'], (int) $data['membership_convention'], $authorizedBy);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('membership');
        } catch (DomainException) {
            throw ValidationException::withMessages(['membership_convention' => __('pos.authorization.convention_unavailable')])->errorBag('membership');
        }

        return to_route('pos.checkout')->withInput(['benefit_rut' => $rut])->with('membership_registered', true);
    }

    public function previewCheckout(Request $request): JsonResponse
    {
        abort_unless($this->cashRegisters->openFor($request->user()) !== null, 403);
        $cart = $this->cartService->load();
        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('pos.errors.cart_empty')]);
        }

        $data = $request->validate([
            'benefit_rut' => ['nullable', 'string', 'max:20'],
            'convention_id' => ['nullable', 'integer', 'min:1'],
            'lookup' => ['sometimes', 'boolean'],
        ]);
        $input = $data['benefit_rut'] ?? null;
        $rut = $input === null || trim($input) === '' ? null : ChileanRut::normalize($input);
        if ($input !== null && trim($input) !== '' && $rut === null) {
            throw ValidationException::withMessages(['benefit_rut' => __('pos.checkout.benefit_invalid')]);
        }

        $conventions = $this->conventionService->forCheckout($rut);
        $selected = isset($data['convention_id']) ? $conventions->firstWhere('id', (int) $data['convention_id']) : null;
        if ($request->boolean('lookup') && $rut !== null && $conventions->count() === 1) {
            $selected = $conventions->first();
        }
        if (isset($data['convention_id']) && $selected === null) {
            throw ValidationException::withMessages(['convention_id' => __('pos.checkout.benefit_ineligible')]);
        }

        try {
            $totals = $this->checkoutService->totalsFor(
                $cart,
                $selected?->id,
                $this->manualDiscountService->load(),
                $this->manualDiscountService->authorizedBy(),
                $this->cartService->lineDiscountAuthors(),
                $rut,
            );
        } catch (DiscountRejected $exception) {
            throw ValidationException::withMessages([$exception->field() => $exception->getMessage()]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['convention_id' => $exception->getMessage()]);
        }

        return response()->json([
            'subtotal' => $totals->subtotal()->pesos(),
            'selected_convention_id' => $selected?->id,
            'controlled_excluded' => $selected !== null && ! $selected->applies_to_controlled && $this->prescriptionPolicy->effectiveCategory($cart) === \App\Pos\Enum\SaleCategory::controlled,
            'discounts' => $totals->discounts()->pesos(),
            'convention_savings' => $selected === null ? 0 : $totals->globalDiscount()->pesos(),
            'total' => $totals->total()->pesos(),
            'conventions' => $conventions->map(fn ($convention): array => [
                'id' => $convention->id,
                'name' => $convention->name,
                'percent' => $convention->percent,
            ])->values(),
            'benefit_rut' => $rut,
        ]);
    }

    /**
     * Confirma la venta (RF-22 a RF-26).
     */
    public function store(Request $request, PrescriptionReviewService $reviews): RedirectResponse
    {
        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('pos.errors.cart_empty')]);
        }

        // RF-89: verificar stock y catálogo justo antes de confirmar la venta.
        try {
            $this->assertCartStillValid($cart);
        } catch (InsufficientStock $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }

        $data = $request->validate([
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'document_type' => ['nullable', Rule::in(['receipt', 'invoice'])],
            'received_amount' => ['nullable', 'integer', 'min:0'],
            'customer_rut' => ['nullable', 'string', 'max:20', 'required_if:document_type,invoice'],
            'invoice_business_name' => ['nullable', 'string', 'max:255', 'required_if:document_type,invoice'],
            'invoice_business_activity' => ['nullable', 'string', 'max:255', 'required_if:document_type,invoice'],
            'invoice_address' => ['nullable', 'string', 'max:255', 'required_if:document_type,invoice'],
            'invoice_commune' => ['nullable', 'string', 'max:100', 'required_if:document_type,invoice'],
            'convention_id' => ['nullable', 'integer'],
            'benefit_rut' => ['nullable', 'string', 'max:20'],
            'prescription_number' => ['nullable', 'string', 'max:50', 'required_with:prescription_prescriber_name,prescription_prescriber_rut,prescription_issued_at'],
            'prescription_prescriber_name' => ['nullable', 'string', 'max:255', 'required_with:prescription_number,prescription_prescriber_rut,prescription_issued_at'],
            'prescription_prescriber_rut' => ['nullable', 'string', 'max:20', 'required_with:prescription_number,prescription_prescriber_name,prescription_issued_at'],
            'prescription_issued_at' => ['nullable', 'date', 'required_with:prescription_number,prescription_prescriber_name,prescription_prescriber_rut'],
            'chemist_email' => ['nullable', 'email', 'max:255'],
            'pharmacist_pin' => ['nullable', 'string', 'regex:/\A[0-9]{8,12}\z/'],
            'request_review' => ['nullable', 'in:1'],
        ]);

        $prescription = $this->prescriptionInput($data);
        $category = $this->prescriptionPolicy->effectiveCategory($cart);
        if (isset($data['request_review'])) {
            abort_unless($category->requiresPrescription() && $this->cashRegisters->openFor($request->user()) !== null, 403);
            $validation = $this->prescriptionPolicy->evaluate($category, $prescription, (int) config('pos.prescription.validity_days'), false,
                now(config('pos.timezone'))->toDateTimeImmutable());
            if ($validation->isInvalid() || $prescription === null) {
                throw ValidationException::withMessages($this->prescriptionErrors($validation->errors()));
            }

            try {
                $reviews->request($request->user()->id, $this->cartService->token(), $cart, $prescription);
            } catch (PrescriptionRejected $exception) {
                throw ValidationException::withMessages($this->prescriptionErrors($exception->errors()));
            }

            return to_route('pos.checkout')->withInput(Arr::except($data, ['supervisor_password', 'pharmacist_pin', 'request_review']));
        }

        $pharmacistByPin = $category->requiresPrescription() ? $this->supervisorId(
            $data, 'chemist_email', 'pharmacist_pin', 'prescription_approval', pharmacistOnly: true,
        ) : null;

        // RF-23/S-2: boleta por defecto; factura con datos tributarios.
        $documentType = DocumentType::from($data['document_type'] ?? DocumentType::receipt->value);
        if ($documentType === DocumentType::invoice) {
            $data['customer_rut'] = ChileanRut::normalize($data['customer_rut']);
            if ($data['customer_rut'] === null) {
                throw ValidationException::withMessages(['customer_rut' => __('pos.checkout.invoice_rut_required')]);
            }
        }
        $invoice = $documentType === DocumentType::invoice
            ? new InvoiceData(
                rut: (string) $data['customer_rut'],
                businessName: (string) $data['invoice_business_name'],
                businessActivity: (string) $data['invoice_business_activity'],
                address: (string) $data['invoice_address'],
                commune: (string) $data['invoice_commune'],
            )
            : null;

        $manualDiscount = $this->manualDiscountService->load();
        $discountAuthorizedBy = $this->manualDiscountService->authorizedBy();
        $lineAuthors = $this->cartService->lineDiscountAuthors();
        if ($manualDiscount !== null && $discountAuthorizedBy === null) {
            return to_route('pos.index')->withErrors(['discount' => __('pos.authorization.required')]);
        }
        foreach ($cart->lines() as $line) {
            if ($line->discount() !== null && ! isset($lineAuthors[$line->productId()])) {
                return to_route('pos.index')->withErrors(['discount' => __('pos.authorization.required')]);
            }
        }
        $conventionId = isset($data['convention_id']) ? (int) $data['convention_id'] : null;
        $benefitRut = $conventionId !== null && array_key_exists('customer_rut', $data)
            ? $data['customer_rut'] : ($data['benefit_rut'] ?? null);
        if ($benefitRut !== null && ($benefitRut = ChileanRut::normalize($benefitRut)) === null) {
            throw ValidationException::withMessages(['benefit_rut' => __('pos.checkout.benefit_invalid')]);
        }

        try {
            $totals = $this->checkoutService->totalsFor(
                $cart,
                $conventionId,
                $manualDiscount,
                $discountAuthorizedBy,
                $this->cartService->lineDiscountAuthors(),
                $benefitRut,
            );
        } catch (DiscountRejected $exception) {
            throw ValidationException::withMessages([$exception->field() => $exception->getMessage()]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['convention_id' => $exception->getMessage()]);
        }

        $method = PaymentMethod::from($data['payment_method']);
        $received = isset($data['received_amount']) ? (int) $data['received_amount'] : null;

        if ($method->requiresReceivedAmount()) {
            if ($received === null || $received < $totals->total()->pesos()) {
                throw ValidationException::withMessages([
                    'received_amount' => __('pos.errors.received_below_total'),
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
                conventionId: $conventionId,
                prescription: $prescription,
                manualDiscount: $manualDiscount,
                discountAuthorizedBy: $discountAuthorizedBy,
                documentType: $documentType,
                invoice: $invoice,
                lineDiscountAuthorizedBy: $lineAuthors,
                benefitRut: $benefitRut,
                pharmacistByPin: $pharmacistByPin,
            );
        } catch (DiscountRejected $exception) {
            throw ValidationException::withMessages([$exception->field() => $exception->getMessage()]);
        } catch (PrescriptionRejected $exception) {
            if (isset($exception->errors()['approval'])) {
                throw ValidationException::withMessages(['prescription_approval' => __('pos.rx_review.required')]);
            }
            if ($exception->requiresSupervisor()) {
                throw ValidationException::withMessages([
                    'prescription_reuse' => __('pos.errors.prescription_reuse'),
                ]);
            }

            throw ValidationException::withMessages($this->prescriptionErrors($exception->errors()));
        } catch (InsufficientStock $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        } catch (CashRegisterNotOpen $exception) {
            // RF-60: sin caja abierta no se cobra y se indica cómo abrirla.
            throw ValidationException::withMessages(['cash' => $exception->getMessage()]);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['convention_id' => $exception->getMessage()]);
        }

        $this->cartService->clear();
        $this->manualDiscountService->clear();

        return redirect()->to(URL::signedRoute('pos.sales.receipt', $sale));
    }

    /**
     * Comprobante reimprimible (RF-25).
     *
     * B3: el comprobante no es público entre cajeros. Sólo lo abre quien
     * vendió, o quien puede anular (Químico y Administrador), que vuelve a
     * esta pantalla tras anular (RF-32).
     */
    public function receipt(Request $request, Sale $sale, ReceiptPolicy $receipts): View
    {
        $viewer = $request->user();
        $canSeeAny = $viewer !== null
            && in_array($viewer->role, User::rolesThatCanVoid(), true);

        if (! $receipts->canView($canSeeAny, $sale->user_id, $viewer?->id)) {
            abort(403, __('pos.errors.forbidden'));
        }

        $sale->loadMissing('lines', 'document', 'reversal', 'discountAuthorizedBy', 'invoiceDetail');

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
     *
     * B5: el servicio se resuelve por el contenedor en la firma; antes se pedía
     * con `app()` dentro del método, que esconde la dependencia y complica
     * probar la acción sin arrancar la aplicación.
     */
    public function void(Request $request, Sale $sale, VoidSaleService $voids): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $voids->void(
                $sale,
                $data['reason'],
                $request->user()?->id,
            );
        } catch (DomainException $exception) {
            // T29: la política lanza una clave de traducción; se traduce aquí.
            throw ValidationException::withMessages(['reason' => __($exception->getMessage())]);
        }

        return redirect()->to(URL::signedRoute('pos.sales.receipt', $sale))
            ->with('voided', true);
    }

    /**
     * Filas del aviso de cambio de stock del cobro (RF-29).
     *
     * @param  array<int, int>  $liveStock
     * @param  int[]  $changedIds
     * @return array<int, array{product_id: int, name: string, requested: int, previous: int, available: int}>
     */
    private function stockChanges(Cart $cart, array $liveStock, array $changedIds): array
    {
        $snapshotted = $this->cartService->snapshottedStock();
        $lines = [];

        foreach ($cart->lines() as $line) {
            $lines[$line->productId()] = $line;
        }

        $changes = [];

        foreach ($changedIds as $productId) {
            $line = $lines[$productId];

            $changes[] = [
                'product_id' => $productId,
                'name' => $line->name(),
                'requested' => $line->quantity(),
                'previous' => $snapshotted[$productId] ?? $line->product->availableStock,
                'available' => max(0, $liveStock[$productId] ?? 0),
            ];
        }

        return $changes;
    }

    /**
     * Candidatos del escaneo: sólo la fila vigente con ese código exacto,
     * incluidos los productos no vendibles, para poder explicar por qué no se
     * agregan (B2, RF-72). Un producto fuera de catálogo no es candidato.
     *
     * @return array<int, array{id: int, barcode: string|null, sellable: bool, stock: int}>
     */
    private function barcodeCandidates(string $term): array
    {
        return Product::query()
            ->active()
            ->where('barcode', $term)
            ->withPosStock(LotStock::today())
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'barcode' => $product->barcode,
                'sellable' => (bool) $product->sellable,
                'stock' => (int) $product->pos_stock,
            ])
            ->all();
    }

    /**
     * Agrega el producto escaneado con cantidad 1 (RF-2, B2).
     */
    private function addScannedProduct(int $productId): RedirectResponse
    {
        $product = Product::query()->withPosStock(LotStock::today())->find($productId);

        if ($product === null) {
            return redirect()->route('pos.index');
        }

        $cart = $this->cartService->load()->add($this->cartService->snapshot($product));
        $this->cartService->persist($cart);

        return redirect()->route('pos.index');
    }

    /**
     * Búsqueda de la caja: sólo productos vendibles y vigentes (RF-72).
     *
     * @return Collection<int, Product>
     */
    private function search(string $term)
    {
        $query = Product::query()->where('sellable', true)->active()->withPosStock(LotStock::today());

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

    /**
     * Construye la receta sólo si el cajero ingresó algún dato (RF-8).
     *
     * @param  array<string, mixed>  $data
     */
    private function prescriptionInput(array $data): ?PrescriptionInput
    {
        $fields = [
            $data['prescription_number'] ?? null,
            $data['prescription_prescriber_name'] ?? null,
            $data['prescription_prescriber_rut'] ?? null,
            $data['prescription_issued_at'] ?? null,
        ];

        $filled = array_filter(
            $fields,
            static fn (?string $value): bool => $value !== null && trim($value) !== '',
        );

        if ($filled === []) {
            return null;
        }

        return new PrescriptionInput(
            number: (string) $data['prescription_number'],
            prescriberName: (string) $data['prescription_prescriber_name'],
            prescriberRut: (string) $data['prescription_prescriber_rut'],
            issuedAt: new DateTimeImmutable(
                (string) $data['prescription_issued_at'].' 00:00:00',
                new DateTimeZone((string) config('pos.timezone')),
            ),
        );
    }

    /**
     * Resuelve la autorización de supervisor por credenciales (RF-11, RF-16).
     *
     * RF-83: antes de comparar la contraseña se comprueba el freno por fuerza
     * bruta, que se lleva por el usuario en sesión y su dirección de origen. Sin
     * él, un cajero podía probar contraseñas de supervisor sin límite y el tope
     * del 10% quedaba a su merced. Un acierto limpia el contador.
     *
     * @param  array<string, mixed>  $data
     */
    private function supervisorId(
        array $data,
        string $emailKey = 'supervisor_email',
        string $passwordKey = 'supervisor_password',
        string $errorKey = 'supervisor_email',
        bool $pharmacistOnly = false,
    ): ?int {
        $email = $data[$emailKey] ?? null;
        $password = $data[$passwordKey] ?? null;

        if ($email === null && $password === null) {
            return null;
        }

        $throttleKey = $this->supervisorThrottleKey();
        $pharmacistKey = 'rx-pin|'.hash('sha256', strtolower((string) $email));

        if (RateLimiter::tooManyAttempts($throttleKey, self::SUPERVISOR_MAX_ATTEMPTS)
            || ($pharmacistOnly && RateLimiter::tooManyAttempts($pharmacistKey, 10))) {
            throw ValidationException::withMessages([
                $errorKey => __('pos.errors.supervisor_throttled', [
                    'seconds' => max(RateLimiter::availableIn($throttleKey), $pharmacistOnly ? RateLimiter::availableIn($pharmacistKey) : 0),
                ]),
            ]);
        }

        $supervisor = User::query()->where('email', $email)->first();

        if ($supervisor === null
            || $supervisor->disabled_at !== null
            || ! in_array($supervisor->role, $pharmacistOnly ? [User::ROLE_QUIMICO] : [User::ROLE_QUIMICO, User::ROLE_ADMIN], true)
            || preg_match('/\A[0-9]{8,12}\z/', (string) $password) !== 1
            || ! Hash::check((string) $password, $supervisor->pharmacist_pin_hash)) {
            RateLimiter::hit($throttleKey, self::SUPERVISOR_DECAY_SECONDS);
            if ($pharmacistOnly) {
                RateLimiter::hit($pharmacistKey, self::SUPERVISOR_DECAY_SECONDS);
            }

            // T28 - RF-84: el intento fallido queda en la bitácora sin credenciales.
            AuditLog::recordSupervisorFailure(request()->user());

            throw ValidationException::withMessages([
                $errorKey => __('pos.errors.supervisor_invalid'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        if ($pharmacistOnly) {
            RateLimiter::clear($pharmacistKey);
        }

        return $supervisor->id;
    }

    /**
     * Contador de intentos de autorización por cajero y dirección de origen
     * (RF-83, D-16). Compartido por las tres vías que exigen supervisor.
     */
    private function supervisorThrottleKey(): string
    {
        return 'supervisor-auth|'.(request()->user()?->getKey() ?? 'guest').'|'.request()->ip();
    }

    /**
     * Traduce los códigos de la política a mensajes para el cajero (RNF-9).
     *
     * @param  array<string, string>  $errors
     * @return array<string, string>
     */
    private function prescriptionErrors(array $errors): array
    {
        $fields = [
            'number' => 'prescription_number',
            'prescriber_name' => 'prescription_prescriber_name',
            'prescriber_rut' => 'prescription_prescriber_rut',
            'issued_at' => 'prescription_issued_at',
        ];

        $messages = [
            'required' => __('pos.errors.prescription_required'),
            'expired' => __('pos.errors.prescription_expired'),
            'issued_in_future' => __('pos.errors.prescription_future'),
            'registered_mismatch' => __('pos.rx_review.registered_mismatch'),
        ];

        $mapped = [];

        foreach ($errors as $field => $code) {
            $mapped[$fields[$field] ?? $field] = $messages[$code] ?? __('pos.errors.prescription_invalid');
        }

        return $mapped;
    }
}
