<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario administrador para panel FilamentPHP
        User::firstOrCreate(
            ['email' => 'admin@mininter.gps'],
            [
                'name' => 'Administrador MININTER GPS',
                'password' => Hash::make('MininterGPS2024!'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Usuario admin creado: admin@mininter.gps');
        $this->command->info('🔑 Password: MininterGPS2024!');
    }
}
