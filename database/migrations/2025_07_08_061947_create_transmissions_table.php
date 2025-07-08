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
        Schema::create('transmissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('municipality_id')->comment('ID de la municipalidad');
            $table->json('payload')->comment('Payload JSON enviado a MININTER');
            $table->integer('response_code')->nullable()->comment('Código HTTP de respuesta');
            $table->enum('status', ['SENT', 'FAILED'])->comment('Estado de la transmisión');
            $table->timestamp('sent_at')->comment('Fecha/hora de envío');
            $table->integer('retry_count')->default(0)->comment('Número de reintentos');
            $table->timestamps();

            // Relaciones
            $table->foreign('municipality_id')->references('id')->on('municipalities')->onDelete('cascade');

            // Índices
            $table->index(['municipality_id', 'status']);
            $table->index(['sent_at', 'status']);
            $table->index('retry_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transmissions');
    }
};
