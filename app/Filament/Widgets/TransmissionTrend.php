<?php

namespace App\Filament\Widgets;

use App\Models\Transmission;
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;

class TransmissionTrend extends ChartWidget
{
    protected static ?string $heading = 'Tendencia de Transmisiones (Últimas 24h)';
    
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $labels = [];
        $successData = [];
        $failedData = [];
        
        // Generar datos para las últimas 24 horas
        for ($i = 23; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $labels[] = $hour->format('H:i');
            
            $startHour = $hour->startOfHour();
            $endHour = $hour->copy()->endOfHour();
            
            $successful = Transmission::whereBetween('sent_at', [$startHour, $endHour])
                ->where('status', 'SENT')
                ->count();
                
            $failed = Transmission::whereBetween('sent_at', [$startHour, $endHour])
                ->where('status', 'FAILED')
                ->count();
            
            $successData[] = $successful;
            $failedData[] = $failed;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Exitosas',
                    'data' => $successData,
                    'borderColor' => '#10b981', // green-500
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Fallidas',
                    'data' => $failedData,
                    'borderColor' => '#ef4444', // red-500
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }
    
    protected function getPollingInterval(): ?string
    {
        return '60s'; // Actualizar cada minuto
    }
}
