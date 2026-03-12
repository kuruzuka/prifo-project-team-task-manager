<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transaction Log Model
 *
 * Records transaction-level audit trails for critical database operations.
 * Each entry represents a complete transaction with before/after states,
 * actor information, and timing metrics.
 *
 * @property int $id
 * @property string $transaction_id
 * @property string $operation_type
 * @property string $operation_name
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property int|null $actor_id
 * @property string|null $actor_ip
 * @property string|null $actor_user_agent
 * @property array|null $old_values
 * @property array|null $new_values
 * @property array|null $context
 * @property string $status
 * @property string|null $error_message
 * @property int|null $duration_ms
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $completed_at
 */
class TransactionLog extends Model
{
    /**
     * Disable default timestamps since we use started_at/completed_at.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'operation_type',
        'operation_name',
        'entity_type',
        'entity_id',
        'actor_id',
        'actor_ip',
        'actor_user_agent',
        'old_values',
        'new_values',
        'context',
        'status',
        'error_message',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Operation types.
     */
    public const TYPE_CREATE = 'create';
    public const TYPE_UPDATE = 'update';
    public const TYPE_DELETE = 'delete';
    public const TYPE_BATCH = 'batch';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_ASSIGN = 'assign';

    /**
     * Status values.
     */
    public const STATUS_STARTED = 'started';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_FAILED = 'failed';

    /**
     * The actor (user) who performed this operation.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Scope: Filter by entity type and ID.
     */
    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    /**
     * Scope: Filter by actor.
     */
    public function scopeByActor($query, int $actorId)
    {
        return $query->where('actor_id', $actorId);
    }

    /**
     * Scope: Filter by operation type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('operation_type', $type);
    }

    /**
     * Scope: Filter by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by date range.
     */
    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('started_at', [$start, $end]);
    }

    /**
     * Scope: Only failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', [self::STATUS_FAILED, self::STATUS_ROLLED_BACK]);
    }

    /**
     * Scope: Only successful transactions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', self::STATUS_COMMITTED);
    }

    /**
     * Get a human-readable summary of this transaction.
     */
    public function getSummaryAttribute(): string
    {
        $actor = $this->actor?->first_name ?? 'System';
        $entity = $this->entity_type ? class_basename($this->entity_type) : 'Unknown';

        return "{$actor} performed {$this->operation_name} on {$entity} #{$this->entity_id}";
    }

    /**
     * Calculate duration if completed.
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration_ms) {
            return null;
        }

        if ($this->duration_ms < 1000) {
            return "{$this->duration_ms}ms";
        }

        return round($this->duration_ms / 1000, 2) . 's';
    }
}
