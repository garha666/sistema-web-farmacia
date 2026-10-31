<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('controlled_register', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_line_id')->unique()->constrained()->restrictOnDelete();
            $table->string('medication');
            $table->unsignedInteger('quantity');
            $table->string('customer_rut', 20)->nullable();
            $table->unsignedInteger('folio')->nullable();
            $table->timestamp('sold_at');
            $table->timestamps();

            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controlled_register');
    }
};
