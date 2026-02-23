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
     * Get the configured User model class name.
     */
    public static function getUserModel(): string
    {
        return config('audit-log.actor.user_model') ?? config('auth.providers.users.model') ?? 'App\\Models\\User';
    }

    /**
     * Get human-readable display name for an actor.
     */
    public static function getActorDisplayName(string $actorType, string|int|null $actorId): string
    {
        if (! $actorId) {
            return ucfirst($actorType ?: 'System');
        }

        $cacheKey = $actorType.'_'.$actorId;
        if (isset(self::$resolvedActors[$cacheKey])) {
            return self::$resolvedActors[$cacheKey];
        }

        $userModel = self::getUserModel();

        // Normalize class names for comparison (remove leading backslashes)
        $normalizedActorType = ltrim($actorType, '\\');
        $normalizedUserModel = ltrim($userModel, '\\');

        // Check if actor is a user (matches configured model, auth model, or common aliases)
        $isUser = $normalizedActorType === 'user' ||
                  $normalizedActorType === $normalizedUserModel ||
                  $normalizedActorType === 'App\Models\User' ||
                  $normalizedActorType === 'App\Models\User\User' ||
                  str_ends_with($normalizedActorType, '\User'); // Catch-all for any namespace ending in User

        if ($isUser && $actorId) {
            // Try to resolve using the configured model first
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

            // If the configured model didn't work (e.g. actor_type is different), try using the actor_type directly if it's a valid class
            if ($normalizedActorType !== 'user' && class_exists($actorType) && $actorType !== $userModel) {
                try {
                    $query = $actorType::query();
                    if (method_exists($actorType, 'withTrashed')) {
                        $query->withTrashed();
                    }
                    $user = $query->find($actorId);

                    if ($user) {
                        $nameFields = ['name', 'fullname', 'full_name', 'username', 'email'];
                        foreach ($nameFields as $field) {
                            if (! empty($user->$field)) {
                                return self::$resolvedActors[$cacheKey] = $user->$field;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Fall through
                }
            }

            return self::$resolvedActors[$cacheKey] = "User #{$actorId}";
        }

        return self::$resolvedActors[$cacheKey] = class_exists($actorType)
            ? class_basename($actorType)." #{$actorId}"
            : ucfirst($actorType ?: 'System');
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
            if (! class_exists($entityType)) {
                return self::$resolvedEntities[$cacheKey] = "#{$entityId}";
            }

            $query = $entityType::query();
            if (method_exists($entityType, 'withTrashed')) {
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
        // 1. Try common mapping patterns based on config
        $modelBasename = class_basename($entityType);
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
                        if ($resourceModel === $entityType) {
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
