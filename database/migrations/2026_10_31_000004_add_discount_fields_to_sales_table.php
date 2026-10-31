<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('discount_origin', 20)->nullable()->after('global_discount');
            $table->string('discount_reason', 255)->nullable()->after('discount_origin');
            $table->foreignId('discount_authorized_by')
                ->nullable()
                ->after('discount_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_authorized_by');
            $table->dropColumn(['discount_origin', 'discount_reason']);
        });
    }
};
