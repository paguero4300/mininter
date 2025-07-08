<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->comment('Nombre de la municipalidad');
            $table->string('token_gps', 32)->unique()->comment('Token GPS para consultar GPServer');
            $table->string('ubigeo', 6)->comment('Código UBIGEO del distrito');
            $table->enum('tipo', ['SERENAZGO', 'POLICIAL'])->comment('Tipo de vehículo');
            $table->string('codigo_comisaria', 6)->nullable()->comment('Código comisaría (solo POLICIAL)');
            $table->boolean('active')->default(true)->comment('Estado activo/inactivo');
            $table->timestamps();

            // Índices
            $table->index(['tipo', 'active']);
            $table->index('ubigeo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
