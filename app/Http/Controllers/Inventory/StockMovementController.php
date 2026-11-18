<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Listado consultable de movimientos de stock (RF-65, RF-74).
 *
 * Muestra producto, tipo, cantidad, existencia resultante, motivo, autor y
 * fecha, paginados del más reciente al más antiguo.
 */
final class StockMovementController extends Controller
{
    public function index(Request $request): View
    {
        return view('inventory.movements.index', [
            'navGroup' => $request->user()?->role === 'bodeguero' ? 'bodeguero' : 'admin',
            'movements' => StockMovement::query()
                ->with(['product', 'user'])
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
