<?php

namespace Tests\Feature\Services;

use App\Services\GPServerClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GPServerClientTest extends TestCase
{
    private GPServerClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = new GPServerClient();
    }

    public function test_can_fetch_gps_objects_successfully()
    {
        // Arrange
        $expectedData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25',
                'speed' => 45,
                'course' => 180
            ],
            [
                'imei' => '987654321098765',
                'lat' => -8.106598,
                'lng' => -79.021011,
                'dt_server' => 1705329025,
                'speed' => 0,
                'course' => 0
            ]
        ];

        Http::fake([
            '*gipies.pe*' => Http::response($expectedData, 200)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('123456789012345', $result[0]['imei']);
        $this->assertEquals(-12.046374, $result[0]['lat']);
    }

    public function test_handles_network_errors_gracefully()
    {
        // Arrange
        Http::fake([
            '*gipies.pe*' => function () {
                throw new ConnectionException('Connection failed');
            }
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert - Should return empty array instead of throwing exception
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_handles_connection_timeout()
    {
        // Arrange
        Http::fake([
            '*gipies.pe*' => Http::response('', 408) // Timeout
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_validates_response_structure()
    {
        // Arrange - Invalid response structure
        Http::fake([
            '*gipies.pe*' => Http::response(['invalid' => 'structure'], 200)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result); // Should filter out invalid data
    }

    public function test_health_check_returns_boolean()
    {
        // Arrange
        Http::fake([
            '*gipies.pe*' => Http::response(['status' => 'ok'], 200)
        ]);

        // Act
        $result = $this->client->healthCheck();

        // Assert
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function test_filters_invalid_gps_objects()
    {
        // Arrange - Mix of valid and invalid GPS objects
        $mixedData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '', // Invalid - empty IMEI
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '987654321098765',
                'lat' => 'invalid', // Invalid - non-numeric lat
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '555444333222111',
                'lat' => -8.106598,
                'lng' => -79.021011,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        Http::fake([
            '*gipies.pe*' => Http::response($mixedData, 200)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should only return valid objects
        $this->assertEquals('123456789012345', $result[0]['imei']);
        $this->assertEquals('555444333222111', $result[1]['imei']);
    }

    public function test_retry_logic_with_connection_errors()
    {
        // Arrange
        Http::fake([
            '*gipies.pe*' => Http::sequence()
                ->push('', 500) // First attempt fails
                ->push('', 500) // Second attempt fails
                ->push([ // Third attempt succeeds
                [
                    'imei' => '123456789012345',
                        'lat' => -12.046374,
                        'lng' => -77.042793,
                    'dt_server' => '2024-01-15 14:30:25'
                ]
                ], 200)
            ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        Http::assertSentCount(3); // Should have retried 3 times
    }

    public function test_handles_empty_response()
    {
        // Arrange
        Http::fake([
            '*gipies.pe*' => Http::response([], 200)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_validates_coordinates_within_bounds()
    {
        // Arrange - Coordinates outside valid bounds
        $outOfBoundsData = [
            [
                'imei' => '123456789012345',
                'lat' => 91.0, // Invalid latitude
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '987654321098765',
                'lat' => -12.046374,
                'lng' => 181.0, // Invalid longitude
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '555444333222111',
                'lat' => -8.106598, // Valid coordinates
                'lng' => -79.021011,
                'dt_server' => '2024-01-15 14:30:25'
            ]
        ];

        Http::fake([
            '*gipies.pe*' => Http::response($outOfBoundsData, 200)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result); // Should only return object with valid coordinates
        $this->assertEquals('555444333222111', $result[0]['imei']);
    }

    public function test_handles_http_error_responses()
    {
        // Arrange
        Http::fake([
            '*gipies.pe*' => Http::response('Internal Server Error', 500)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_validates_datetime_formats()
    {
        // Arrange - Various datetime formats
        $mixedDateTimeData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25' // Valid ISO format
            ],
            [
                'imei' => '987654321098765',
                'lat' => -8.106598,
                'lng' => -79.021011,
                'dt_server' => 1705329025 // Valid timestamp
            ],
            [
                'imei' => '555444333222111',
                'lat' => -13.5319,
                'lng' => -71.9675,
                'dt_server' => 'invalid-date' // Invalid format
            ]
        ];

        Http::fake([
            '*gipies.pe*' => Http::response($mixedDateTimeData, 200)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Should filter out invalid datetime
        $this->assertEquals('123456789012345', $result[0]['imei']);
        $this->assertEquals('987654321098765', $result[1]['imei']);
    }

    public function test_health_check_handles_errors()
    {
        // Arrange
        Http::fake([
            '*gipies.pe*' => Http::response('Error', 500)
        ]);

        // Act
        $result = $this->client->healthCheck();

        // Assert
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function test_filters_objects_with_missing_required_fields()
    {
        // Arrange
        $incompleteData = [
            [
                'imei' => '123456789012345',
                'lat' => -12.046374,
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '987654321098765',
                // missing 'lat'
                'lng' => -77.042793,
                'dt_server' => '2024-01-15 14:30:25'
            ],
            [
                'imei' => '555444333222111',
                'lat' => -8.106598,
                'lng' => -79.021011
                // missing 'dt_server'
            ]
        ];

        Http::fake([
            '*gipies.pe*' => Http::response($incompleteData, 200)
        ]);

        // Act
        $result = $this->client->fetchGpsObjects('test-token');

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result); // Should only return complete objects
        $this->assertEquals('123456789012345', $result[0]['imei']);
    }
}
