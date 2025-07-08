<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class MunicipalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $municipalities = [
            [
                'id' => '645d2bfa-2e02-4f0c-95a9-2032e9e2a941',
                'name' => 'MUNICIPALIDAD DE HUARAZ',
                'token_gps' => '6B7E791FE9B05E845825B0F232AD65FC',
                'ubigeo' => '020101',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => '2ac6963e-8ac1-4444-b709-fa7d1ab0f1eb',
                'name' => 'MUNICIPALIDAD DE ACOLLA',
                'token_gps' => '9F5605BF8B91E838D7AE8A3DE526913E',
                'ubigeo' => '120402',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => '2b87c3ec-3a19-419a-ae63-ccb0ce8c9014',
                'name' => 'MUNICIPALIDAD DE APATA',
                'token_gps' => '6CAAD9D386D3AE0F6D395E13783B5239',
                'ubigeo' => '120403',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => '5ab398b4-a6e7-455a-a691-153f6ce78231',
                'name' => 'MUNICIPALIDAD DE HUAMANCACA',
                'token_gps' => '62827EC667E36A94D8E425016CDD59EA',
                'ubigeo' => '120905',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => 'fabc13e7-cd3b-48d5-821b-5ba9ac3267ab',
                'name' => 'MUNICIPALIDAD GREGORIO GALBARRACÍN',
                'token_gps' => 'D6DA254BA78A69E5F25086DF9695DF73',
                'ubigeo' => '230110',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => '9b6e814b-1661-4bee-a0ca-d331db1c1d25',
                'name' => 'MUNICIPALIDAD SAMEGUA',
                'token_gps' => 'B9F0726BBCBF5A871CB8A9770E987178',
                'ubigeo' => '180104',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => '69e69897-52ea-4310-940e-cea95fa725e5',
                'name' => 'MUNICIPALIDAD CIUDAD NUEVA',
                'token_gps' => '94AE8A0B24264D1C9F3B666C88823A80',
                'ubigeo' => '230104',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => '99ec8a66-df6b-41ba-a96f-ad13390a4a25',
                'name' => 'MUNICIPALIDAD DE TACNA',
                'token_gps' => '5DF4031AC4061139BDDFD120084C516C',
                'ubigeo' => '230101',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ],
            [
                'id' => 'fabc13e7-cd3b-48d5-821b-5ba9ac3267ac',
                'name' => 'MUNICIPALIDAD GREGORIO GALBARRACÍN',
                'token_gps' => '6DD3BE213236B049F03EA7C35EBD2257',
                'ubigeo' => '230110',
                'tipo' => 'POLICIAL',
                'codigo_comisaria' => '230110',
                'active' => true,
            ],
            [
                'id' => '99ec8a66-df6b-41ba-a96f-ad13390a4a26',
                'name' => 'MUNICIPALIDAD DE TACNA',
                'token_gps' => '08D001253591DB820DD46FFF4F901E51',
                'ubigeo' => '230101',
                'tipo' => 'POLICIAL',
                'codigo_comisaria' => '230101',
                'active' => true,
            ],
        ];

        foreach ($municipalities as $municipality) {
            Municipality::updateOrCreate(
                [
                    'token_gps' => $municipality['token_gps'],
                    'tipo' => $municipality['tipo']
                ],
                $municipality
            );
        }

        $this->command->info('✅ Municipalidades maestras creadas exitosamente');
        $this->command->info('📊 Total SERENAZGO: ' . Municipality::serenazgo()->count());
        $this->command->info('📊 Total POLICIAL: ' . Municipality::policial()->count());
    }
}
