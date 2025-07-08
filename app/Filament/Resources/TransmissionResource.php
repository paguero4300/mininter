<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransmissionResource\Pages;
use App\Filament\Resources\TransmissionResource\RelationManagers;
use App\Models\Transmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransmissionResource extends Resource
{
    protected static ?string $model = Transmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    
    protected static ?string $navigationLabel = 'Transmisiones';
    
    protected static ?string $modelLabel = 'Transmisión';
    
    protected static ?string $pluralModelLabel = 'Transmisiones';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de Transmisión')
            ->schema([
                Forms\Components\Select::make('municipality_id')
                            ->label('Municipalidad')
                    ->relationship('municipality', 'name')
                            ->disabled()
                            ->dehydrated(false),
                        
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'SENT' => 'ENVIADO',
                                'FAILED' => 'FALLIDO',
                                'PENDING' => 'PENDIENTE',
                                'RETRYING' => 'REINTENTANDO',
                            ])
                            ->disabled()
                            ->dehydrated(false),
                        
                Forms\Components\TextInput::make('response_code')
                            ->label('Código de Respuesta')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Datos de Envío')
                    ->schema([
                Forms\Components\DateTimePicker::make('sent_at')
                            ->label('Fecha de Envío')
                            ->disabled()
                            ->dehydrated(false),
                        
                Forms\Components\TextInput::make('retry_count')
                            ->label('Intentos Realizados')
                    ->numeric()
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Payload y Respuesta')
                    ->schema([
                        Forms\Components\Textarea::make('payload')
                            ->label('Payload Enviado (JSON)')
                            ->rows(10)
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''),
                        
                        Forms\Components\Textarea::make('response_data')
                            ->label('Respuesta Recibida')
                            ->rows(6)
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Sin respuesta registrada'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('municipality.name')
                    ->label('Municipalidad')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'success' => 'SENT',
                        'danger' => 'FAILED',
                        'warning' => 'PENDING',
                        'info' => 'RETRYING',
                    ])
                    ->icons([
                        'heroicon-m-check-circle' => 'SENT',
                        'heroicon-m-x-circle' => 'FAILED',
                        'heroicon-m-clock' => 'PENDING',
                        'heroicon-m-arrow-path' => 'RETRYING',
                    ]),
                
                Tables\Columns\TextColumn::make('response_code')
                    ->label('Código HTTP')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state >= 200 && $state < 300,
                        'warning' => fn ($state) => $state >= 300 && $state < 400,
                        'danger' => fn ($state) => $state >= 400,
                    ])
                    ->placeholder('N/A'),
                
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Fecha Envío')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('retry_count')
                    ->label('Intentos')
                    ->badge()
                    ->colors([
                        'success' => fn ($state) => $state === 0,
                        'warning' => fn ($state) => $state > 0 && $state < 3,
                        'danger' => fn ($state) => $state >= 3,
                    ]),
                
                Tables\Columns\TextColumn::make('municipality.tipo')
                    ->label('Tipo')
                    ->badge()
                    ->colors([
                        'success' => 'SERENAZGO',
                        'warning' => 'POLICIAL',
                    ])
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'SENT' => 'ENVIADO',
                        'FAILED' => 'FALLIDO',
                        'PENDING' => 'PENDIENTE',
                        'RETRYING' => 'REINTENTANDO',
                    ]),
                
                Tables\Filters\SelectFilter::make('municipality')
                    ->relationship('municipality', 'name')
                    ->label('Municipalidad'),
                
                Tables\Filters\SelectFilter::make('municipality.tipo')
                    ->label('Tipo')
                    ->options([
                        'SERENAZGO' => 'SERENAZGO',
                        'POLICIAL' => 'POLICIAL',
                    ]),
                
                Tables\Filters\Filter::make('response_code')
                    ->form([
                        Forms\Components\Select::make('code_range')
                            ->options([
                                '2xx' => '2xx - Éxito',
                                '4xx' => '4xx - Error Cliente',
                                '5xx' => '5xx - Error Servidor',
                            ])
                            ->placeholder('Filtrar por código HTTP'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['code_range'] === '2xx',
                            fn (Builder $query) => $query->whereBetween('response_code', [200, 299])
                        )->when(
                            $data['code_range'] === '4xx',
                            fn (Builder $query) => $query->whereBetween('response_code', [400, 499])
                        )->when(
                            $data['code_range'] === '5xx',
                            fn (Builder $query) => $query->whereBetween('response_code', [500, 599])
                        );
                    }),
                
                Tables\Filters\Filter::make('fecha')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'],
                                fn (Builder $query, $date): Builder => $query->whereDate('sent_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                Tables\Actions\Action::make('retry')
                    ->label('Reintentar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Transmission $record) => $record->status === 'FAILED')
                    ->action(function (Transmission $record) {
                        // TODO: Implementar reenvío manual
                        \Filament\Notifications\Notification::make()
                            ->title('Reintento programado')
                            ->body("Se reintentará el envío para {$record->municipality->name}")
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('retry_failed')
                        ->label('Reintentar Fallidos')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records) {
                            $failed = $records->where('status', 'FAILED');
                            // TODO: Implementar reenvío bulk
                            \Filament\Notifications\Notification::make()
                                ->title('Reintentos programados')
                                ->body("Se reintentarán {$failed->count()} transmisiones fallidas")
                                ->info()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('sent_at', 'desc')
            ->poll('30s'); // Auto-refresh cada 30 segundos
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransmissions::route('/'),
            'view' => Pages\ViewTransmission::route('/{record}'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'FAILED')->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }
    
    // Deshabilitar creación y edición
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function canEdit($record): bool
    {
        return false;
    }
    
    public static function canDelete($record): bool
    {
        return false;
    }
}
