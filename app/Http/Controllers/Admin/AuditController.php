<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\View\View;

/**
 * Bitácora de anulaciones y autorizaciones (RF-36).
 */
final class AuditController extends Controller
{
    public function index(): View
    {
        return view('admin.audit.index', [
            'logs' => AuditLog::query()
                ->with(['user', 'auditable'])
                ->latest('id')
                ->paginate(30),
        ]);
    }
}
