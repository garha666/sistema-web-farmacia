<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Services\Inventory\StockReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class StockReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $warehouse = $request->user()->isBodeguero();
        $receipts = StockReceipt::query()->with(['product', 'receiver', 'reviewer']);

        if ($warehouse) {
            $receipts->where('receiver_id', $request->user()->id);
        } else {
            $receipts->where('controlled', true)->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END");
        }

        return view('inventory.receipts', [
            'warehouse' => $warehouse,
            'navGroup' => $warehouse ? 'bodeguero' : 'quimico',
            'receipts' => $receipts->latest('id')->paginate(15),
            'products' => $warehouse ? Product::query()->orderBy('name')->get(['id', 'name']) : [],
            'today' => now(config('pos.timezone'))->toDateString(),
        ]);
    }

    public function store(Request $request, StockReceiptService $service): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'lot' => ['required', 'string', 'max:50'],
            'expires_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(config('pos.timezone'))->toDateString()],
            'expected_quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'received_quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'photo' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:1536'],
        ], [], __('receipts.fields'));

        $path = $request->file('photo')->store('private/stock-receipts', 'local');
        if ($path === false) {
            throw ValidationException::withMessages(['photo' => __('receipts.photo_failed')]);
        }

        unset($data['photo']);
        try {
            $receipt = $service->receive($request->user(), $data, $path);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return to_route('inventory.receipts.index')->with('receipt_status', __('receipts.saved.'.$receipt->status));
    }

    public function update(Request $request, StockReceipt $receipt, StockReceiptService $service): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], __('receipts.fields'));

        $service->decide($receipt, $request->user(), $data['decision'], $data['reason']);

        return to_route('inventory.receipts.index')->with('receipt_status', __('receipts.saved.'.$data['decision']));
    }

    public function photo(Request $request, StockReceipt $receipt): StreamedResponse
    {
        $user = $request->user();
        abort_unless(($user->isBodeguero() && $user->id === $receipt->receiver_id)
            || ($user->isQuimico() && $receipt->controlled), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($receipt->photo_path), 404);

        return $disk->download($receipt->photo_path, 'recepcion-'.$receipt->id.'.'.pathinfo($receipt->photo_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
