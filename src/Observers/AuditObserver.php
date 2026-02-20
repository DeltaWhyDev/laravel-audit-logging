<?php

namespace DeltaWhyDev\AuditLog\Observers;

use DeltaWhyDev\AuditLog\Services\Audit\AuditLogger;
use DeltaWhyDev\AuditLog\Enums\AuditAction;
use DeltaWhyDev\AuditLog\Traits\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class AuditObserver
{
    /**
     * Handle the model "created" event
     */
    public function created(Model $model): void
    {
        if ($model instanceof Pivot) {
            $this->handlePivotEvent($model, 'created');
            return;
        }

        if (!$this->shouldAudit($model)) {
            return;
        }
        
        if (!$model->shouldLogCreated()) {
            return;
        }
        
        $this->logChange($model, AuditAction::CREATED, [], $model->getAttributes());
        
        // Notify parents
        $this->handleParentLogging($model, 'created');
    }
    
    /**
     * Handle the model "updated" event
     */
    public function updated(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }
        
        if (!$model->shouldLogUpdated()) {
            return;
        }
        
        // Get original attributes before changes
        $original = $model->getOriginal();
        $changes = $model->getChanges();
        
        // Filter out excluded attributes
        $excluded = $model->getExcludedAttributes();
        $original = array_diff_key($original, array_flip($excluded));
        $changes = array_diff_key($changes, array_flip($excluded));
        
        if (empty($changes)) {
            // Check if there are relation changes
            $relationChanges = $model->relationChanges ?? null;
            if (empty($relationChanges)) {
                return; // No meaningful changes
            }
        }
        
        // Reconstruct old values
        $oldAttributes = [];
        foreach ($changes as $key => $newValue) {
            $oldAttributes[$key] = $original[$key] ?? null;
        }
        
        // Merge with all original attributes for diff calculation
        $allOldAttributes = array_merge($original, $oldAttributes);
        $allNewAttributes = $model->getAttributes();
        
        $this->logChange($model, AuditAction::UPDATED, $allOldAttributes, $allNewAttributes);
    }
    
    /**
     * Handle the model "deleted" event
     */
    public function deleted(Model $model): void
    {
        if ($model instanceof Pivot) {
            $this->handlePivotEvent($model, 'deleted');
            return;
        }

        // Notify parents BEFORE detailed audit check? 
        // Or strictly if auditing is enabled for this model.
        // Let's check auditing enabled first.
        if ($this->shouldAudit($model)) {
             $this->handleParentLogging($model, 'deleted');
        }

        if (!$this->shouldAudit($model)) {
            return;
        }
        
        if (!$model->shouldLogDeleted()) {
            return;
        }
        
        $this->logChange($model, AuditAction::DELETED, $model->getAttributes(), []);
    }
    
    /**
     * Handle the model "restored" event
     */
    public function restored(Model $model): void
    {
        if (!$this->shouldAudit($model)) {
            return;
        }
        
        if (!$model->shouldLogRestored()) {
            return;
        }
        
        $this->logChange($model, AuditAction::RESTORED, [], $model->getAttributes());
    }
    
    /**
     * Log a change
     */
    protected function logChange(Model $model, AuditAction $action, array $oldAttributes, array $newAttributes): void
    {
        $this->processChange($model, $action, $oldAttributes, $newAttributes);
    }
    
    /**
     * Process and register the change with PendingAudit
     */
    protected function processChange(Model $model, AuditAction $action, array $oldAttributes, array $newAttributes): void
    {
        // Get sensitive fields from model
        $sensitiveFields = $model->getSensitiveFields();
        
        // Filter out excluded attributes before calculating diff
        $excluded = $model->getExcludedAttributes();
        $oldAttributes = array_diff_key($oldAttributes, array_flip($excluded));
        $newAttributes = array_diff_key($newAttributes, array_flip($excluded));
        
        $attributes = AuditLogger::calculateDiff($oldAttributes, $newAttributes, $excluded, $sensitiveFields);
        
        // Check if there are relation changes
        $relationChanges = $model->relationChanges ?? null;
        $formattedRelations = [];

        if (empty($attributes) && empty($relationChanges) && $action === AuditAction::UPDATED) {
            return; // No changes to log
        }
        
        // Format relation changes if any
        if (!empty($relationChanges)) {
            foreach ($relationChanges as $relationName => $changes) {
                $added = $this->getRelationModels($model, $relationName, $changes['added'] ?? []);
                $removed = $this->getRelationModels($model, $relationName, $changes['removed'] ?? []);
                
                $formattedRelations[$relationName] = [
                    'added' => $added,
                    'removed' => $removed,
                ];
            }
            // Clear relation changes
            $model->relationChanges = null;
        }

        // Register with PendingAudit
        \DeltaWhyDev\AuditLog\Services\Audit\PendingAudit::getInstance()->registerChange(
            $model,
            $action,
            $attributes,
            $formattedRelations
        );
    }
    
    /**
     * Get relation models by IDs
     */
    protected function getRelationModels(Model $model, string $relation, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        
        try {
            if (method_exists($model, $relation)) {
                $relationInstance = $model->$relation();
                $relatedModel = $relationInstance->getRelated();
                return $relatedModel->whereIn('id', $ids)->get()->map(function ($item) {
                    return $this->formatRelationData($item);
                })->toArray();
            }
        } catch (\Exception $e) {
            // Fallback to just IDs
        }
        
        return array_map(fn($id) => ['id' => $id], $ids);
    }
    
    /**
     * Handle pivot events by logging relation changes on parent models
     */
    protected function handlePivotEvent(Pivot $pivot, string $event): void
    {
        // Find belongsTo relations to identify parents
        $reflector = new ReflectionClass($pivot);
        $methods = $reflector->getMethods(ReflectionMethod::IS_PUBLIC);
        
        $parents = [];
        
        foreach ($methods as $method) {
            if ($method->getNumberOfParameters() > 0 || $method->class !== $pivot::class) {
                continue;
            }
            
            try {
                $returnType = $method->getReturnType();
            } catch (\Throwable $e) {
                continue;
            }
        }
        
        $attributes = $pivot->getAttributes();
        foreach ($attributes as $key => $value) {
            if (Str::endsWith($key, '_id')) {
                $relationName = Str::camel(Str::beforeLast($key, '_id'));
                if (method_exists($pivot, $relationName)) {
                    try {
                        $related = $pivot->$relationName; // Get the model
                        if ($related instanceof Model) {
                            $parents[$relationName] = $related;
                        }
                    } catch (\Throwable $e) {}
                }
            }
        }
        
        $parentsList = array_values($parents);
        if (empty($parentsList)) return;

        foreach ($parentsList as $parent) {
             // Identify the "other" side
             $others = array_filter($parentsList, fn($p) => $p->isNot($parent));
             
             foreach ($others as $other) {
                 // Guess relation name on $parent for $other
                 $relationName = Str::plural(Str::camel(class_basename($other)));
                 
                 // Check if actually Auditable?
                 if (!method_exists($parent, 'isAuditingEnabled')) {
                     continue;
                 }

                 $action = AuditAction::UPDATED;
                 
                 // Prepare added/removed arrays
                 $addedData = [];
                 if ($event === 'created') {
                     $addedData[] = $this->formatRelationData($other);
                 }
                 
                 $removedData = [];
                 if ($event === 'deleted') {
                     $removedData[] = $this->formatRelationData($other);
                 }
                 
                 // Register with PendingAudit
                 \DeltaWhyDev\AuditLog\Services\Audit\PendingAudit::getInstance()->registerChange(
                    $parent,
                    $action,
                    [], // No attribute changes on parent
                    [
                        $relationName => [
                            'added' => $addedData,
                            'removed' => $removedData
                        ]
                    ]
                 );
             }
        }
    }

    /**
     * Handle pivot attached event (avoiding duplicates if Pivot model exists)
     */
    public function pivotAttached($model, $relationName, $pivotIds, $pivotAttributes)
    {
        // Standard attach with 'using' fires 'created' on Pivot model, which we already handle.
    }


    /**
     * Handle pivot detached event (Crucial for detaching)
     */
    public function pivotDetached($model, $relationName, $pivotIds)
    {
        if (empty($pivotIds)) return;

        try {
            $relation = $model->$relationName();
            $relatedModel = $relation->getRelated();
            
            // Fetch related items
            $relatedItems = $relatedModel::whereIn($relatedModel->getKeyName(), $pivotIds)->get();
            
            $removedData = $relatedItems->map(function ($item) {
                return $this->formatRelationData($item);
            })->toArray();
            
            if (empty($removedData)) return;
            
            // Register with PendingAudit
            \DeltaWhyDev\AuditLog\Services\Audit\PendingAudit::getInstance()->registerChange(
                $model,
                AuditAction::RELATIONS_UPDATED,
                [],
                [
                    $relationName => [
                        'added' => [],
                        'removed' => $removedData
                    ]
                ]
            );
                
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AuditObserver: pivotDetached failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle pivot updated event
     */
    public function pivotUpdated($model, $relationName, $pivotIds, $pivotAttributes)
    {
        // Similar to attached, might duplicate 'updated' on Pivot model.
    }


    /**
     * Handle logging parent relations on create/delete
     */
    protected function handleParentLogging(Model $model, string $event): void
    {
        if (!method_exists($model, 'getAuditParents')) {
            return;
        }

        $parents = $model->getAuditParents();
        if (empty($parents)) {
            return;
        }

        foreach ($parents as $relationName) {
            try {
                if (!method_exists($model, $relationName)) {
                    continue;
                }
                
                $relation = $model->$relationName();
                $parent = $relation->first(); // Get the parent model instance
                
                if (!$parent || !($parent instanceof Model)) {
                    continue;
                }

                // Check if parent is auditable
                if (!method_exists($parent, 'isAuditingEnabled')) {
                    continue;
                }

                // Prepare changes
                $childData = $this->formatRelationData($model);
                
                $added = [];
                $removed = [];
                
                if ($event === 'created') {
                    $added[] = $childData;
                } elseif ($event === 'deleted') {
                    $removed[] = $childData;
                }

                // Guess the inverse relation name (from Parent perspective)
                // e.g. Child is StoragePosition, Parent is Warehouse
                // Relation on Child is "warehouse"
                // Relation on Parent should be "storage_positions"
                // We can guess it from child class name pluralized?
                $inverseRelation = Str::plural(Str::camel(class_basename($model)));

                \DeltaWhyDev\AuditLog\Services\Audit\PendingAudit::getInstance()->registerChange(
                    $parent,
                    AuditAction::RELATIONS_UPDATED,
                    [],
                    [
                        $inverseRelation => [
                            'added' => $added,
                            'removed' => $removed,
                        ]
                    ]
                );

            } catch (\Exception $e) {
                // Ignore errors to avoid blocking main execution
            }
        }
    }

    /**
     * Check if should audit this model
     */
    protected function shouldAudit(Model $model): bool
    {
        if (!in_array(Auditable::class, class_uses_recursive($model))) {
            return false;
        }
        
        return $model::isAuditingEnabled();
    }

    /**
     * Format relation data to be minimal (id, name, type)
     */
    private function formatRelationData(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'name' => $model->name ?? $model->title ?? $model->label ?? $model->code ?? ("#" . $model->getKey()),
            'type' => get_class($model),
        ];
    }
}
