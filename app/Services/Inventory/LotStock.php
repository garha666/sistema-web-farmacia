<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\SaleLine;
use App\Models\StockReceipt;

final class LotStock
{
    public static function today(): string
    {
        return now(config('pos.timezone'))->toDateString();
    }

    /** Caller holds the product lock. Null means insufficient eligible stock. */
    public function consume(int $productId, int $stock, int $quantity, ?string $saleDate): ?array
    {
        $lots = StockReceipt::query()->where('product_id', $productId)
            ->where('remaining_quantity', '>', 0)->orderBy('expires_on')->orderBy('id')->get();

        $legacy = $stock - $lots->sum('remaining_quantity');
        $eligible = $saleDate === null ? $lots : $lots->filter(
            fn (StockReceipt $lot): bool => $lot->expires_on->toDateString() >= $saleDate,
        );
        if ($quantity > $legacy + $eligible->sum('remaining_quantity')) {
            return null;
        }

        $left = $saleDate === null ? max(0, $quantity - $legacy) : $quantity;
        $allocations = [];
        foreach ($eligible as $lot) {
            if ($left === 0) {
                break;
            }
            $used = min($left, $lot->remaining_quantity);
            $lot->decrement('remaining_quantity', $used);
            $allocations[$lot->id] = ['quantity' => $used];
            $left -= $used;
        }

        return $allocations;
    }

    /** Caller holds the product lock. */
    public function restore(SaleLine $line): void
    {
        foreach ($line->lots()->get() as $lot) {
            $lot->increment('remaining_quantity', $lot->pivot->quantity);
        }
    }
}
