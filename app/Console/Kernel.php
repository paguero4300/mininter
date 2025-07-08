<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

/**
 * Kernel de consola para comandos y scheduler
 */
class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sincronizar datos GPS cada minuto
        $schedule->command('gps:sync-all')
            ->everyMinute()
            ->withoutOverlapping(5) // Evitar solapamiento, timeout 5 minutos
            ->onSuccess(function () {
                Log::info('Scheduled sync completed successfully');
            })
            ->onFailure(function () {
                Log::error('Scheduled sync failed');
            });

        // Health check cada 5 minutos
        $schedule->command('gps:health-check')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onFailure(function () {
                Log::warning('Health check failed');
            });

        // Limpieza de logs diaria a las 2:00 AM
        $schedule->command('gps:log-cleanup --force')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->onSuccess(function () {
                Log::info('Log cleanup completed');
            })
            ->onFailure(function () {
                Log::error('Log cleanup failed');
            });

        // Limpiar jobs fallidos cada hora
        $schedule->command('queue:prune-failed-jobs --hours=72')
            ->hourly()
            ->withoutOverlapping();

        // Restart queue workers cada 6 horas para evitar memory leaks
        $schedule->command('queue:restart')
            ->everySixHours()
            ->onSuccess(function () {
                Log::info('Queue workers restarted');
            });
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
} 