<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T11 - RF-56, RF-59, RF-61: cada venta pertenece al turno en que se cobró.
 *
 * La pertenencia se fija al cobrar y no se deduce de una ventana de fechas:
 * así una venta de las 23:59:59 no cae en dos turnos y una anulación posterior
 * no reescribe el cierre (caso límite 7). Nullable porque las ventas históricas
 * anteriores a este módulo no tienen turno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cash_register_id')->nullable()->constrained()->nullOnDelete();
            $table->index('cash_register_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['cash_register_id']);
            $table->dropConstrainedForeignId('cash_register_id');
        });
    }
};
