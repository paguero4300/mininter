<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Municipality;

/**
 * Transformador de datos GPS entre formatos
 * 
 * Convierte datos GPS desde formato GPServer al formato
 * esperado por MININTER según especificaciones PT-GPS v1.2
 */
class PayloadTransformer
{
    /**
     * Transformar datos GPS para envío a SERENAZGO
     *
     * @param array $gpsObjects Datos GPS desde GPServer
     * @param Municipality $municipality Municipalidad
     * @return array Datos transformados
     */
    public function transformForSerenazgo(array $gpsObjects, Municipality $municipality): array
    {
        $transformedData = [];

        foreach ($gpsObjects as $gpsObject) {
            if (!$this->isValidGpsObject($gpsObject)) {
                continue;
            }

            $transformed = $this->transformBaseGpsObject($gpsObject);
            
            // Campos específicos para SERENAZGO
            $transformed['idMunicipalidad'] = $municipality->id;
            
            $transformedData[] = $transformed;
        }

        Log::info('PayloadTransformer: Datos transformados para SERENAZGO', [
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'original_count' => count($gpsObjects),
            'transformed_count' => count($transformedData)
        ]);

        return $transformedData;
    }

    /**
     * Transformar datos GPS para envío a POLICIAL
     *
     * @param array $gpsObjects Datos GPS desde GPServer
     * @param Municipality $municipality Municipalidad
     * @return array Datos transformados
     */
    public function transformForPolicial(array $gpsObjects, Municipality $municipality): array
    {
        $transformedData = [];

        foreach ($gpsObjects as $gpsObject) {
            if (!$this->isValidGpsObject($gpsObject)) {
                continue;
            }

            $transformed = $this->transformBaseGpsObject($gpsObject);
            
            // Campos específicos para POLICIAL
            $transformed['idTransmision'] = $this->generateTransmissionId();
            $transformed['codigoComisaria'] = $municipality->codigo_comisaria;
            
            $transformedData[] = $transformed;
        }

        Log::info('PayloadTransformer: Datos transformados para POLICIAL', [
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'codigo_comisaria' => $municipality->codigo_comisaria,
            'original_count' => count($gpsObjects),
            'transformed_count' => count($transformedData)
        ]);

        return $transformedData;
    }

    /**
     * Transformar objeto GPS base según especificación MININTER
     *
     * @param array $gpsObject Objeto GPS desde GPServer
     * @return array Objeto transformado en formato MININTER
     */
    private function transformBaseGpsObject(array $gpsObject): array
    {
        return [
            'alarma' => $this->formatAlarm($gpsObject['alarm'] ?? null),
            'altitud' => $this->formatAltitude($gpsObject['altitude'] ?? 0),
            'angulo' => $this->formatCourse($gpsObject['angle'] ?? $gpsObject['course'] ?? 0),
            'distancia' => $this->formatDistance($gpsObject['distance'] ?? 0),
            'fechaHora' => $this->formatDateTime($gpsObject['dt_server']),
            'horasMotor' => $this->formatEngineHours($gpsObject['engine_hours'] ?? 0),
            'ignition' => $this->formatIgnition($gpsObject['ignition'] ?? 0),
            'imei' => $this->sanitizeImei($gpsObject['imei']),
            'latitud' => $this->formatCoordinate($gpsObject['lat']),
            'longitud' => $this->formatCoordinate($gpsObject['lng']),
            'motion' => $this->formatMotion($gpsObject['motion'] ?? null, $gpsObject['speed'] ?? 0),
            'placa' => $this->extractPlateNumber($gpsObject),
            'totalDistancia' => $this->formatTotalDistance($gpsObject['odometer'] ?? 0),
            'totalHorasMotor' => $this->formatTotalEngineHours($gpsObject['engine_hours'] ?? 0),
            'ubigeo' => $this->extractUbigeo($gpsObject),
            'valid' => $this->formatValid($gpsObject['loc_valid'] ?? 1),
            'velocidad' => $this->formatSpeed($gpsObject['speed'] ?? 0)
        ];
    }

    /**
     * Validar objeto GPS
     *
     * @param mixed $gpsObject
     * @return bool
     */
    private function isValidGpsObject($gpsObject): bool
    {
        if (!is_array($gpsObject)) {
            return false;
        }

        $requiredFields = ['imei', 'lat', 'lng', 'dt_server'];
        
        foreach ($requiredFields as $field) {
            if (!isset($gpsObject[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitizar IMEI
     *
     * @param mixed $imei
     * @return string
     */
    private function sanitizeImei($imei): string
    {
        $imei = (string) $imei;
        return preg_replace('/[^0-9]/', '', $imei);
    }

    /**
     * Formatear coordenada
     *
     * @param mixed $coordinate
     * @return float
     */
    private function formatCoordinate($coordinate): float
    {
        return round((float) $coordinate, 6);
    }

    /**
     * Formatear fecha y hora según especificación
     *
     * @param mixed $dateTime
     * @return string Formato: dd/MM/yyyy HH:mm:ss
     */
    private function formatDateTime($dateTime): string
    {
        try {
            if (is_numeric($dateTime)) {
                // Timestamp Unix
                $carbon = Carbon::createFromTimestamp((int) $dateTime);
            } else {
                // String de fecha
                $carbon = Carbon::parse($dateTime);
            }

            return $carbon->format('d/m/Y H:i:s');

        } catch (\Exception $e) {
            Log::warning('PayloadTransformer: Error al formatear fecha', [
                'datetime' => $dateTime,
                'error' => $e->getMessage()
            ]);

            // Retornar fecha actual como fallback
            return Carbon::now()->format('d/m/Y H:i:s');
        }
    }

    /**
     * Formatear velocidad
     *
     * @param mixed $speed
     * @return int Velocidad en km/h
     */
    private function formatSpeed($speed): int
    {
        $speed = (float) $speed;
        return max(0, (int) round($speed));
    }

    /**
     * Formatear rumbo
     *
     * @param mixed $course
     * @return int Rumbo en grados (0-360)
     */
    private function formatCourse($course): int
    {
        $course = (float) $course;
        $course = fmod($course, 360);
        return $course < 0 ? (int) ($course + 360) : (int) $course;
    }

    /**
     * Formatear altitud
     *
     * @param mixed $altitude
     * @return int Altitud en metros
     */
    private function formatAltitude($altitude): int
    {
        return (int) round((float) $altitude);
    }

    /**
     * Formatear precisión
     *
     * @param mixed $precision
     * @return float Precisión en metros
     */
    private function formatPrecision($precision): float
    {
        return round((float) $precision, 1);
    }

    /**
     * Formatear nivel de batería
     *
     * @param mixed $battery
     * @return int Nivel de batería en porcentaje (0-100)
     */
    private function formatBattery($battery): int
    {
        $battery = (int) $battery;
        return max(0, min(100, $battery));
    }

    /**
     * Formatear estado de ignición
     *
     * @param mixed $ignition
     * @return bool
     */
    private function formatIgnition($ignition): bool
    {
        if (is_bool($ignition)) {
            return $ignition;
        }
        
        if (is_numeric($ignition)) {
            return (int) $ignition === 1;
        }
        
        if (is_string($ignition)) {
            return strtolower($ignition) === 'on' || $ignition === '1';
        }
        
        return false;
    }

    /**
     * Formatear estado del vehículo
     *
     * @param mixed $status
     * @return string
     */
    private function formatStatus($status): string
    {
        $status = strtoupper((string) $status);
        
        $validStatuses = ['MOVING', 'STOPPED', 'PARKED', 'OFFLINE', 'UNKNOWN'];
        
        return in_array($status, $validStatuses) ? $status : 'UNKNOWN';
    }

    /**
     * Formatear alarma
     *
     * @param mixed $alarm
     * @return string
     */
    private function formatAlarm($alarm): string
    {
        if (empty($alarm)) {
            return '';
        }
        
        return (string) $alarm;
    }

    /**
     * Formatear distancia
     *
     * @param mixed $distance
     * @return int Distancia en metros
     */
    private function formatDistance($distance): int
    {
        return (int) round((float) $distance);
    }

    /**
     * Formatear horas de motor
     *
     * @param mixed $engineHours
     * @return int Horas de motor
     */
    private function formatEngineHours($engineHours): int
    {
        return (int) round((float) $engineHours);
    }

    /**
     * Formatear distancia total
     *
     * @param mixed $odometer
     * @return int Distancia total en metros
     */
    private function formatTotalDistance($odometer): int
    {
        // Convertir km a metros si es necesario
        $distance = (float) $odometer;
        if ($distance < 1000) {
            // Si es menor a 1000, probablemente está en km
            $distance = $distance * 1000;
        }
        return (int) round($distance);
    }

    /**
     * Formatear total de horas de motor
     *
     * @param mixed $totalEngineHours
     * @return int Total de horas de motor
     */
    private function formatTotalEngineHours($totalEngineHours): int
    {
        return (int) round((float) $totalEngineHours);
    }

    /**
     * Formatear estado de movimiento
     *
     * @param mixed $motion
     * @param mixed $speed
     * @return bool
     */
    private function formatMotion($motion, $speed): bool
    {
        if ($motion !== null) {
            return (bool) $motion;
        }
        
        // Si no hay campo motion, inferir del speed
        return (float) $speed > 1; // Considerado en movimiento si speed > 1 km/h
    }

    /**
     * Formatear validez de ubicación GPS
     *
     * @param mixed $valid
     * @return bool
     */
    private function formatValid($valid): bool
    {
        if (is_bool($valid)) {
            return $valid;
        }
        
        if (is_numeric($valid)) {
            return (int) $valid === 1;
        }
        
        if (is_string($valid)) {
            return strtolower($valid) === 'true' || $valid === '1';
        }
        
        return true; // Por defecto asumir válido
    }

    /**
     * Extraer número de placa del objeto GPS
     *
     * @param array $gpsObject
     * @return string
     */
    private function extractPlateNumber(array $gpsObject): string
    {
        // Buscar en varios campos posibles
        $plateFields = ['plate_number', 'placa', 'name'];
        
        foreach ($plateFields as $field) {
            if (isset($gpsObject[$field]) && !empty($gpsObject[$field])) {
                $plate = trim((string) $gpsObject[$field]);
                
                // Si el campo 'name' contiene algo como "C-14, EGN-802", extraer la placa
                if ($field === 'name' && strpos($plate, ',') !== false) {
                    $parts = explode(',', $plate);
                    if (count($parts) >= 2) {
                        $plate = trim($parts[1]);
                    }
                }
                
                // Limpiar caracteres especiales pero mantener letras y números
                $plate = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($plate));
                
                if (strlen($plate) >= 3) {
                    return $plate;
                }
            }
        }
        
        // Si no se encuentra placa, usar parte del IMEI como fallback
        $imei = $gpsObject['imei'] ?? '';
        return 'IMEI-' . substr($imei, -6);
    }

    /**
     * Extraer código UBIGEO del objeto GPS
     *
     * @param array $gpsObject
     * @return string
     */
    private function extractUbigeo(array $gpsObject): string
    {
        // Buscar en custom_fields
        if (isset($gpsObject['custom_fields']) && is_array($gpsObject['custom_fields'])) {
            foreach ($gpsObject['custom_fields'] as $field) {
                if (isset($field['name']) && $field['name'] === 'ubigeo' && isset($field['value'])) {
                    $ubigeo = trim((string) $field['value']);
                    if (strlen($ubigeo) === 6 && is_numeric($ubigeo)) {
                        return $ubigeo;
                    }
                }
            }
        }
        
        // Buscar directamente en el objeto
        if (isset($gpsObject['ubigeo'])) {
            $ubigeo = trim((string) $gpsObject['ubigeo']);
            if (strlen($ubigeo) === 6 && is_numeric($ubigeo)) {
                return $ubigeo;
            }
        }
        
        // Fallback: usar ubigeo de Tacna (ejemplo por defecto)
        return '230101';
    }

    /**
     * Generar ID de transmisión único
     *
     * @return string UUID v4
     */
    private function generateTransmissionId(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }

    /**
     * Validar coordenadas GPS
     *
     * @param float $lat Latitud
     * @param float $lng Longitud
     * @return bool
     */
    public function areValidCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    /**
     * Obtener resumen de transformación
     *
     * @param array $originalData Datos originales
     * @param array $transformedData Datos transformados
     * @param string $type Tipo de transformación (SERENAZGO/POLICIAL)
     * @return array
     */
    public function getTransformationSummary(array $originalData, array $transformedData, string $type): array
    {
        return [
            'type' => $type,
            'original_count' => count($originalData),
            'transformed_count' => count($transformedData),
            'success_rate' => count($originalData) > 0 ? 
                round((count($transformedData) / count($originalData)) * 100, 2) : 0,
            'timestamp' => Carbon::now()->toISOString()
        ];
    }
} 