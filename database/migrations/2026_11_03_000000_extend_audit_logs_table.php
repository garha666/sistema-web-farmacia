<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S12 (RF-36): la autorización de un descuento de línea guarda el tipo de
 * descuento y el monto aplicado. El producto y la venta se resuelven por el
 * subject polimórfico (`sale_lines`), así que no se duplica la referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('discount_type', 10)
                ->nullable()
                ->comment('"percent"|"amount"');
            $table->unsignedInteger('discount_value')
                ->nullable()
                ->comment('Monto aplicado en pesos');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
