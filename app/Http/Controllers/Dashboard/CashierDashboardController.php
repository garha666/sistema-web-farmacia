<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Panel del Técnico (RF-56, RF-57, RF-61, RF-62).
 *
 * En T11 sólo resuelve el aterrizaje; el turno llega en T13.
 */
final class CashierDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.tecnico', ['navGroup' => 'tecnico']);
    }
}
