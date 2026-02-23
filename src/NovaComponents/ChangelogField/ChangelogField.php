<?php

namespace DeltaWhyDev\AuditLog\NovaComponents\ChangelogField;

use DeltaWhyDev\AuditLog\Enums\AuditAction;
use DeltaWhyDev\AuditLog\Models\AuditLog;
use DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver;
use Laravel\Nova\Fields\Field;

class ChangelogField extends Field
{
    /**
     * The field's component.
     */
    public $component = 'changelog-field';

    /**
     * Field label configuration
     */
    protected array $fieldLabels = [];

    protected ?string $entityType = null;

    protected string|int|null $entityId = null;

    protected int $perPage = 25;

    protected bool $showPagination = true;

    protected bool $alwaysExpanded = false;

    /**
     * Create a new field.
     */
    public function __construct($name = 'Changelog', $attribute = null)
    {
        parent::__construct($name, $attribute);
        $this->hideFromIndex();
        $this->hideWhenCreating();
        $this->hideWhenUpdating();
    }

    /**
     * Set field labels for attribute names
     */
    public function fieldLabels(array $labels): self
    {
        $this->fieldLabels = $labels;

        return $this;
    }

    /**
     * Set entity type and ID (auto-detected if not set)
     */
    public function forEntity(string $entityType, string|int $entityId): self
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;

        return $this;
    }

    /**
     * Set items per page
     */
    public function perPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }

    /**
     * Disable pagination
     */
    public function withoutPagination(): self
    {
        $this->showPagination = false;

        return $this;
    }

    /**
     * Set the field to be always expanded
     */
    public function alwaysExpanded(bool $alwaysExpanded = true): self
    {
        $this->alwaysExpanded = $alwaysExpanded;

        return $this;
    }

    /**
     * Resolve the field's value.
     */
    public function resolve($resource, $attribute = null): void
    {
        // If a custom resolution callback is defined (e.g. via resolveUsing), use it
        // This allows passing specific data (like a single log entry) instead of querying
        if ($this->resolveCallback) {
            parent::resolve($resource, $attribute);

            return;
        }

        // Auto-detect entity if not set
        if (! $this->entityType) {
            $this->entityType = get_class($resource);
        }
        if (! $this->entityId) {
            $this->entityId = $resource->id;
        }

        // Load audit logs
        $query = AuditLog::forEntity($this->entityType, $this->entityId)
            ->orderBy('created_at', 'desc')
            ->limit($this->perPage);

        $logs = $query->get()
            ->map(function ($log) {
                return $this->formatLog($log);
            });

        $this->value = $logs;
    }

    /**
     * Format log entry for display
     */
    /**
     * Format log entry for display
     */
    /**
     * Format log entry for display
     */
    public function formatLog(AuditLog $log): array
    {
        // Auto-set entity context if missing (crucial when used in AuditLog resource)
        if (! $this->entityType) {
            $this->entityType = $log->entity_type;
            $this->entityId = $log->entity_id;
        }

        // Get actor info
        $actorName = $this->getActorName($log);
        $actorUrl = $this->getActorUri($log);

        // Generate dynamic summary
        $summary = $this->generateSummary($log);

        return [
            'id' => $log->id,
            'summary' => $summary,
            'action' => $log->action instanceof AuditAction
                ? $log->action->value()
                : (string) $log->action,
            'action_label' => $log->action instanceof AuditAction
                ? $log->action->label()
                : (string) $log->action,
            'actor' => $actorName,
            'actor_id' => $log->actor_id,
            'actor_type' => $log->actor_type, // Useful if we need to distinguish system vs user
            'actor_url' => $actorUrl,
            'entity_name' => $this->getEntityDisplayName($this->entityType, $this->entityId),
            'entity_id' => $this->entityId,
            'entity_url' => $this->getNovaResourceUri($this->entityType, $this->entityId),
            'audit_log_resource_uri' => 'audit-logs', // Standard URI key for audit logs
            'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
            'attributes' => $this->formatAttributes($log->attributes ?? []),
            'relations' => $this->formatRelations($log->relations ?? []),
            'metadata' => $log->metadata ?? [],
        ];
    }

    /**
     * Generate dynamic summary string
     */
    protected function generateSummary(AuditLog $log): string
    {
        $parts = [];

        // Check attributes
        if (! empty($log->attributes)) {
            $fields = array_keys($log->attributes);
            $fieldLabels = array_map(function ($field) {
                return $this->formatFieldName($field);
            }, $fields);

            // Limit to 3 fields then add "..."
            if (count($fieldLabels) > 3) {
                $fieldLabels = array_slice($fieldLabels, 0, 3);
                $fieldLabels[] = '...';
            }

            if (! empty($fieldLabels)) {
                $parts[] = 'Modified: '.implode(', ', $fieldLabels);
            }
        }

        // Check relations
        if (! empty($log->relations)) {
            $relations = array_keys($log->relations);
            $relationLabels = array_map(function ($relation) {
                return $this->formatFieldName($relation);
            }, $relations);

            // Limit to 3 relations then add "..."
            if (count($relationLabels) > 3) {
                $relationLabels = array_slice($relationLabels, 0, 3);
                $relationLabels[] = '...';
            }

            if (! empty($relationLabels)) {
                $parts[] = 'Relations: '.implode(', ', $relationLabels);
            }
        }

        if (empty($parts)) {
            return $log->summary ?? 'No changes detailed';
        }

        $summaryText = implode(' | ', $parts);

        if (mb_strlen($summaryText) > 30) {
            return mb_substr($summaryText, 0, 27).'...';
        }

        return $summaryText;
    }

    /**
     * Get actor name
     */
    protected function getActorName(AuditLog $log): string
    {
        return ResourceResolver::getActorDisplayName($log->actor_type, $log->actor_id);
    }

    /**
     * Get actor URI (full URL)
     */
    protected function getActorUri(AuditLog $log): ?string
    {
        $userModel = ResourceResolver::getUserModel();

        if (($log->actor_type === 'user' || $log->actor_type === $userModel) && $log->actor_id) {
            return $this->getNovaResourceUri($userModel, $log->actor_id);
        }

        return null;
    }

    /**
     * Get Nova resource URI for an entity (Full Path)
     */
    protected function getNovaResourceUri(string $entityType, string|int $entityId): ?string
    {
        return ResourceResolver::getNovaResourceUri($entityType, $entityId);
    }

    /**
     * Format attributes with custom labels
     */
    protected function formatAttributes(array $attributes): array
    {
        $formatted = [];
        $context = [
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'attributes' => $attributes,
        ];

        foreach ($attributes as $field => $change) {
            $label = $this->fieldLabels[$field]
                ?? config("audit-log.field_labels.{$field}", null)
                ?? $this->formatFieldName($field);

            $old = $change['old'] ?? null;
            $new = $change['new'] ?? null;

            // Check for transformer
            $transformer = $this->getTransformer($this->entityType, $field);

            if ($transformer) {
                $diff = $transformer->transformDiff($old, $new, $context);

                if (is_array($diff)) {
                    // Handle flattened diffs
                    foreach ($diff as $changeItem) {
                        $oldItem = $changeItem['old'] ?? 'null';
                        $newItem = $changeItem['new'] ?? 'null';

                        if ($oldItem === $newItem) {
                            continue;
                        }

                        $formatted[] = [
                            'field' => $field, // Keep original field for reference
                            'label' => $changeItem['label'],
                            'old' => $oldItem,
                            'new' => $newItem,
                            'is_diff_row' => true,
                        ];
                    }

                    continue;
                }

                $oldVal = $transformer->transform($old, 'old', $context);
                $newVal = $transformer->transform($new, 'new', $context);
            } else {
                $diff = null;

                // Try to use the underlying model's cast mechanism if class exists
                $usedDummyModel = false;
                if ($this->entityType && class_exists($this->entityType)) {
                    try {
                        $dummyModel = new $this->entityType;

                        $dummyModel->setAttribute($field, $old);
                        $oldVal = $this->formatValue($dummyModel->getAttribute($field), $field, $dummyModel);

                        $dummyModel->setAttribute($field, $new);
                        $newVal = $this->formatValue($dummyModel->getAttribute($field), $field, $dummyModel);

                        $usedDummyModel = true;
                    } catch (\Throwable $e) {
                        // Fallback mechanism if dummy model initialization fails
                    }
                }

                if (! $usedDummyModel) {
                    $oldVal = $this->formatValue($old, $field);
                    $newVal = $this->formatValue($new, $field);
                }
            }

            // Skip entries that resulted in the exact same rendered value (e.g. "All Allowed" -> "All Allowed")
            if ($oldVal === $newVal) {
                continue;
            }

            $formatted[] = [
                'field' => $field,
                'label' => $label,
                'old' => $oldVal,
                'new' => $newVal,
                'diff' => $diff, // Passed to frontend
            ];
        }

        return $formatted;
    }

    /**
     * Format relations
     */
    protected function formatRelations(array $relations): array
    {
        $formatted = [];

        foreach ($relations as $relation => $changes) {
            $modelClass = ResourceResolver::guessRelatedModelClass($relation);

            $formatItem = function ($item) use ($modelClass) {
                // $item could be string/int, or array ['id' => 1, 'name' => 'foo']
                $id = is_array($item) ? ($item['id'] ?? null) : $item;
                $loggedName = is_array($item) ? ($item['name'] ?? $id) : $item;

                $name = $loggedName;
                $url = null;

                if ($modelClass && $id) {
                    $url = ResourceResolver::getNovaResourceUri($modelClass, $id);

                    // Prioritize actual live DB data over statically logged names if it exists
                    $liveName = ResourceResolver::getEntityDisplayName($modelClass, $id);
                    if ($liveName !== "#{$id}" && ! str_contains($liveName, '(deleted)')) {
                        $name = $liveName;
                    } elseif (str_contains($liveName, '(deleted)')) {
                        $name = $loggedName.' (deleted)';
                    }
                }

                return [
                    'id' => $id,
                    'name' => (string) $name,
                    'url' => $url,
                ];
            };

            $formatted[] = [
                'relation' => $relation,
                'label' => $this->formatFieldName($relation),
                'added' => array_map($formatItem, $changes['added'] ?? []),
                'removed' => array_map($formatItem, $changes['removed'] ?? []),
            ];
        }

        return $formatted;
    }

    /**
     * Format field name (snake_case to Title Case)
     */
    protected function formatFieldName(string $field): string
    {
        $labels = config('audit-log.field_labels', []);
        if (isset($labels[$field])) {
            return $labels[$field];
        }

        return str_replace('_', ' ', ucwords($field, '_'));
    }

    /**
     * Format value for display
     */
    protected function formatValue($value, ?string $fieldName = null, ?\Illuminate\Database\Eloquent\Model $model = null): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Handle Enum logic natively via AuditLogger logic
        if ($model && $fieldName && \DeltaWhyDev\AuditLog\Services\Audit\AuditLogger::isEnumField($fieldName, $model)) {
            $formatted = \DeltaWhyDev\AuditLog\Services\Audit\AuditLogger::formatEnum($value, $fieldName, $model);
            if (is_array($formatted) && isset($formatted['display'])) {
                return (string) $formatted['display'];
            }
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Prepare the field for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'fieldLabels' => $this->fieldLabels,
            'perPage' => $this->perPage,
            'showPagination' => $this->showPagination,
            'alwaysExpanded' => $this->alwaysExpanded,
        ]);
    }

    /**
     * Get transformer for a field
     */
    protected function getTransformer(?string $entityType, string $field): ?\DeltaWhyDev\AuditLog\Transformers\BaseTransformer
    {
        if (! $entityType) {
            return null;
        }

        $transformers = config('audit-log.transformers', []);

        // Check for exact class match
        $modelTransformers = $transformers[$entityType] ?? [];
        $transformerClass = $modelTransformers[$field] ?? null;

        if ($transformerClass && class_exists($transformerClass)) {
            return new $transformerClass;
        }

        return null;
    }

    /**
     * Get human-readable display name for an entity
     */
    protected function getEntityDisplayName(string $entityType, string|int $entityId): string
    {
        try {
            if (! class_exists($entityType)) {
                return "#{$entityId}";
            }

            $entity = $entityType::find($entityId);

            if (! $entity) {
                return "#{$entityId} (deleted)";
            }

            // Try common name fields in order of preference
            $nameFields = ['name', 'title', 'label', 'code', 'reference', 'ref', 'email', 'identifier'];

            foreach ($nameFields as $field) {
                if (isset($entity->{$field}) && ! empty($entity->{$field})) {
                    $value = $entity->{$field};
                    // Truncate long names
                    if (strlen($value) > 30) {
                        return substr($value, 0, 27).'...';
                    }

                    return $value;
                }
            }

            // Fall back to short class name if no specific name field found
            return class_basename($entityType);
        } catch (\Throwable $e) {
            return ''; // Return empty string instead of ID to avoid "#123" if lookup fails
        }
    }
}
