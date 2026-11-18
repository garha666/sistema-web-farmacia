<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\AdminPanelService;
use Illuminate\View\View;

/**
 * Panel del Administrador (RF-46 a RF-51).
 *
 * Reúne el resumen del día, los documentos por resolver, las alertas de
 * inventario, la auditoría reciente, los convenios por vencer y los cortes de
 * caja. Las consultas viven en `AdminPanelService`.
 */
final class AdminDashboardController extends Controller
{
    public function __invoke(AdminPanelService $panel): View
    {
        return view('dashboard.admin', [
            'navGroup' => 'admin',
            ...$panel->for(),
        ]);
    }
}
