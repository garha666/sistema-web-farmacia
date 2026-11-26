<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->string('close_reason', 500)->nullable();
            $table->json('closed_payment_breakdown')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->dropColumn(['close_reason', 'closed_payment_breakdown']);
        });
    }
};
