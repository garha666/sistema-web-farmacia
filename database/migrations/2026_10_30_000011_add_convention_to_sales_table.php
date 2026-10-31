<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // RF-40: la venta conserva el convenio, su porcentaje y el ahorro
            // (global_discount) aunque el convenio se desactive después.
            $table->foreignId('convention_id')
                ->nullable()
                ->after('customer_rut')
                ->constrained('conventions')
                ->nullOnDelete();

            $table->string('convention_name', 100)->nullable()->after('convention_id');
            $table->decimal('convention_percent', 5, 2)->nullable()->after('convention_name');

            $table->index('convention_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['convention_id']);
            $table->dropConstrainedForeignId('convention_id');
            $table->dropColumn(['convention_name', 'convention_percent']);
        });
    }
};
