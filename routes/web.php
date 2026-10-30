<?php

use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('pos.index');
});

Route::prefix('pos')->name('pos.')->group(function (): void {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::post('/cart', [PosController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{product}', [PosController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{product}', [PosController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [PosController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [PosController::class, 'store'])->name('checkout.store');

    Route::get('/sales/{sale}/receipt', [PosController::class, 'receipt'])->name('sales.receipt');
});
