<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Dashboard\WarehousePanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel del Bodeguero (RF-63 a RF-67).
 *
 * Muestra el resumen del inventario, la prioridad de reposición, los
 * movimientos recientes, el formulario de ajuste y los accesos de lectura. Las
 * consultas viven en `WarehousePanelService`.
 */
final class WarehouseDashboardController extends Controller
{
    public function __invoke(Request $request, WarehousePanelService $panel): View
    {
        return view('dashboard.bodeguero', [
            'navGroup' => 'bodeguero',
            'products' => Product::query()->active()->orderBy('name')->get(),
            ...$panel->for(),
        ]);
    }
}
