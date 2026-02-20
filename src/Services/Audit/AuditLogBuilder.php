<?php

namespace DeltaWhyDev\AuditLog\Services\Audit;

use DeltaWhyDev\AuditLog\Models\AuditLog;
use DeltaWhyDev\AuditLog\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class AuditLogBuilder
{
    protected Model $entity;
    protected AuditAction|string $action;
    protected ?string $actorType = null;
    protected ?int $actorId = null;
    protected ?array $attributes = null;
    protected ?array $relations = null;
    protected array $metadata = [];
    
    public function __construct(Model $entity)
    {
        $this->entity = $entity;
    }
    
    /**
     * Set the action type
     */
    public function action(AuditAction|string $action): self
    {
        $this->action = $action;
        return $this;
    }
    
    /**
     * Set the actor (user, system, etc.)
     */
    public function by($actor): self
    {
        if (is_object($actor)) {
            // Assume it's a User model
            $this->actorType = 'user';
            $this->actorId = $actor->id;
        } elseif (is_array($actor)) {
            $this->actorType = $actor['type'] ?? 'system';
            $this->actorId = $actor['id'] ?? null;
        } else {
            $this->actorType = 'system';
            $this->actorId = null;
        }
        
        return $this;
    }
    
    /**
     * Set attribute changes
     */
    public function withAttributes(array $oldAttributes, array $newAttributes, array $sensitiveFields = []): self
    {
        $this->attributes = AuditLogger::calculateDiff($oldAttributes, $newAttributes, [], $sensitiveFields);
        return $this;
    }

    /**
     * Set raw attribute changes (already calculated diff)
     */
    public function withRawAttributes(array $attributes): self
    {
        $this->attributes = $attributes;
        return $this;
    }
    
    /**
     * Set relation changes
     */
    public function withRelations(string $relationName, $added = [], $removed = []): self
    {
        if ($this->relations === null) {
            $this->relations = [];
        }
        
        if (!isset($this->relations[$relationName])) {
            $this->relations[$relationName] = [
                'added' => [],
                'removed' => [],
            ];
        }
        
        // Convert collections to arrays
        if ($added instanceof Collection) {
            $added = $added->toArray();
        }
        if ($removed instanceof Collection) {
            $removed = $removed->toArray();
        }
        
        // Enrich with basic data
        $this->relations[$relationName]['added'] = array_merge(
            $this->relations[$relationName]['added'],
            $this->enrichRelations($added)
        );
        
        $this->relations[$relationName]['removed'] = array_merge(
            $this->relations[$relationName]['removed'],
            $this->enrichRelations($removed)
        );
        
        return $this;
    }
    
    /**
     * Add metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }
    
    /**
     * Write the log entry
     */
    /**
     * Write the log entry
     */
    public function log(): ?AuditLog
    {
        // Resolve actor if not set
        if ($this->actorType === null) {
            $actor = AuditLogger::resolveActor();
            $this->actorType = $actor['type'];
            $this->actorId = $actor['id'];
        }
        
        // Ensure metadata is merged with defaults
        $this->metadata = array_merge([
            'request_id' => AuditLogger::getRequestId(),
            'source' => AuditLogger::detectSource(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ], $this->metadata);
        
        // Convert action to enum value if string
        $actionValue = $this->action instanceof AuditAction 
            ? $this->action->value 
            : AuditAction::fromString($this->action)->value;
            
        $data = [
            'entity_type' => get_class($this->entity),
            'entity_id' => $this->entity->id,
            'action' => $actionValue,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'attributes' => $this->attributes,
            'relations' => $this->relations,
            'metadata' => $this->metadata,
            'created_at' => now(),
        ];
        
        // Check if queueing is enabled
        if (config('audit-log.queue.enabled', false)) {
            $job = new \DeltaWhyDev\AuditLog\Jobs\ProcessAuditLog($data);
            
            $connection = config('audit-log.queue.connection');
            $queue = config('audit-log.queue.queue');
            
            if ($connection) {
                $job->onConnection($connection);
            }
            
            if ($queue) {
                $job->onQueue($queue);
            }
            
            dispatch($job);
            
            return null; // Return null when queued
        }
        
        return AuditLog::create($data);
    }
    
    /**
     * Enrich relation data with basic info
     */
    protected function enrichRelations(array $relations): array
    {
        return array_map(function ($relation) {
            if (is_array($relation)) {
                return $relation;
            }
            
            if (is_object($relation)) {
                return [
                    'id' => $relation->id,
                    'name' => $relation->name ?? $relation->title ?? null,
                ];
            }
            
            return ['id' => $relation];
        }, $relations);
    }
}
