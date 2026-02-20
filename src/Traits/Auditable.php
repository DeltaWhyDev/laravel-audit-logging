<?php

namespace DeltaWhyDev\AuditLog\Traits;

use DeltaWhyDev\AuditLog\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    protected static bool $auditingEnabled = true;

    
    /**
     * Boot the trait
     */
    public static function bootAuditable(): void
    {
        // Register observer
        static::observe(AuditObserver::class);
        
        // Register Pivot events manually as they are not standard observable events
        static::registerModelEvent('pivotAttached', function ($model, $relationName, $pivotIds, $pivotAttributes) {
            (new AuditObserver)->pivotAttached($model, $relationName, $pivotIds, $pivotAttributes);
        });
        
        static::registerModelEvent('pivotDetached', function ($model, $relationName, $pivotIds) {
            (new AuditObserver)->pivotDetached($model, $relationName, $pivotIds);
        });
        
        static::registerModelEvent('pivotUpdated', function ($model, $relationName, $pivotIds, $pivotAttributes) {
            (new AuditObserver)->pivotUpdated($model, $relationName, $pivotIds, $pivotAttributes);
        });
    }
    
    /**
     * Get audit configuration
     */
    public function getAuditConfig(): array
    {
        $defaultConfig = config('audit-log.default_config', []);
        
        return array_merge([
            'exclude_attributes' => ['created_at', 'updated_at', 'deleted_at'],
            'track_relations' => [],
            'log_created' => true,
            'log_updated' => true,
            'log_deleted' => true,
            'log_restored' => true,
            'sensitive_fields' => [],
            'json_fields_format' => [],
            'date_fields_format' => [],
            'relation_link_fields' => [],
            'photo_relations' => [],
            'document_relations' => [],
            'number_formatters' => [],
            'custom_views' => [],
        ], $defaultConfig, property_exists($this, 'auditConfig') ? $this->auditConfig : []);
    }
    
    /**
     * Check if auditing is enabled
     */
    public static function isAuditingEnabled(): bool
    {
        return static::$auditingEnabled;
    }
    
    /**
     * Disable auditing temporarily
     */
    public static function disableAuditing(): void
    {
        static::$auditingEnabled = false;
    }
    
    /**
     * Enable auditing
     */
    public static function enableAuditing(): void
    {
        static::$auditingEnabled = true;
    }
    
    /**
     * Execute callback without auditing
     */
    public static function withoutAuditing(callable $callback)
    {
        $wasEnabled = static::$auditingEnabled;
        static::disableAuditing();
        
        try {
            return $callback();
        } finally {
            if ($wasEnabled) {
                static::enableAuditing();
            }
        }
    }
    
    /**
     * Save without auditing
     */
    public function saveWithoutAuditing(array $options = []): bool
    {
        return static::withoutAuditing(function () use ($options) {
            return $this->save($options);
        });
    }
    
    /**
     * Get attributes to exclude from logging
     */
    public function getExcludedAttributes(): array
    {
        $config = $this->getAuditConfig();
        return $config['exclude_attributes'] ?? [];
    }
    
    /**
     * Get sensitive fields that should be masked
     */
    public function getSensitiveFields(): array
    {
        $config = $this->getAuditConfig();
        $modelSensitive = $config['sensitive_fields'] ?? [];
        
        // Merge with global config
        return array_merge(
            config('audit-log.sensitive_fields', []),
            $modelSensitive
        );
    }
    
    /**
     * Get relations to track
     */
    public function getTrackedRelations(): array
    {
        $config = $this->getAuditConfig();
        return $config['track_relations'] ?? [];
    }
    
    /**
     * Check if should log created event
     */
    public function shouldLogCreated(): bool
    {
        $config = $this->getAuditConfig();
        return $config['log_created'] ?? true;
    }
    
    /**
     * Check if should log updated event
     */
    public function shouldLogUpdated(): bool
    {
        $config = $this->getAuditConfig();
        return $config['log_updated'] ?? true;
    }
    
    /**
     * Check if should log deleted event
     */
    public function shouldLogDeleted(): bool
    {
        $config = $this->getAuditConfig();
        return $config['log_deleted'] ?? true;
    }
    
    /**
     * Check if should log restored event
     */
    public function shouldLogRestored(): bool
    {
        $config = $this->getAuditConfig();
        return $config['log_restored'] ?? true;
    }
    
    /**
     * Get JSON field formatters
     */
    public function getJsonFieldFormatters(): array
    {
        $config = $this->getAuditConfig();
        return array_merge(
            config('audit-log.json_fields_format', []),
            $config['json_fields_format'] ?? []
        );
    }
    
    /**
     * Get date field formatters
     */
    public function getDateFieldFormatters(): array
    {
        $config = $this->getAuditConfig();
        return array_merge(
            config('audit-log.date_fields_format', []),
            $config['date_fields_format'] ?? []
        );
    }
    
    /**
     * Get relation link fields
     */
    public function getRelationLinkFields(): array
    {
        $config = $this->getAuditConfig();
        return array_merge(
            config('audit-log.relation_link_fields', []),
            $config['relation_link_fields'] ?? []
        );
    }
    
    /**
     * Get photo relations
     */
    public function getPhotoRelations(): array
    {
        $config = $this->getAuditConfig();
        return array_merge(
            config('audit-log.photo_relations', []),
            $config['photo_relations'] ?? []
        );
    }
    
    /**
     * Get document relations
     */
    public function getDocumentRelations(): array
    {
        $config = $this->getAuditConfig();
        return array_merge(
            config('audit-log.document_relations', []),
            $config['document_relations'] ?? []
        );
    }

    /**
     * Get parent relations that should be audited when this model changes.
     * Defaults to standard Eloquent $touches property if not specified.
     */
    public function getAuditParents(): array
    {
        if (property_exists($this, 'auditParents')) {
            return $this->auditParents;
        }

        return $this->touches ?? [];
    }
}
