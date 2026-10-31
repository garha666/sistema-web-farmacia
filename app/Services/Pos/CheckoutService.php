<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\ControlledRegisterEntry;
use App\Models\Convention;
use App\Models\Document;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Pos\Calculator\PriceCalculator;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleCategory;
use App\Pos\Enum\SaleStatus;
use App\Pos\Exception\DiscountRejected;
use App\Pos\Exception\EmptyCart;
use App\Pos\Exception\InsufficientStock;
use App\Pos\Exception\PrescriptionRejected;
use App\Pos\Policy\DiscountPolicy;
use App\Pos\Policy\PrescriptionPolicy;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use App\Pos\Value\ConventionSnapshot;
use App\Pos\Value\Discount;
use App\Pos\Value\PrescriptionInput;
use App\Pos\Value\ReceiptTotals;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

/**
 * Confirma la venta en una sola transacción (RF-22).
 *
 * Todo o nada: stock, venta, líneas, documento y movimientos. Si algo falla,
 * el carrito queda intacto (RF-6).
 */
final class CheckoutService
{
    public function __construct(
        private readonly PriceCalculator $calculator,
        private readonly ConventionService $conventions,
        private readonly PrescriptionPolicy $prescriptions,
        private readonly DiscountPolicy $discounts,
        private readonly AuditService $audit,
    ) {
    }

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
    ): Sale {
        if ($cart->isEmpty()) {
            throw new EmptyCart('El carrito está vacío.');
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

        // RF-11: receta ya usada en otra venta; exige supervisor.
        if ($result->requiresSupervisor() && $reuseAuthorizedBy === null) {
            throw new PrescriptionRejected(
                ['number' => 'prescription_already_used'],
                requiresSupervisor: true,
            );
        }

        // RF-16/RF-17/RF-40: el descuento se re-evalúa al confirmar. El convenio
        // prevalece sobre el descuento manual; sobre el tope exige supervisor.
        [$convention, $globalDiscount, $discountNeedsSupervisor] = $this->resolveDiscount(
            $cart,
            $conventionId,
            $manualDiscount,
            $discountAuthorizedBy,
        );

        $totals = $this->calculator->totals($cart, (int) config('pos.iva_rate'), $globalDiscount);

        // 3 intentos: reintenta si PostgreSQL detecta un deadlock (SQLSTATE 40P01)
        // o una espera de lock agotada por concurrencia entre cajas (RF-28).
        return DB::transaction(function () use ($cart, $method, $receivedAmount, $customerRut, $token, $userId, $convention, $totals, $prescription, $reuseAuthorizedBy, $alreadyUsed, $category, $globalDiscount, $discountAuthorizedBy, $discountNeedsSupervisor): Sale {
            $existing = Sale::query()->where('token', $token)->first();

            if ($existing !== null) {
                return $existing->load('lines', 'document');
            }

            // RF-27/RF-28: bloqueo pesimista de las filas de producto en orden
            // de id (mismo orden que VoidSaleService) para serializar la venta
            // de la última unidad y evitar deadlocks.
            $this->lockProducts($cart);

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
            }

            foreach ($cart->lines() as $line) {
                $affected = Product::query()
                    ->whereKey($line->productId())
                    ->where('stock', '>=', $line->quantity())
                    ->decrement('stock', $line->quantity());

                if ($affected === 0) {
                    throw new InsufficientStock("Sin stock suficiente para el producto {$line->productId()}.");
                }
            }

            $changeAmount = $method->requiresReceivedAmount() && $receivedAmount !== null
                ? max(0, $receivedAmount - $totals->total()->pesos())
                : null;

            $sale = Sale::query()->create([
                'token' => $token,
                'subtotal' => $totals->subtotal()->pesos(),
                'line_discounts' => $totals->lineDiscounts()->pesos(),
                'global_discount' => $totals->globalDiscount()->pesos(),
                'discount_origin' => $globalDiscount?->origin,
                'discount_reason' => $globalDiscount?->reason,
                'discount_authorized_by' => $discountNeedsSupervisor ? $discountAuthorizedBy : null,
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
            ]);

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

                StockMovement::query()->create([
                    'product_id' => $line->productId(),
                    'sale_id' => $sale->id,
                    'quantity' => -$line->quantity(),
                    'reason' => 'sale',
                ]);
            }

            $folio = $this->nextFolio(DocumentType::receipt);

            $sale->document()->create([
                'type' => DocumentType::receipt,
                'folio' => $folio,
                'status' => $folio === null ? DocumentStatus::pending : DocumentStatus::issued,
                'issued_at' => $folio === null ? null : now(),
            ]);

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
                        'folio' => $folio,
                        'sold_at' => $sale->sold_at,
                    ]);
                }
            }

            // RF-11/RF-36: la reutilización autorizada queda auditada con su autor.
            if ($alreadyUsed && $reuseAuthorizedBy !== null && $prescription !== null) {
                $this->audit->record(
                    'sale.prescription_reuse',
                    $sale,
                    $reuseAuthorizedBy,
                    "Receta {$prescription->number} reutilizada con autorización de supervisor.",
                );
            }

            // RF-16/RF-36: el descuento sobre el tope queda auditado con su autor.
            if ($discountNeedsSupervisor && $discountAuthorizedBy !== null) {
                $this->audit->record(
                    'sale.discount_authorized',
                    $sale,
                    $discountAuthorizedBy,
                    'Descuento manual sobre el tope autorizado por supervisor.',
                );
            }

            return $sale->load('lines', 'document');
        }, 3);
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
    ): ReceiptTotals {
        [, $globalDiscount] = $this->resolveDiscount(
            $cart,
            $conventionId,
            $manualDiscount,
            $discountAuthorizedBy,
        );

        return $this->calculator->totals($cart, (int) config('pos.iva_rate'), $globalDiscount);
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
    ): array {
        $today = new DateTimeImmutable('now', new DateTimeZone((string) config('pos.timezone')));

        // RF-40: el convenio se valida al confirmar, no al armar el carrito.
        $convention = $conventionId === null
            ? null
            : $this->conventions->findApplicable($conventionId, $today);

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

        $decision = $this->discounts->evaluate(
            subtotal: $this->calculator->subtotal($cart)->pesos(),
            discount: $manualDiscount,
            convention: $snapshot,
            generalCapPercent: (int) config('pos.discount.general_cap_percent'),
            today: $today,
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
     * Bloquea las filas de producto involucradas en el carrito (RF-27, RF-28).
     *
     * El orden por `id` es estable entre cajas: dos carritos con productos
     * distintos pero cruzados toman los locks en el mismo orden, lo que evita
     * el deadlock clásico de bloqueos cruzados.
     */
    private function lockProducts(Cart $cart): void
    {
        $ids = array_map(
            fn (CartLine $line): int => $line->productId(),
            $cart->lines(),
        );

        sort($ids);

        Product::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Siguiente folio del rango configurado; null si el rango se agotó (RF-24).
     */
    private function nextFolio(DocumentType $type): ?int
    {
        $range = $type === DocumentType::receipt
            ? ['from' => (int) config('pos.folio.receipt_from'), 'to' => (int) config('pos.folio.receipt_to')]
            : ['from' => (int) config('pos.folio.invoice_from'), 'to' => (int) config('pos.folio.invoice_to')];

        $last = Document::query()
            ->where('type', $type)
            ->whereNotNull('folio')
            ->max('folio');

        $next = $last === null ? $range['from'] : ((int) $last) + 1;

        return $next > $range['to'] ? null : $next;
    }
}
