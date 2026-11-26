<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conventions', function (Blueprint $table): void {
            $table->boolean('requires_membership')->default(false);
        });

        Schema::create('convention_memberships', function (Blueprint $table): void {
            $table->foreignId('convention_id')->constrained()->cascadeOnDelete();
            $table->string('rut', 10);
            $table->primary(['rut', 'convention_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convention_memberships');
        Schema::table('conventions', function (Blueprint $table): void {
            $table->dropColumn('requires_membership');
        });
    }
};
