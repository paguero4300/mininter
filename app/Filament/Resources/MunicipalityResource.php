<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MunicipalityResource\Pages;
use App\Filament\Resources\MunicipalityResource\RelationManagers;
use App\Models\Municipality;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MunicipalityResource extends Resource
{
    protected static ?string $model = Municipality::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    
    protected static ?string $navigationLabel = 'Municipalidades';
    
    protected static ?string $modelLabel = 'Municipalidad';
    
    protected static ?string $pluralModelLabel = 'Municipalidades';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información Básica')
            ->schema([
                Forms\Components\TextInput::make('name')
                            ->label('Nombre de la Municipalidad')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        
                        Forms\Components\TextInput::make('ubigeo')
                            ->label('Código UBIGEO')
                            ->required()
                            ->length(6)
                            ->numeric()
                            ->placeholder('Ej: 230101')
                            ->helperText('Código UBIGEO de 6 dígitos'),
                        
                        Forms\Components\Select::make('tipo')
                            ->label('Tipo de Municipalidad')
                    ->required()
                            ->options([
                                'SERENAZGO' => 'SERENAZGO',
                                'POLICIAL' => 'POLICIAL',
                            ])
                            ->reactive()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Configuración GPS')
                    ->schema([
                Forms\Components\TextInput::make('token_gps')
                            ->label('Token GPS (GPServer)')
                    ->required()
                            ->length(32)
                            ->password()
                            ->revealable()
                            ->helperText('Token de 32 caracteres para acceso a GPServer')
                            ->columnSpan(2),
                        
                Forms\Components\TextInput::make('codigo_comisaria')
                            ->label('Código de Comisaría')
                            ->maxLength(6)
                            ->numeric()
                            ->hidden(fn (Forms\Get $get) => $get('tipo') !== 'POLICIAL')
                            ->required(fn (Forms\Get $get) => $get('tipo') === 'POLICIAL')
                            ->helperText('Requerido solo para tipo POLICIAL')
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Estado')
                    ->schema([
                Forms\Components\Toggle::make('active')
                            ->label('Activo')
                            ->helperText('Solo las municipalidades activas serán sincronizadas')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Municipalidad')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                
                Tables\Columns\TextColumn::make('ubigeo')
                    ->label('UBIGEO')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->colors([
                        'success' => 'SERENAZGO',
                        'warning' => 'POLICIAL',
                    ])
                    ->icons([
                        'heroicon-m-shield-check' => 'SERENAZGO',
                        'heroicon-m-identification' => 'POLICIAL',
                    ]),
                
                Tables\Columns\TextColumn::make('codigo_comisaria')
                    ->label('Cód. Comisaría')
                    ->placeholder('N/A')
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('transmissions_count')
                    ->label('Transmisiones')
                    ->counts('transmissions')
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options([
                        'SERENAZGO' => 'SERENAZGO',
                        'POLICIAL' => 'POLICIAL',
                    ]),
                
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('test_gps')
                    ->label('Test GPS')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (Municipality $record) {
                        // TODO: Implementar test de conexión GPS
                        \Filament\Notifications\Notification::make()
                            ->title('Test GPS ejecutado')
                            ->body("Verificando conexión para {$record->name}")
                            ->info()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['active' => true]);
                        }),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Desactivar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each->update(['active' => false]);
                        }),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\TransmissionsRelationManager::class, // TODO: Crear después
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMunicipalities::route('/'),
            'create' => Pages\CreateMunicipality::route('/create'),
            'edit' => Pages\EditMunicipality::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('active', true)->count();
    }
    
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}
