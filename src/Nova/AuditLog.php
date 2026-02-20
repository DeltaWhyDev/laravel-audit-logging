<?php

namespace DeltaWhyDev\AuditLog\Nova;

use DeltaWhyDev\AuditLog\Enums\AuditAction;
use DeltaWhyDev\AuditLog\Models\AuditLog as AuditLogModel;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use DeltaWhyDev\AuditLog\NovaComponents\ChangelogField\ChangelogField;

class AuditLog extends Resource
{
    /**
     * The model the resource corresponds to.
     */
    public static $model = AuditLogModel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     */
    public static $title = 'summary';

    /**
     * The columns that should be searched.
     */
    /**
     * The columns that should be searched.
     *
     * @return array
     */
    public static function searchableColumns()
    {
        return config('audit-log.searchable_columns', []);
    }

    /**
     * Indicates if the resource should be displayed in the sidebar.
     */
    public static $displayInNavigation = true;

    /**
     * Get the displayable label of the resource.
     */
    public static function label(): string
    {
        return __('Audit Logs');
    }

    /**
     * Get the displayable singular label of the resource.
     */
    public static function singularLabel(): string
    {
        return __('Audit Log');
    }

    /**
     * Build an "index" query for the given resource.
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        $fields = [
            ID::make()
                ->hideFromDetail()
                ->sortable(),

            Text::make('Summary', function () {
                $actorName = \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getActorDisplayName($this->actor_type, $this->actor_id);
                $actorHtml = $actorName;
                if ($this->actor_type === 'user' && $this->actor_id) {
                    $userModel = \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getUserModel();
                    $actorUri = $this->getNovaResourceUri($userModel, $this->actor_id);
                    if ($actorUri) {
                        $actorHtml = sprintf('<a href="%s" class="link-default font-bold">%s</a>', $actorUri, e($actorName));
                    }
                }

                $entityName = class_basename($this->entity_type);
                $entityDisplayName = $this->getEntityDisplayName($this->entity_type, $this->entity_id);
                $entityHtml = sprintf('%s: %s', $entityName, e($entityDisplayName));
                
                $entityUri = $this->getNovaResourceUri($this->entity_type, $this->entity_id);
                if ($entityUri) {
                    $entityHtml = sprintf('<a href="%s" class="link-default font-bold">%s</a>', $entityUri, $entityHtml);
                }

                // Get action label and style
                $action = $this->action;
                $label = $action instanceof AuditAction ? $action->label() : 'Unknown';
                $actionValue = $action instanceof AuditAction ? $action->value : (int) $action;
                
                $actionHtml = sprintf(
                    '<span class="mx-1 text-gray-500 font-medium">%s</span>',
                    strtolower($label)
                );

                return sprintf('%s %s %s', $actorHtml, $actionHtml, $entityHtml);
            })
                ->asHtml()
                ->onlyOnIndex(),

            Text::make('Modified Fields', function () {
                if ($this->action->value !== \DeltaWhyDev\AuditLog\Enums\AuditAction::UPDATED->value) {
                    return '—';
                }

                $labels = config('audit-log.field_labels', []);
                $changedFields = [];
                
                if (!empty($this->attributes)) {
                    foreach (array_keys($this->attributes) as $key) {
                        $changedFields[] = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                    }
                }
                if (!empty($this->relations)) {
                    foreach (array_keys($this->relations) as $key) {
                        $changedFields[] = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                    }
                }

                if (empty($changedFields)) {
                    return '—';
                }

                $text = implode(', ', $changedFields);
                if (strlen($text) > 50) {
                    return substr($text, 0, 47) . '...';
                }
                return $text;
            })
                ->onlyOnIndex()
                ->sortable(false),

            DateTime::make('Created At')
                ->sortable()
                ->hideFromDetail()
                ->displayUsing(function ($value) {
                    return $value?->format('Y-m-d H:i:s');
                }),

            // Changes Table - Replaced with ChangelogField
            ChangelogField::make('Details')
                ->resolveUsing(function ($value, $resource) {
                    return [(new ChangelogField())->formatLog($resource)];
                })
                ->withoutPagination()
                ->alwaysExpanded()
                ->hideFromIndex()
                ->showOnDetail(),

            // Relation Changes - Included in ChangelogField above, but keeping config check if needed
            // The ChangelogField handles relations internally if they exist in the log data

            // Conditionally show Metadata based on config
            config('audit-log.nova.show_metadata', false)
                ? KeyValue::make('Metadata')
                    ->onlyOnDetail()
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                : null,

            // Conditionally show Raw JSON based on config
            config('audit-log.nova.show_raw_json', false)
                ? Textarea::make('Raw JSON', function () {
                    return json_encode([
                        'attributes' => $this->attributes,
                        'relations' => $this->relations,
                        'metadata' => $this->metadata,
                    ], JSON_PRETTY_PRINT);
                })
                    ->onlyOnDetail()
                    ->rows(10)
                : null,
        ];

        // Filter out null fields (disabled by config)
        return array_filter($fields);
    }

    /**
     * Format value for display
     */
    public function formatValue($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Format value for display in table (more user-friendly)
     */
    protected function formatDisplayValue($value): string
    {
        if (is_null($value)) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            // Truncate long arrays
            $json = json_encode($value);
            if (strlen($json) > 100) {
                return substr($json, 0, 97).'...';
            }

            return $json;
        }
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 97).'...';
        }

        return (string) $value;
    }

    /**
     * Get human-readable label for a field
     */
    protected function getFieldLabel(string $field): string
    {
        // Check config for custom labels
        $labels = config('audit-log.field_labels', []);
        if (isset($labels[$field])) {
            return $labels[$field];
        }

        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $field));
    }

    protected function getActorDisplayName(): string
    {
        return \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getActorDisplayName($this->actor_type, $this->actor_id);
    }

    /**
     * Get actor Nova resource URI
     */
    protected function getActorUri(): ?string
    {
        if ($this->actor_type === 'user' && $this->actor_id) {
            $userModel = \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getUserModel();

            return $this->getNovaResourceUri($userModel, $this->actor_id);
        }

        return null;
    }

    /**
     * Format value for diff display with styling
     */
    protected function formatDiffValue($value, bool $isOld): string
    {
        // Handle null/empty values
        if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
            return '<span class="text-slate-400 dark:text-slate-500 italic">None</span>';
        }

        // Format value based on type
        $displayValue = $this->formatDisplayValue($value);

        // Apply styling based on old vs new
        if ($isOld) {
            // Strikethrough for old values
            return sprintf('<span class="text-red-600 dark:text-red-400 line-through">%s</span>', e($displayValue));
        } else {
            // Bold green for new values
            return sprintf('<span class="text-green-600 dark:text-green-400 font-medium">%s</span>', e($displayValue));
        }
    }

    /**
     * Format value for simple display (used in 2-column change summary)
     */
    protected function formatSimpleValue($value): string
    {
        // Handle null/empty values
        if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
            return '<em class="text-slate-400 dark:text-slate-500">None</em>';
        }

        // Format and escape the value
        $displayValue = $this->formatDisplayValue($value);

        return e($displayValue);
    }

    /**
     * Format value for cell display with colored badge (used in 3-column diff)
     */
    protected function formatCellValue($value, string $type): string
    {
        // Handle null/empty values
        if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
            return '<span class="text-gray-400 dark:text-gray-500 italic">Not set</span>';
        }

        // Format the value
        $displayValue = $this->formatDisplayValue($value);

        // Apply styling based on old/new
        if ($type === 'old') {
            return sprintf(
                '<span class="bg-red-100 dark:bg-red-500/20 text-red-800 dark:text-red-400 px-2 py-0.5 rounded text-sm border border-transparent dark:border-red-500/30">%s</span>',
                e($displayValue)
            );
        } else {
            return sprintf(
                '<span class="bg-green-100 dark:bg-green-500/20 text-green-800 dark:text-green-400 px-2 py-0.5 rounded text-sm border border-transparent dark:border-green-500/30">%s</span>',
                e($displayValue)
            );
        }
    }

    /**
     * Get Nova resource URI for an entity
     */
    protected function getNovaResourceUri(string $entityType, int $entityId): ?string
    {
        return \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getNovaResourceUri($entityType, $entityId);
    }

    /**
     * Guess related model class from relation name
     */
    protected function guessRelatedModelClass(string $relation): ?string
    {
        return \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::guessRelatedModelClass($relation);
    }

    /**
     * Get human-readable display name for an entity
     */
    protected function getEntityDisplayName(string $entityType, int $entityId): string
    {
        return \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getEntityDisplayName($entityType, $entityId);
    }

    /**
     * Get the filters available for the resource.
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new \DeltaWhyDev\AuditLog\Nova\Filters\AuditLogActorFilter,
            new \DeltaWhyDev\AuditLog\Nova\Filters\AuditLogEntityFilter,
        ];
    }

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate($request): bool
    {
        return false; // Audit logs are read-only
    }

    /**
     * Determine if the current user can update the given resource.
     */
    public function authorizedToUpdate($request): bool
    {
        return false; // Audit logs are read-only
    }

    /**
     * Determine if the current user can delete the given resource.
     */
    public function authorizedToDelete($request): bool
    {
        return false; // Audit logs are read-only
    }

    /**
     * Get transformer for a field
     */
    protected function getTransformer(string $entityType, string $field): ?\DeltaWhyDev\AuditLog\Transformers\BaseTransformer
    {
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
     * Determine if the current user can replicate the given resource.
     */
    public function authorizedToReplicate($request): bool
    {
        return false; // Audit logs cannot be replicated
    }
}
