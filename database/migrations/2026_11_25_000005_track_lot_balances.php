<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table): void {
            $table->unsignedInteger('remaining_quantity')->nullable();
            $table->index(['product_id', 'expires_on', 'id'], 'stock_receipts_lot_index');
        });

        Schema::create('sale_line_lots', function (Blueprint $table): void {
            $table->foreignId('sale_line_id')->constrained();
            $table->foreignId('stock_receipt_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->primary(['sale_line_id', 'stock_receipt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_line_lots');
        Schema::table('stock_receipts', function (Blueprint $table): void {
            $table->dropIndex('stock_receipts_lot_index');
            $table->dropColumn('remaining_quantity');
        });
    }
};
