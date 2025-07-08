<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\SyncMunicipalityJob;
use App\Models\Municipality;
use App\Models\Transmission;
use App\Services\GPServerClient;
use App\Services\MininterClient;
use App\Services\PayloadTransformer;
use App\Services\ValidationService;
use App\Services\LoggingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Test para SyncMunicipalityJob
 */
class SyncMunicipalityJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que el job se puede encolar correctamente
     */
    public function test_job_can_be_queued(): void
    {
        Queue::fake();

        $municipality = Municipality::factory()->create([
            'active' => true,
            'tipo' => 'SERENAZGO'
        ]);

        SyncMunicipalityJob::dispatch($municipality);

        Queue::assertPushed(SyncMunicipalityJob::class, function ($job) use ($municipality) {
            return $job->municipality->id === $municipality->id;
        });
    }

    /**
     * Test que el job se salta municipalidades inactivas
     */
    public function test_job_skips_inactive_municipalities(): void
    {
        $municipality = Municipality::factory()->create([
            'active' => false,
            'tipo' => 'SERENAZGO'
        ]);

        $gpServerClient = $this->createMock(GPServerClient::class);
        $mininterClient = $this->createMock(MininterClient::class);
        $transformer = $this->createMock(PayloadTransformer::class);
        $validator = $this->createMock(ValidationService::class);
        $logger = $this->createMock(LoggingService::class);

        // El logger debe recibir el log de municipalidad inactiva
        $logger->expects($this->once())
            ->method('logInfo')
            ->with('gps', 'Municipalidad inactiva, saltando sincronización');

        // GPServerClient no debe ser llamado
        $gpServerClient->expects($this->never())
            ->method('fetchGpsObjects');

        $job = new SyncMunicipalityJob($municipality);
        $job->handle($gpServerClient, $mininterClient, $transformer, $validator, $logger);
    }

    /**
     * Test que el job maneja municipalidades sin datos GPS
     */
    public function test_job_handles_empty_gps_data(): void
    {
        $municipality = Municipality::factory()->create([
            'active' => true,
            'tipo' => 'SERENAZGO'
        ]);

        $gpServerClient = $this->createMock(GPServerClient::class);
        $mininterClient = $this->createMock(MininterClient::class);
        $transformer = $this->createMock(PayloadTransformer::class);
        $validator = $this->createMock(ValidationService::class);
        $logger = $this->createMock(LoggingService::class);

        // GPServerClient retorna array vacío
        $gpServerClient->expects($this->once())
            ->method('fetchGpsObjects')
            ->willReturn([]);

        // Logger debe recibir el warning
        $logger->expects($this->once())
            ->method('logWarning')
            ->with('gps', 'No se obtuvieron datos GPS');

        // Validator no debe ser llamado
        $validator->expects($this->never())
            ->method('validateGpsData');

        $job = new SyncMunicipalityJob($municipality);
        $job->handle($gpServerClient, $mininterClient, $transformer, $validator, $logger);
    }

    /**
     * Test que el job tiene configuración correcta
     */
    public function test_job_has_correct_configuration(): void
    {
        $municipality = Municipality::factory()->create();
        $job = new SyncMunicipalityJob($municipality);

        $this->assertEquals(300, $job->timeout);
        $this->assertEquals(5, $job->tries);
        $this->assertEquals([1000, 2000, 4000, 8000, 16000], $job->backoff);
        $this->assertNotEmpty($job->getJobId());
    }

    /**
     * Test que el job tiene tags correctos
     */
    public function test_job_has_correct_tags(): void
    {
        $municipality = Municipality::factory()->create([
            'tipo' => 'SERENAZGO'
        ]);

        $job = new SyncMunicipalityJob($municipality);
        $tags = $job->tags();

        $this->assertContains('sync-municipality', $tags);
        $this->assertContains("municipality:{$municipality->id}", $tags);
        $this->assertContains("type:SERENAZGO", $tags);
        $this->assertContains("job:{$job->getJobId()}", $tags);
    }

    /**
     * Test que el job maneja errores de conexión
     */
    public function test_job_handles_connection_errors(): void
    {
        $municipality = Municipality::factory()->create([
            'active' => true,
            'tipo' => 'SERENAZGO'
        ]);

        $gpServerClient = $this->createMock(GPServerClient::class);
        $mininterClient = $this->createMock(MininterClient::class);
        $transformer = $this->createMock(PayloadTransformer::class);
        $validator = $this->createMock(ValidationService::class);
        $logger = $this->createMock(LoggingService::class);

        // GPServerClient lanza excepción
        $gpServerClient->expects($this->once())
            ->method('fetchGpsObjects')
            ->willThrowException(new \Exception('Connection failed'));

        // Logger debe recibir el error
        $logger->expects($this->once())
            ->method('logConnectionError')
            ->with('GPServer', $this->anything(), 'Connection failed');

        $job = new SyncMunicipalityJob($municipality);

        $this->expectException(\Exception::class);
        $job->handle($gpServerClient, $mininterClient, $transformer, $validator, $logger);
    }

    /**
     * Test que el job crea registros de transmisión
     */
    public function test_job_creates_transmission_records(): void
    {
        $municipality = Municipality::factory()->create([
            'active' => true,
            'tipo' => 'SERENAZGO'
        ]);

        $gpsData = [
            ['imei' => '123456789012345', 'lat' => -12.0, 'lng' => -77.0, 'dt_server' => '2024-01-01 10:00:00']
        ];

        $validationResult = [
            'valid' => true,
            'valid_data' => $gpsData,
            'success_rate' => 100,
            'errors' => []
        ];

        $transformedData = [
            ['imei' => '123456789012345', 'lat' => -12.0, 'lng' => -77.0, 'fechaHora' => '01/01/2024 10:00:00']
        ];

        $mininterResponse = [
            'success' => true,
            'status_code' => 200,
            'message' => 'OK'
        ];

        $gpServerClient = $this->createMock(GPServerClient::class);
        $mininterClient = $this->createMock(MininterClient::class);
        $transformer = $this->createMock(PayloadTransformer::class);
        $validator = $this->createMock(ValidationService::class);
        $logger = $this->createMock(LoggingService::class);

        // Configurar mocks
        $gpServerClient->method('fetchGpsObjects')->willReturn($gpsData);
        $validator->method('validateGpsData')->willReturn($validationResult);
        $transformer->method('transformForSerenazgo')->willReturn($transformedData);
        $transformer->method('getTransformationSummary')->willReturn(['success' => true]);
        $mininterClient->method('sendSerenazgoData')->willReturn($mininterResponse);

        // Logger methods (void, sin willReturn)
        $logger->expects($this->once())->method('logSyncStart');
        $logger->expects($this->once())->method('logGpsDataReceived');
        $logger->expects($this->once())->method('logDataTransformation');
        $logger->expects($this->once())->method('logTransmissionSent');
        $logger->expects($this->once())->method('logSyncEnd');

        $job = new SyncMunicipalityJob($municipality);
        $job->handle($gpServerClient, $mininterClient, $transformer, $validator, $logger);

        // Verificar que se creó el registro de transmisión
        $this->assertDatabaseHas('transmissions', [
            'municipality_id' => $municipality->id,
            'status' => 'SENT'
        ]);
    }
} 