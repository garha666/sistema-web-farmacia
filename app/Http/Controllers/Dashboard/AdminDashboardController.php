<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Panel del Administrador (RF-46 a RF-51).
 *
 * En T11 sólo resuelve el aterrizaje; los indicadores llegan en T14.
 */
final class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.admin', ['navGroup' => 'admin']);
    }
}
