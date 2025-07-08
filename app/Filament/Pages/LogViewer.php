<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;

class LogViewer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'Visor de Logs';
    
    protected static string $view = 'filament.pages.log-viewer';
    
    protected static ?string $title = 'Visor de Logs GPS';
    
    protected static ?int $navigationSort = 99;
    
    public ?array $data = [];
    
    public $logEntries = [];
    
    public $selectedChannel = 'all';
    public $searchText = '';
    public $selectedDate = null;
    public $selectedLevel = 'all';

    protected function getFormSchema(): array
    {
        return [
            Select::make('selectedChannel')
                ->label('Canal de Log')
                ->options([
                    'all' => 'Todos los canales',
                    'gps' => 'GPS',
                    'transmissions' => 'Transmisiones',
                    'system' => 'Sistema',
                    'errors' => 'Errores',
                ])
                ->default('all')
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadLogs()),
                
            Select::make('selectedLevel')
                ->label('Nivel de Log')
                ->options([
                    'all' => 'Todos los niveles',
                    'DEBUG' => 'Debug',
                    'INFO' => 'Info',
                    'WARNING' => 'Warning',
                    'ERROR' => 'Error',
                ])
                ->default('all')
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadLogs()),
                
            DatePicker::make('selectedDate')
                ->label('Fecha')
                ->default(now()->format('Y-m-d'))
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadLogs()),
                
            TextInput::make('searchText')
                ->label('Buscar en logs')
                ->placeholder('Buscar mensaje, municipalidad, IP...')
                ->debounce(500)
                ->reactive()
                ->afterStateUpdated(fn () => $this->loadLogs()),
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            'selectedChannel' => 'all',
            'selectedLevel' => 'all',
            'selectedDate' => now()->format('Y-m-d'),
            'searchText' => '',
        ]);
        
        $this->loadLogs();
    }

    public function loadLogs(): void
    {
        $this->logEntries = [];
        
        $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : now();
        $logsPath = storage_path('logs/gps');
        
        if (!File::exists($logsPath)) {
            return;
        }
        
        $channels = $this->selectedChannel === 'all' 
            ? ['gps', 'transmissions', 'system', 'errors']
            : [$this->selectedChannel];
        
        foreach ($channels as $channel) {
            $filename = "{$channel}-{$date->format('Y-m-d')}.log";
            $filepath = "{$logsPath}/{$filename}";
            
            if (File::exists($filepath)) {
                $content = File::get($filepath);
                $this->parseLogContent($content, $channel);
            }
        }
        
        // Ordenar por timestamp descendente
        usort($this->logEntries, function ($a, $b) {
            return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
        });
        
        // Aplicar filtros adicionales
        $this->applyFilters();
    }
    
    private function parseLogContent(string $content, string $channel): void
    {
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Parsear línea de log estándar de Laravel
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+)$/', $line, $matches)) {
                $timestamp = $matches[1];
                $level = strtoupper($matches[2]);
                $message = $matches[3];
                
                // Intentar parsear contexto JSON si existe
                $context = [];
                if (strpos($message, '{') !== false) {
                    $jsonStart = strpos($message, '{');
                    $jsonPart = substr($message, $jsonStart);
                    $decoded = json_decode($jsonPart, true);
                    if ($decoded) {
                        $context = $decoded;
                        $message = trim(substr($message, 0, $jsonStart));
                    }
                }
                
                $this->logEntries[] = [
                    'timestamp' => $timestamp,
                    'channel' => $channel,
                    'level' => $level,
                    'message' => $message,
                    'context' => $context,
                    'raw' => $line,
                ];
            }
        }
    }
    
    private function applyFilters(): void
    {
        if ($this->selectedLevel !== 'all') {
            $this->logEntries = array_filter($this->logEntries, function ($entry) {
                return $entry['level'] === $this->selectedLevel;
            });
        }
        
        if (!empty($this->searchText)) {
            $searchTerm = strtolower($this->searchText);
            $this->logEntries = array_filter($this->logEntries, function ($entry) use ($searchTerm) {
                return strpos(strtolower($entry['message']), $searchTerm) !== false ||
                       strpos(strtolower(json_encode($entry['context'])), $searchTerm) !== false;
            });
        }
        
        // Limitar a 500 entradas para performance
        $this->logEntries = array_slice($this->logEntries, 0, 500);
    }
    
    public function refreshLogs(): void
    {
        $this->loadLogs();
    }
    
    public function downloadLogs(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : now();
        $channel = $this->selectedChannel === 'all' ? 'all' : $this->selectedChannel;
        
        if ($channel === 'all') {
            // TODO: Crear ZIP con todos los logs del día
            $filename = "gps-logs-{$date->format('Y-m-d')}.log";
            $filepath = storage_path("logs/gps/system-{$date->format('Y-m-d')}.log");
        } else {
            $filename = "{$channel}-{$date->format('Y-m-d')}.log";
            $filepath = storage_path("logs/gps/{$filename}");
        }
        
        if (File::exists($filepath)) {
            return response()->download($filepath, $filename);
        }
        
        abort(404, 'Archivo de log no encontrado');
    }
    
    protected function getActions(): array
    {
        return [
            \Filament\Actions\Action::make('refresh')
                ->label('Actualizar')
                ->icon('heroicon-o-arrow-path')
                ->action('refreshLogs'),
                
            \Filament\Actions\Action::make('download')
                ->label('Descargar')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('downloadLogs'),
        ];
    }
} 