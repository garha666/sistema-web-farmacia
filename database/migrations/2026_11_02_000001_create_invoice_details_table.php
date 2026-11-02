<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * S-2: datos tributarios del cliente para la factura simulada (RF-23).
     *
     * Van en una tabla 1:1 con la venta para no llenar `sales` de columnas que
     * sólo aplican cuando el documento es una factura.
     */
    public function up(): void
    {
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('rut', 20);
            $table->string('business_name', 255);
            $table->string('business_activity', 255);
            $table->string('address', 255);
            $table->string('commune', 100);
            $table->unsignedInteger('folio_from')->nullable();
            $table->unsignedInteger('folio_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
