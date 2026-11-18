<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Panel del Químico (RF-52 a RF-55).
 *
 * En T11 sólo resuelve el aterrizaje; los indicadores llegan en T15.
 */
final class ChemistDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.quimico', ['navGroup' => 'quimico']);
    }
}
