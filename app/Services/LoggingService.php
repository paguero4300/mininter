<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Municipality;
use App\Models\Transmission;

/**
 * Servicio de logging personalizado para el sistema GPS
 * 
 * Proporciona logging estructurado con contexto específico
 * para diferentes componentes del sistema MININTER GPS Proxy
 */
class LoggingService
{
    private const GPS_CHANNEL = 'gps';
    private const TRANSMISSION_CHANNEL = 'transmissions';
    private const SYSTEM_CHANNEL = 'system';
    private const ERROR_CHANNEL = 'errors';

    /**
     * Loggear inicio de sincronización
     *
     * @param Municipality $municipality
     * @param string $jobId
     * @return void
     */
    public function logSyncStart(Municipality $municipality, string $jobId): void
    {
        $this->logInfo(self::GPS_CHANNEL, 'Sincronización iniciada', [
            'job_id' => $jobId,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'municipality_type' => $municipality->tipo,
            'started_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear fin de sincronización
     *
     * @param Municipality $municipality
     * @param string $jobId
     * @param array $results
     * @return void
     */
    public function logSyncEnd(Municipality $municipality, string $jobId, array $results): void
    {
        $this->logInfo(self::GPS_CHANNEL, 'Sincronización completada', [
            'job_id' => $jobId,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'municipality_type' => $municipality->tipo,
            'results' => $results,
            'completed_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear datos GPS obtenidos
     *
     * @param Municipality $municipality
     * @param array $gpsData
     * @param string $jobId
     * @return void
     */
    public function logGpsDataReceived(Municipality $municipality, array $gpsData, string $jobId): void
    {
        $this->logInfo(self::GPS_CHANNEL, 'Datos GPS recibidos', [
            'job_id' => $jobId,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'data_count' => count($gpsData),
            'received_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear transformación de datos
     *
     * @param Municipality $municipality
     * @param array $transformationSummary
     * @param string $jobId
     * @return void
     */
    public function logDataTransformation(Municipality $municipality, array $transformationSummary, string $jobId): void
    {
        $this->logInfo(self::GPS_CHANNEL, 'Datos transformados', [
            'job_id' => $jobId,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'transformation_summary' => $transformationSummary,
            'transformed_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear envío de transmisión
     *
     * @param Transmission $transmission
     * @param Municipality $municipality
     * @param array $response
     * @param string $jobId
     * @return void
     */
    public function logTransmissionSent(Transmission $transmission, Municipality $municipality, array $response, string $jobId): void
    {
        $level = $response['success'] ? 'info' : 'warning';
        
        // Obtener status code del primer envío
        $statusCode = null;
        if (!empty($response['responses'])) {
            $statusCode = $response['responses'][0]['status_code'] ?? null;
        }
        
        $this->log($level, self::TRANSMISSION_CHANNEL, 'Transmisión enviada', [
            'job_id' => $jobId,
            'transmission_id' => $transmission->id,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'municipality_type' => $municipality->tipo,
            'response' => [
                'success' => $response['success'],
                'status_code' => $statusCode,
                'total_objects' => $response['total_objects'] ?? 0,
                'successful_sends' => $response['successful_sends'] ?? 0,
                'failed_sends' => $response['failed_sends'] ?? 0,
                'first_error' => $response['first_error'] ?? null
            ],
            'sent_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear reintento de transmisión
     *
     * @param Transmission $transmission
     * @param Municipality $municipality
     * @param int $retryCount
     * @param string $reason
     * @param string $jobId
     * @return void
     */
    public function logTransmissionRetry(Transmission $transmission, Municipality $municipality, int $retryCount, string $reason, string $jobId): void
    {
        $this->logWarning(self::TRANSMISSION_CHANNEL, 'Reintento de transmisión', [
            'job_id' => $jobId,
            'transmission_id' => $transmission->id,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'retry_count' => $retryCount,
            'reason' => $reason,
            'retry_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear error de transmisión
     *
     * @param Transmission $transmission
     * @param Municipality $municipality
     * @param string $error
     * @param string $jobId
     * @return void
     */
    public function logTransmissionError(Transmission $transmission, Municipality $municipality, string $error, string $jobId): void
    {
        $this->logError(self::TRANSMISSION_CHANNEL, 'Error en transmisión', [
            'job_id' => $jobId,
            'transmission_id' => $transmission->id,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'error' => $error,
            'error_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear error de validación
     *
     * @param Municipality $municipality
     * @param array $validationErrors
     * @param string $jobId
     * @return void
     */
    public function logValidationError(Municipality $municipality, array $validationErrors, string $jobId): void
    {
        $this->logWarning(self::ERROR_CHANNEL, 'Error de validación', [
            'job_id' => $jobId,
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'validation_errors' => $validationErrors,
            'error_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear error de conexión
     *
     * @param string $service Servicio que falló (GPServer/MININTER)
     * @param string $endpoint Endpoint que falló
     * @param string $error Error específico
     * @param string $jobId
     * @return void
     */
    public function logConnectionError(string $service, string $endpoint, string $error, string $jobId): void
    {
        $this->logError(self::ERROR_CHANNEL, 'Error de conexión', [
            'job_id' => $jobId,
            'service' => $service,
            'endpoint' => $endpoint,
            'error' => $error,
            'error_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear health check
     *
     * @param string $service Servicio verificado
     * @param array $results Resultados del health check
     * @return void
     */
    public function logHealthCheck(string $service, array $results): void
    {
        $level = $results['accessible'] ?? true ? 'info' : 'warning';
        
        $this->log($level, self::SYSTEM_CHANNEL, 'Health check', [
            'service' => $service,
            'results' => $results,
            'checked_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear métricas del sistema
     *
     * @param array $metrics Métricas del sistema
     * @return void
     */
    public function logSystemMetrics(array $metrics): void
    {
        $this->logInfo(self::SYSTEM_CHANNEL, 'Métricas del sistema', [
            'metrics' => $metrics,
            'recorded_at' => Carbon::now()->toISOString()
        ]);
    }

    /**
     * Loggear información general
     *
     * @param string $channel Canal de log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @return void
     */
    public function logInfo(string $channel, string $message, array $context = []): void
    {
        $this->log('info', $channel, $message, $context);
    }

    /**
     * Loggear advertencia
     *
     * @param string $channel Canal de log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @return void
     */
    public function logWarning(string $channel, string $message, array $context = []): void
    {
        $this->log('warning', $channel, $message, $context);
    }

    /**
     * Loggear error
     *
     * @param string $channel Canal de log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @return void
     */
    public function logError(string $channel, string $message, array $context = []): void
    {
        $this->log('error', $channel, $message, $context);
    }

    /**
     * Loggear con nivel específico
     *
     * @param string $level Nivel de log
     * @param string $channel Canal de log
     * @param string $message Mensaje
     * @param array $context Contexto adicional
     * @return void
     */
    private function log(string $level, string $channel, string $message, array $context = []): void
    {
        // Añadir contexto base
        $context = array_merge($context, [
            'channel' => $channel,
            'system' => 'mininter-gps-proxy',
            'timestamp' => Carbon::now()->toISOString(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ]);

        // Usar el logger de Laravel
        Log::channel($channel)->{$level}($message, $context);
    }

    /**
     * Generar resumen de logs por período
     *
     * @param string $period Período (hour, day, week, month)
     * @param string $channel Canal específico (opcional)
     * @return array
     */
    public function generateLogSummary(string $period = 'day', string $channel = null): array
    {
        $startDate = match ($period) {
            'hour' => Carbon::now()->subHour(),
            'day' => Carbon::now()->subDay(),
            'week' => Carbon::now()->subWeek(),
            'month' => Carbon::now()->subMonth(),
            default => Carbon::now()->subDay()
        };

        // En una implementación real, esto leería los logs desde archivos o BD
        // Por ahora, retornamos una estructura base
        return [
            'period' => $period,
            'start_date' => $startDate->toISOString(),
            'end_date' => Carbon::now()->toISOString(),
            'channel' => $channel,
            'summary' => [
                'total_logs' => 0,
                'info_logs' => 0,
                'warning_logs' => 0,
                'error_logs' => 0,
                'sync_jobs' => 0,
                'transmissions' => 0,
                'errors' => 0
            ]
        ];
    }

    /**
     * Exportar logs a archivo
     *
     * @param string $channel Canal de logs
     * @param string $startDate Fecha de inicio
     * @param string $endDate Fecha de fin
     * @return string Ruta del archivo exportado
     */
    public function exportLogs(string $channel, string $startDate, string $endDate): string
    {
        $filename = sprintf(
            'logs_export_%s_%s_to_%s.json',
            $channel,
            Carbon::parse($startDate)->format('Y-m-d'),
            Carbon::parse($endDate)->format('Y-m-d')
        );

        $filePath = "exports/logs/{$filename}";

        // En una implementación real, esto leería y filtraría los logs
        $exportData = [
            'channel' => $channel,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'exported_at' => Carbon::now()->toISOString(),
            'logs' => [
                // Logs filtrados por fecha y canal
            ]
        ];

        Storage::disk('local')->put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));

        return $filePath;
    }

    /**
     * Limpiar logs antiguos
     *
     * @param int $daysToKeep Días a mantener
     * @return int Cantidad de logs eliminados
     */
    public function cleanOldLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        
        // En una implementación real, esto eliminaría logs de archivos o BD
        // Por ahora, retornamos 0
        
        $this->logInfo(self::SYSTEM_CHANNEL, 'Limpieza de logs ejecutada', [
            'days_to_keep' => $daysToKeep,
            'cutoff_date' => $cutoffDate->toISOString(),
            'logs_cleaned' => 0
        ]);

        return 0;
    }

    /**
     * Verificar salud del sistema de logging
     *
     * @return array Estado del sistema de logging
     */
    public function checkLoggingHealth(): array
    {
        $channels = [
            self::GPS_CHANNEL,
            self::TRANSMISSION_CHANNEL,
            self::SYSTEM_CHANNEL,
            self::ERROR_CHANNEL
        ];

        $results = [];
        
        foreach ($channels as $channel) {
            try {
                // Intentar escribir un log de prueba
                Log::channel($channel)->info('Health check test log');
                $results[$channel] = [
                    'accessible' => true,
                    'status' => 'OK'
                ];
            } catch (\Exception $e) {
                $results[$channel] = [
                    'accessible' => false,
                    'status' => 'ERROR',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'overall_status' => collect($results)->every(fn($result) => $result['accessible']) ? 'OK' : 'ERROR',
            'channels' => $results,
            'checked_at' => Carbon::now()->toISOString()
        ];
    }
} 