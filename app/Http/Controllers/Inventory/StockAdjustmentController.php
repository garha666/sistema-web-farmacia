<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Models\Product;
use App\Pos\Enum\StockMovementType;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;

/**
 * Registro de una entrada o salida de stock (RF-66, RF-73).
 *
 * La validación de forma vive en `AdjustStockRequest` y la regla de negocio en
 * `StockAdjustmentService`. El controlador sólo traduce un rechazo en un
 * mensaje de error que nombra el motivo.
 */
final class StockAdjustmentController extends Controller
{
    public function store(AdjustStockRequest $request, StockAdjustmentService $adjustments): RedirectResponse
    {
        $data = $request->validated();

        $product = Product::query()->findOrFail($data['product_id']);

        $result = $adjustments->adjust(
            $product,
            StockMovementType::from($data['type']),
            (int) $data['quantity'],
            (string) $data['reason'],
            $request->user()?->getKey(),
        );

        if (! $result->accepted()) {
            return back()
                ->withInput()
                ->withErrors([
                    'quantity' => __('pos.panel.bodeguero.error.'.$result->rejection(), [
                        'available' => $result->available(),
                    ]),
                ]);
        }

        return redirect()
            ->route('dashboard.bodeguero')
            ->with('success', __('pos.panel.bodeguero.adjusted'));
    }
}
