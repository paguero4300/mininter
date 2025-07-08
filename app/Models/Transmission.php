<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo Transmission
 * 
 * Representa una transmisión de datos GPS al MININTER
 * 
 * @property string $id
 * @property string $municipality_id
 * @property array $payload
 * @property int|null $response_code
 * @property string $status
 * @property Carbon $sent_at
 * @property int $retry_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Transmission extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'transmissions';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'municipality_id',
        'payload',
        'response_code',
        'status',
        'sent_at',
        'retry_count',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_code' => 'integer',
        'sent_at' => 'datetime',
        'retry_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'retry_count' => 0,
        'status' => 'FAILED',
    ];

    /**
     * Relación con municipalidad
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Scope para transmisiones exitosas
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'SENT');
    }

    /**
     * Scope para transmisiones fallidas
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    /**
     * Scope para transmisiones con reintentos
     */
    public function scopeWithRetries($query)
    {
        return $query->where('retry_count', '>', 0);
    }

    /**
     * Verificar si la transmisión fue exitosa
     */
    public function wasSuccessful(): bool
    {
        return $this->status === 'SENT';
    }

    /**
     * Verificar si la transmisión falló
     */
    public function hasFailed(): bool
    {
        return $this->status === 'FAILED';
    }

    /**
     * Verificar si se puede reintentar
     */
    public function canRetry(): bool
    {
        return $this->retry_count < 5 && $this->hasFailed();
    }

    /**
     * Incrementar contador de reintentos
     */
    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }

    /**
     * Marcar como enviada exitosamente
     */
    public function markAsSent(int $responseCode): void
    {
        $this->update([
            'status' => 'SENT',
            'response_code' => $responseCode,
            'sent_at' => now(),
        ]);
    }

    /**
     * Marcar como fallida
     */
    public function markAsFailed(int $responseCode): void
    {
        $this->update([
            'status' => 'FAILED',
            'response_code' => $responseCode,
            'sent_at' => now(),
        ]);
    }

    /**
     * Obtener el tiempo transcurrido desde el último intento
     */
    public function getTimeSinceLastAttempt(): int
    {
        return $this->sent_at ? (int) now()->diffInSeconds($this->sent_at) : 0;
    }

    /**
     * Calcular el delay para el próximo reintento (backoff exponencial)
     */
    public function getNextRetryDelay(): int
    {
        $delays = [60, 120, 300, 600, 1800]; // segundos
        return $delays[$this->retry_count] ?? 1800;
    }
}
