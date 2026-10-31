<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ConventionController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\SaleController;
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

    // Sólo Químico (supervisor) y Admin pueden anular (RF-32).
    Route::post('/sales/{sale}/void', [PosController::class, 'void'])
        ->middleware(['auth', 'role:quimico,admin'])
        ->name('sales.void');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    // Lectura compartida: Químico (supervisor) y Admin.
    Route::middleware('role:quimico,admin')->group(function (): void {
        Route::get('/conventions', [ConventionController::class, 'index'])->name('conventions.index');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });

    // Gestión completa: sólo Admin.
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/conventions/create', [ConventionController::class, 'create'])->name('conventions.create');
        Route::post('/conventions', [ConventionController::class, 'store'])->name('conventions.store');
        Route::get('/conventions/{convention}/edit', [ConventionController::class, 'edit'])->name('conventions.edit');
        Route::patch('/conventions/{convention}', [ConventionController::class, 'update'])->name('conventions.update');
        Route::delete('/conventions/{convention}', [ConventionController::class, 'destroy'])->name('conventions.destroy');

        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('/documents/{document}/retry', [DocumentController::class, 'retry'])->name('documents.retry');
    });
});
