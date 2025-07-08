<?php

namespace App\Filament\Widgets;

use App\Models\Municipality;
use App\Models\Transmission;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        $totalMunicipalities = Municipality::count();
        $activeMunicipalities = Municipality::where('active', true)->count();
        
        $today = now()->startOfDay();
        $transmissionsToday = Transmission::whereDate('sent_at', '>=', $today)->count();
        $successfulToday = Transmission::whereDate('sent_at', '>=', $today)
            ->where('status', 'SENT')->count();
        $failedToday = Transmission::whereDate('sent_at', '>=', $today)
            ->where('status', 'FAILED')->count();
        
        $successRate = $transmissionsToday > 0 ? 
            round(($successfulToday / $transmissionsToday) * 100, 1) : 0;
        
        // Promedio tiempo respuesta últimas 24h (simulado)
        $avgResponseTime = Transmission::where('sent_at', '>=', now()->subHours(24))
            ->where('status', 'SENT')
            ->count() > 0 ? rand(150, 500) : 0; // TODO: Implementar cálculo real
        
        return [
            Stat::make('Municipalidades Activas', $activeMunicipalities)
                ->description("de {$totalMunicipalities} registradas")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($activeMunicipalities === $totalMunicipalities ? 'success' : 'warning')
                ->chart([
                    $activeMunicipalities, 
                    $totalMunicipalities - $activeMunicipalities
                ]),
            
            Stat::make('Transmisiones Hoy', $transmissionsToday)
                ->description("{$successfulToday} exitosas, {$failedToday} fallidas")
                ->descriptionIcon($failedToday > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($failedToday === 0 ? 'success' : ($failedToday > $successfulToday ? 'danger' : 'warning'))
                ->chart($this->getHourlyStats()),
            
            Stat::make('Tasa de Éxito', "{$successRate}%")
                ->description($transmissionsToday > 0 ? "de {$transmissionsToday} transmisiones" : "Sin transmisiones")
                ->descriptionIcon($successRate >= 95 ? 'heroicon-m-face-smile' : ($successRate >= 80 ? 'heroicon-m-face-frown' : 'heroicon-m-exclamation-circle'))
                ->color($successRate >= 95 ? 'success' : ($successRate >= 80 ? 'warning' : 'danger'))
                ->chart($this->getSuccessRateChart()),
            
            Stat::make('Tiempo Respuesta Promedio', "{$avgResponseTime}ms")
                ->description($avgResponseTime > 0 ? "últimas 24 horas" : "Sin datos")
                ->descriptionIcon($avgResponseTime <= 300 ? 'heroicon-m-bolt' : 'heroicon-m-clock')
                ->color($avgResponseTime <= 300 ? 'success' : ($avgResponseTime <= 500 ? 'warning' : 'danger'))
                ->chart($this->getResponseTimeChart()),
        ];
    }
    
    private function getHourlyStats(): array
    {
        // Obtener estadísticas por hora para las últimas 8 horas
        $hours = [];
        for ($i = 7; $i >= 0; $i--) {
            $hour = now()->subHours($i)->startOfHour();
            $count = Transmission::whereBetween('sent_at', [
                $hour, 
                $hour->copy()->endOfHour()
            ])->count();
            $hours[] = $count;
        }
        return $hours;
    }
    
    private function getSuccessRateChart(): array
    {
        // Tasa de éxito por las últimas 7 horas
        $rates = [];
        for ($i = 6; $i >= 0; $i--) {
            $hour = now()->subHours($i)->startOfHour();
            $total = Transmission::whereBetween('sent_at', [
                $hour, 
                $hour->copy()->endOfHour()
            ])->count();
            
            $successful = Transmission::whereBetween('sent_at', [
                $hour, 
                $hour->copy()->endOfHour()
            ])->where('status', 'SENT')->count();
            
            $rate = $total > 0 ? round(($successful / $total) * 100) : 100;
            $rates[] = $rate;
        }
        return $rates;
    }
    
    private function getResponseTimeChart(): array
    {
        // Simulación de tiempos de respuesta por hora (TODO: Implementar medición real)
        $times = [];
        for ($i = 6; $i >= 0; $i--) {
            $times[] = rand(150, 500);
        }
        return $times;
    }
    
    protected function getPollingInterval(): ?string
    {
        return '30s'; // Actualizar cada 30 segundos
    }
}
