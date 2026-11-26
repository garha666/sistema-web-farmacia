<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PrescriptionReview;
use App\Services\Dashboard\ChemistPanelService;
use App\Services\Pos\PrescriptionReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
    public function __invoke(Request $request, ChemistPanelService $panel, PrescriptionReviewService $reviews): View
    {
        return view('dashboard.quimico', [
            'navGroup' => 'quimico',
            'prescriptionReviews' => $reviews->pending(),
            ...$panel->for($request->user()),
        ]);
    }

    public function pending(PrescriptionReviewService $reviews): JsonResponse
    {
        $pending = $reviews->pending();

        return response()->json([
            'count' => $pending->total(),
            'html' => view('dashboard.partials.pending-prescriptions', ['prescriptionReviews' => $pending])->render(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function approve(Request $request, PrescriptionReview $review, PrescriptionReviewService $reviews): RedirectResponse
    {
        $data = $request->validate(['fingerprint' => ['required', 'string', 'size:64']]);
        if (! $reviews->approve($review, $request->user(), $data['fingerprint'])) {
            return to_route('dashboard.quimico')->withErrors(['review' => __('pos.rx_review.changed')]);
        }

        return to_route('dashboard.quimico')->with('prescription_approved', true);
    }
}
