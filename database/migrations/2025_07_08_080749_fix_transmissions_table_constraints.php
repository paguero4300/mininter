<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el campo sent_at para permitir NULL
        Schema::table('transmissions', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->change();
        });

        // Para SQLite, usamos una estrategia más simple sin recrear la columna
        if (DB::getDriverName() === 'sqlite') {
            // SQLite no soporta bien ENUM, así que ya es flexible
            // Solo necesitamos asegurar que el campo puede aceptar PENDING
            DB::statement("UPDATE transmissions SET status = 'SENT' WHERE status NOT IN ('SENT', 'FAILED', 'PENDING')");
        } else {
            // Para MySQL/PostgreSQL, modificar el enum
            DB::statement("ALTER TABLE transmissions MODIFY status ENUM('PENDING', 'SENT', 'FAILED') DEFAULT 'PENDING'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver el campo sent_at a NOT NULL
        Schema::table('transmissions', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable(false)->change();
        });

        // Limpiar registros PENDING y volver al enum original
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("UPDATE transmissions SET status = 'FAILED' WHERE status = 'PENDING'");
        } else {
            // Primero limpiar registros PENDING
            DB::statement("UPDATE transmissions SET status = 'FAILED' WHERE status = 'PENDING'");
            // Luego cambiar el enum
            DB::statement("ALTER TABLE transmissions MODIFY status ENUM('SENT', 'FAILED') DEFAULT 'FAILED'");
        }
    }
};
