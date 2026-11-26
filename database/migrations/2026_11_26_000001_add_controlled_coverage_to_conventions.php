<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('conventions', fn (Blueprint $table) => $table->boolean('applies_to_controlled')->default(false));
    }

    public function down(): void
    {
        Schema::table('conventions', fn (Blueprint $table) => $table->dropColumn('applies_to_controlled'));
    }
};
