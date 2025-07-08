<?php

namespace Tests\Feature\Services;

use App\Services\ValidationService;
use Tests\TestCase;

class ValidationServiceTest extends TestCase
{
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validator = new ValidationService();
    }

    public function test_validates_complete_gps_data_successfully()
    {
        // Arrange
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'speed' => 45.5,
                'course' => 180
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(1, $result['total_objects']);
        $this->assertEquals(1, $result['valid_objects']);
        $this->assertEquals(0, $result['invalid_objects']);
        $this->assertCount(1, $result['valid_data']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(100.0, $result['success_rate']);
    }

    public function test_validates_gps_structure_correctly()
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
            // Missing required field
            [
                'imei' => '555444333222111',
                'lng' => -77.042793,
                // missing 'lat'
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Empty field
            [
                'imei' => '',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(3, $result['total_objects']);
        $this->assertEquals(1, $result['valid_objects']);
        $this->assertEquals(2, $result['invalid_objects']);
        $this->assertCount(1, $result['valid_data']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validates_coordinates_ranges()
    {
        // Arrange
        $gpsObjects = [
            // Invalid latitude > 90
            [
                'imei' => '123456789012345',
                'lat' => 95.0,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Invalid longitude < -180
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -185.0,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Valid coordinates within Peru
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(3, $result['total_objects']);
        $this->assertEquals(1, $result['valid_objects']);
        $this->assertEquals(2, $result['invalid_objects']);
        $this->assertEquals('555444333222111', $result['valid_data'][0]['imei']);
    }

    public function test_validates_peru_geographic_bounds()
    {
        // Arrange
        $gpsObjects = [
            // Valid coordinates in Peru (Lima)
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Invalid coordinates outside Peru (Brazil)
            [
                'imei' => '555444333222111',
                'lat' => -23.5505,
                'lng' => -46.6333,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(2, $result['total_objects']);
        $this->assertEquals(1, $result['valid_objects']);
        $this->assertEquals(1, $result['invalid_objects']);
        $this->assertEquals('123456789012345', $result['valid_data'][0]['imei']);
    }

    public function test_validates_peru_coordinates_correctly()
    {
        // Arrange - Valid coordinates within Peru
        $validPeruCoordinates = [
            ['imei' => '123456789012345', 'lat' => -12.046374, 'lng' => -77.042793, 'dt_server' => '2024-01-15 14:30:25'], // Lima
            ['imei' => '555444333222111', 'lat' => -13.5319, 'lng' => -71.9675, 'dt_server' => '2024-01-15 14:30:25'], // Cusco
            ['imei' => '987654321098765', 'lat' => -8.1116, 'lng' => -79.0287, 'dt_server' => '2024-01-15 14:30:25']  // Trujillo
        ];

        // Act
        $result = $this->validator->validateGpsData($validPeruCoordinates);

        // Assert
        $this->assertEquals(3, $result['total_objects']);
        $this->assertEquals(3, $result['valid_objects']);
        $this->assertEquals(0, $result['invalid_objects']);
        $this->assertEquals(100.0, $result['success_rate']);
    }

    public function test_validates_datetime_formats()
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
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15T14:30:25'
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25.123'
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(3, $result['total_objects']);
        $this->assertEquals(3, $result['valid_objects']);
        $this->assertEquals(0, $result['invalid_objects']);
    }

    public function test_validates_invalid_datetime_formats()
    {
        // Arrange - Invalid date formats
        $gpsObjects = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => 'invalid-date'
            ],
            [
                'imei' => '555444333222111',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '32/13/2024 25:70:80'
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => ''
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(3, $result['total_objects']);
        $this->assertEquals(0, $result['valid_objects']);
        $this->assertEquals(3, $result['invalid_objects']);
        $this->assertEquals(0.0, $result['success_rate']);
    }

    public function test_validates_imei_formats()
    {
        // Arrange
        $gpsObjects = [
            // Valid IMEI
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Too short IMEI
            [
                'imei' => '12345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // All zeros IMEI
            [
                'imei' => '000000000000000',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

            // Act
        $result = $this->validator->validateGpsData($gpsObjects);

            // Assert
        $this->assertEquals(3, $result['total_objects']);
        $this->assertEquals(1, $result['valid_objects']);
        $this->assertEquals(2, $result['invalid_objects']);
        $this->assertEquals('123456789012345', $result['valid_data'][0]['imei']);
    }

    public function test_validates_optional_fields()
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
                'battery' => 85
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(1, $result['total_objects']);
        $this->assertEquals(1, $result['valid_objects']);
        
        $validObject = $result['valid_data'][0];
        $this->assertEquals(45.5, $validObject['speed']);
        $this->assertEquals(180, $validObject['course']);
        $this->assertEquals(85, $validObject['battery']);
    }

    public function test_handles_empty_gps_data_array()
    {
        // Arrange
        $gpsObjects = [];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(0, $result['total_objects']);
        $this->assertEquals(0, $result['valid_objects']);
        $this->assertEquals(0, $result['invalid_objects']);
        $this->assertEmpty($result['valid_data']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(0, $result['success_rate']);
    }

    public function test_validates_transformed_data()
    {
        // Arrange
        $transformedData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'fechaHora' => '15/01/2024 14:30:25',
                'idMunicipalidad' => 'test-municipality'
            ]
        ];

        // Act
        $result = $this->validator->validateTransformedData($transformedData, 'SERENAZGO');

        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEquals(1, $result['total_objects']);
        $this->assertEquals(0, $result['error_count']);
        $this->assertEmpty($result['errors']);
    }

    public function test_detects_suspicious_coordinates()
    {
        // Arrange
        $gpsObjects = [
            // Suspicious (0,0) coordinates
            [
                'imei' => '123456789012345',
                'lat' => 0.0,
                'lng' => 0.0,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            // Valid coordinates
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        // Act
        $result = $this->validator->validateGpsData($gpsObjects);

        // Assert
        $this->assertEquals(2, $result['total_objects']);
        $this->assertEquals(1, $result['valid_objects']);
        $this->assertEquals(1, $result['invalid_objects']);
        $this->assertEquals('987654321098765', $result['valid_data'][0]['imei']);
        
        // Check that suspicious coordinates are flagged in errors
        $this->assertNotEmpty($result['errors']);
        $suspiciousError = collect($result['errors'])->first(fn($error) => $error['type'] === 'SUSPICIOUS_COORDINATES');
        $this->assertNotNull($suspiciousError);
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
                'dt_server' => '2024-01-15 14:30:25'
            ];
        }

        // Act
        $startTime = microtime(true);
        $result = $this->validator->validateGpsData($gpsObjects);
        $endTime = microtime(true);

        // Assert
        $executionTime = $endTime - $startTime;
        $this->assertLessThan(5.0, $executionTime); // Should complete within 5 seconds
        $this->assertEquals(1000, $result['total_objects']);
        $this->assertGreaterThan(900, $result['valid_objects']); // Most should be valid
    }
}
