<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LoggingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Comando para limpiar logs antiguos
 * 
 * Mantiene el almacenamiento optimizado eliminando logs antiguos
 * según políticas de retención configuradas
 */
class LogCleanupCommand extends Command
{
    /**
     * Nombre del comando
     */
    protected $signature = 'gps:log-cleanup 
                          {--days=30 : Días de retención de logs}
                          {--dry-run : Mostrar qué archivos se eliminarían sin borrar}
                          {--force : Forzar limpieza sin confirmación}';

    /**
     * Descripción del comando
     */
    protected $description = 'Limpiar logs antiguos del sistema GPS';

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
        $retentionDays = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🧹 Iniciando limpieza de logs...');
        $this->info("📅 Eliminando logs anteriores a: {$retentionDays} días");

        try {
            // Obtener directorio de logs
            $logPaths = $this->getLogPaths();
            
            if (empty($logPaths)) {
                $this->warn('⚠️  No se encontraron directorios de logs');
                return Command::SUCCESS;
            }

            // Mostrar directorios a limpiar
            $this->info('📁 Directorios a limpiar:');
            foreach ($logPaths as $path) {
                $this->info("   • {$path}");
            }

            // Analizar archivos de log
            $filesToDelete = $this->analyzeLogFiles($logPaths, $retentionDays);

            if (empty($filesToDelete)) {
                $this->info('✅ No se encontraron logs antiguos para eliminar');
                return Command::SUCCESS;
            }

            // Mostrar resumen
            $this->displayCleanupSummary($filesToDelete, $dryRun);

            // Confirmar eliminación si no es dry-run
            if (!$dryRun && !$force) {
                if (!$this->confirm('¿Continuar con la eliminación?')) {
                    $this->info('❌ Operación cancelada');
                    return Command::SUCCESS;
                }
            }

            // Ejecutar limpieza
            $results = $this->performCleanup($filesToDelete, $dryRun);

            // Mostrar resultados
            $this->displayResults($results, $startTime, $dryRun);

            // Log del proceso
            $this->logger->logInfo('system', 'Limpieza de logs completada', [
                'retention_days' => $retentionDays,
                'dry_run' => $dryRun,
                'results' => $results,
                'duration_seconds' => $startTime->diffInSeconds(Carbon::now())
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la limpieza: {$e->getMessage()}");
            
            $this->logger->logError('system', 'Error en limpieza de logs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'retention_days' => $retentionDays,
                'dry_run' => $dryRun
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Obtener rutas de logs
     */
    private function getLogPaths(): array
    {
        $paths = [];
        
        // Directorio principal de logs
        $mainLogPath = storage_path('logs');
        if (File::exists($mainLogPath)) {
            $paths[] = $mainLogPath;
        }

        // Directorio de logs GPS
        $gpsLogPath = storage_path('logs/gps');
        if (File::exists($gpsLogPath)) {
            $paths[] = $gpsLogPath;
        }

        return $paths;
    }

    /**
     * Analizar archivos de log
     */
    private function analyzeLogFiles(array $logPaths, int $retentionDays): array
    {
        $filesToDelete = [];
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        foreach ($logPaths as $path) {
            $files = File::allFiles($path);
            
            foreach ($files as $file) {
                $filePath = $file->getPathname();
                $fileName = $file->getFilename();
                
                // Solo procesar archivos de log
                if (!$this->isLogFile($fileName)) {
                    continue;
                }

                // Verificar fecha de modificación
                $lastModified = Carbon::createFromTimestamp($file->getMTime());
                
                if ($lastModified->lt($cutoffDate)) {
                    $filesToDelete[] = [
                        'path' => $filePath,
                        'name' => $fileName,
                        'size' => $file->getSize(),
                        'modified' => $lastModified,
                        'age_days' => $lastModified->diffInDays(Carbon::now())
                    ];
                }
            }
        }

        // Ordenar por fecha de modificación
        usort($filesToDelete, function ($a, $b) {
            return $a['modified']->compare($b['modified']);
        });

        return $filesToDelete;
    }

    /**
     * Verificar si es un archivo de log
     */
    private function isLogFile(string $fileName): bool
    {
        $logExtensions = ['.log', '.txt'];
        $logPatterns = [
            '/^laravel-.*\.log$/',
            '/^.*-\d{4}-\d{2}-\d{2}\.log$/',
            '/^gps-.*\.log$/',
            '/^transmissions-.*\.log$/',
            '/^system-.*\.log$/',
            '/^errors-.*\.log$/'
        ];

        // Verificar extensión
        foreach ($logExtensions as $ext) {
            if (str_ends_with($fileName, $ext)) {
                return true;
            }
        }

        // Verificar patrones
        foreach ($logPatterns as $pattern) {
            if (preg_match($pattern, $fileName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mostrar resumen de limpieza
     */
    private function displayCleanupSummary(array $filesToDelete, bool $dryRun): void
    {
        $totalFiles = count($filesToDelete);
        $totalSize = array_sum(array_column($filesToDelete, 'size'));
        $totalSizeMB = round($totalSize / 1024 / 1024, 2);

        $this->info('');
        $this->info('📊 Resumen de limpieza:');
        $this->info("   • Archivos a eliminar: {$totalFiles}");
        $this->info("   • Espacio a liberar: {$totalSizeMB} MB");

        if ($dryRun) {
            $this->info('   • Modo: DRY RUN (simulación)');
        }

        $this->info('');
        $this->info('📋 Archivos por eliminar:');
        
        foreach ($filesToDelete as $file) {
            $sizeMB = round($file['size'] / 1024 / 1024, 2);
            $this->info(sprintf(
                "   • %s (%s MB, %d días)",
                $file['name'],
                $sizeMB,
                $file['age_days']
            ));
        }
    }

    /**
     * Realizar limpieza
     */
    private function performCleanup(array $filesToDelete, bool $dryRun): array
    {
        $results = [
            'total_files' => count($filesToDelete),
            'deleted_files' => 0,
            'deleted_size' => 0,
            'errors' => []
        ];

        foreach ($filesToDelete as $file) {
            try {
                if (!$dryRun) {
                    File::delete($file['path']);
                }
                
                $results['deleted_files']++;
                $results['deleted_size'] += $file['size'];
                
                $this->info("✅ " . ($dryRun ? 'Simularía eliminar' : 'Eliminado') . ": {$file['name']}");

            } catch (\Exception $e) {
                $error = "Error eliminando {$file['name']}: {$e->getMessage()}";
                $results['errors'][] = $error;
                $this->error("❌ {$error}");
            }
        }

        return $results;
    }

    /**
     * Mostrar resultados
     */
    private function displayResults(array $results, Carbon $startTime, bool $dryRun): void
    {
        $duration = $startTime->diffInSeconds(Carbon::now());
        $deletedSizeMB = round($results['deleted_size'] / 1024 / 1024, 2);

        $this->info('');
        $this->info('📊 Resultados de la limpieza:');
        $this->info("   • Archivos procesados: {$results['total_files']}");
        $this->info("   • Archivos " . ($dryRun ? 'que se eliminarían' : 'eliminados') . ": {$results['deleted_files']}");
        $this->info("   • Espacio " . ($dryRun ? 'que se liberaría' : 'liberado') . ": {$deletedSizeMB} MB");
        $this->info("   • Errores: " . count($results['errors']));
        $this->info("   • Duración: {$duration}s");

        if (!empty($results['errors'])) {
            $this->warn('');
            $this->warn('⚠️  Errores encontrados:');
            foreach ($results['errors'] as $error) {
                $this->warn("   • {$error}");
            }
        }

        if ($dryRun) {
            $this->info('');
            $this->info('💡 Para ejecutar la limpieza real, use:');
            $this->info('   php artisan gps:log-cleanup --force');
        }
    }
} 