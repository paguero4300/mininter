<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Cargar municipalidades maestras
        $this->call(MunicipalitySeeder::class);

        // Crear usuario administrador para panel FilamentPHP
        $this->call(AdminUserSeeder::class);
    }
}
