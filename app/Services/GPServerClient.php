<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para consumir datos GPS desde GPServer (GIPIES)
 * 
 * Consume la API de GIPIES para obtener datos de ubicación GPS
 * de los vehículos de las municipalidades.
 */
class GPServerClient
{
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;

    public function __construct()
    {
        $this->baseUrl = config('services.gpserver.base_url', env('GPSERVER_BASE_URL'));
        $this->timeout = config('services.gpserver.timeout', 30);
        $this->connectTimeout = config('services.gpserver.connect_timeout', 10);
    }

    /**
     * Obtener objetos GPS desde GPServer
     *
     * @param string $token Token GPS de la municipalidad
     * @return array Array de objetos GPS o array vacío en caso de error
     */
    public function fetchGpsObjects(string $token): array
    {
        try {
            Log::info('GPServer: Iniciando consulta GPS', [
                'token' => substr($token, 0, 8) . '...',
                'url' => $this->baseUrl
            ]);

            $response = Http::withOptions([
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'verify' => true, // Verificar certificados SSL
            ])
            ->retry(3, 100, function (\Exception $exception, $request) {
                // Reintentar en errores de conexión
                if ($exception instanceof ConnectionException) {
                    return true;
                }
                
                // Reintentar en errores HTTP 5xx (servidor)
                if ($exception instanceof RequestException && $exception->response) {
                    return $exception->response->status() >= 500;
                }
                
                return false;
            })
            ->get($this->baseUrl, [
                'api' => 'user',
                'key' => $token,
                'cmd' => 'USER_GET_OBJECTS'
            ]);

            return $this->handleResponse($response, $token);

        } catch (ConnectionException $e) {
            Log::error('GPServer: Error de conexión', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);
            return [];

        } catch (RequestException $e) {
            Log::error('GPServer: Error HTTP', [
                'token' => substr($token, 0, 8) . '...',
                'status' => $e->response->status(),
                'error' => $e->getMessage()
            ]);
            return [];

        } catch (\Exception $e) {
            Log::error('GPServer: Error inesperado', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Procesar la respuesta de GPServer
     *
     * @param Response $response
     * @param string $token
     * @return array
     */
    private function handleResponse(Response $response, string $token): array
    {
        if (!$response->successful()) {
            Log::warning('GPServer: Respuesta no exitosa', [
                'token' => substr($token, 0, 8) . '...',
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return [];
        }

        $data = $response->json();

        if (!is_array($data)) {
            Log::warning('GPServer: Respuesta no es un array', [
                'token' => substr($token, 0, 8) . '...',
                'type' => gettype($data)
            ]);
            return [];
        }

        $validObjects = $this->validateGpsObjects($data);

        Log::info('GPServer: Consulta exitosa', [
            'token' => substr($token, 0, 8) . '...',
            'total_objects' => count($data),
            'valid_objects' => count($validObjects)
        ]);

        return $validObjects;
    }

    /**
     * Validar y filtrar objetos GPS
     *
     * @param array $objects
     * @return array
     */
    private function validateGpsObjects(array $objects): array
    {
        $validObjects = [];

        foreach ($objects as $object) {
            if (!is_array($object)) {
                continue;
            }

            // Verificar campos obligatorios
            $requiredFields = ['imei', 'lat', 'lng', 'dt_server'];
            $hasAllFields = true;

            foreach ($requiredFields as $field) {
                if (!isset($object[$field]) || empty(trim((string)$object[$field]))) {
                    $hasAllFields = false;
                    break;
                }
            }

            if (!$hasAllFields) {
                continue;
            }

            // Validar IMEI (debe tener al menos 10 dígitos numéricos)
            $imei = trim((string) $object['imei']);
            $numericImei = preg_replace('/[^0-9]/', '', $imei);
            if (strlen($numericImei) < 10) {
                Log::debug('GPServer: IMEI inválido', [
                    'imei' => $imei,
                    'numeric_length' => strlen($numericImei)
                ]);
                continue;
            }

            // Validar que las coordenadas sean numéricas antes del cast
            if (!is_numeric($object['lat']) || !is_numeric($object['lng'])) {
                Log::debug('GPServer: Coordenadas no numéricas', [
                    'imei' => $object['imei'] ?? 'unknown',
                    'lat' => $object['lat'],
                    'lng' => $object['lng']
                ]);
                continue;
            }

            // Validar coordenadas
            $lat = (float) $object['lat'];
            $lng = (float) $object['lng'];

            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                Log::debug('GPServer: Coordenadas inválidas', [
                    'imei' => $object['imei'] ?? 'unknown',
                    'lat' => $lat,
                    'lng' => $lng
                ]);
                continue;
            }

            // Validar fecha
            if (!$this->isValidDateTime($object['dt_server'])) {
                Log::debug('GPServer: Fecha inválida', [
                    'imei' => $object['imei'] ?? 'unknown',
                    'dt_server' => $object['dt_server']
                ]);
                continue;
            }

            $validObjects[] = $object;
        }

        return $validObjects;
    }

    /**
     * Validar formato de fecha/hora
     *
     * @param mixed $dateTime
     * @return bool
     */
    private function isValidDateTime($dateTime): bool
    {
        if (!is_string($dateTime) && !is_numeric($dateTime)) {
            return false;
        }

        // Intentar parsear como timestamp o fecha ISO
        try {
            if (is_numeric($dateTime)) {
                return $dateTime > 0 && $dateTime <= time() + 3600; // No más de 1 hora en el futuro
            }

            $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTime);
            return $parsed !== false;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verificar conectividad con GPServer
     *
     * @return bool
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::withOptions([
                'timeout' => 5,
                'connect_timeout' => 3,
            ])->get($this->baseUrl);

            return $response->status() < 500;

        } catch (\Exception $e) {
            Log::error('GPServer: Health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
} 