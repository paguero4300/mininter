<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Municipality;
use App\Models\Transmission;
use App\Services\GPServerClient;
use App\Services\MininterClient;
use App\Services\PayloadTransformer;
use App\Services\ValidationService;
use App\Services\LoggingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Job principal para sincronizar datos GPS de una municipalidad
 * 
 * Procesa el flujo completo: GPServer → Validación → Transformación → MININTER
 * con manejo robusto de errores y reintentos con backoff exponencial.
 */
class SyncMunicipalityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tiempo máximo de ejecución en segundos
     */
    public int $timeout = 300;

    /**
     * Número máximo de reintentos
     */
    public int $tries = 5;

    /**
     * Backoff personalizado en milisegundos
     */
    public array $backoff = [1000, 2000, 4000, 8000, 16000];

    /**
     * ID único del job para tracking
     */
    private string $jobId;

    /**
     * Constructor
     */
    public function __construct(
        public Municipality $municipality
    ) {
        $this->jobId = (string) Str::uuid();
        $this->onQueue('sync');
    }

    /**
     * Ejecutar el job
     */
    public function handle(
        GPServerClient $gpServerClient,
        MininterClient $mininterClient,
        PayloadTransformer $transformer,
        ValidationService $validator,
        LoggingService $logger
    ): void {
        $logger->logSyncStart($this->municipality, $this->jobId);

        try {
            // 1. Verificar que la municipalidad esté activa
            if (!$this->municipality->active) {
                $logger->logInfo('gps', 'Municipalidad inactiva, saltando sincronización', [
                    'job_id' => $this->jobId,
                    'municipality_id' => $this->municipality->id,
                    'municipality_name' => $this->municipality->name
                ]);
                return;
            }

            // 2. Obtener datos GPS desde GPServer
            $gpsData = $this->fetchGpsData($gpServerClient, $logger);
            
            if (empty($gpsData)) {
                $logger->logWarning('gps', 'No se obtuvieron datos GPS', [
                    'job_id' => $this->jobId,
                    'municipality_id' => $this->municipality->id,
                    'municipality_name' => $this->municipality->name
                ]);
                return;
            }

            $logger->logGpsDataReceived($this->municipality, $gpsData, $this->jobId);

            // 3. Validar datos GPS
            $validation = $this->validateGpsData($gpsData, $validator, $logger);
            
            if ($validation['valid_objects'] == 0 || empty($validation['valid_data'])) {
                $logger->logValidationError($this->municipality, $validation['errors'], $this->jobId);
                return;
            }

            // 4. Transformar datos según tipo de municipalidad
            $transformedData = $this->transformGpsData($validation['valid_data'], $transformer, $logger);
            
            if (empty($transformedData)) {
                $logger->logError('gps', 'Error en transformación de datos', [
                    'job_id' => $this->jobId,
                    'municipality_id' => $this->municipality->id,
                    'municipality_name' => $this->municipality->name
                ]);
                return;
            }

            // 5. Enviar datos a MININTER
            $this->sendToMininter($transformedData, $mininterClient, $logger);

            // 6. Log de finalización exitosa
            $results = [
                'gps_objects_received' => count($gpsData),
                'valid_objects' => count($validation['valid_data']),
                'transformed_objects' => count($transformedData),
                'success' => true
            ];

            $logger->logSyncEnd($this->municipality, $this->jobId, $results);

        } catch (\Exception $e) {
            $this->handleJobFailure($e, $logger);
            throw $e; // Re-throw para que Laravel maneje el retry
        }
    }

    /**
     * Obtener datos GPS desde GPServer
     */
    private function fetchGpsData(GPServerClient $gpServerClient, LoggingService $logger): array
    {
        try {
            return $gpServerClient->fetchGpsObjects($this->municipality->token_gps);
        } catch (\Exception $e) {
            $logger->logConnectionError(
                'GPServer',
                config('services.gpserver.base_url'),
                $e->getMessage(),
                $this->jobId
            );
            throw $e;
        }
    }

    /**
     * Validar datos GPS
     */
    private function validateGpsData(array $gpsData, ValidationService $validator, LoggingService $logger): array
    {
        $validation = $validator->validateGpsData($gpsData);

        if ($validation['success_rate'] < 50) {
            $logger->logWarning('gps', 'Baja tasa de éxito en validación', [
                'job_id' => $this->jobId,
                'municipality_id' => $this->municipality->id,
                'success_rate' => $validation['success_rate'],
                'total_objects' => $validation['total_objects'],
                'valid_objects' => $validation['valid_objects']
            ]);
        }

        return $validation;
    }

    /**
     * Transformar datos GPS
     */
    private function transformGpsData(array $validGpsData, PayloadTransformer $transformer, LoggingService $logger): array
    {
        try {
            $transformedData = match ($this->municipality->tipo) {
                'SERENAZGO' => $transformer->transformForSerenazgo($validGpsData, $this->municipality),
                'POLICIAL' => $transformer->transformForPolicial($validGpsData, $this->municipality),
                default => throw new \InvalidArgumentException("Tipo de municipalidad no válido: {$this->municipality->tipo}")
            };

            $summary = $transformer->getTransformationSummary($validGpsData, $transformedData, $this->municipality->tipo);
            $logger->logDataTransformation($this->municipality, $summary, $this->jobId);

            return $transformedData;

        } catch (\Exception $e) {
            $logger->logError('gps', 'Error en transformación de datos', [
                'job_id' => $this->jobId,
                'municipality_id' => $this->municipality->id,
                'error' => $e->getMessage(),
                'type' => $this->municipality->tipo
            ]);
            throw $e;
        }
    }

    /**
     * Enviar datos a MININTER
     */
    private function sendToMininter(array $transformedData, MininterClient $mininterClient, LoggingService $logger): void
    {
        DB::transaction(function () use ($transformedData, $mininterClient, $logger) {
            // Crear registro de transmisión
            $transmission = Transmission::create([
                'id' => Str::uuid(),
                'municipality_id' => $this->municipality->id,
                'payload' => $transformedData,
                'status' => 'PENDING',
                'retry_count' => 0,
                'sent_at' => null
            ]);

            try {
                // Enviar según tipo de municipalidad
                $response = match ($this->municipality->tipo) {
                    'SERENAZGO' => $mininterClient->sendSerenazgoData($transformedData, $this->municipality->id),
                    'POLICIAL' => $mininterClient->sendPolicialData($transformedData, $this->municipality->id),
                    default => throw new \InvalidArgumentException("Tipo de municipalidad no válido: {$this->municipality->tipo}")
                };

                // Actualizar registro de transmisión
                $statusCode = null;
                $responseBody = null;
                
                // Obtener el código de respuesta del primer envío exitoso
                if (!empty($response['responses'])) {
                    $firstResponse = $response['responses'][0];
                    $statusCode = $firstResponse['status_code'] ?? null;
                    $responseBody = $firstResponse['response_body'] ?? null;
                }
                
                $transmission->update([
                    'status' => $response['success'] ? 'SENT' : 'FAILED',
                    'response_code' => $statusCode,
                    'response_body' => $responseBody,
                    'sent_at' => Carbon::now(),
                    'error_message' => $response['first_error'] ?? null
                ]);

                $logger->logTransmissionSent($transmission, $this->municipality, $response, $this->jobId);

                if (!$response['success']) {
                    throw new \RuntimeException("Error en envío a MININTER: {$response['first_error']}");
                }

            } catch (\Exception $e) {
                $transmission->update([
                    'status' => 'FAILED',
                    'error_message' => $e->getMessage(),
                    'sent_at' => Carbon::now()
                ]);

                $logger->logTransmissionError($transmission, $this->municipality, $e->getMessage(), $this->jobId);
                throw $e;
            }
        });
    }

    /**
     * Manejar fallo del job
     */
    private function handleJobFailure(\Exception $e, LoggingService $logger): void
    {
        $logger->logError('errors', 'Fallo en SyncMunicipalityJob', [
            'job_id' => $this->jobId,
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        // Si es el último intento, marcar como fallido definitivo
        if ($this->attempts() >= $this->tries) {
            $logger->logError('errors', 'Job fallido definitivamente', [
                'job_id' => $this->jobId,
                'municipality_id' => $this->municipality->id,
                'municipality_name' => $this->municipality->name,
                'final_attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);
        } else {
            $nextDelay = $this->backoff[$this->attempts() - 1] ?? 16000;
            $logger->logTransmissionRetry(
                new Transmission(['id' => $this->jobId]), // Dummy transmission for logging
                $this->municipality,
                $this->attempts(),
                $e->getMessage(),
                $this->jobId
            );

            $logger->logInfo('gps', 'Reintentando job', [
                'job_id' => $this->jobId,
                'municipality_id' => $this->municipality->id,
                'attempt' => $this->attempts(),
                'next_delay_ms' => $nextDelay,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Manejar job fallido definitivamente
     */
    public function failed(\Throwable $exception): void
    {
        $logger = app(LoggingService::class);
        
        $logger->logError('errors', 'Job fallido definitivamente', [
            'job_id' => $this->jobId,
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'failed_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Obtener delay personalizado para reintentos
     */
    public function backoff(): array
    {
        return $this->backoff;
    }

    /**
     * Tags únicos para identificar el job
     */
    public function tags(): array
    {
        return [
            'sync-municipality',
            "municipality:{$this->municipality->id}",
            "type:{$this->municipality->tipo}",
            "job:{$this->jobId}"
        ];
    }

    /**
     * Obtener ID único del job
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }
} 