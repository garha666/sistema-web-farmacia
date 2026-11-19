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
use App\Pos\Policy\StockPolicy;
use App\Pos\Value\Cart;
use App\Pos\Value\Discount;
use App\Pos\Value\InvoiceData;
use App\Pos\Value\PrescriptionInput;
use App\Services\Cash\CashRegisterService;
use App\Services\Pos\CartService;
use App\Services\Pos\CheckoutService;
use App\Services\Pos\ConventionService;
use App\Services\Pos\ManualDiscountService;
use App\Services\Pos\VoidSaleService;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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

    public function index(Request $request): View|RedirectResponse
    {
        $term = trim((string) $request->query('q', ''));

        // B2 (RF-1, RF-2, RNF-3): el lector entrega el código completo seguido
        // de Enter. El acierto exacto agrega al carrito y vuelve a la caja sin
        // ?q; un código que no puede venderse avisa y tampoco deja el término.
        if ($term !== '') {
            $scan = $this->barcodeScanPolicy->evaluate($term, $this->barcodeCandidates($term));

            if ($scan['product_id'] !== null) {
                return $this->addScannedProduct($scan['product_id']);
            }

            if ($scan['error'] !== null) {
                return redirect()
                    ->route('pos.index')
                    ->withErrors(['barcode' => __('pos.errors.'.$scan['error'])]);
            }
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

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $product = Product::query()->find($data['product_id']);

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

        if ($product->stock < 1) {
            throw ValidationException::withMessages([
                'product_id' => __('pos.errors.product_out_of_stock'),
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
                'quantity' => __('pos.errors.quantity_available', ['stock' => $product->stock]),
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
     * Aplica el descuento manual de la venta en curso (RF-13, RF-16, RF-17).
     *
     * Bajo el tope se aplica directo. Sobre el tope no se guarda hasta que
     * vengan las credenciales de un supervisor; la vista abre el modal.
     */
    public function applyDiscount(Request $request): RedirectResponse
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

        $authorizedBy = $decision->requiresSupervisor() ? $this->supervisorId($data) : null;

        if ($decision->requiresSupervisor() && $authorizedBy === null) {
            throw ValidationException::withMessages([
                'discount_supervisor' => __('pos.errors.discount_above_cap', [
                    'cap' => (int) config('pos.discount.general_cap_percent'),
                ]),
            ]);
        }

        $this->manualDiscountService->store(
            $type,
            (float) $data['discount_value'],
            $reason,
            $authorizedBy,
        );

        return redirect()->route('pos.index');
    }

    /**
     * Quita el descuento manual de la venta en curso (RF-13).
     */
    public function removeDiscount(): RedirectResponse
    {
        $this->manualDiscountService->clear();

        return redirect()->route('pos.index');
    }

    /**
     * Aplica el descuento de una línea del carrito (RF-4, RF-13 a RF-18).
     *
     * Exige motivo, no deja la línea en cero y el acumulado de la transacción
     * no supera el tope general; sobre el tope pide supervisor.
     */
    public function applyLineDiscount(Request $request, Product $product): RedirectResponse
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

        $lineAuthorizedBy = null;

        if ($decision->requiresSupervisor()) {
            $lineAuthorizedBy = $this->supervisorId(
                $data,
                'line_supervisor_email',
                'line_supervisor_password',
                'line_discount_supervisor',
            );

            if ($lineAuthorizedBy === null) {
                throw ValidationException::withMessages([
                    'line_discount_supervisor' => __('pos.errors.discount_above_cap', [
                        'cap' => (int) config('pos.discount.general_cap_percent'),
                    ]),
                ]);
            }
        }

        $this->cartService->persist(
            $cart->applyLineDiscount($product->id, $discount),
        );

        // S12/RF-36: recuerda al supervisor para auditarlo al confirmar el cobro.
        if ($lineAuthorizedBy !== null) {
            $this->cartService->authorizeLineDiscount($product->id, $lineAuthorizedBy);
        }

        return redirect()->route('pos.index');
    }

    /**
     * Quita el descuento de una línea del carrito (RF-13).
     */
    public function removeLineDiscount(Product $product): RedirectResponse
    {
        $cart = $this->cartService->load()
            ->applyLineDiscount($product->id, null);

        $this->cartService->persist($cart);

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
     * Pantalla de cobro (RF-20, RF-21).
     */
    public function checkout(Request $request): View|RedirectResponse
    {
        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            return redirect()
                ->route('pos.index')
                ->withErrors(['cart' => __('pos.errors.cart_empty')]);
        }

        $manualDiscount = $this->manualDiscountService->load();

        // RF-29: avisar si la existencia cambió mientras el carrito estaba armado.
        $liveStock = $this->cartService->liveStock($cart);
        $changedIds = $this->stockPolicy->detectChanges(
            $cart,
            $liveStock,
            $this->cartService->snapshottedStock(),
        );

        return view('pos.checkout', [
            'cart' => $cart,
            'totals' => $this->priceCalculator->totals(
                $cart,
                (int) config('pos.iva_rate'),
                $manualDiscount,
            ),
            'calculator' => $this->priceCalculator,
            'manualDiscount' => $manualDiscount,
            'stockChanges' => $this->stockChanges($cart, $liveStock, $changedIds),
            'methods' => PaymentMethod::cases(),
            'conventions' => $this->conventionService->applicable(),
            'requiresPrescription' => $this->prescriptionPolicy->cartRequiresPrescription($cart),
            'openRegister' => $this->cashRegisters->openFor($request->user()),
        ]);
    }

    /**
     * Confirma la venta (RF-22 a RF-26).
     */
    public function store(Request $request): RedirectResponse
    {
        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('pos.errors.cart_empty')]);
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
            'prescription_number' => ['nullable', 'string', 'max:50', 'required_with:prescription_prescriber_name,prescription_prescriber_rut,prescription_issued_at'],
            'prescription_prescriber_name' => ['nullable', 'string', 'max:255', 'required_with:prescription_number,prescription_prescriber_rut,prescription_issued_at'],
            'prescription_prescriber_rut' => ['nullable', 'string', 'max:20', 'required_with:prescription_number,prescription_prescriber_name,prescription_issued_at'],
            'prescription_issued_at' => ['nullable', 'date', 'required_with:prescription_number,prescription_prescriber_name,prescription_prescriber_rut'],
            'supervisor_email' => ['nullable', 'email'],
            'supervisor_password' => ['nullable', 'string'],
        ]);

        $prescription = $this->prescriptionInput($data);
        $supervisorId = $this->supervisorId($data);

        // RF-23/S-2: boleta por defecto; factura con datos tributarios.
        $documentType = DocumentType::from($data['document_type'] ?? DocumentType::receipt->value);
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
        $discountAuthorizedBy = $supervisorId ?? $this->manualDiscountService->authorizedBy();
        $conventionId = isset($data['convention_id']) ? (int) $data['convention_id'] : null;

        try {
            $totals = $this->checkoutService->totalsFor(
                $cart,
                $conventionId,
                $manualDiscount,
                $discountAuthorizedBy,
                $this->cartService->lineDiscountAuthors(),
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
                reuseAuthorizedBy: $supervisorId,
                manualDiscount: $manualDiscount,
                discountAuthorizedBy: $discountAuthorizedBy,
                documentType: $documentType,
                invoice: $invoice,
                lineDiscountAuthorizedBy: $this->cartService->lineDiscountAuthors(),
            );
        } catch (DiscountRejected $exception) {
            throw ValidationException::withMessages([$exception->field() => $exception->getMessage()]);
        } catch (PrescriptionRejected $exception) {
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

        return redirect()->route('pos.sales.receipt', $sale);
    }

    /**
     * Comprobante reimprimible (RF-25).
     */
    public function receipt(Sale $sale): View
    {
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
            // T29: la política lanza una clave de traducción; se traduce aquí.
            throw ValidationException::withMessages(['reason' => __($exception->getMessage())]);
        }

        return redirect()->route('pos.sales.receipt', $sale)
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
            ->get()
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'barcode' => $product->barcode,
                'sellable' => (bool) $product->sellable,
                'stock' => (int) $product->stock,
            ])
            ->all();
    }

    /**
     * Agrega el producto escaneado con cantidad 1 (RF-2, B2).
     */
    private function addScannedProduct(int $productId): RedirectResponse
    {
        $product = Product::query()->find($productId);

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
        $query = Product::query()->where('sellable', true)->active();

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
    ): ?int {
        $email = $data[$emailKey] ?? null;
        $password = $data[$passwordKey] ?? null;

        if ($email === null && $password === null) {
            return null;
        }

        $throttleKey = $this->supervisorThrottleKey();

        if (RateLimiter::tooManyAttempts($throttleKey, self::SUPERVISOR_MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                $errorKey => __('pos.errors.supervisor_throttled', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $supervisor = User::query()->where('email', $email)->first();

        if ($supervisor === null
            || ! in_array($supervisor->role, [User::ROLE_QUIMICO, User::ROLE_ADMIN], true)
            || ! Hash::check((string) $password, $supervisor->password)) {
            RateLimiter::hit($throttleKey, self::SUPERVISOR_DECAY_SECONDS);

            // T28 - RF-84: el intento fallido queda en la bitácora sin credenciales.
            AuditLog::recordSupervisorFailure(request()->user());

            throw ValidationException::withMessages([
                $errorKey => __('pos.errors.supervisor_invalid'),
            ]);
        }

        RateLimiter::clear($throttleKey);

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
        ];

        $mapped = [];

        foreach ($errors as $field => $code) {
            $mapped[$fields[$field] ?? $field] = $messages[$code] ?? __('pos.errors.prescription_invalid');
        }

        return $mapped;
    }
}
