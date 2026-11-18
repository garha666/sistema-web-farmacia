<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Panel del Bodeguero (RF-63 a RF-67).
 *
 * En T11 sólo resuelve el aterrizaje; el inventario llega en T17.
 */
final class WarehouseDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.bodeguero', ['navGroup' => 'bodeguero']);
    }
}
