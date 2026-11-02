<?php

namespace App\Providers;

use App\Pos\Contract\DocumentEmitter;
use App\Pos\Emission\SimulatedDocumentEmitter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // D-1/RF-24: la emisión tributaria es simulada y sustituible en pruebas.
        $this->app->bind(DocumentEmitter::class, SimulatedDocumentEmitter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
