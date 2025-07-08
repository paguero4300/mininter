<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory para Municipality
 */
class MunicipalityFactory extends Factory
{
    protected $model = Municipality::class;

    /**
     * Define el estado por defecto del modelo
     */
    public function definition(): array
    {
        // Municipalidades reales del Perú con sus ubigeos
        $municipalities = [
            ['name' => 'Municipalidad Provincial de Lima', 'ubigeo' => '150101'],
            ['name' => 'Municipalidad de Miraflores', 'ubigeo' => '150122'],
            ['name' => 'Municipalidad de San Isidro', 'ubigeo' => '150130'],
            ['name' => 'Municipalidad de Callao', 'ubigeo' => '070101'],
            ['name' => 'Municipalidad de Arequipa', 'ubigeo' => '040101'],
            ['name' => 'Municipalidad de Trujillo', 'ubigeo' => '130101'],
            ['name' => 'Municipalidad de Chiclayo', 'ubigeo' => '140101'],
            ['name' => 'Municipalidad de Huancayo', 'ubigeo' => '120101'],
            ['name' => 'Municipalidad de Iquitos', 'ubigeo' => '160101'],
            ['name' => 'Municipalidad de Cusco', 'ubigeo' => '080101'],
        ];

        $selected = $this->faker->randomElement($municipalities);
        $tipo = $this->faker->randomElement(['SERENAZGO', 'POLICIAL']);

        return [
            'name' => $selected['name'],
            'token_gps' => $this->faker->regexify('[A-Z0-9]{32}'),
            'ubigeo' => $selected['ubigeo'],
            'tipo' => $tipo,
            'codigo_comisaria' => $tipo === 'POLICIAL' ? $this->faker->regexify('[0-9]{6}') : null,
            'active' => $this->faker->boolean(85), // 85% probabilidad de estar activo
        ];
    }

    /**
     * State para municipalidades activas
     */
    public function active(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'active' => true,
            ];
        });
    }

    /**
     * State para municipalidades inactivas
     */
    public function inactive(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'active' => false,
            ];
        });
    }

    /**
     * State para tipo SERENAZGO
     */
    public function serenazgo(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
            ];
        });
    }

    /**
     * State para tipo POLICIAL
     */
    public function policial(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'tipo' => 'POLICIAL',
                'codigo_comisaria' => $this->faker->regexify('[0-9]{6}'),
            ];
        });
    }

    /**
     * State para municipalidades específicas para testing
     */
    public function lima(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'Municipalidad Provincial de Lima',
                'ubigeo' => '150101',
                'tipo' => 'SERENAZGO',
                'codigo_comisaria' => null,
                'active' => true,
            ];
        });
    }

    /**
     * State para municipalidades con token específico
     */
    public function withToken(string $token): self
    {
        return $this->state(function (array $attributes) use ($token) {
            return [
                'token_gps' => $token,
            ];
        });
    }
} 