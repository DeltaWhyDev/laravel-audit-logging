<?php

namespace DeltaWhyDev\AuditLog\Helpers;

use DeltaWhyDev\AuditLog\Models\AuditLog;
use DeltaWhyDev\AuditLog\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

if (!function_exists('audit_log')) {
    /**
     * Quick helper to log an audit entry
     */
    function audit_log(Model $entity, string $action, array $options = []): AuditLog
    {
        $builder = AuditLogger::for($entity)->action($action);
        
        if (isset($options['by'])) {
            $builder->by($options['by']);
        }
        
        if (isset($options['attributes'])) {
            $builder->withAttributes(
                $options['attributes']['old'] ?? [],
                $options['attributes']['new'] ?? [],
                $options['attributes']['sensitive_fields'] ?? []
            );
        }
        
        if (isset($options['relations'])) {
            foreach ($options['relations'] as $relationName => $changes) {
                $builder->withRelations(
                    $relationName,
                    $changes['added'] ?? [],
                    $changes['removed'] ?? []
                );
            }
        }
        
        if (isset($options['metadata'])) {
            $builder->metadata($options['metadata']);
        }
        
        return $builder->log();
    }
}
