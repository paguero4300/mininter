<?php

namespace Tests\Feature\Services;

use App\Services\PayloadTransformer;
use App\Models\Municipality;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayloadTransformerTest extends TestCase
{
    use RefreshDatabase;

    private PayloadTransformer $transformer;
    private Municipality $serenazgoMunicipality;
    private Municipality $policialMunicipality;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->transformer = new PayloadTransformer();
        
        // Create real municipalities for testing
        $this->serenazgoMunicipality = Municipality::create([
            'name' => 'Test SERENAZGO',
            'tipo' => 'SERENAZGO',
            'ubigeo' => '150101',
            'active' => true,
            'token_gps' => 'test-serenazgo-token',
            'codigo_comisaria' => null
        ]);

        $this->policialMunicipality = Municipality::create([
            'name' => 'Test POLICIAL',
            'tipo' => 'POLICIAL',
            'ubigeo' => '150102',
            'active' => true,
            'token_gps' => 'test-policial-token',
            'codigo_comisaria' => 'POL001'
        ]);
    }

    public function test_transforms_for_serenazgo_successfully()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'speed' => 45.5,
                'course' => 180,
                'altitude' => 150,
                'precision' => 5.2,
                'battery' => 85,
                'ignition' => 1,
                'status' => 'active'
            ]
        ];

        // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $transformed = $result[0];
        $this->assertEquals('123456789012345', $transformed['imei']);
        $this->assertEquals(-12.046374, $transformed['lat']);
        $this->assertEquals(-77.042793, $transformed['lng']);
        $this->assertEquals('15/01/2024 14:30:25', $transformed['fechaHora']);
        $this->assertEquals(46, $transformed['velocidad']); // Rounded
        $this->assertEquals(180, $transformed['rumbo']);
        $this->assertEquals($this->serenazgoMunicipality->id, $transformed['idMunicipalidad']);
        
        // Check all expected fields are present
        $this->assertArrayHasKey('altitud', $transformed);
        $this->assertArrayHasKey('precision', $transformed);
        $this->assertArrayHasKey('bateria', $transformed);
        $this->assertArrayHasKey('ignicion', $transformed);
        $this->assertArrayHasKey('estado', $transformed);
    }

    public function test_transforms_for_policial_successfully()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '987654321098765',
                'lat' => -13.5319,
                'lng' => -71.9675,
                'dt_server' => '2024-01-15 16:45:30',
                'speed' => 35.8,
                'course' => 270,
                'altitude' => 3400,
                'precision' => 3.1,
                'battery' => 92,
                'ignition' => 0,
                'status' => 'inactive'
            ]
        ];

        // Act
        $result = $this->transformer->transformForPolicial($gpsObjects, $this->policialMunicipality);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);

        $transformed = $result[0];
        $this->assertEquals('987654321098765', $transformed['imei']);
        $this->assertEquals(-13.5319, $transformed['lat']);
        $this->assertEquals(-71.9675, $transformed['lng']);
        $this->assertEquals('15/01/2024 16:45:30', $transformed['fechaHora']);
        $this->assertEquals(36, $transformed['velocidad']); // Rounded
        $this->assertEquals(270, $transformed['rumbo']);
        $this->assertArrayHasKey('idTransmision', $transformed);
        $this->assertEquals('POL001', $transformed['codigoComisaria']);
        $this->assertIsString($transformed['idTransmision']);
        $this->assertNotEmpty($transformed['idTransmision']);
    }

    public function test_transforms_multiple_gps_objects()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '111111111111111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '222222222222222',
                'lat' => -13.5319,
                'lng' => -71.9675,
                'dt_server' => '2024-01-15 14:35:00'
            ],
            [
                'imei' => '333333333333333',
                'lat' => -8.1116,
                'lng' => -79.0287,
                'dt_server' => '2024-01-15 14:40:15'
            ]
        ];

        // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        
        // Verify each object was transformed
        foreach ($result as $index => $transformed) {
            $this->assertEquals($gpsObjects[$index]['imei'], $transformed['imei']);
            $this->assertEquals($gpsObjects[$index]['lat'], $transformed['lat']);
            $this->assertEquals($gpsObjects[$index]['lng'], $transformed['lng']);
            $this->assertArrayHasKey('fechaHora', $transformed);
            $this->assertEquals($this->serenazgoMunicipality->id, $transformed['idMunicipalidad']);
        }
    }

    public function test_formats_datetime_correctly()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => 1705329025 // Unix timestamp
            ]
        ];

        // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

        // Assert
        $this->assertEquals('15/01/2024 14:30:25', $result[0]['fechaHora']);
        
        // Verify timestamp conversion
        $expectedFromTimestamp = Carbon::createFromTimestamp(1705329025)->format('d/m/Y H:i:s');
        $this->assertEquals($expectedFromTimestamp, $result[1]['fechaHora']);
    }

    public function test_handles_invalid_gps_objects()
    {
        // Arrange
        $gpsObjects = [
            // Valid object
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Invalid - missing required field
            [
                'imei' => '987654321098765',
                'lng' => -77.042793,
                // missing 'lat'
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Invalid - not an array
            'invalid_object',
            // Valid object
            [
                'imei' => '555444333222111',
                'lat' => -13.5319,
                'lng' => -71.9675,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Only valid objects transformed
        $this->assertEquals('123456789012345', $result[0]['imei']);
        $this->assertEquals('555444333222111', $result[1]['imei']);
    }

    public function test_formats_speed_correctly()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'speed' => 45.7
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'speed' => -5.2 // Negative speed should be 0
            ],
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
                // No speed provided - should default to 0
            ]
        ];

        // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

        // Assert
        $this->assertEquals(46, $result[0]['velocidad']); // Rounded up
        $this->assertEquals(0, $result[1]['velocidad']); // Negative becomes 0
        $this->assertEquals(0, $result[2]['velocidad']); // Default value
    }

    public function test_formats_course_correctly()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'course' => 180.5
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'course' => 370 // Should wrap to 10
            ],
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'course' => -45 // Should wrap to 315
            ]
            ];

            // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

            // Assert
        $this->assertEquals(180, $result[0]['rumbo']); // Rounded down
        $this->assertEquals(10, $result[1]['rumbo']); // 370 % 360 = 10
        $this->assertEquals(315, $result[2]['rumbo']); // -45 + 360 = 315
    }

    public function test_formats_battery_level_correctly()
    {
        // Arrange
        $gpsObjects = [
            [
            'imei' => '123456789012345',
            'lat' => -12.046374,
            'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'battery' => 85
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'battery' => 150 // Should cap at 100
            ],
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'battery' => -10 // Should be 0
            ]
        ];

        // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

        // Assert
        $this->assertEquals(85, $result[0]['bateria']);
        $this->assertEquals(100, $result[1]['bateria']); // Capped at 100
        $this->assertEquals(0, $result[2]['bateria']); // Negative becomes 0
    }

    public function test_validates_coordinates_correctly()
    {
        // Act & Assert - Valid coordinates
        $this->assertTrue($this->transformer->areValidCoordinates(-12.046374, -77.042793)); // Lima
        $this->assertTrue($this->transformer->areValidCoordinates(-13.5319, -71.9675)); // Cusco
        $this->assertTrue($this->transformer->areValidCoordinates(-8.1116, -79.0287)); // Trujillo
        $this->assertTrue($this->transformer->areValidCoordinates(0, 0)); // Valid but suspicious coordinates
        $this->assertTrue($this->transformer->areValidCoordinates(-90, -180)); // Edge cases
        $this->assertTrue($this->transformer->areValidCoordinates(90, 180)); // Edge cases
        
        // Invalid coordinates - out of range
        $this->assertFalse($this->transformer->areValidCoordinates(100, -77.042793)); // Invalid lat > 90
        $this->assertFalse($this->transformer->areValidCoordinates(-12.046374, 200)); // Invalid lng > 180
        $this->assertFalse($this->transformer->areValidCoordinates(-91, -77.042793)); // Invalid lat < -90
        $this->assertFalse($this->transformer->areValidCoordinates(-12.046374, -181)); // Invalid lng < -180
    }

    public function test_gets_transformation_summary()
    {
        // Arrange
        $originalData = [
            ['imei' => '123456789012345', 'lat' => -12.046374, 'lng' => -77.042793, 'dt_server' => '2024-01-15 14:30:25'],
            ['imei' => '987654321098765', 'lat' => -13.5319, 'lng' => -71.9675, 'dt_server' => '2024-01-15 14:35:00'],
            ['imei' => 'invalid'], // Invalid object
        ];
        
        $transformedData = [
            ['imei' => '123456789012345', 'lat' => -12.046374, 'lng' => -77.042793, 'fechaHora' => '15/01/2024 14:30:25'],
            ['imei' => '987654321098765', 'lat' => -13.5319, 'lng' => -71.9675, 'fechaHora' => '15/01/2024 14:35:00']
        ];

        // Act
        $summary = $this->transformer->getTransformationSummary($originalData, $transformedData, 'SERENAZGO');

        // Assert
        $this->assertIsArray($summary);
        $this->assertEquals(3, $summary['original_count']);
        $this->assertEquals(2, $summary['transformed_count']);
        $this->assertEquals('SERENAZGO', $summary['type']);
        $this->assertArrayHasKey('timestamp', $summary);
        $this->assertArrayHasKey('success_rate', $summary);
        $this->assertEquals(66.67, $summary['success_rate']); // 2/3 * 100 = 66.67%
    }

    public function test_sanitizes_imei_correctly()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123-456-789-012-345', // With dashes
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '987.654.321.098.765', // With dots
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => 'ABC123456789012345DEF', // With letters
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

        // Assert
        $this->assertEquals('123456789012345', $result[0]['imei']); // Dashes removed
        $this->assertEquals('987654321098765', $result[1]['imei']); // Dots removed
        $this->assertEquals('123456789012345', $result[2]['imei']); // Letters removed
    }

    public function test_formats_status_field_correctly()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'status' => 'moving'
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'status' => 'invalid_status'
            ],
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
                // No status provided
            ]
            ];

            // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

            // Assert
        $this->assertEquals('MOVING', $result[0]['estado']); // Converted to uppercase
        $this->assertEquals('UNKNOWN', $result[1]['estado']); // Invalid status becomes UNKNOWN
        $this->assertEquals('UNKNOWN', $result[2]['estado']); // Default value
    }

    public function test_formats_ignition_field_correctly()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'ignition' => 1
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'ignition' => 'on'
            ],
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'ignition' => 0
            ]
            ];

            // Act
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);

            // Assert
        $this->assertTrue($result[0]['ignicion']); // 1 becomes true
        $this->assertTrue($result[1]['ignicion']); // 'on' becomes true
        $this->assertFalse($result[2]['ignicion']); // 0 becomes false
    }

    public function test_generates_unique_transmission_ids_for_policial()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        // Act
        $result1 = $this->transformer->transformForPolicial($gpsObjects, $this->policialMunicipality);
        $result2 = $this->transformer->transformForPolicial($gpsObjects, $this->policialMunicipality);

        // Assert
        $this->assertNotEquals($result1[0]['idTransmision'], $result2[0]['idTransmision']);
        $this->assertIsString($result1[0]['idTransmision']);
        $this->assertIsString($result2[0]['idTransmision']);
        // Check if they look like UUIDs
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $result1[0]['idTransmision']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $result2[0]['idTransmision']);
    }

    public function test_performance_with_large_dataset()
    {
        // Arrange - Create 1000 GPS objects
        $gpsObjects = [];
        for ($i = 0; $i < 1000; $i++) {
            $gpsObjects[] = [
                'imei' => sprintf('%015d', $i + 123456789012345),
                'lat' => -12.046374 + (rand(-100, 100) / 10000),
                'lng' => -77.042793 + (rand(-100, 100) / 10000),
                'dt_server' => '2024-01-15 14:30:25',
                'speed' => rand(0, 100),
                'course' => rand(0, 360)
            ];
        }

        // Act
        $startTime = microtime(true);
        $result = $this->transformer->transformForSerenazgo($gpsObjects, $this->serenazgoMunicipality);
        $endTime = microtime(true);

        // Assert
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(3.0, $executionTime); // Should complete within 3 seconds
        $this->assertIsArray($result);
        $this->assertCount(1000, $result); // All should be transformed
        
        // Verify transformation structure
        $sample = $result[0];
        $this->assertArrayHasKey('imei', $sample);
        $this->assertArrayHasKey('fechaHora', $sample);
        $this->assertArrayHasKey('idMunicipalidad', $sample);
    }
}
