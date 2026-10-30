<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\Document;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Pos\Calculator\PriceCalculator;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\DocumentType;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use App\Pos\Exception\EmptyCart;
use App\Pos\Exception\InsufficientStock;
use App\Pos\Value\Cart;
use App\Pos\Value\CartLine;
use Illuminate\Support\Facades\DB;

/**
 * Confirma la venta en una sola transacción (RF-22).
 *
 * Todo o nada: stock, venta, líneas, documento y movimientos. Si algo falla,
 * el carrito queda intacto (RF-6).
 */
final class CheckoutService
{
    public function __construct(private readonly PriceCalculator $calculator)
    {
    }

    public function checkout(
        Cart $cart,
        PaymentMethod $method,
        ?int $receivedAmount,
        ?string $customerRut,
        string $token,
        ?int $userId = null,
    ): Sale {
        if ($cart->isEmpty()) {
            throw new EmptyCart('El carrito está vacío.');
        }

        $totals = $this->calculator->totals($cart, (int) config('pos.iva_rate'));

        // 3 intentos: reintenta si PostgreSQL detecta un deadlock (SQLSTATE 40P01)
        // o una espera de lock agotada por concurrencia entre cajas (RF-28).
        return DB::transaction(function () use ($cart, $method, $receivedAmount, $customerRut, $token, $userId, $totals): Sale {
            $existing = Sale::query()->where('token', $token)->first();

            if ($existing !== null) {
                return $existing->load('lines', 'document');
            }

            // RF-27/RF-28: bloqueo pesimista de las filas de producto en orden
            // de id (mismo orden que VoidSaleService) para serializar la venta
            // de la última unidad y evitar deadlocks.
            $this->lockProducts($cart);

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
                'net' => $totals->net()->pesos(),
                'iva' => $totals->iva()->pesos(),
                'total' => $totals->total()->pesos(),
                'payment_method' => $method,
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount,
                'customer_rut' => $customerRut,
                'status' => SaleStatus::confirmed,
                'sold_at' => now(),
                'user_id' => $userId,
            ]);

            /** @var CartLine $line */
            foreach ($cart->lines() as $line) {
                $sale->lines()->create([
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

            return $sale->load('lines', 'document');
        }, 3);
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
