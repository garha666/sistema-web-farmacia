<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_clients', function (Blueprint $table): void {
            $table->string('rut', 10)->primary();
            $table->string('name', 120);
        });

        Schema::table('convention_memberships', function (Blueprint $table): void {
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('convention_memberships', fn (Blueprint $table) => $table->dropConstrainedForeignId('authorized_by'));
        Schema::dropIfExists('pos_clients');
    }
};
