<?php

namespace DeltaWhyDev\AuditLog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use DeltaWhyDev\AuditLog\Enums\AuditAction;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    
    public $timestamps = false;
    
    /**
     * Get the database connection for the model.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        return config('audit-log.connection') ?: parent::getConnectionName();
    }
    
    use \Illuminate\Database\Eloquent\Prunable;
    
    /**
     * Get the prunable model query.
     */
    public function prunable()
    {
        // Get retention days from config, default to 365
        $days = config('audit-log.pruning.retention_days', 365);
        
        return static::where('created_at', '<=', now()->subDays($days));
    }

    protected $fillable = [
        'entity_type',
        'entity_id',
        'action',
        'actor_type',
        'actor_id',
        'attributes',
        'relations',
        'metadata',
        'created_at',
    ];
    
    protected $casts = [
        'entity_id' => 'integer',
        'actor_id' => 'integer',
        'action' => AuditAction::class,
        'attributes' => 'array',
        'relations' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
    
    /**
     * Scope: Filter by entity
     */
    public function scopeForEntity(Builder $query, string $entityType, int $entityId): Builder
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }
    
    /**
     * Scope: Filter by actor
     */
    public function scopeByActor(Builder $query, string $actorType, ?int $actorId = null): Builder
    {
        $query->where('actor_type', $actorType);
        
        if ($actorId !== null) {
            $query->where('actor_id', $actorId);
        }
        
        return $query;
    }
    
    /**
     * Scope: Filter by action
     */
    public function scopeByAction(Builder $query, AuditAction|string|int $action): Builder
    {
        // Handle enum, string, or integer
        if ($action instanceof AuditAction) {
            $action = $action->value;
        } elseif (is_string($action)) {
            $action = AuditAction::fromString($action)->value;
        }
        
        return $query->where('action', $action);
    }
    
    /**
     * Scope: Filter by date range
     */
    public function scopeInDateRange(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
    
    /**
     * Get human-readable summary
     */
    public function getSummaryAttribute(): string
    {
        $actorName = $this->getActorName();
        $entityName = $this->getEntityName();
        $entityDisplayName = \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getEntityDisplayName($this->entity_type, $this->entity_id);
        
        return sprintf(
            '%s %s %s %s',
            $actorName,
            $this->action->label(),
            $entityName,
            $entityDisplayName
        );
    }
    
    /**
     * Get actor name
     */
    protected function getActorName(): string
    {
        return \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getActorDisplayName($this->actor_type, $this->actor_id);
    }
    
    /**
     * Get entity name (short class name)
     */
    protected function getEntityName(): string
    {
        $parts = explode('\\', $this->entity_type);
        return end($parts);
    }
}
