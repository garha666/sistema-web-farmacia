<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedInteger('folio')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'folio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
