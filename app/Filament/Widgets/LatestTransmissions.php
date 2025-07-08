<?php

namespace App\Filament\Widgets;

use App\Models\Transmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;

class LatestTransmissions extends BaseWidget
{
    protected static ?string $heading = 'Últimas Transmisiones';
    
    protected static ?int $sort = 3;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $maxHeight = '400px';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transmission::with('municipality')
                    ->latest('sent_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('municipality.name')
                    ->label('Municipalidad')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->icon('heroicon-m-building-office-2'),
                    
                TextColumn::make('municipality.tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SERENAZGO' => 'success',
                        'POLICIAL' => 'info',
                        default => 'gray',
                    }),
                    
                BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'success' => 'SENT',
                        'danger' => 'FAILED',
                        'warning' => 'PENDING',
                        'gray' => 'QUEUED',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'SENT',
                        'heroicon-m-x-circle' => 'FAILED',
                        'heroicon-m-clock' => 'PENDING',
                        'heroicon-m-queue-list' => 'QUEUED',
                    ]),
                    
                TextColumn::make('response_code')
                    ->label('Código Resp.')
                    ->badge()
                    ->color(function ($state) {
                        if (!$state) return 'gray';
                        return str_starts_with((string)$state, '2') ? 'success' : 'danger';
                    })
                    ->formatStateUsing(fn ($state) => $state ?: '-'),
                    
                TextColumn::make('sent_at')
                    ->label('Enviado')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->icon('heroicon-m-clock')
                    ->since()
                    ->tooltip(fn ($record) => $record->sent_at?->format('d/m/Y H:i:s')),
                    
                TextColumn::make('retry_count')
                    ->label('Reintentos')
                    ->badge()
                    ->color(function ($state) {
                        return match (true) {
                            $state == 0 => 'success',
                            $state <= 2 => 'warning',
                            default => 'danger'
                        };
                    })
                    ->icon(function ($state) {
                        return match (true) {
                            $state == 0 => 'heroicon-m-check',
                            $state <= 2 => 'heroicon-m-arrow-path',
                            default => 'heroicon-m-exclamation-triangle'
                        };
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('Ver Detalles')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->url(fn (Transmission $record): string => 
                        route('filament.admin.resources.transmissions.view', $record)
                    )
                    ->openUrlInNewTab(false),
            ])
            ->defaultSort('sent_at', 'desc')
            ->striped()
            ->paginated(false)
            ->poll('30s'); // Auto-refresh cada 30 segundos
    }
    
    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10]; // Solo mostrar 10 registros
    }
}
