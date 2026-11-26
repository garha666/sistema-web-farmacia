<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bitácora de anulaciones y autorizaciones (RF-36).
 */
final class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.audit.index', [
            'search' => $search,
            'logs' => AuditLog::query()
                ->with(['user', 'auditable', 'saleLine'])
                ->search($search)
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
