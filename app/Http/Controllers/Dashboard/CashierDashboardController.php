<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\CashierPanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel del Técnico (RF-56, RF-57, RF-61, RF-62).
 *
 * Muestra el estado del turno y el formulario de apertura cuando no hay caja.
 */
final class CashierDashboardController extends Controller
{
    public function __invoke(Request $request, CashierPanelService $panel): View
    {
        return view('dashboard.tecnico', [
            'navGroup' => 'tecnico',
            ...$panel->for($request->user()),
        ]);
    }
}
