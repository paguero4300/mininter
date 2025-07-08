<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de validación para datos GPS
 * 
 * Valida datos GPS según reglas de negocio específicas
 * para el sistema MININTER GPS Proxy
 */
class ValidationService
{
    /**
     * Validar datos GPS desde GPServer
     *
     * @param array $gpsObjects Array de objetos GPS
     * @return array Resultado de validación
     */
    public function validateGpsData(array $gpsObjects): array
    {
        $validObjects = [];
        $invalidObjects = [];
        $errors = [];

        foreach ($gpsObjects as $index => $gpsObject) {
            $validation = $this->validateSingleGpsObject($gpsObject, $index);
            
            if ($validation['valid']) {
                $validObjects[] = $gpsObject;
            } else {
                $invalidObjects[] = [
                    'index' => $index,
                    'object' => $gpsObject,
                    'errors' => $validation['errors']
                ];
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        $result = [
            'total_objects' => count($gpsObjects),
            'valid_objects' => count($validObjects),
            'invalid_objects' => count($invalidObjects),
            'success_rate' => count($gpsObjects) > 0 ? 
                round((count($validObjects) / count($gpsObjects)) * 100, 2) : 0,
            'valid_data' => $validObjects,
            'invalid_data' => $invalidObjects,
            'errors' => $errors
        ];

        Log::info('ValidationService: Validación GPS completada', [
            'total' => $result['total_objects'],
            'valid' => $result['valid_objects'],
            'invalid' => $result['invalid_objects'],
            'success_rate' => $result['success_rate']
        ]);

        return $result;
    }

    /**
     * Validar un objeto GPS individual
     *
     * @param mixed $gpsObject Objeto GPS
     * @param int $index Índice del objeto
     * @return array Resultado de validación
     */
    private function validateSingleGpsObject($gpsObject, int $index): array
    {
        $errors = [];

        // Verificar que es un array
        if (!is_array($gpsObject)) {
            return [
                'valid' => false,
                'errors' => [
                    'type' => 'INVALID_TYPE',
                    'message' => "Objeto GPS en índice $index no es un array",
                    'index' => $index
                ]
            ];
        }

        // Validar estructura básica
        $structureValidation = $this->validateGpsStructure($gpsObject);
        if (!$structureValidation['valid']) {
            $errors = array_merge($errors, $structureValidation['errors']);
        }

        // Validar coordenadas
        $coordinatesValidation = $this->validateCoordinates($gpsObject);
        if (!$coordinatesValidation['valid']) {
            $errors = array_merge($errors, $coordinatesValidation['errors']);
        }

        // Validar fecha/hora
        $dateTimeValidation = $this->validateDateTime($gpsObject);
        if (!$dateTimeValidation['valid']) {
            $errors = array_merge($errors, $dateTimeValidation['errors']);
        }

        // Validar IMEI
        $imeiValidation = $this->validateImei($gpsObject);
        if (!$imeiValidation['valid']) {
            $errors = array_merge($errors, $imeiValidation['errors']);
        }

        // Validar campos opcionales
        $optionalValidation = $this->validateOptionalFields($gpsObject);
        if (!$optionalValidation['valid']) {
            $errors = array_merge($errors, $optionalValidation['errors']);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validar estructura básica del objeto GPS
     *
     * @param array $gpsObject
     * @return array
     */
    private function validateGpsStructure(array $gpsObject): array
    {
        $requiredFields = ['imei', 'lat', 'lng', 'dt_server'];
        $errors = [];

        foreach ($requiredFields as $field) {
            if (!isset($gpsObject[$field])) {
                $errors[] = [
                    'type' => 'MISSING_FIELD',
                    'field' => $field,
                    'message' => "Campo requerido '$field' no está presente"
                ];
            } elseif ($gpsObject[$field] === null || $gpsObject[$field] === '') {
                $errors[] = [
                    'type' => 'EMPTY_FIELD',
                    'field' => $field,
                    'message' => "Campo requerido '$field' está vacío"
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validar coordenadas GPS
     *
     * @param array $gpsObject
     * @return array
     */
    private function validateCoordinates(array $gpsObject): array
    {
        $errors = [];

        if (!isset($gpsObject['lat']) || !isset($gpsObject['lng'])) {
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        $lat = (float) $gpsObject['lat'];
        $lng = (float) $gpsObject['lng'];

        // Validar rango de latitud
        if ($lat < -90 || $lat > 90) {
            $errors[] = [
                'type' => 'INVALID_LATITUDE',
                'field' => 'lat',
                'value' => $lat,
                'message' => "Latitud inválida: $lat (debe estar entre -90 y 90)"
            ];
        }

        // Validar rango de longitud
        if ($lng < -180 || $lng > 180) {
            $errors[] = [
                'type' => 'INVALID_LONGITUDE',
                'field' => 'lng',
                'value' => $lng,
                'message' => "Longitud inválida: $lng (debe estar entre -180 y 180)"
            ];
        }

        // Validar coordenadas no sean (0,0) - posiblemente inválidas
        if (abs($lat) < 0.001 && abs($lng) < 0.001) {
            $errors[] = [
                'type' => 'SUSPICIOUS_COORDINATES',
                'field' => 'lat,lng',
                'value' => "($lat, $lng)",
                'message' => "Coordenadas sospechosas: probablemente inválidas"
            ];
        }

        // Validar que las coordenadas sean para territorio peruano (aproximado)
        if (!$this->isInPeruBounds($lat, $lng)) {
            $errors[] = [
                'type' => 'COORDINATES_OUT_OF_PERU',
                'field' => 'lat,lng',
                'value' => "($lat, $lng)",
                'message' => "Coordenadas fuera del territorio peruano"
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validar fecha y hora
     *
     * @param array $gpsObject
     * @return array
     */
    private function validateDateTime(array $gpsObject): array
    {
        $errors = [];

        if (!isset($gpsObject['dt_server'])) {
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        $dateTime = $gpsObject['dt_server'];

        try {
            if (is_numeric($dateTime)) {
                $timestamp = (int) $dateTime;
                
                // Validar que no sea timestamp muy antiguo (antes del 2000)
                if ($timestamp < 946684800) {
                    $errors[] = [
                        'type' => 'TIMESTAMP_TOO_OLD',
                        'field' => 'dt_server',
                        'value' => $timestamp,
                        'message' => "Timestamp muy antiguo: $timestamp"
                    ];
                }
                
                // Validar que no sea timestamp en el futuro (más de 1 hora)
                if ($timestamp > time() + 3600) {
                    $errors[] = [
                        'type' => 'TIMESTAMP_IN_FUTURE',
                        'field' => 'dt_server',
                        'value' => $timestamp,
                        'message' => "Timestamp en el futuro: $timestamp"
                    ];
                }
            } else {
                // Intentar parsear como fecha
                $parsedDate = Carbon::parse($dateTime);
                
                // Validar que no sea muy antigua
                if ($parsedDate->isBefore(Carbon::parse('2000-01-01'))) {
                    $errors[] = [
                        'type' => 'DATE_TOO_OLD',
                        'field' => 'dt_server',
                        'value' => $dateTime,
                        'message' => "Fecha muy antigua: $dateTime"
                    ];
                }
                
                // Validar que no sea en el futuro
                if ($parsedDate->isAfter(Carbon::now()->addHour())) {
                    $errors[] = [
                        'type' => 'DATE_IN_FUTURE',
                        'field' => 'dt_server',
                        'value' => $dateTime,
                        'message' => "Fecha en el futuro: $dateTime"
                    ];
                }
            }
        } catch (\Exception $e) {
            $errors[] = [
                'type' => 'INVALID_DATETIME_FORMAT',
                'field' => 'dt_server',
                'value' => $dateTime,
                'message' => "Formato de fecha inválido: $dateTime"
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validar IMEI
     *
     * @param array $gpsObject
     * @return array
     */
    private function validateImei(array $gpsObject): array
    {
        $errors = [];

        if (!isset($gpsObject['imei'])) {
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        $imei = (string) $gpsObject['imei'];

        // Remover caracteres no numéricos
        $numericImei = preg_replace('/[^0-9]/', '', $imei);

        // Validar longitud
        if (strlen($numericImei) < 14 || strlen($numericImei) > 17) {
            $errors[] = [
                'type' => 'INVALID_IMEI_LENGTH',
                'field' => 'imei',
                'value' => $imei,
                'message' => "IMEI con longitud inválida: $imei (debe tener 14-17 dígitos)"
            ];
        }

        // Validar que no sea solo ceros
        if (strlen($numericImei) > 0 && $numericImei === str_repeat('0', strlen($numericImei))) {
            $errors[] = [
                'type' => 'IMEI_ALL_ZEROS',
                'field' => 'imei',
                'value' => $imei,
                'message' => "IMEI inválido: todos los dígitos son cero"
            ];
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validar campos opcionales
     *
     * @param array $gpsObject
     * @return array
     */
    private function validateOptionalFields(array $gpsObject): array
    {
        $errors = [];

        // Validar velocidad si existe
        if (isset($gpsObject['speed'])) {
            $speed = (float) $gpsObject['speed'];
            if ($speed < 0 || $speed > 500) { // 500 km/h como máximo razonable
                $errors[] = [
                    'type' => 'INVALID_SPEED',
                    'field' => 'speed',
                    'value' => $speed,
                    'message' => "Velocidad inválida: $speed (debe estar entre 0 y 500 km/h)"
                ];
            }
        }

        // Validar rumbo si existe
        if (isset($gpsObject['course'])) {
            $course = (float) $gpsObject['course'];
            if ($course < 0 || $course >= 360) {
                $errors[] = [
                    'type' => 'INVALID_COURSE',
                    'field' => 'course',
                    'value' => $course,
                    'message' => "Rumbo inválido: $course (debe estar entre 0 y 359 grados)"
                ];
            }
        }

        // Validar batería si existe
        if (isset($gpsObject['battery'])) {
            $battery = (int) $gpsObject['battery'];
            if ($battery < 0 || $battery > 100) {
                $errors[] = [
                    'type' => 'INVALID_BATTERY',
                    'field' => 'battery',
                    'value' => $battery,
                    'message' => "Nivel de batería inválido: $battery (debe estar entre 0 y 100)"
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Verificar si las coordenadas están dentro de los límites de Perú
     *
     * @param float $lat
     * @param float $lng
     * @return bool
     */
    private function isInPeruBounds(float $lat, float $lng): bool
    {
        // Límites aproximados de Perú
        $peruBounds = [
            'lat_min' => -18.4,
            'lat_max' => -0.0,
            'lng_min' => -81.4,
            'lng_max' => -68.7
        ];

        return $lat >= $peruBounds['lat_min'] && $lat <= $peruBounds['lat_max'] &&
               $lng >= $peruBounds['lng_min'] && $lng <= $peruBounds['lng_max'];
    }

    /**
     * Validar datos transformados antes del envío
     *
     * @param array $transformedData
     * @param string $type Tipo de datos (SERENAZGO/POLICIAL)
     * @return array
     */
    public function validateTransformedData(array $transformedData, string $type): array
    {
        $errors = [];

        foreach ($transformedData as $index => $data) {
            if (!is_array($data)) {
                $errors[] = [
                    'type' => 'INVALID_TRANSFORMED_TYPE',
                    'index' => $index,
                    'message' => "Dato transformado en índice $index no es un array"
                ];
                continue;
            }

            // Validar campos base requeridos
            $requiredFields = ['imei', 'lat', 'lng', 'fechaHora'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    $errors[] = [
                        'type' => 'MISSING_TRANSFORMED_FIELD',
                        'index' => $index,
                        'field' => $field,
                        'message' => "Campo requerido '$field' falta en dato transformado"
                    ];
                }
            }

            // Validar campos específicos por tipo
            if ($type === 'SERENAZGO' && !isset($data['idMunicipalidad'])) {
                $errors[] = [
                    'type' => 'MISSING_MUNICIPALITY_ID',
                    'index' => $index,
                    'message' => "Campo 'idMunicipalidad' requerido para SERENAZGO"
                ];
            }

            if ($type === 'POLICIAL') {
                if (!isset($data['idTransmision'])) {
                    $errors[] = [
                        'type' => 'MISSING_TRANSMISSION_ID',
                        'index' => $index,
                        'message' => "Campo 'idTransmision' requerido para POLICIAL"
                    ];
                }
                if (!isset($data['codigoComisaria'])) {
                    $errors[] = [
                        'type' => 'MISSING_COMISARIA_CODE',
                        'index' => $index,
                        'message' => "Campo 'codigoComisaria' requerido para POLICIAL"
                    ];
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_objects' => count($transformedData),
            'error_count' => count($errors)
        ];
    }
} 