<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\CashRegister;
use App\Models\ControlledRegisterEntry;
use App\Models\Convention;
use App\Models\Prescription;
use App\Models\PrescriptionReview;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Services\Inventory\LotStock;
use App\Pos\Calculator\PriceCalculator;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\DiscountOrigin;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleCategory;
use App\Pos\Enum\SaleStatus;
use App\Pos\Enum\StockMovementType;
use App\Pos\Exception\CashRegisterNotOpen;
use App\Pos\Exception\DiscountRejected;
use App\Pos\Exception\EmissionFailed;
use App\Pos\Exception\EmptyCart;
use App\Pos\Exception\InsufficientStock;
use App\Pos\Exception\PrescriptionRejected;
use App\Pos\Policy\DiscountPolicy;
use App\Pos\Policy\PrescriptionPolicy;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\ConventionSnapshot;
use App\Pos\Value\Discount;
use App\Pos\Value\InvoiceData;
use App\Pos\Value\PrescriptionInput;
use App\Pos\Value\ReceiptTotals;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Confirma la venta en una sola transacción (RF-22) y emite después (RF-24).
 *
 * Todo o nada: stock, venta, líneas, movimientos, datos de factura y un único
 * documento original pendiente. Recién cuando esa transacción confirma se
 * intenta la emisión simulada: un fallo del simulador deja el documento
 * pendiente sin deshacer la venta, y un error de base de datos se propaga.
 */
final class CheckoutService
{
    public function __construct(
        private readonly PriceCalculator $calculator,
        private readonly ConventionService $conventions,
        private readonly LotStock $lots,
        private readonly PrescriptionPolicy $prescriptions,
        private readonly DiscountPolicy $discounts,
        private readonly AuditService $audit,
        private readonly DocumentService $documents,
        private readonly PrescriptionReviewService $prescriptionReviews,
    ) {}

    public function checkout(
        Cart $cart,
        PaymentMethod $method,
        ?int $receivedAmount,
        ?string $customerRut,
        string $token,
        ?int $userId = null,
        ?int $conventionId = null,
        ?PrescriptionInput $prescription = null,
        ?int $reuseAuthorizedBy = null,
        ?Discount $manualDiscount = null,
        ?int $discountAuthorizedBy = null,
        ?DocumentType $documentType = null,
        ?InvoiceData $invoice = null,
        array $lineDiscountAuthorizedBy = [],
        ?string $benefitRut = null,
        ?int $pharmacistByPin = null,
    ): Sale {
        if ($cart->isEmpty()) {
            throw new EmptyCart(__('pos.errors.cart_empty'));
        }

        // RF-14/RF-16: el carrito pudo cambiar desde que se autorizó el
        // descuento de cada línea; el tope se revalida recién al cobrar.
        $this->assertLineDiscountsAuthorized($cart, $lineDiscountAuthorizedBy);

        // RF-23: la factura exige los datos tributarios del cliente (S-2).
        $documentType ??= DocumentType::receipt;

        if ($documentType === DocumentType::invoice && $invoice === null) {
            throw new DomainException(__('pos.errors.invoice_customer_required'));
        }

        // RF-8/RF-9: validar la receta antes de tocar stock o dinero.
        $category = $this->prescriptions->effectiveCategory($cart);

        $alreadyUsed = $prescription !== null
            && $category->requiresPrescription()
            && Prescription::query()
                ->where('number', $prescription->number)
                ->where('prescriber_rut', $prescription->prescriberRut)
                ->exists();

        $result = $this->prescriptions->evaluate(
            $category,
            $prescription,
            (int) config('pos.prescription.validity_days'),
            $alreadyUsed,
            new DateTimeImmutable('now', new DateTimeZone((string) config('pos.timezone'))),
        );

        if ($result->isInvalid()) {
            throw new PrescriptionRejected($result->errors());
        }

        // RF-16/RF-17/RF-40: el descuento se re-evalúa al confirmar. El convenio
        // prevalece sobre el descuento manual; sobre el tope exige supervisor.
        [$convention, $globalDiscount] = $this->resolveDiscount(
            $cart,
            $conventionId,
            $manualDiscount,
            $discountAuthorizedBy,
            $benefitRut,
        );

        $totals = $this->calculator->totals($cart, (int) config('pos.iva_rate'), $globalDiscount);
        if ($globalDiscount?->origin !== DiscountOrigin::manual || $totals->globalDiscount()->pesos() === 0) {
            $discountAuthorizedBy = null;
        }

        // 3 intentos: reintenta si PostgreSQL detecta un deadlock (SQLSTATE 40P01)
        // o una espera de lock agotada por concurrencia entre cajas (RF-28).
        $sale = DB::transaction(function () use ($cart, $method, $receivedAmount, $customerRut, $token, $userId, $convention, $totals, $prescription, $alreadyUsed, $category, $globalDiscount, $discountAuthorizedBy, $documentType, $invoice, $lineDiscountAuthorizedBy, $pharmacistByPin): Sale {
            $existing = Sale::query()->where('token', $token)->first();

            if ($existing !== null) {
                return $existing->load('lines', 'document');
            }

            // RF-60: sin una caja abierta del propio cajero no se confirma el
            // cobro. El bloqueo serializa el cobro con el cierre del turno.
            $register = $this->openRegisterFor($userId);

            $pharmacistId = null;
            if ($category->requiresPrescription() && $prescription !== null) {
                $pharmacistId = $pharmacistByPin ?? $this->prescriptionReviews->pharmacistFor($register->user_id, $token, $cart, $prescription);
                if ($pharmacistId === null || ! User::query()->whereKey($pharmacistId)->where('role', User::ROLE_QUIMICO)
                    ->whereNull('disabled_at')->sharedLock()->first()) {
                    throw new PrescriptionRejected(['approval' => 'required']);
                }
            }

            // RF-27/RF-28: bloqueo pesimista de las filas de producto en orden
            // de id (mismo orden que VoidSaleService) para serializar la venta
            // de la última unidad y evitar deadlocks.
            $lockedStock = $this->lockProducts($cart);

            // RF-74: existencia resultante por producto, calculada con la
            // existencia bloqueada antes de descontar.
            $resultingStock = [];
            $allocations = [];
            $saleDate = LotStock::today();

            // RF-11: la receta se guarda una sola vez y se reutiliza la existente
            // cuando un supervisor autorizó su repetición.
            $prescriptionModel = null;

            if ($category->requiresPrescription() && $prescription !== null) {
                $prescriptionModel = Prescription::query()->firstOrCreate(
                    [
                        'number' => $prescription->number,
                        'prescriber_rut' => $prescription->prescriberRut,
                    ],
                    [
                        'prescriber_name' => $prescription->prescriberName,
                        'issued_on' => $prescription->issuedAt->format('Y-m-d'),
                    ],
                );
                $this->prescriptionReviews->assertConsistent($prescriptionModel, $prescription);
                $alreadyUsed = ! $prescriptionModel->wasRecentlyCreated;
            }

            foreach ($cart->lines() as $line) {
                $productId = $line->productId();
                $allocation = $this->lots->consume($productId, $lockedStock[$productId] ?? 0, $line->quantity(), $saleDate);
                if ($allocation === null) {
                    throw new InsufficientStock($this->shortageMessage($line));
                }
                $allocations[$productId] = $allocation;
                $affected = Product::query()
                    ->whereKey($line->productId())
                    ->where('stock', '>=', $line->quantity())
                    ->decrement('stock', $line->quantity());

                if ($affected === 0) {
                    throw new InsufficientStock($this->shortageMessage($line));
                }

                $resultingStock[$line->productId()] = $lockedStock[$line->productId()] - $line->quantity();
            }

            $changeAmount = $method->requiresReceivedAmount() && $receivedAmount !== null
                ? max(0, $receivedAmount - $totals->total()->pesos())
                : null;

            try {
                $sale = Sale::query()->create([
                    'token' => $token,
                    'subtotal' => $totals->subtotal()->pesos(),
                    'line_discounts' => $totals->lineDiscounts()->pesos(),
                    'global_discount' => $totals->globalDiscount()->pesos(),
                    'discount_origin' => $globalDiscount?->origin,
                    'discount_reason' => $globalDiscount?->reason,
                    'discount_authorized_by' => $discountAuthorizedBy,
                    'net' => $totals->net()->pesos(),
                    'iva' => $totals->iva()->pesos(),
                    'total' => $totals->total()->pesos(),
                    'payment_method' => $method,
                    'received_amount' => $receivedAmount,
                    'change_amount' => $changeAmount,
                    'customer_rut' => $customerRut,
                    'convention_id' => $convention?->id,
                    'convention_name' => $convention?->name,
                    'convention_percent' => $convention?->percent,
                    'prescription_id' => $prescriptionModel?->id,
                    'status' => SaleStatus::confirmed,
                    'sold_at' => now(),
                    'user_id' => $userId,
                    'cash_register_id' => $register->id,
                ]);
            } catch (QueryException $exception) {
                // RF-22: doble clic simultáneo. Otro cajero ya creó la venta
                // con este token (índice único en sales.token). La devolvemos
                // en lugar de lanzar un error 500 al cajero.
                if ($exception->getCode() === '23505') {
                    return Sale::query()->where('token', $token)->first()->load('lines', 'document');
                }
                throw $exception;
            }

            $createdLines = [];

            /** @var CartLine $line */
            foreach ($cart->lines() as $line) {
                $createdLines[$line->productId()] = $sale->lines()->create([
                    'product_id' => $line->productId(),
                    'name' => $line->name(),
                    'unit_price' => $line->unitPrice()->pesos(),
                    'quantity' => $line->quantity(),
                    'discount_amount' => $this->calculator->lineDiscount($line)->pesos(),
                    'subtotal' => $this->calculator->lineSubtotal($line)->pesos(),
                ]);
                $createdLines[$line->productId()]->lots()->attach($allocations[$line->productId()]);

                StockMovement::query()->create([
                    'product_id' => $line->productId(),
                    'sale_id' => $sale->id,
                    'user_id' => $sale->user_id,
                    'type' => StockMovementType::sale,
                    'quantity' => -$line->quantity(),
                    'resulting_stock' => $resultingStock[$line->productId()],
                ]);
            }

            // RF-23/RF-24: el documento original nace pendiente y sin folio
            // dentro de la misma transacción que la venta. La emisión simulada
            // se intenta después de confirmar, para no poder deshacer la venta.
            $sale->document()->create([
                'type' => $documentType,
                'folio' => null,
                'status' => DocumentStatus::pending,
                'issued_at' => null,
            ]);

            // RF-23/S-2: la factura guarda los datos tributarios del cliente y
            // el rango de folios usado, para poder reimprimirla tal cual.
            if ($documentType === DocumentType::invoice && $invoice !== null) {
                $sale->invoiceDetail()->create([
                    'rut' => $invoice->rut,
                    'business_name' => $invoice->businessName,
                    'business_activity' => $invoice->businessActivity,
                    'address' => $invoice->address,
                    'commune' => $invoice->commune,
                    'folio_from' => (int) config('pos.folio.invoice_from'),
                    'folio_to' => (int) config('pos.folio.invoice_to'),
                ]);
            }

            // RF-10: una fila del libro por cada línea controlada de la venta.
            if ($prescriptionModel !== null && $category === SaleCategory::controlled) {
                foreach ($cart->lines() as $line) {
                    if ($line->category() !== SaleCategory::controlled) {
                        continue;
                    }

                    ControlledRegisterEntry::query()->create([
                        'prescription_id' => $prescriptionModel->id,
                        'sale_id' => $sale->id,
                        'sale_line_id' => $createdLines[$line->productId()]->id,
                        'medication' => $line->name(),
                        'quantity' => $line->quantity(),
                        'customer_rut' => $customerRut,
                        'folio' => null,
                        'sold_at' => $sale->sold_at,
                    ]);
                }
            }

            // S12/RF-36: la autorización del descuento de cada línea queda
            // auditada con su autor, ligada a la SaleLine real de la venta.
            foreach ($cart->lines() as $line) {
                $authorizedBy = $lineDiscountAuthorizedBy[$line->productId()] ?? null;

                if ($line->discount() === null || $authorizedBy === null) {
                    continue;
                }

                $applied = $this->calculator->lineDiscount($line)->pesos();

                $this->audit->recordLineDiscountAuthorization(
                    line: $createdLines[$line->productId()],
                    authorizedBy: (int) $authorizedBy,
                    type: $line->discount()->type,
                    discountValue: $applied,
                    reason: $line->discount()->reason,
                );
            }

            // RF-11/RF-36: la reutilización autorizada queda auditada con su autor.
            if ($pharmacistId !== null) {
                $this->audit->record('sale.prescription_approved', $sale, $pharmacistId,
                    __($pharmacistByPin === null ? 'pos.rx_review.audit_panel' : 'pos.rx_review.audit_pin'));
                PrescriptionReview::query()->where('cashier_id', $register->user_id)->where('checkout_token', $token)->delete();
            }

            if ($alreadyUsed && $pharmacistId !== null && $prescription !== null) {
                $this->audit->record(
                    'sale.prescription_reuse',
                    $sale,
                    $pharmacistId,
                    "Receta {$prescription->number} reutilizada con autorización del Químico.",
                );
            }

            // RF-16/RF-36: el descuento sobre el tope queda auditado con su autor.
            if ($globalDiscount !== null && $discountAuthorizedBy !== null) {
                $this->audit->record(
                    'sale.discount_authorized',
                    $sale,
                    $discountAuthorizedBy,
                    __('pos.authorization.manual_audit'),
                );
            }

            return $sale->load('lines', 'document');
        }, 3);

        // RF-24: la venta ya está confirmada. La emisión se simula después, por
        // lo que su fallo no puede revertir la venta ni el stock.
        if ($sale->wasRecentlyCreated) {
            $this->issueDocument($sale);
        }

        return $sale;
    }

    /**
     * Emisión simulada posterior a la confirmación (RF-24).
     *
     * Sólo se captura el fallo esperado del simulador. Un error de base de datos
     * o de integridad se propaga: nunca se disfraza de documento pendiente.
     */
    private function issueDocument(Sale $sale): void
    {
        $document = $sale->document;

        if ($document === null) {
            return;
        }

        try {
            $this->documents->emit($document);
        } catch (EmissionFailed) {
            // El documento queda pendiente para reintento desde administración.
        }

        $sale->unsetRelation('document');
        $sale->load('lines', 'document');
    }

    /**
     * Totales que se cobrarían con el convenio o descuento manual vigentes.
     *
     * Lo usa la caja para validar el efectivo recibido antes de confirmar.
     */
    public function totalsFor(
        Cart $cart,
        ?int $conventionId,
        ?Discount $manualDiscount = null,
        ?int $discountAuthorizedBy = null,
        array $lineDiscountAuthorizedBy = [],
        ?string $benefitRut = null,
    ): ReceiptTotals {
        $this->assertLineDiscountsAuthorized($cart, $lineDiscountAuthorizedBy);

        [, $globalDiscount] = $this->resolveDiscount(
            $cart,
            $conventionId,
            $manualDiscount,
            $discountAuthorizedBy,
            $benefitRut,
        );

        return $this->calculator->totals($cart, (int) config('pos.iva_rate'), $globalDiscount);
    }

    /**
     * Revalida el tope acumulado de los descuentos de línea al cobrar (RF-14, RF-16).
     *
     * El descuento se autorizó sobre el carrito de ese momento; cambiar
     * cantidades o quitar líneas altera el ahorro sin volver a evaluar el tope.
     * Una línea con supervisor recordado conserva su autorización (RF-36);
     * las demás deben seguir dentro del tope o pedir supervisor de nuevo.
     *
     * @param  array<int, int>  $lineDiscountAuthorizedBy  id de producto => id del supervisor
     */
    private function assertLineDiscountsAuthorized(Cart $cart, array $lineDiscountAuthorizedBy): void
    {
        $subtotal = $this->calculator->subtotal($cart)->pesos();
        $totalLineDiscounts = $this->calculator->lineDiscounts($cart)->pesos();
        $cap = (int) config('pos.discount.general_cap_percent');

        /** @var CartLine $line */
        foreach ($cart->lines() as $line) {
            if ($line->discount() === null) {
                continue;
            }

            $decision = $this->discounts->evaluateLine(
                lineBase: $this->calculator->lineSubtotal($line)->pesos(),
                discount: $line->discount(),
                transactionSubtotal: $subtotal,
                otherLinesDiscountPesos: $totalLineDiscounts - $this->calculator->lineDiscount($line)->pesos(),
                generalCapPercent: $cap,
            );

            // RF-18: ningún descuento puede dejar la línea en cero o negativo.
            if ($decision->isRejected()) {
                throw new DiscountRejected(
                    'line_value',
                    __('pos.errors.discount_zero_total'),
                );
            }

            if ($decision->requiresSupervisor()
                && ! isset($lineDiscountAuthorizedBy[$line->productId()])) {
                throw new DiscountRejected(
                    'line_discount_supervisor',
                    __('pos.errors.line_discount_revalidate', [
                        'name' => $line->name(),
                        'cap' => $cap,
                    ]),
                );
            }
        }
    }

    /**
     * Resuelve el descuento que se aplicará a la venta (RF-13 a RF-18, RF-40).
     *
     * El convenio prevalece sobre el descuento manual y no consume el tope.
     * Sobre el tope sólo se aplica si un supervisor lo autorizó.
     *
     * @return array{0: ?Convention, 1: ?Discount, 2: bool}
     */
    private function resolveDiscount(
        Cart $cart,
        ?int $conventionId,
        ?Discount $manualDiscount,
        ?int $discountAuthorizedBy,
        ?string $benefitRut,
    ): array {
        $today = new DateTimeImmutable('now', new DateTimeZone((string) config('pos.timezone')));

        // RF-40: el convenio se valida al confirmar, no al armar el carrito.
        $convention = $conventionId === null
            ? null
            : $this->conventions->findForCheckout($conventionId, $today, $benefitRut);

        if ($convention === null && $manualDiscount === null) {
            return [null, null, false];
        }

        $snapshot = $convention === null ? null : new ConventionSnapshot(
            id: $convention->id,
            name: $convention->name,
            percent: $convention->percent,
            active: (bool) $convention->active,
            validFrom: $convention->valid_from->toDateTimeImmutable(),
            validUntil: $convention->valid_until->toDateTimeImmutable(),
        );

        $excludedBase = 0;
        if ($convention !== null && ! $convention->applies_to_controlled) {
            foreach ($cart->lines() as $line) {
                if ($line->category() === SaleCategory::controlled) {
                    $excludedBase += $this->calculator->lineSubtotal($line)->pesos() - $this->calculator->lineDiscount($line)->pesos();
                }
            }
        }
        $decision = $this->discounts->evaluate(
            subtotal: $this->calculator->subtotal($cart)->pesos(),
            discount: $manualDiscount,
            convention: $snapshot,
            generalCapPercent: (int) config('pos.discount.general_cap_percent'),
            today: $today,
            lineDiscountsPesos: $this->calculator->lineDiscounts($cart)->pesos(),
            excludedBase: $excludedBase,
        );

        if ($decision->isRejected()) {
            throw match ($decision->reason()) {
                'reason_required' => new DiscountRejected(
                    'discount_reason',
                    'Todo descuento manual exige un motivo.',
                ),
                default => new DiscountRejected(
                    'discount_value',
                    'El descuento no puede dejar el total en cero o negativo.',
                ),
            };
        }

        // RF-16: sobre el tope se exige autorización de supervisor.
        if ($decision->requiresSupervisor() && $discountAuthorizedBy === null) {
            throw new DiscountRejected(
                'discount_supervisor',
                'El descuento supera el tope del '
                    .(int) config('pos.discount.general_cap_percent')
                    .'%. Ingresa las credenciales de un supervisor para autorizarlo.',
            );
        }

        return [$convention, $decision->discount(), $decision->requiresSupervisor()];
    }

    /**
     * RF-28: mensaje específico cuando otra caja se adelantó.
     *
     * Sólo cubre el fallo del descuento condicional (la fila ya no alcanza); el
     * aviso previo de cambio de stock de S5 es otro flujo. La existencia se lee
     * dentro de la transacción, con la fila de producto ya bloqueada, y el
     * nombre es el que el cajero ve en el carrito.
     */
    private function shortageMessage(CartLine $line): string
    {
        $available = Product::query()
            ->whereKey($line->productId())
            ->withPosStock(LotStock::today())->first()?->pos_stock;

        return __('pos.errors.concurrent_stock', [
            'name' => $line->name(),
            'available' => max(0, (int) $available),
        ]);
    }

    /**
     * Bloquea las filas de producto involucradas en el carrito (RF-27, RF-28).
     *
     * El orden por `id` es estable entre cajas: dos carritos con productos
     * distintos pero cruzados toman los locks en el mismo orden, lo que evita
     * el deadlock clásico de bloqueos cruzados.
     *
     * @return array<int, int> existencia bloqueada antes de descontar, por id de producto
     */
    private function lockProducts(Cart $cart): array
    {
        $ids = array_map(
            fn (CartLine $line): int => $line->productId(),
            $cart->lines(),
        );

        sort($ids);

        $locked = Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()->keyBy('id');

        foreach ($cart->lines() as $line) {
            $product = $locked->get($line->productId());
            if ($product !== null && $product->sale_category !== $line->category()) {
                throw new InsufficientStock(__('pos.rx_review.category_changed'));
            }
        }

        return $locked->pluck('stock', 'id')->all();
    }

    /**
     * Turno abierto del cajero (RF-60).
     *
     * Se bloquea la fila para que un cierre simultáneo no pueda grabar el acta
     * mientras la venta todavía no se ha sellado con el turno.
     */
    private function openRegisterFor(?int $userId): CashRegister
    {
        $register = $userId === null ? null : CashRegister::query()
            ->where('user_id', $userId)
            ->whereNull('closed_at')
            ->orderByDesc('opened_at')
            ->lockForUpdate()
            ->first();

        if ($register === null) {
            throw new CashRegisterNotOpen(__('pos.cash_register.error.open_to_charge'));
        }

        return $register;
    }
}
