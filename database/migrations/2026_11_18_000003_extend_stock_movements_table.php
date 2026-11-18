<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T11 - RF-65 y RF-74: el movimiento de stock pasa a ser trazable.
 *
 * `reason` sólo distinguía venta de anulación; el inventario necesita además
 * entradas y salidas manuales, autor y existencia resultante. Los dos valores
 * existentes (`sale`, `void`) ya son válidos, así que renombrar la columna no
 * reinterpreta ningún dato. `resulting_stock` se rellena hacia atrás con la
 * suma acumulada por producto, en el mismo orden en que se registraron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->renameColumn('reason', 'type');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('note', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('resulting_stock')->nullable();
        });

        DB::statement(
            'UPDATE stock_movements AS m
             SET resulting_stock = s.total
             FROM (
                 SELECT id, SUM(quantity) OVER (PARTITION BY product_id ORDER BY id) AS total
                 FROM stock_movements
             ) AS s
             WHERE m.id = s.id'
        );

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('resulting_stock')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['note', 'resulting_stock']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->renameColumn('type', 'reason');
        });
    }
};
