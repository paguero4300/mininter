<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filtros -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Filtros de Búsqueda
            </h3>
            
            {{ $this->form }}
        </div>

        <!-- Estadísticas rápidas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="text-sm font-medium text-blue-600 dark:text-blue-400">Total Entradas</div>
                <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ count($logEntries) }}</div>
            </div>
            
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="text-sm font-medium text-green-600 dark:text-green-400">Info</div>
                <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                    {{ collect($logEntries)->where('level', 'INFO')->count() }}
                </div>
            </div>
            
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <div class="text-sm font-medium text-yellow-600 dark:text-yellow-400">Warning</div>
                <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">
                    {{ collect($logEntries)->where('level', 'WARNING')->count() }}
                </div>
            </div>
            
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="text-sm font-medium text-red-600 dark:text-red-400">Error</div>
                <div class="text-2xl font-bold text-red-900 dark:text-red-100">
                    {{ collect($logEntries)->where('level', 'ERROR')->count() }}
                </div>
            </div>
        </div>

        <!-- Logs -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Entradas de Log
                    @if($selectedDate)
                        - {{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }}
                    @endif
                </h3>
            </div>

            <div class="overflow-hidden">
                @if(empty($logEntries))
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No hay logs</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            No se encontraron entradas de log para los filtros seleccionados.
                        </p>
                    </div>
                @else
                    <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
                        @foreach($logEntries as $entry)
                            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <!-- Header con timestamp y nivel -->
                                        <div class="flex items-center space-x-3 mb-2">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 font-mono">
                                                {{ $entry['timestamp'] }}
                                            </span>
                                            
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @switch($entry['level'])
                                                    @case('ERROR')
                                                        bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                                                        @break
                                                    @case('WARNING')
                                                        bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                                        @break
                                                    @case('INFO')
                                                        bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                                        @break
                                                    @case('DEBUG')
                                                        bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                                        @break
                                                    @default
                                                        bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                                @endswitch
                                            ">
                                                {{ $entry['level'] }}
                                            </span>
                                            
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400">
                                                {{ strtoupper($entry['channel']) }}
                                            </span>
                                        </div>

                                        <!-- Mensaje -->
                                        <p class="text-sm text-gray-900 dark:text-gray-100 mb-2 font-mono">
                                            {{ $entry['message'] }}
                                        </p>

                                        <!-- Contexto si existe -->
                                        @if(!empty($entry['context']))
                                            <details class="mt-2">
                                                <summary class="text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:text-gray-700 dark:hover:text-gray-300">
                                                    Ver contexto ({{ count($entry['context']) }} items)
                                                </summary>
                                                <pre class="mt-2 text-xs bg-gray-100 dark:bg-gray-900 p-3 rounded border overflow-x-auto">{{ json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Auto-refresh notice -->
        <div class="text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                <svg class="inline h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Logs actualizados automáticamente • Mostrando últimas {{ count($logEntries) }} entradas
            </p>
        </div>
    </div>

    <!-- Auto-refresh script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh cada 30 segundos
            setInterval(function() {
                if (typeof Livewire !== 'undefined') {
                    Livewire.emit('refreshLogs');
                }
            }, 30000);
        });
    </script>
</x-filament-panels::page> 