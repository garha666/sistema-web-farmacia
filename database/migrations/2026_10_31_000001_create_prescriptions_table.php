<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50);
            $table->string('prescriber_name');
            $table->string('prescriber_rut', 20);
            $table->date('issued_on');
            $table->timestamps();

            // RF-11: permite detectar la reutilización de una misma receta.
            $table->unique(['number', 'prescriber_rut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
