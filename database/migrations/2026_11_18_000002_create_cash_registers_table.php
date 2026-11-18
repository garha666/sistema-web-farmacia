<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T11 - RF-56 a RF-59: turno de caja con su arqueo.
 *
 * El cierre graba el arqueo y los totales del turno (D5 del plan): es el acta
 * del cierre, no una tabla de resumen, así que una anulación posterior no puede
 * reescribir un turno cerrado (caso límite 7). El índice único parcial impide
 * una segunda caja abierta para el mismo usuario (S-9, RF-58); el builder de
 * Laravel no expresa la cláusula WHERE, por eso va en SQL crudo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('opening_amount');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            // Snapshot del arqueo, escrito una sola vez al cerrar.
            $table->unsignedInteger('total_sales')->nullable();
            $table->unsignedInteger('total_voids')->nullable();
            $table->unsignedInteger('cash_sales')->nullable();
            $table->unsignedInteger('cash_voids')->nullable();
            $table->integer('expected_cash')->nullable();
            $table->unsignedInteger('declared_cash')->nullable();
            $table->integer('difference')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'opened_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX cash_registers_open_unique
             ON cash_registers (user_id)
             WHERE closed_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
