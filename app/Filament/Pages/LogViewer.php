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
                // Verificar tamaño del archivo
                $fileSize = File::size($filepath);
                
                if ($fileSize > 10 * 1024 * 1024) { // 10MB límite
                    // Para archivos grandes, leer solo las últimas líneas
                    $content = $this->readLogFileTail($filepath, 1000); // Últimas 1000 líneas
                    
                    // Agregar notificación de que es un archivo grande
                    \Filament\Notifications\Notification::make()
                        ->title('Archivo de log grande detectado')
                        ->body("El archivo {$filename} es grande (" . round($fileSize / 1024 / 1024, 2) . "MB). Mostrando solo las últimas 1000 líneas.")
                        ->warning()
                        ->send();
                        
                } else {
                    // Para archivos pequeños, leer todo el contenido
                    $content = File::get($filepath);
                }
                
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
    
    private function readLogFileTail(string $filepath, int $lines = 1000): string
    {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            return '';
        }
        
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];
        
        while ($linecounter > 0) {
            $t = ' ';
            while ($t != "\n") {
                if(fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos --;
            }
            
            $linecounter --;
            if($beginning) {
                rewind($handle);
            }
            
            $text[$lines - $linecounter - 1] = fgets($handle);
            
            if($beginning) break;
        }
        
        fclose($handle);
        return implode('', array_reverse($text));
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

    public function clearLogs(): void
    {
        try {
            $date = $this->selectedDate ? Carbon::parse($this->selectedDate) : now();
            $clearedFiles = [];
            
            if ($this->selectedChannel === 'all') {
                // Limpiar todos los logs del día
                $channels = ['gps', 'transmissions', 'system', 'errors'];
                foreach ($channels as $channel) {
                    $filename = "{$channel}-{$date->format('Y-m-d')}.log";
                    $filepath = storage_path("logs/gps/{$filename}");
                    if (File::exists($filepath)) {
                        File::put($filepath, '');
                        $clearedFiles[] = $filename;
                    }
                }
            } else {
                // Limpiar log específico
                $filename = "{$this->selectedChannel}-{$date->format('Y-m-d')}.log";
                $filepath = storage_path("logs/gps/{$filename}");
                if (File::exists($filepath)) {
                    File::put($filepath, '');
                    $clearedFiles[] = $filename;
                }
            }
            
            if (!empty($clearedFiles)) {
                \Filament\Notifications\Notification::make()
                    ->title('Logs limpiados exitosamente')
                    ->body('Se limpiaron los siguientes archivos: ' . implode(', ', $clearedFiles))
                    ->success()
                    ->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Sin archivos para limpiar')
                    ->body('No se encontraron archivos de log para la fecha seleccionada')
                    ->warning()
                    ->send();
            }
            
            // Recargar logs después de limpiar
            $this->loadLogs();
            
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al limpiar logs')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function clearAllLogs(): void
    {
        try {
            $clearedFiles = [];
            $logsPath = storage_path('logs/gps');
            
            if (File::exists($logsPath)) {
                $files = File::glob($logsPath . '/*.log');
                foreach ($files as $file) {
                    File::put($file, '');
                    $clearedFiles[] = basename($file);
                }
            }
            
            // También limpiar el log principal de Laravel
            $laravelLog = storage_path('logs/laravel.log');
            if (File::exists($laravelLog)) {
                File::put($laravelLog, '');
                $clearedFiles[] = 'laravel.log';
            }
            
            if (!empty($clearedFiles)) {
                \Filament\Notifications\Notification::make()
                    ->title('Todos los logs limpiados')
                    ->body('Se limpiaron ' . count($clearedFiles) . ' archivos de log')
                    ->success()
                    ->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Sin archivos para limpiar')
                    ->body('No se encontraron archivos de log')
                    ->warning()
                    ->send();
            }
            
            // Recargar logs después de limpiar
            $this->loadLogs();
            
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al limpiar logs')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
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
                
            \Filament\Actions\Action::make('clear')
                ->label('Limpiar Logs Actuales')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Confirmar limpieza de logs')
                ->modalDescription(function () {
                    $date = $this->selectedDate ? Carbon::parse($this->selectedDate)->format('d/m/Y') : now()->format('d/m/Y');
                    $channel = $this->selectedChannel === 'all' ? 'todos los canales' : $this->selectedChannel;
                    return "¿Estás seguro de que quieres limpiar los logs de {$channel} para el día {$date}? Esta acción no se puede deshacer.";
                })
                ->modalSubmitActionLabel('Sí, limpiar logs')
                ->action('clearLogs'),
                
            \Filament\Actions\Action::make('clearAll')
                ->label('Limpiar Todos los Logs')
                ->icon('heroicon-o-fire')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('⚠️ Confirmar limpieza completa')
                ->modalDescription('¿Estás seguro de que quieres limpiar TODOS los archivos de log del sistema? Esta acción eliminará todos los logs GPS y de Laravel, y no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, limpiar TODOS los logs')
                ->action('clearAllLogs'),
        ];
    }
} 