<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PayloadTransformer;
use App\Models\Municipality;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayloadTransformerTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private PayloadTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new PayloadTransformer();
    }

    /**
     * Test que verifica la conversión de zona horaria de UTC a America/Lima
     */
    public function test_datetime_is_converted_from_utc_to_lima_timezone(): void
    {
        // Preparar datos de prueba
        $municipality = Municipality::factory()->create();
        
        // Fecha en UTC: 2024-01-15 15:30:00 UTC
        $utcTimestamp = Carbon::parse('2024-01-15 15:30:00', 'UTC')->timestamp;
        
        $gpsData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => $utcTimestamp,
                'speed' => 50,
                'course' => 180,
                'altitude' => 150,
                'loc_valid' => 1
            ]
        ];

        // Transformar datos
        $result = $this->transformer->transformForSerenazgo($gpsData, $municipality);

        // Verificar que hay resultado
        $this->assertCount(1, $result);
        
        // Verificar que la fecha se convirtió correctamente
        // UTC 15:30:00 debería convertirse a Lima 10:30:00 (GMT-5)
        $expectedLimaTime = '15/01/2024 10:30:00';
        $this->assertEquals($expectedLimaTime, $result[0]['fechaHora']);
    }

    /**
     * Test que verifica la conversión con timestamp en string
     */
    public function test_datetime_conversion_with_string_timestamp(): void
    {
        $municipality = Municipality::factory()->create();
        
        // Usar timestamp como string
        $utcTimestamp = (string) Carbon::parse('2024-01-15 20:45:30', 'UTC')->timestamp;
        
        $gpsData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => $utcTimestamp,
                'speed' => 30,
                'loc_valid' => 1
            ]
        ];

        $result = $this->transformer->transformForSerenazgo($gpsData, $municipality);

        // UTC 20:45:30 debería convertirse a Lima 15:45:30 (GMT-5)
        $expectedLimaTime = '15/01/2024 15:45:30';
        $this->assertEquals($expectedLimaTime, $result[0]['fechaHora']);
    }

    /**
     * Test que verifica la conversión con fecha en formato string
     */
    public function test_datetime_conversion_with_date_string(): void
    {
        $municipality = Municipality::factory()->create();
        
        // Usar fecha como string ISO
        $utcDateString = '2024-01-15T12:00:00Z';
        
        $gpsData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => $utcDateString,
                'speed' => 25,
                'loc_valid' => 1
            ]
        ];

        $result = $this->transformer->transformForSerenazgo($gpsData, $municipality);

        // UTC 12:00:00 debería convertirse a Lima 07:00:00 (GMT-5)
        $expectedLimaTime = '15/01/2024 07:00:00';
        $this->assertEquals($expectedLimaTime, $result[0]['fechaHora']);
    }

    /**
     * Test que verifica diferencia de zona horaria
     */
    public function test_timezone_difference_is_five_hours(): void
    {
        $municipality = Municipality::factory()->create();
        
        // Crear una fecha específica en UTC
        $utcDate = Carbon::parse('2024-06-15 18:00:00', 'UTC');
        
        $gpsData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => $utcDate->timestamp,
                'speed' => 40,
                'loc_valid' => 1
            ]
        ];

        $result = $this->transformer->transformForSerenazgo($gpsData, $municipality);

        // UTC 18:00:00 debería convertirse a Lima 13:00:00 (5 horas menos)
        $expectedLimaTime = '15/06/2024 13:00:00';
        $this->assertEquals($expectedLimaTime, $result[0]['fechaHora']);
    }

    /**
     * Test que verifica que funciona también para transformación policial
     */
    public function test_timezone_conversion_works_for_policial(): void
    {
        $municipality = Municipality::factory()->create([
            'codigo_comisaria' => 'TEST001'
        ]);
        
        $utcTimestamp = Carbon::parse('2024-01-15 22:15:45', 'UTC')->timestamp;
        
        $gpsData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => $utcTimestamp,
                'speed' => 60,
                'loc_valid' => 1
            ]
        ];

        $result = $this->transformer->transformForPolicial($gpsData, $municipality);

        // UTC 22:15:45 debería convertirse a Lima 17:15:45 (GMT-5)
        $expectedLimaTime = '15/01/2024 17:15:45';
        $this->assertEquals($expectedLimaTime, $result[0]['fechaHora']);
    }
} 