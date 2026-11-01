<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Calculator\PriceCalculator;
use App\Pos\Enum\DiscountType;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Exception\DiscountRejected;
use App\Pos\Exception\InsufficientStock;
use App\Pos\Exception\PrescriptionRejected;
use App\Pos\Money;
use App\Pos\Policy\DiscountPolicy;
use App\Pos\Policy\PrescriptionPolicy;
use App\Pos\Policy\StockPolicy;
use App\Pos\Value\Cart;
use App\Pos\Value\Discount;
use App\Pos\Value\PrescriptionInput;
use App\Services\Pos\CartService;
use App\Services\Pos\CheckoutService;
use App\Services\Pos\ConventionService;
use App\Services\Pos\ManualDiscountService;
use App\Services\Pos\VoidSaleService;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        private readonly ConventionService $conventionService,
        private readonly PrescriptionPolicy $prescriptionPolicy,
        private readonly DiscountPolicy $discountPolicy,
        private readonly ManualDiscountService $manualDiscountService,
        private readonly StockPolicy $stockPolicy,
    ) {
    }

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
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
        ]);

        $cart = $this->cartService->load();

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages([
                'discount_value' => 'El carrito está vacío.',
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
        );

        if ($decision->isRejected()) {
            $field = $decision->reason() === 'reason_required' ? 'discount_reason' : 'discount_value';

            throw ValidationException::withMessages([
                $field => $field === 'discount_reason'
                    ? 'Todo descuento manual exige un motivo.'
                    : 'El descuento no puede dejar el total en cero o negativo.',
            ]);
        }

        $authorizedBy = $decision->requiresSupervisor() ? $this->supervisorId($data) : null;

        if ($decision->requiresSupervisor() && $authorizedBy === null) {
            throw ValidationException::withMessages([
                'discount_supervisor' => 'El descuento supera el tope del '
                    .(int) config('pos.discount.general_cap_percent')
                    .'%. Ingresa las credenciales de un supervisor para autorizarlo.',
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
            'manualDiscount' => $manualDiscount,
            'stockChanges' => $this->stockChanges($cart, $liveStock, $changedIds),
            'methods' => PaymentMethod::cases(),
            'conventions' => $this->conventionService->applicable(),
            'requiresPrescription' => $this->prescriptionPolicy->cartRequiresPrescription($cart),
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

        $manualDiscount = $this->manualDiscountService->load();
        $discountAuthorizedBy = $supervisorId ?? $this->manualDiscountService->authorizedBy();
        $conventionId = isset($data['convention_id']) ? (int) $data['convention_id'] : null;

        try {
            $totals = $this->checkoutService->totalsFor(
                $cart,
                $conventionId,
                $manualDiscount,
                $discountAuthorizedBy,
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
                conventionId: $conventionId,
                prescription: $prescription,
                reuseAuthorizedBy: $supervisorId,
                manualDiscount: $manualDiscount,
                discountAuthorizedBy: $discountAuthorizedBy,
            );
        } catch (DiscountRejected $exception) {
            throw ValidationException::withMessages([$exception->field() => $exception->getMessage()]);
        } catch (PrescriptionRejected $exception) {
            if ($exception->requiresSupervisor()) {
                throw ValidationException::withMessages([
                    'prescription_reuse' => 'La receta ya fue utilizada en otra venta. Ingresa las credenciales de un supervisor para autorizarla.',
                ]);
            }

            throw ValidationException::withMessages($this->prescriptionErrors($exception->errors()));
        } catch (InsufficientStock $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
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
        $sale->loadMissing('lines', 'document', 'reversal', 'discountAuthorizedBy');

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
     * @param  array<string, mixed>  $data
     */
    private function supervisorId(array $data): ?int
    {
        $email = $data['supervisor_email'] ?? null;
        $password = $data['supervisor_password'] ?? null;

        if ($email === null && $password === null) {
            return null;
        }

        $supervisor = User::query()->where('email', $email)->first();

        if ($supervisor === null
            || ! in_array($supervisor->role, [User::ROLE_QUIMICO, User::ROLE_ADMIN], true)
            || ! Hash::check((string) $password, $supervisor->password)) {
            throw ValidationException::withMessages([
                'supervisor_email' => 'Credenciales de supervisor inválidas.',
            ]);
        }

        return $supervisor->id;
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
            'required' => 'Este dato es obligatorio para vender medicamentos con receta.',
            'expired' => 'La receta está vencida: la vigencia máxima es de 30 días desde su emisión.',
            'issued_in_future' => 'La fecha de emisión de la receta no puede ser futura.',
        ];

        $mapped = [];

        foreach ($errors as $field => $code) {
            $mapped[$fields[$field] ?? $field] = $messages[$code] ?? 'Dato de receta inválido.';
        }

        return $mapped;
    }
}
