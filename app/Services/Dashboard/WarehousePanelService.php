<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Product;
use App\Models\StockMovement;
use App\Pos\Value\InventorySnapshot;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Datos del panel del Bodeguero (RF-63 a RF-65).
 *
 * Reúne el resumen del inventario, la prioridad de reposición y los últimos
 * movimientos. Las consultas se acotan al día en la zona horaria del negocio.
 */
final class WarehousePanelService
{
    /**
     * @return array{
     *     summary: InventorySnapshot,
     *     replenishment: Collection<int, Product>,
     *     movements: Collection<int, StockMovement>
     * }
     */
    public function for(?DateTimeImmutable $day = null): array
    {
        $today = $day ?? CarbonImmutable::now(config('pos.timezone'));

        return [
            'summary' => $this->summary($today->format('Y-m-d')),
            'replenishment' => $this->replenishment(),
            'movements' => $this->movements(),
        ];
    }

    private function summary(string $date): InventorySnapshot
    {
        return new InventorySnapshot(
            active: Product::query()->active()->count(),
            belowMinimum: Product::query()->active()->belowMinimum()->count(),
            outOfStock: Product::query()->active()->outOfStock()->count(),
            movementsToday: StockMovement::query()->whereDate('created_at', $date)->count(),
        );
    }

    /**
     * Productos activos bajo el mínimo, de menor a mayor existencia (RF-64).
     *
     * @return Collection<int, Product>
     */
    private function replenishment(): Collection
    {
        return Product::query()
            ->active()
            ->belowMinimum()
            ->orderBy('stock')
            ->orderBy('name')
            ->get();
    }

    /**
     * Últimos movimientos con producto, tipo, motivo, autor y fecha (RF-65).
     *
     * @return Collection<int, StockMovement>
     */
    private function movements(): Collection
    {
        return StockMovement::query()
            ->with(['product', 'user'])
            ->latest('id')
            ->limit(10)
            ->get();
    }
}
