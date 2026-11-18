<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ChemistPanelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel del Químico (RF-52 a RF-55).
 *
 * Muestra el resumen del día, las autorizaciones propias, las anulaciones de
 * hoy y salidas de lectura a convenios y auditoría. Las consultas viven en
 * `ChemistPanelService`.
 */
final class ChemistDashboardController extends Controller
{
    public function __invoke(Request $request, ChemistPanelService $panel): View
    {
        return view('dashboard.quimico', [
            'navGroup' => 'quimico',
            ...$panel->for($request->user()),
        ]);
    }
}
