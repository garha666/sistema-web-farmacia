<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('active_ingredient')->nullable();
            $table->string('barcode', 32)->nullable()->unique();
            $table->unsignedInteger('price')->default(0);
            $table->string('sale_category', 20)->default('free');
            $table->integer('stock')->default(0);
            $table->boolean('sellable')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('active_ingredient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
