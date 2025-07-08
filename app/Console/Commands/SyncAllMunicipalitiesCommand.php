<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Municipality;
use App\Jobs\SyncMunicipalityJob;
use App\Services\LoggingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando para sincronizar todas las municipalidades activas
 * 
 * Ejecuta jobs para cada municipalidad activa en la cola 'sync'
 * Se ejecuta cada minuto a través del scheduler
 */
class SyncAllMunicipalitiesCommand extends Command
{
    /**
     * Nombre del comando
     */
    protected $signature = 'gps:sync-all 
                          {--municipality=* : IDs específicas de municipalidades a sincronizar}
                          {--force : Forzar sincronización incluso si hay jobs pendientes}
                          {--dry-run : Mostrar qué se haría sin ejecutar}';

    /**
     * Descripción del comando
     */
    protected $description = 'Sincronizar datos GPS de todas las municipalidades activas';

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
        $this->info('🚀 Iniciando sincronización de municipalidades...');

        try {
            // Obtener municipalidades a sincronizar
            $municipalities = $this->getMunicipalities();
            
            if ($municipalities->isEmpty()) {
                $this->warn('⚠️  No se encontraron municipalidades para sincronizar');
                return Command::SUCCESS;
            }

            $this->info("📊 Municipalidades encontradas: {$municipalities->count()}");

            // Mostrar resumen
            $this->displayMunicipalitiesSummary($municipalities);

            // Verificar jobs pendientes si no se fuerza
            if (!$this->option('force') && $this->hasPendingJobs()) {
                $this->warn('⚠️  Hay jobs pendientes en la cola. Use --force para forzar nueva sincronización');
                return Command::FAILURE;
            }

            // Modo dry-run
            if ($this->option('dry-run')) {
                $this->info('🔍 Modo dry-run activado - No se ejecutarán jobs');
                return Command::SUCCESS;
            }

            // Ejecutar sincronización
            $results = $this->syncMunicipalities($municipalities);

            // Mostrar resultados
            $this->displayResults($results, $startTime);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la sincronización: {$e->getMessage()}");
            
            $this->logger->logError('system', 'Error en SyncAllMunicipalitiesCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'command' => $this->signature,
                'arguments' => $this->arguments(),
                'options' => $this->options()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Obtener municipalidades a sincronizar
     */
    private function getMunicipalities()
    {
        $municipalityIds = $this->option('municipality');
        
        if (!empty($municipalityIds)) {
            return Municipality::whereIn('id', $municipalityIds)->get();
        }

        return Municipality::active()->get();
    }

    /**
     * Mostrar resumen de municipalidades
     */
    private function displayMunicipalitiesSummary($municipalities): void
    {
        $this->table(
            ['ID', 'Nombre', 'Tipo', 'Ubigeo', 'Estado'],
            $municipalities->map(function ($municipality) {
                return [
                    substr($municipality->id, 0, 8) . '...',
                    $municipality->name,
                    $municipality->tipo,
                    $municipality->ubigeo,
                    $municipality->active ? '✅ Activa' : '❌ Inactiva'
                ];
            })
        );
    }

    /**
     * Verificar si hay jobs pendientes
     */
    private function hasPendingJobs(): bool
    {
        // Verificar jobs pendientes en la cola 'sync'
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'sync')
            ->where('reserved_at', null)
            ->count();

        // Verificar jobs en ejecución
        $runningJobs = DB::table('jobs')
            ->where('queue', 'sync')
            ->where('reserved_at', '!=', null)
            ->count();

        $total = $pendingJobs + $runningJobs;

        if ($total > 0) {
            $this->info("📋 Jobs en cola 'sync': {$pendingJobs} pendientes, {$runningJobs} en ejecución");
        }

        return $total > 0;
    }

    /**
     * Sincronizar municipalidades
     */
    private function syncMunicipalities($municipalities): array
    {
        $results = [
            'total' => $municipalities->count(),
            'dispatched' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($municipalities as $municipality) {
            try {
                if (!$municipality->active) {
                    $this->warn("⚠️  Saltando {$municipality->name} (inactiva)");
                    $results['skipped']++;
                    continue;
                }

                // Dispatch job
                SyncMunicipalityJob::dispatch($municipality)
                    ->onQueue('sync')
                    ->delay(now()->addSeconds(rand(1, 10))); // Pequeño delay aleatorio

                $this->info("✅ Job enviado para {$municipality->name}");
                $results['dispatched']++;

                // Log del dispatch
                $this->logger->logInfo('system', 'Job SyncMunicipalityJob enviado', [
                    'municipality_id' => $municipality->id,
                    'municipality_name' => $municipality->name,
                    'municipality_type' => $municipality->tipo,
                    'command' => 'sync-all'
                ]);

            } catch (\Exception $e) {
                $error = "Error con {$municipality->name}: {$e->getMessage()}";
                $this->error("❌ {$error}");
                $results['errors'][] = $error;

                $this->logger->logError('system', 'Error enviando job', [
                    'municipality_id' => $municipality->id,
                    'municipality_name' => $municipality->name,
                    'error' => $e->getMessage(),
                    'command' => 'sync-all'
                ]);
            }
        }

        return $results;
    }

    /**
     * Mostrar resultados
     */
    private function displayResults(array $results, Carbon $startTime): void
    {
        $duration = $startTime->diffInSeconds(Carbon::now());
        
        $this->info('');
        $this->info('📊 Resumen de sincronización:');
        $this->info("   • Total municipalidades: {$results['total']}");
        $this->info("   • Jobs enviados: {$results['dispatched']}");
        $this->info("   • Saltadas: {$results['skipped']}");
        $this->info("   • Errores: " . count($results['errors']));
        $this->info("   • Duración: {$duration}s");

        if (!empty($results['errors'])) {
            $this->warn('');
            $this->warn('⚠️  Errores encontrados:');
            foreach ($results['errors'] as $error) {
                $this->warn("   • {$error}");
            }
        }

        // Log del resumen
        $this->logger->logInfo('system', 'Sincronización completada', [
            'command' => 'sync-all',
            'results' => $results,
            'duration_seconds' => $duration,
            'executed_at' => $startTime->toISOString()
        ]);

        if ($results['dispatched'] > 0) {
            $this->info('');
            $this->info('🎯 Jobs enviados a la cola. Verifique el progreso con:');
            $this->info('   php artisan horizon:status');
            $this->info('   php artisan queue:work --queue=sync');
        }
    }
} 