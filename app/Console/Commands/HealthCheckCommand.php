<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GPServerClient;
use App\Services\MininterClient;
use App\Services\LoggingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Comando para verificar el estado de salud del sistema
 * 
 * Verifica conectividad con:
 * - GPServer API
 * - Endpoints MININTER (SERENAZGO y POLICIAL)
 * - Base de datos
 * - Redis
 * - Cola de trabajos
 */
class HealthCheckCommand extends Command
{
    /**
     * Nombre del comando
     */
    protected $signature = 'gps:health-check 
                          {--detailed : Mostrar información detallada}
                          {--alert : Enviar alertas si hay problemas}';

    /**
     * Descripción del comando
     */
    protected $description = 'Verificar estado de salud del sistema GPS';

    /**
     * Servicio de logging
     */
    private LoggingService $logger;

    /**
     * Constructor
     */
    public function __construct(LoggingService $logger)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * Ejecutar el comando
     */
    public function handle(): int
    {
        $startTime = Carbon::now();
        $this->info('🏥 Iniciando verificación de salud del sistema...');

        $healthStatus = [
            'overall' => 'healthy',
            'checks' => [],
            'timestamp' => $startTime->toISOString(),
            'errors' => []
        ];

        try {
            // Verificar base de datos
            $healthStatus['checks']['database'] = $this->checkDatabase();

            // Verificar Redis
            $healthStatus['checks']['redis'] = $this->checkRedis();

            // Verificar GPServer
            $healthStatus['checks']['gpserver'] = $this->checkGPServer();

            // Verificar endpoints MININTER
            $healthStatus['checks']['mininter_serenazgo'] = $this->checkMininterSerenazgo();
            $healthStatus['checks']['mininter_policial'] = $this->checkMininterPolicial();

            // Verificar cola de trabajos
            $healthStatus['checks']['queue'] = $this->checkQueue();

            // Verificar logs
            $healthStatus['checks']['logs'] = $this->checkLogs();

            // Evaluar estado general
            $healthStatus = $this->evaluateOverallHealth($healthStatus);

            // Mostrar resultados
            $this->displayHealthResults($healthStatus);

            // Log del health check
            $this->logger->logInfo('system', 'Health check completado', [
                'overall_status' => $healthStatus['overall'],
                'checks' => array_map(fn($check) => $check['status'], $healthStatus['checks']),
                'duration_seconds' => $startTime->diffInSeconds(Carbon::now())
            ]);

            return $healthStatus['overall'] === 'healthy' ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Error durante health check: {$e->getMessage()}");
            
            $this->logger->logError('system', 'Error en health check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Verificar base de datos
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            
            // Verificar conexión
            DB::connection()->getPdo();
            
            // Verificar tablas principales
            $municipalitiesCount = DB::table('municipalities')->count();
            $transmissionsCount = DB::table('transmissions')->count();
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'municipalities_count' => $municipalitiesCount,
                'transmissions_count' => $transmissionsCount,
                'message' => 'Base de datos funcionando correctamente'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Error de conexión a base de datos'
            ];
        }
    }

    /**
     * Verificar Redis
     */
    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            
            // Test básico
            Redis::ping();
            
            // Verificar configuración de cache
            Redis::set('health_check_test', 'ok', 'EX', 60);
            $testValue = Redis::get('health_check_test');
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => $testValue === 'ok' ? 'healthy' : 'degraded',
                'response_time_ms' => $responseTime,
                'message' => 'Redis funcionando correctamente'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Error de conexión a Redis'
            ];
        }
    }

    /**
     * Verificar GPServer
     */
    private function checkGPServer(): array
    {
        try {
            $gpServerClient = app(GPServerClient::class);
            $start = microtime(true);

            // Verificar endpoint de health
            $healthy = $gpServerClient->healthCheck();
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => $healthy ? 'healthy' : 'degraded',
                'response_time_ms' => $responseTime,
                'endpoint' => config('services.gpserver.base_url'),
                'message' => $healthy ? 'GPServer accesible' : 'GPServer no responde correctamente'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'endpoint' => config('services.gpserver.base_url'),
                'message' => 'Error de conexión a GPServer'
            ];
        }
    }

    /**
     * Verificar endpoint MININTER SERENAZGO
     */
    private function checkMininterSerenazgo(): array
    {
        try {
            $mininterClient = app(MininterClient::class);
            $start = microtime(true);

            // Verificar endpoint de health
            $healthy = $mininterClient->healthCheckSerenazgo();
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => $healthy ? 'healthy' : 'degraded',
                'response_time_ms' => $responseTime,
                'endpoint' => config('services.mininter.serenazgo_endpoint'),
                'message' => $healthy ? 'Endpoint SERENAZGO accesible' : 'Endpoint SERENAZGO no responde'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'endpoint' => config('services.mininter.serenazgo_endpoint'),
                'message' => 'Error de conexión a endpoint SERENAZGO'
            ];
        }
    }

    /**
     * Verificar endpoint MININTER POLICIAL
     */
    private function checkMininterPolicial(): array
    {
        try {
            $mininterClient = app(MininterClient::class);
            $start = microtime(true);

            // Verificar endpoint de health
            $healthy = $mininterClient->healthCheckPolicial();
            
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => $healthy ? 'healthy' : 'degraded',
                'response_time_ms' => $responseTime,
                'endpoint' => config('services.mininter.policial_endpoint'),
                'message' => $healthy ? 'Endpoint POLICIAL accesible' : 'Endpoint POLICIAL no responde'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'endpoint' => config('services.mininter.policial_endpoint'),
                'message' => 'Error de conexión a endpoint POLICIAL'
            ];
        }
    }

    /**
     * Verificar cola de trabajos
     */
    private function checkQueue(): array
    {
        try {
            // Verificar jobs pendientes
            $pendingJobs = DB::table('jobs')->where('queue', 'sync')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            
            // Verificar jobs viejos (más de 1 hora)
            $oldJobs = DB::table('jobs')
                ->where('queue', 'sync')
                ->where('created_at', '<', Carbon::now()->subHour())
                ->count();

            $status = 'healthy';
            if ($oldJobs > 0) {
                $status = 'degraded';
            }
            if ($failedJobs > 10) {
                $status = 'unhealthy';
            }

            return [
                'status' => $status,
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'old_jobs' => $oldJobs,
                'message' => "Cola: {$pendingJobs} pendientes, {$failedJobs} fallidos"
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Error verificando cola de trabajos'
            ];
        }
    }

    /**
     * Verificar logs
     */
    private function checkLogs(): array
    {
        try {
            $logPath = storage_path('logs');
            $gpsLogPath = storage_path('logs/gps');
            
            // Verificar que los directorios existen
            $logsWritable = is_writable($logPath);
            $gpsLogsWritable = is_writable($gpsLogPath);
            
            // Verificar tamaño de logs
            $logSize = $this->getDirectorySize($logPath);
            $logSizeMB = round($logSize / 1024 / 1024, 2);

            $status = 'healthy';
            if (!$logsWritable || !$gpsLogsWritable) {
                $status = 'unhealthy';
            }
            if ($logSizeMB > 500) { // Más de 500MB
                $status = 'degraded';
            }

            return [
                'status' => $status,
                'logs_writable' => $logsWritable,
                'gps_logs_writable' => $gpsLogsWritable,
                'total_size_mb' => $logSizeMB,
                'message' => "Logs: {$logSizeMB}MB total"
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Error verificando logs'
            ];
        }
    }

    /**
     * Evaluar estado general
     */
    private function evaluateOverallHealth(array $healthStatus): array
    {
        $unhealthyCount = 0;
        $degradedCount = 0;

        foreach ($healthStatus['checks'] as $check) {
            if ($check['status'] === 'unhealthy') {
                $unhealthyCount++;
                $healthStatus['errors'][] = $check['message'];
            } elseif ($check['status'] === 'degraded') {
                $degradedCount++;
            }
        }

        if ($unhealthyCount > 0) {
            $healthStatus['overall'] = 'unhealthy';
        } elseif ($degradedCount > 0) {
            $healthStatus['overall'] = 'degraded';
        } else {
            $healthStatus['overall'] = 'healthy';
        }

        return $healthStatus;
    }

    /**
     * Mostrar resultados
     */
    private function displayHealthResults(array $healthStatus): void
    {
        $this->info('');
        $this->info('📊 Resumen de Health Check:');
        $this->info('─────────────────────────────────────');

        $overallIcon = match ($healthStatus['overall']) {
            'healthy' => '✅',
            'degraded' => '⚠️',
            'unhealthy' => '❌',
            default => '❓'
        };

        $this->info("{$overallIcon} Estado general: " . strtoupper($healthStatus['overall']));
        $this->info('');

        foreach ($healthStatus['checks'] as $component => $check) {
            $icon = match ($check['status']) {
                'healthy' => '✅',
                'degraded' => '⚠️',
                'unhealthy' => '❌',
                default => '❓'
            };

            $this->info("{$icon} " . ucfirst($component) . ': ' . $check['message']);
            
            if ($this->option('detailed')) {
                if (isset($check['response_time_ms'])) {
                    $this->info("   └─ Tiempo respuesta: {$check['response_time_ms']}ms");
                }
                if (isset($check['endpoint'])) {
                    $this->info("   └─ Endpoint: {$check['endpoint']}");
                }
                if (isset($check['error'])) {
                    $this->warn("   └─ Error: {$check['error']}");
                }
            }
        }

        if (!empty($healthStatus['errors'])) {
            $this->info('');
            $this->warn('🚨 Problemas encontrados:');
            foreach ($healthStatus['errors'] as $error) {
                $this->warn("   • {$error}");
            }
        }
    }

    /**
     * Obtener tamaño de directorio
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        if (is_dir($path)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
} 