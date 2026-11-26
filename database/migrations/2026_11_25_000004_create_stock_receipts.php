<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('receiver_id')->constrained('users');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->string('lot', 50);
            $table->date('expires_on');
            $table->unsignedInteger('expected_quantity');
            $table->unsignedInteger('received_quantity');
            $table->string('photo_path');
            $table->boolean('controlled');
            $table->string('status', 15);
            $table->string('review_reason', 500)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['controlled', 'status']);
            $table->index('receiver_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipts');
    }
};
