<?php

namespace DeltaWhyDev\AuditLog\Services\Audit;

use Illuminate\Support\Str;
use Laravel\Nova\Nova;
use Laravel\Nova\Resource;

class ResourceResolver
{
    /**
     * Cache for resolved actors to prevent N+1 queries
     */
    protected static array $resolvedActors = [];

    /**
     * Cache for resolved entities to prevent N+1 queries
     */
    protected static array $resolvedEntities = [];

    /**
     * Resolve a Model to a short entity_type alias for storage.
     * Checks the package entity_type_map first, then falls back to getMorphClass().
     */
    public static function resolveEntityType(\Illuminate\Database\Eloquent\Model $entity): string
    {
        $map = config('audit-log.entity_type_map', []);

        if (! empty($map)) {
            $fqcn = get_class($entity);

            if (isset($map[$fqcn])) {
                return $map[$fqcn];
            }
        }

        return $entity->getMorphClass();
    }

    /**
     * Resolve an entity_type alias back to a FQCN.
     * Checks the package entity_type_map first, then Laravel's global morphMap,
     * then treats the value as a FQCN directly.
     */
    public static function resolveEntityClass(string $entityType): string
    {
        // 1. Check package entity_type_map (reversed: alias => FQCN)
        $map = config('audit-log.entity_type_map', []);
        $flipped = array_flip($map);

        if (isset($flipped[$entityType])) {
            return $flipped[$entityType];
        }

        // 2. Check Laravel's global morphMap
        $morphed = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($entityType);

        if ($morphed) {
            return $morphed;
        }

        // 3. Treat as FQCN
        return $entityType;
    }

    /**
     * Get the configured User model class name.
     */
    public static function getUserModel(): string
    {
        return config('audit-log.actor.user_model') ?? config('auth.providers.users.model') ?? 'App\\Models\\User';
    }

    /**
     * Get human-readable display name for an actor.
     */
    public static function getActorDisplayName(string|int|null $actorId): string
    {
        if (! $actorId) {
            return 'System';
        }

        $cacheKey = 'user_'.$actorId;
        if (isset(self::$resolvedActors[$cacheKey])) {
            return self::$resolvedActors[$cacheKey];
        }

        $userModel = self::getUserModel();

        if (class_exists($userModel)) {
            $query = $userModel::query();
            if (method_exists($userModel, 'withTrashed')) {
                $query->withTrashed();
            }

            $user = $query->find($actorId);

            if ($user) {
                // Try to find a display name from common fields
                $nameFields = ['name', 'fullname', 'full_name', 'username', 'email'];
                foreach ($nameFields as $field) {
                    if (! empty($user->$field)) {
                        return self::$resolvedActors[$cacheKey] = $user->$field;
                    }
                }

                return self::$resolvedActors[$cacheKey] = "User #{$actorId}";
            }
        }

        return self::$resolvedActors[$cacheKey] = "User #{$actorId}";
    }

    /**
     * Get human-readable display name for an entity.
     */
    public static function getEntityDisplayName(string $entityType, string|int $entityId): string
    {
        $cacheKey = $entityType.'_'.$entityId;
        if (isset(self::$resolvedEntities[$cacheKey])) {
            return self::$resolvedEntities[$cacheKey];
        }

        try {
            $entityClass = self::resolveEntityClass($entityType);
            if (! class_exists($entityClass)) {
                return self::$resolvedEntities[$cacheKey] = "#{$entityId}";
            }

            $query = $entityClass::query();
            if (method_exists($entityClass, 'withTrashed')) {
                $query->withTrashed();
            }

            $entity = $query->find($entityId);

            if (! $entity) {
                return self::$resolvedEntities[$cacheKey] = "#{$entityId} (deleted)";
            }

            // Try common name fields in order of preference
            $nameFields = ['name', 'title', 'label', 'code', 'reference', 'ref', 'email', 'identifier', 'fullname', 'full_name', 'username'];

            foreach ($nameFields as $field) {
                if (isset($entity->{$field}) && ! empty($entity->{$field})) {
                    $value = $entity->{$field};
                    // Truncate long names
                    if (is_string($value) && strlen($value) > 50) {
                        return self::$resolvedEntities[$cacheKey] = substr($value, 0, 47).'...';
                    }

                    return self::$resolvedEntities[$cacheKey] = (string) $value;
                }
            }

            // Fall back to ID
            return self::$resolvedEntities[$cacheKey] = "#{$entityId}";
        } catch (\Throwable $e) {
            return self::$resolvedEntities[$cacheKey] = "#{$entityId}";
        }
    }

    /**
     * Get Nova resource URI for an entity.
     */
    public static function getNovaResourceUri(string $entityType, string|int $entityId): ?string
    {
        $entityClass = self::resolveEntityClass($entityType);

        // 1. Try common mapping patterns based on config
        $modelBasename = class_basename($entityClass);
        $novaNamespace = config('audit-log.nova.namespace', 'App\\Nova');

        $possibleResourceClass = $novaNamespace.'\\'.$modelBasename;
        if (class_exists($possibleResourceClass) && is_subclass_of($possibleResourceClass, Resource::class)) {
            return '/nova/resources/'.$possibleResourceClass::uriKey().'/'.$entityId;
        }

        // 2. Try to find by checking registered resources (slower but more accurate)
        if (class_exists(Nova::class)) {
            try {
                foreach (Nova::$resources as $resource) {
                    if (is_subclass_of($resource, Resource::class)) {
                        $resourceModel = $resource::$model ?? null;
                        if ($resourceModel === $entityClass || $resourceModel === $entityType) {
                            return '/nova/resources/'.$resource::uriKey().'/'.$entityId;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silently fail
            }
        }

        return null;
    }

    /**
     * Guess related model class from relation name.
     */
    public static function guessRelatedModelClass(string $relation): ?string
    {
        $singular = Str::singular($relation);
        $studly = Str::studly($singular);
        $modelNamespace = config('audit-log.model_namespace', 'App\\Models');

        // Configurable candidates
        $candidates = [
            "{$modelNamespace}\\{$studly}",
            "{$modelNamespace}\\{$studly}\\{$studly}", // Nested folder structure support
            "{$modelNamespace}\\User\\{$studly}",      // Project specific legacy support
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
