<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo Municipality
 * 
 * Representa una municipalidad que transmite datos GPS al MININTER
 * 
 * @property string $id
 * @property string $name
 * @property string $token_gps
 * @property string $ubigeo
 * @property string $tipo
 * @property string|null $codigo_comisaria
 * @property bool $active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Municipality extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'municipalities';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'token_gps',
        'ubigeo',
        'tipo',
        'codigo_comisaria',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'active' => true,
    ];

    /**
     * Relación con transmisiones
     */
    public function transmissions(): HasMany
    {
        return $this->hasMany(Transmission::class);
    }

    /**
     * Scope para municipalidades activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para tipo SERENAZGO
     */
    public function scopeSerenazgo($query)
    {
        return $query->where('tipo', 'SERENAZGO');
    }

    /**
     * Scope para tipo POLICIAL
     */
    public function scopePolicial($query)
    {
        return $query->where('tipo', 'POLICIAL');
    }

    /**
     * Verificar si es tipo SERENAZGO
     */
    public function isSerenazgo(): bool
    {
        return $this->tipo === 'SERENAZGO';
    }

    /**
     * Verificar si es tipo POLICIAL
     */
    public function isPolicial(): bool
    {
        return $this->tipo === 'POLICIAL';
    }

    /**
     * Obtener el campo ID para MININTER según el tipo
     */
    public function getMininterIdField(): string
    {
        return $this->isSerenazgo() ? 'idMunicipalidad' : 'idTransmision';
    }

    /**
     * Obtener el valor del ID para MININTER
     */
    public function getMininterIdValue(): string
    {
        return $this->isSerenazgo() ? $this->id : \Illuminate\Support\Str::uuid()->toString();
    }

    /**
     * Obtener transmisiones exitosas
     */
    public function successfulTransmissions(): HasMany
    {
        return $this->transmissions()->where('status', 'SENT');
    }

    /**
     * Obtener transmisiones fallidas
     */
    public function failedTransmissions(): HasMany
    {
        return $this->transmissions()->where('status', 'FAILED');
    }
}
