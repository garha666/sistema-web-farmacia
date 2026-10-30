<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('line_discounts')->default(0);
            $table->unsignedInteger('global_discount')->default(0);
            $table->unsignedInteger('net');
            $table->unsignedInteger('iva');
            $table->unsignedInteger('total');
            $table->string('payment_method', 20);
            $table->unsignedInteger('received_amount')->nullable();
            $table->unsignedInteger('change_amount')->nullable();
            $table->string('customer_rut', 20)->nullable();
            $table->string('status', 20)->default('confirmed');
            $table->timestamp('sold_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('sold_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
