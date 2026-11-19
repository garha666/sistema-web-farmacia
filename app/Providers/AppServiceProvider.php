<?php

namespace App\Providers;

use App\Pos\Contract\DocumentEmitter;
use App\Pos\Emission\SimulatedDocumentEmitter;
use Illuminate\Support\Facades\URL;
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
        // T26 - RNF-27: en producción las URLs generadas van por HTTPS, para
        // que redirecciones y enlaces no se degraden a HTTP tras el proxy.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
