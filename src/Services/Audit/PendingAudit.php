<?php

namespace DeltaWhyDev\AuditLog\Services\Audit;

use DeltaWhyDev\AuditLog\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PendingAudit
{
    /**
     * Singleton instance
     */
    protected static ?self $instance = null;

    /**
     * Pending changes keyed by model class and ID
     * @var array<string, array>
     */
    protected array $pending = [];

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * Register a change for a model
     */
    public function registerChange(Model $model, AuditAction $action, array $attributes = [], array $relations = []): void
    {
        $key = $this->getKey($model);

        if (!isset($this->pending[$key])) {
            $this->pending[$key] = [
                'model' => $model,
                'action' => $action,
                'attributes' => [],
                'relations' => [],
            ];
            
            // Register flush callback if inside transaction
            if (DB::transactionLevel() > 0) {
                DB::afterCommit(function () use ($key) {
                    $this->flush($key);
                });
            } else {
                // If not in transaction, flush immediately
                $this->flush($key);
                return;
            }
        }

        // Merge action (Update takes precedence if we are accumulating changes)
        $currentAction = $this->pending[$key]['action'];
        if ($action === AuditAction::DELETED) {
             $this->pending[$key]['action'] = AuditAction::DELETED;
        } elseif ($currentAction !== AuditAction::CREATED && $currentAction !== AuditAction::DELETED) {
             if ($action === AuditAction::CREATED) {
                 $this->pending[$key]['action'] = AuditAction::CREATED;
             }
        }

        // Merge attributes
        foreach ($attributes as $field => $change) {
            if (!isset($this->pending[$key]['attributes'][$field])) {
                $this->pending[$key]['attributes'][$field] = $change;
            } else {
                // Update "new" value, keep original "old" value
                $this->pending[$key]['attributes'][$field]['new'] = $change['new'];
            }
        }

        // Merge relations
        // Structure: ['relationName' => ['added' => [], 'removed' => []]]
        foreach ($relations as $relationName => $changes) {
            if (!isset($this->pending[$key]['relations'][$relationName])) {
                $this->pending[$key]['relations'][$relationName] = [
                    'added' => [],
                    'removed' => [],
                ];
            }
            
            if (!empty($changes['added'])) {
                $this->pending[$key]['relations'][$relationName]['added'] = array_merge(
                    $this->pending[$key]['relations'][$relationName]['added'],
                    $changes['added']
                );
            }
            
            if (!empty($changes['removed'])) {
                $this->pending[$key]['relations'][$relationName]['removed'] = array_merge(
                    $this->pending[$key]['relations'][$relationName]['removed'],
                    $changes['removed']
                );
            }
        }
    }

    /**
     * Flush pending changes for a specific key
     */
    protected function flush(string $key): void
    {
        if (!isset($this->pending[$key])) {
            return;
        }

        $data = $this->pending[$key];
        unset($this->pending[$key]); // Clear ensuring we don't double log if called again

        $model = $data['model'];
        $action = $data['action'];
        $attributes = $data['attributes'];
        $relations = $data['relations'];

        // If no changes at all, skip
        if (empty($attributes) && empty($relations) && $action !== AuditAction::DELETED && $action !== AuditAction::RESTORED && $action !== AuditAction::CREATED) {
            return;
        }

        // If action is RELATIONS_UPDATED but we have attribute changes, upgrade to UPDATED
        if ($action === AuditAction::RELATIONS_UPDATED && !empty($attributes)) {
            $action = AuditAction::UPDATED;
        }

        // Write the log using AuditLogger builder
        $builder = AuditLogger::for($model)
            ->action($action)
            ->by(AuditLogger::resolveActor());
            
        $this->writeLogViaBuilder($builder, $attributes, $relations);
    }

    protected function writeLogViaBuilder($builder, $attributes, $relations)
    {
        if (!empty($attributes)) {
            $builder->withRawAttributes($attributes);
        }
        
        foreach ($relations as $name => $changes) {
            $builder->withRelations($name, $changes['added'], $changes['removed']);
        }
        
        // We can pass empty metadata as default handling in builder adds request_id etc.
        $builder->log();
    }

    protected function getKey(Model $model): string
    {
        return get_class($model) . ':' . $model->getKey();
    }
}
