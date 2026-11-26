<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ControlledRegisterEntry;
use App\Models\PrescriptionReview;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Libro de controlados: consulta y exportación CSV mensual (RF-10, supuesto S-4).
 */
final class ControlledRegisterController extends Controller
{
    public function index(Request $request): View
    {
        return $this->register($this->filters($request));
    }

    public function clinical(Request $request): View
    {
        return $this->register($this->filters($request), clinical: true);
    }

    private function register(array $filters, bool $clinical = false): View
    {
        $entries = $this->query($filters)
            ->with(['prescription', 'sale'])
            ->latest('sold_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.controlled-register.index', [
            'entries' => $entries,
            'filters' => $filters,
            'clinical' => $clinical,
            'approvedReviews' => $clinical ? $this->filterDates(
                PrescriptionReview::query()->whereNotNull('pharmacist_id'), $filters, 'updated_at'
            )->latest('updated_at')->latest('id')->paginate(15, ['*'], 'reviews_page')->withQueryString() : null,
            'dispensedApprovals' => $clinical ? $this->filterDates(
                AuditLog::query()->with(['auditable' => fn (MorphTo $relation) => $relation->morphWith([Sale::class => ['prescription']])])
                    ->where('action', 'sale.prescription_approved')
                    ->whereHasMorph('auditable', [Sale::class], fn (Builder $query) => $query->whereHas('prescription')),
                $filters, 'created_at'
            )->latest('id')->paginate(15, ['*'], 'approvals_page')->withQueryString() : null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        $entries = $this->query($filters)
            ->orderBy('sold_at')
            ->orderBy('id')
            ->cursor();

        $filename = 'libro-controlados-'.now()->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['RUT', 'Folio', 'Fecha', 'Medicamento', 'Cantidad']);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->customer_rut,
                    $entry->folio,
                    $entry->sold_at->format('d-m-Y'),
                    $entry->medication,
                    $entry->quantity,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ControlledRegisterEntry>
     */
    private function query(array $filters): Builder
    {
        return $this->filterDates(ControlledRegisterEntry::query(), $filters, 'sold_at');
    }

    private function filterDates(Builder $query, array $filters, string $column): Builder
    {
        return $query
            ->when(
                ! empty($filters['from']),
                fn (Builder $query) => $query->whereDate($column, '>=', $filters['from']),
            )
            ->when(
                ! empty($filters['to']),
                fn (Builder $query) => $query->whereDate($column, '<=', $filters['to']),
            );
    }
}
