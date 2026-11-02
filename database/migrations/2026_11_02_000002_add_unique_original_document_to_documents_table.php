<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * S9 - RF-24: como máximo un documento original por venta.
     *
     * El índice es parcial para no bloquear la nota de crédito, que también
     * referencia `sale_id` pero lleva `reversal_of_id`. Si en algún entorno ya
     * existieran duplicados, la migración falla sin borrar datos.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX documents_sale_original_unique
             ON documents (sale_id)
             WHERE reversal_of_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS documents_sale_original_unique');
    }
};
