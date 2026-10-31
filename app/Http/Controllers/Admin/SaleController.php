<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convention;
use App\Models\Sale;
use App\Models\User;
use App\Pos\Enum\DocumentStatus;
use App\Pos\Enum\PaymentMethod;
use App\Pos\Enum\SaleStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Historial de ventas con filtros y detalle (RF-37, RF-38).
 */
final class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'payment_method' => ['nullable', new Enum(PaymentMethod::class)],
            'status' => ['nullable', new Enum(SaleStatus::class)],
            'document_status' => ['nullable', new Enum(DocumentStatus::class)],
            'user_id' => ['nullable', 'integer'],
            'convention_id' => ['nullable', 'integer'],
        ]);

        $sales = $this->query($filters)
            ->with(['document', 'user', 'convention'])
            ->latest('sold_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.sales.index', [
            'sales' => $sales,
            'filters' => $filters,
            'cashiers' => User::query()->orderBy('name')->get(),
            'conventions' => Convention::query()->orderBy('name')->get(),
            'methods' => PaymentMethod::cases(),
            'statuses' => SaleStatus::cases(),
            'documentStatuses' => DocumentStatus::cases(),
        ]);
    }

    public function show(Sale $sale): View
    {
        $sale->load([
            'lines',
            'document',
            'reversal',
            'convention',
            'user',
            'auditLogs.user',
        ]);

        return view('admin.sales.show', [
            'sale' => $sale,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Sale>
     */
    private function query(array $filters): Builder
    {
        $query = Sale::query();

        if (! empty($filters['from'])) {
            $query->whereDate('sold_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('sold_at', '<=', $filters['to']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['convention_id'])) {
            $query->where('convention_id', $filters['convention_id']);
        }

        if (! empty($filters['document_status'])) {
            $query->whereHas('document', function (Builder $document) use ($filters): void {
                $document->where('status', $filters['document_status']);
            });
        }

        return $query;
    }
}
