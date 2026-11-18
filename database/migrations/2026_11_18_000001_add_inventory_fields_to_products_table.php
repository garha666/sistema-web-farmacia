<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T11 - RF-48, RF-63, RF-72: umbral de reposición y baja de catálogo.
 *
 * `min_stock` es el umbral de RF-48/S-11 y `active` separa «fuera de catálogo»
 * (RF-72) de «no vendible» (RF-70), que son estados distintos: el primero sale
 * de la búsqueda de la caja, el segundo sólo impide venderlo de nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('min_stock')->default(0);
            $table->boolean('active')->default(true);
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['active']);
            $table->dropColumn(['min_stock', 'active']);
        });
    }
};
