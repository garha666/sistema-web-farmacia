<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Pos\Enum\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listado consultable de movimientos de stock (RF-65, RF-74).
 *
 * Muestra producto, tipo, cantidad, existencia resultante, motivo, autor y
 * fecha, paginados del más reciente al más antiguo. El filtro por tipo es
 * opcional: un valor desconocido se ignora y muestra todos.
 */
final class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        $raw = $request->query('type');
        $type = is_string($raw) ? StockMovementType::tryFrom($raw) : null;

        return view('inventory.movements.index', [
            'navGroup' => $request->user()?->role === 'bodeguero' ? 'bodeguero' : 'admin',
            'selectedType' => $type?->value ?? '',
            'filterTypes' => [StockMovementType::in, StockMovementType::out, StockMovementType::sale, StockMovementType::void],
            'movements' => StockMovement::query()
                ->with(['product', 'user'])
                ->when($type !== null, fn (Builder $query) => $query->where('type', $type->value))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }
}
