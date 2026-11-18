<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\ControlledRegisterController;
use App\Http\Controllers\Admin\ConventionController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Dashboard\CashierDashboardController;
use App\Http\Controllers\Dashboard\ChemistDashboardController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\WarehouseDashboardController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

// T11 - RF-41: la raíz y /panel llevan a cada rol a su panel.
Route::middleware('auth')->group(function (): void {
    Route::get('/', [DashboardController::class, 'home'])->name('dashboard.home');
    Route::get('/panel', [DashboardController::class, 'home'])->name('dashboard.index');

    // T11 - RF-43: cada panel exige el rol que le corresponde.
    Route::get('/panel/admin', AdminDashboardController::class)
        ->middleware('role:admin')
        ->name('dashboard.admin');
    Route::get('/panel/quimico', ChemistDashboardController::class)
        ->middleware('role:quimico')
        ->name('dashboard.quimico');
    Route::get('/panel/tecnico', CashierDashboardController::class)
        ->middleware('role:tecnico')
        ->name('dashboard.tecnico');
    Route::get('/panel/bodeguero', WarehouseDashboardController::class)
        ->middleware('role:bodeguero')
        ->name('dashboard.bodeguero');
});

// T11 - RF-42: el Bodeguero no entra al POS ni a las pantallas de venta.
Route::prefix('pos')->name('pos.')->middleware(['auth', 'role:tecnico,quimico,admin'])->group(function (): void {
    Route::get('/', [PosController::class, 'index'])->name('index');
    Route::post('/cart', [PosController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{product}', [PosController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{product}', [PosController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [PosController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [PosController::class, 'store'])->name('checkout.store');

    Route::post('/discount', [PosController::class, 'applyDiscount'])->name('discount.apply');
    Route::delete('/discount', [PosController::class, 'removeDiscount'])->name('discount.remove');

    Route::post('/cart/{product}/discount', [PosController::class, 'applyLineDiscount'])->name('cart.discount');
    Route::delete('/cart/{product}/discount', [PosController::class, 'removeLineDiscount'])->name('cart.discount.remove');

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

        Route::get('/controlled-register', [ControlledRegisterController::class, 'index'])
            ->name('controlled-register.index');
        Route::get('/controlled-register/export', [ControlledRegisterController::class, 'export'])
            ->name('controlled-register.export');
    });
});
