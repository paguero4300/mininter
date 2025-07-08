<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para enviar datos GPS a endpoints MININTER
 * 
 * Maneja el envío de datos GPS transformados a los endpoints
 * SERENAZGO y POLICIAL del MININTER con TLS 1.2+
 */
class MininterClient
{
    private string $serenazgoEndpoint;
    private string $policialEndpoint;
    private int $timeout;
    private int $connectTimeout;
    private bool $verifySSL;
    private string $sslVersion;

    public function __construct()
    {
        $this->serenazgoEndpoint = config('services.mininter.serenazgo_endpoint', env('MININTER_SERENAZGO_ENDPOINT'));
        $this->policialEndpoint = config('services.mininter.policial_endpoint', env('MININTER_POLICIAL_ENDPOINT'));
        $this->timeout = config('services.mininter.timeout', 30);
        $this->connectTimeout = config('services.mininter.connect_timeout', 10);
        $this->verifySSL = config('services.mininter.verify_ssl', true);
        $this->sslVersion = config('services.mininter.ssl_version', 'TLSv1.2');
    }

    /**
     * Enviar datos GPS a endpoint SERENAZGO
     *
     * @param array $payload Datos GPS transformados
     * @param string $municipalityId ID de la municipalidad
     * @return array Response con status y detalles
     */
    public function sendSerenazgoData(array $payload, string $municipalityId): array
    {
        Log::info('MininterClient: Enviando datos SERENAZGO', [
            'municipality_id' => $municipalityId,
            'payload_count' => count($payload)
        ]);

        return $this->sendMultipleData($this->serenazgoEndpoint, $payload, 'SERENAZGO', $municipalityId);
    }

    /**
     * Enviar datos GPS a endpoint POLICIAL
     *
     * @param array $payload Datos GPS transformados
     * @param string $municipalityId ID de la municipalidad
     * @return array Response con status y detalles
     */
    public function sendPolicialData(array $payload, string $municipalityId): array
    {
        Log::info('MininterClient: Enviando datos POLICIAL', [
            'municipality_id' => $municipalityId,
            'payload_count' => count($payload)
        ]);

        return $this->sendMultipleData($this->policialEndpoint, $payload, 'POLICIAL', $municipalityId);
    }

    /**
     * Enviar múltiples objetos GPS individualmente
     *
     * @param string $endpoint URL del endpoint
     * @param array $payload Array de objetos GPS
     * @param string $type Tipo de endpoint (SERENAZGO/POLICIAL)
     * @param string $municipalityId ID de la municipalidad
     * @return array Resumen de envíos
     */
    private function sendMultipleData(string $endpoint, array $payload, string $type, string $municipalityId): array
    {
        $results = [
            'success' => true,
            'total_objects' => count($payload),
            'successful_sends' => 0,
            'failed_sends' => 0,
            'responses' => [],
            'first_error' => null
        ];

        foreach ($payload as $index => $gpsObject) {
            $response = $this->sendSingleData($endpoint, $gpsObject, $type, $municipalityId, $index);
            
            $results['responses'][] = $response;
            
            if ($response['success']) {
                $results['successful_sends']++;
            } else {
                $results['failed_sends']++;
                $results['success'] = false;
                
                // Guardar el primer error
                if ($results['first_error'] === null) {
                    $results['first_error'] = $response['message'] ?? 'Error desconocido';
                }
            }
        }

        Log::info('MininterClient: Envío múltiple completado', [
            'type' => $type,
            'municipality_id' => $municipalityId,
            'total' => $results['total_objects'],
            'successful' => $results['successful_sends'],
            'failed' => $results['failed_sends']
        ]);

        return $results;
    }

    /**
     * Enviar un objeto GPS individual a endpoint específico
     *
     * @param string $endpoint URL del endpoint
     * @param array $gpsObject Objeto GPS individual a enviar
     * @param string $type Tipo de endpoint (SERENAZGO/POLICIAL)
     * @param string $municipalityId ID de la municipalidad
     * @param int $index Índice del objeto en el array original
     * @return array
     */
    private function sendSingleData(string $endpoint, array $gpsObject, string $type, string $municipalityId, int $index): array
    {
        try {
            // Configurar opciones SSL según configuración
            $curlOptions = [];
            if ($this->verifySSL) {
                $curlOptions[CURLOPT_SSLVERSION] = $this->sslVersion === 'TLSv1.2' ? CURL_SSLVERSION_TLSv1_2 : CURL_SSLVERSION_TLSv1_2;
                $curlOptions[CURLOPT_SSL_CIPHER_LIST] = $this->sslVersion;
            } else {
                // En development: ignorar verificación SSL
                $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
                $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
            }

            $response = Http::withOptions([
                'timeout' => $this->timeout,
                'connect_timeout' => $this->connectTimeout,
                'verify' => $this->verifySSL,
                'curl' => $curlOptions
            ])
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'MininterGPSProxy/1.0'
            ])
            ->retry(3, 1000, function (\Exception $exception) {
                // Solo reintentar en errores de conexión o 5xx
                if ($exception instanceof ConnectionException) {
                    return true;
                }
                
                if ($exception instanceof RequestException) {
                    return $exception->response->status() >= 500;
                }
                
                return false;
            })
            ->post($endpoint, $gpsObject);

            return $this->handleResponse($response, $type, $municipalityId);

        } catch (ConnectionException $e) {
            Log::error('MininterClient: Error de conexión', [
                'type' => $type,
                'municipality_id' => $municipalityId,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'error' => 'CONNECTION_ERROR',
                'message' => 'Error de conexión con MININTER'
            ];

        } catch (RequestException $e) {
            Log::error('MininterClient: Error HTTP', [
                'type' => $type,
                'municipality_id' => $municipalityId,
                'endpoint' => $endpoint,
                'status' => $e->response->status(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status_code' => $e->response->status(),
                'error' => 'HTTP_ERROR',
                'message' => $e->getMessage(),
                'response_body' => $e->response->body()
            ];

        } catch (\Exception $e) {
            Log::error('MininterClient: Error inesperado', [
                'type' => $type,
                'municipality_id' => $municipalityId,
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'error' => 'UNEXPECTED_ERROR',
                'message' => 'Error inesperado al enviar datos'
            ];
        }
    }

    /**
     * Procesar respuesta del endpoint MININTER
     *
     * @param Response $response
     * @param string $type
     * @param string $municipalityId
     * @return array
     */
    private function handleResponse(Response $response, string $type, string $municipalityId): array
    {
        $statusCode = $response->status();
        $responseBody = $response->body();
        
        Log::info('MininterClient: Respuesta recibida', [
            'type' => $type,
            'municipality_id' => $municipalityId,
            'status_code' => $statusCode,
            'response_length' => strlen($responseBody)
        ]);

        // Determinar si fue exitoso
        $isSuccess = $response->successful();

        $result = [
            'success' => $isSuccess,
            'status_code' => $statusCode,
            'response_body' => $responseBody,
            'response_headers' => $response->headers()
        ];

        if ($isSuccess) {
            Log::info('MininterClient: Envío exitoso', [
                'type' => $type,
                'municipality_id' => $municipalityId,
                'status_code' => $statusCode
            ]);
        } else {
            Log::warning('MininterClient: Envío fallido', [
                'type' => $type,
                'municipality_id' => $municipalityId,
                'status_code' => $statusCode,
                'response_body' => $responseBody
            ]);

            $result['error'] = $this->getErrorType($statusCode);
            $result['message'] = $this->getErrorMessage($statusCode);
        }

        return $result;
    }

    /**
     * Obtener tipo de error según código de estado
     *
     * @param int $statusCode
     * @return string
     */
    private function getErrorType(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 400 && $statusCode < 500 => 'CLIENT_ERROR',
            $statusCode >= 500 => 'SERVER_ERROR',
            default => 'UNKNOWN_ERROR'
        };
    }

    /**
     * Obtener mensaje de error según código de estado
     *
     * @param int $statusCode
     * @return string
     */
    private function getErrorMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Datos inválidos enviados',
            401 => 'No autorizado',
            403 => 'Acceso prohibido',
            404 => 'Endpoint no encontrado',
            408 => 'Timeout de solicitud',
            429 => 'Demasiadas solicitudes',
            500 => 'Error interno del servidor MININTER',
            502 => 'Bad Gateway',
            503 => 'Servicio no disponible',
            504 => 'Timeout del gateway',
            default => "Error HTTP: $statusCode"
        };
    }

    /**
     * Verificar conectividad con endpoints MININTER
     *
     * @return array Estado de conectividad
     */
    public function healthCheck(): array
    {
        $results = [];

        // Verificar endpoint SERENAZGO
        $results['serenazgo'] = $this->checkEndpoint($this->serenazgoEndpoint, 'SERENAZGO');
        
        // Verificar endpoint POLICIAL
        $results['policial'] = $this->checkEndpoint($this->policialEndpoint, 'POLICIAL');

        return $results;
    }

    /**
     * Verificar un endpoint específico
     *
     * @param string $endpoint
     * @param string $type
     * @return array
     */
    private function checkEndpoint(string $endpoint, string $type): array
    {
        try {
            // Usar la misma configuración SSL que en sendData
            $curlOptions = [];
            if ($this->verifySSL) {
                $curlOptions[CURLOPT_SSLVERSION] = $this->sslVersion === 'TLSv1.2' ? CURL_SSLVERSION_TLSv1_2 : CURL_SSLVERSION_TLSv1_2;
            } else {
                $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
                $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
            }

            $response = Http::withOptions([
                'timeout' => 5,
                'connect_timeout' => 3,
                'verify' => $this->verifySSL,
                'curl' => $curlOptions
            ])
            ->withHeaders([
                'User-Agent' => 'MininterGPSProxy/1.0'
            ])
            ->head($endpoint);

            return [
                'accessible' => true,
                'status_code' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("MininterClient: Health check failed for $type", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);

            return [
                'accessible' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Verificar endpoint SERENAZGO específicamente
     *
     * @return bool
     */
    public function healthCheckSerenazgo(): bool
    {
        $result = $this->checkEndpoint($this->serenazgoEndpoint, 'SERENAZGO');
        return $result['accessible'] && ($result['status_code'] ?? 0) < 500;
    }

    /**
     * Verificar endpoint POLICIAL específicamente
     *
     * @return bool
     */
    public function healthCheckPolicial(): bool
    {
        $result = $this->checkEndpoint($this->policialEndpoint, 'POLICIAL');
        return $result['accessible'] && ($result['status_code'] ?? 0) < 500;
    }
} 