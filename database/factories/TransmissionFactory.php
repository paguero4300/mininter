<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\Transmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para Transmission
 */
class TransmissionFactory extends Factory
{
    protected $model = Transmission::class;

    /**
     * Define el estado por defecto del modelo
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['SENT', 'FAILED']);
        
        // Generar payload GPS realista
        $gpsObjects = [];
        $numObjects = $this->faker->numberBetween(1, 10);
        
        for ($i = 0; $i < $numObjects; $i++) {
            $gpsObjects[] = [
                'imei' => $this->faker->regexify('[0-9]{15}'),
                'lat' => $this->faker->latitude(-18.5, -0.5), // Coordenadas de Perú
                'lng' => $this->faker->longitude(-81.5, -68.5), // Coordenadas de Perú
                'fechaHora' => $this->faker->dateTimeBetween('-1 hour', 'now')->format('d/m/Y H:i:s'),
                'velocidad' => $this->faker->numberBetween(0, 120),
                'rumbo' => $this->faker->numberBetween(0, 359),
                'bateria' => $this->faker->numberBetween(10, 100),
                'estatus' => $this->faker->randomElement(['1', '0']),
                'ignicion' => $this->faker->randomElement(['1', '0']),
            ];
        }

        return [
            'municipality_id' => Municipality::factory(),
            'payload' => $gpsObjects,
            'response_code' => $status === 'SENT' ? $this->faker->randomElement([200, 201, 202]) : $this->faker->randomElement([400, 401, 403, 404, 500, 502, 503]),
            'status' => $status,
            'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'retry_count' => $status === 'FAILED' ? $this->faker->numberBetween(0, 5) : 0,
        ];
    }

    /**
     * State para transmisiones enviadas exitosamente
     */
    public function sent(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'SENT',
                'response_code' => $this->faker->randomElement([200, 201, 202]),
                'retry_count' => 0,
                'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            ];
        });
    }

    /**
     * State para transmisiones fallidas
     */
    public function failed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'FAILED',
                'response_code' => $this->faker->randomElement([400, 401, 403, 404, 500, 502, 503]),
                'retry_count' => $this->faker->numberBetween(1, 5),
                'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            ];
        });
    }

    /**
     * State para transmisiones con reintentos
     */
    public function withRetries(int $retries = null): self
    {
        $retryCount = $retries ?? $this->faker->numberBetween(1, 5);
        
        return $this->state(function (array $attributes) use ($retryCount) {
            return [
                'status' => 'FAILED',
                'response_code' => $this->faker->randomElement([500, 502, 503, 504]),
                'retry_count' => $retryCount,
                'sent_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            ];
        });
    }

    /**
     * State para municipalidad específica
     */
    public function forMunicipality(Municipality $municipality): self
    {
        return $this->state(function (array $attributes) use ($municipality) {
            return [
                'municipality_id' => $municipality->id,
            ];
        });
    }

    /**
     * State para payload SERENAZGO específico
     */
    public function serenazgoPayload(): self
    {
        return $this->state(function (array $attributes) {
            $gpsObjects = [
                [
                    'imei' => $this->faker->regexify('[0-9]{15}'),
                    'lat' => $this->faker->latitude(-12.2, -11.8), // Lima
                    'lng' => $this->faker->longitude(-77.2, -76.8), // Lima
                    'fechaHora' => now()->format('d/m/Y H:i:s'),
                    'velocidad' => $this->faker->numberBetween(0, 60),
                    'rumbo' => $this->faker->numberBetween(0, 359),
                    'bateria' => $this->faker->numberBetween(50, 100),
                    'estatus' => '1',
                    'ignicion' => '1',
                    'idMunicipalidad' => $this->faker->uuid(),
                ]
            ];

            return [
                'payload' => $gpsObjects,
            ];
        });
    }

    /**
     * State para payload POLICIAL específico
     */
    public function policialPayload(): self
    {
        return $this->state(function (array $attributes) {
            $gpsObjects = [
                [
                    'imei' => $this->faker->regexify('[0-9]{15}'),
                    'lat' => $this->faker->latitude(-12.2, -11.8), // Lima
                    'lng' => $this->faker->longitude(-77.2, -76.8), // Lima
                    'fechaHora' => now()->format('d/m/Y H:i:s'),
                    'velocidad' => $this->faker->numberBetween(0, 80),
                    'rumbo' => $this->faker->numberBetween(0, 359),
                    'bateria' => $this->faker->numberBetween(30, 100),
                    'estatus' => '1',
                    'ignicion' => '1',
                    'idTransmision' => $this->faker->uuid(),
                ]
            ];

            return [
                'payload' => $gpsObjects,
            ];
        });
    }

    /**
     * State para payload vacío
     */
    public function emptyPayload(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'payload' => [],
                'status' => 'FAILED',
                'response_code' => 400,
            ];
        });
    }

    /**
     * State para transmisión reciente
     */
    public function recent(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'sent_at' => now()->subMinutes($this->faker->numberBetween(1, 30)),
            ];
        });
    }

    /**
     * State para transmisión antigua
     */
    public function old(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'sent_at' => now()->subDays($this->faker->numberBetween(1, 30)),
            ];
        });
    }
} 