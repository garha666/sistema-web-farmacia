<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('pharmacist_pin_hash')->nullable());

        Schema::create('prescription_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('checkout_token');
            $table->char('fingerprint', 64);
            $table->text('snapshot')->nullable();
            $table->foreignId('pharmacist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['cashier_id', 'checkout_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_reviews');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('pharmacist_pin_hash'));
    }
};
