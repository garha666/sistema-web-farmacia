<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\User;
use App\Pos\Enum\SaleCategory;
use App\Pos\Enum\StockMovementType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockReceiptService
{
    public function __construct(private readonly StockAdjustmentService $stock) {}

    public function receive(User $receiver, array $delivery, string $photoPath): StockReceipt
    {
        return DB::transaction(function () use ($receiver, $delivery, $photoPath): StockReceipt {
            $product = Product::query()->lockForUpdate()->findOrFail($delivery['product_id']);
            $controlled = $product->sale_category === SaleCategory::controlled;
            $receipt = new StockReceipt;
            $receipt->forceFill($delivery + [
                'receiver_id' => $receiver->id,
                'photo_path' => $photoPath,
                'controlled' => $controlled,
                'status' => $controlled ? 'pending' : 'received',
                'remaining_quantity' => $controlled ? null : $delivery['received_quantity'],
            ])->save();

            if (! $controlled) {
                $this->addStock($receipt, $product, $receiver);
            }

            return $receipt;
        }, 3);
    }

    public function decide(StockReceipt $receipt, User $reviewer, string $decision, string $reason): void
    {
        DB::transaction(function () use ($receipt, $reviewer, $decision, $reason): void {
            $locked = StockReceipt::query()->lockForUpdate()->findOrFail($receipt->id);
            abort_unless($locked->controlled && $locked->receiver_id !== $reviewer->id, 403);
            abort_unless($locked->status === 'pending', 409);

            $now = now(config('pos.timezone'));
            if ($decision === 'approved') {
                if ($locked->expires_on->toDateString() < $now->toDateString()) {
                    throw ValidationException::withMessages(['review' => __('receipts.expired')]);
                }
                $this->addStock($locked, $locked->product, $reviewer);
            }

            $locked->forceFill([
                'status' => $decision,
                'review_reason' => $reason,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => $now,
                'remaining_quantity' => $decision === 'approved' ? $locked->received_quantity : null,
            ])->save();
        }, 3);
    }

    private function addStock(StockReceipt $receipt, Product $product, User $actor): void
    {
        if ($receipt->received_quantity === 0) {
            return;
        }

        $this->stock->adjust($product, StockMovementType::in, $receipt->received_quantity,
            __('receipts.movement', ['id' => $receipt->id, 'lot' => $receipt->lot]), $actor->id);
    }
}
