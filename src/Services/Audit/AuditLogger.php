<?php

namespace DeltaWhyDev\AuditLog\Services\Audit;

use DeltaWhyDev\AuditLog\Models\AuditLog;
use DeltaWhyDev\AuditLog\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Create a new audit log entry for an entity
     */
    public static function for(Model $entity): AuditLogBuilder
    {
        return new AuditLogBuilder($entity);
    }
    
    /**
     * Calculate diff between old and new attributes
     */
    public static function calculateDiff(array $oldAttributes, array $newAttributes, array $excludeAttributes = [], array $sensitiveFields = []): array
    {
        $diff = [];
        
        // Check for changed attributes
        foreach ($newAttributes as $key => $newValue) {
            $oldValue = $oldAttributes[$key] ?? null;
            
            // Skip excluded attributes and timestamps
            if (in_array($key, $excludeAttributes) || in_array($key, ['created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }
            
            // Use smart comparison that handles type differences
            if (!self::valuesAreEqual($oldValue, $newValue)) {
                // Mask sensitive fields
                $isSensitive = self::isSensitiveField($key, $sensitiveFields);
                
                // Format values (boolean, enum, date will be formatted later in formatValue)
                $oldFormatted = $isSensitive ? self::maskValue($oldValue) : $oldValue;
                $newFormatted = $isSensitive ? self::maskValue($newValue) : $newValue;
                
                $diff[$key] = [
                    'old' => $oldFormatted,
                    'new' => $newFormatted,
                ];
            }
        }
        
        // Check for removed attributes
        foreach ($oldAttributes as $key => $oldValue) {
            if (!array_key_exists($key, $newAttributes) && !in_array($key, $excludeAttributes)) {
                $isSensitive = self::isSensitiveField($key, $sensitiveFields);
                
                $diff[$key] = [
                    'old' => $isSensitive ? self::maskValue($oldValue) : $oldValue,
                    'new' => null,
                ];
            }
        }
        
        return $diff;
    }
    
    /**
     * Compare two values smartly, handling type differences
     */
    public static function valuesAreEqual($oldValue, $newValue): bool
    {
        // Both null
        if (is_null($oldValue) && is_null($newValue)) {
            return true;
        }
        
        // Both empty (including empty array, empty string, null)
        if (empty($oldValue) && empty($newValue)) {
            return true;
        }
        
        // Normalize arrays to JSON for comparison
        if (is_array($oldValue)) {
            $oldValue = json_encode($oldValue);
        }
        if (is_array($newValue)) {
            $newValue = json_encode($newValue);
        }
        
        // Both are strings now (or one is null)
        if (is_string($oldValue) && is_string($newValue)) {
            // Remove any double-encoding
            $oldValue = trim($oldValue);
            $newValue = trim($newValue);
            
            // Compare as strings
            return $oldValue === $newValue;
        }
        
        // For booleans, compare as boolean
        if (is_bool($oldValue) || is_bool($newValue)) {
            return (bool) $oldValue === (bool) $newValue;
        }
        
        // For numeric, compare numerically
        if (is_numeric($oldValue) && is_numeric($newValue)) {
            return (float) $oldValue === (float) $newValue;
        }
        
        // Fall back to loose comparison
        return $oldValue == $newValue;
    }
    
    /**
     * Check if a field is sensitive
     */
    public static function isSensitiveField(string $field, array $customSensitiveFields = []): bool
    {
        // Merge custom sensitive fields with default ones
        $sensitiveFields = array_merge(
            config('audit-log.sensitive_fields', []),
            $customSensitiveFields
        );
        
        // Check exact match
        if (in_array($field, $sensitiveFields)) {
            return true;
        }
        
        // Check pattern match (e.g., *password*, *secret*)
        foreach ($sensitiveFields as $pattern) {
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace(['*', '.'], ['.*', '\.'], $pattern) . '$/i';
                if (preg_match($regex, $field)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Mask a sensitive value
     */
    public static function maskValue($value): string
    {
        if (is_null($value) || $value === '') {
            return '[REDACTED]';
        }
        
        $value = (string) $value;
        $length = strlen($value);
        
        if ($length === 0) {
            return '[REDACTED]';
        }
        
        // Show first 2 and last 2 characters, mask the rest
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        $visible = 2;
        $masked = str_repeat('*', max(4, $length - ($visible * 2)));
        
        return substr($value, 0, $visible) . $masked . substr($value, -$visible);
    }
    
    /**
     * Resolve actor from current context
     */
    public static function resolveActor(): array
    {
        $user = Auth::user();
        
        if ($user) {
            return [
                'type' => 'user',
                'id' => $user->id,
            ];
        }
        
        // Check if running in a job
        if (app()->runningInConsole()) {
            return [
                'type' => 'system',
                'id' => null,
            ];
        }
        
        return [
            'type' => 'system',
            'id' => null,
        ];
    }
    
    /**
     * Get current request ID or generate one
     */
    public static function getRequestId(): ?string
    {
        if (!app()->runningInConsole() && request()) {
            return request()->header('X-Request-ID') 
                ?? request()->header('X-Correlation-ID')
                ?? null;
        }
        
        return null;
    }
    
    /**
     * Detect source of the request
     */
    public static function detectSource(): string
    {
        if (app()->runningInConsole()) {
            return 'console';
        }
        
        if (!request()) {
            return 'system';
        }
        
        $path = request()->path();
        
        if (str_starts_with($path, 'api/')) {
            return 'api';
        }
        
        if (str_starts_with($path, 'nova-api/') || str_starts_with($path, 'nova/')) {
            return 'admin_panel';
        }
        
        return 'web';
    }
    
    /**
     * Format boolean value with icon support (Nova-style)
     */
    public static function formatBoolean($value, ?string $fieldName = null): array
    {
        $isTrue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $config = config('audit-log.boolean_display', []);
        $style = $config['style'] ?? 'icon';
        
        if ($style === 'icon') {
            return [
                'value' => $isTrue,
                'display' => $isTrue 
                    ? ($config['true_icon'] ?? '✓') 
                    : ($config['false_icon'] ?? '✗'),
                'color' => $isTrue 
                    ? ($config['true_color'] ?? 'green') 
                    : ($config['false_color'] ?? 'red'),
                'type' => 'boolean',
            ];
        }
        
        return [
            'value' => $isTrue,
            'display' => $isTrue 
                ? ($config['true_label'] ?? 'Yes') 
                : ($config['false_label'] ?? 'No'),
            'type' => 'boolean',
        ];
    }
    
    /**
     * Format enum value
     */
    public static function formatEnum($value, ?string $fieldName, Model $model): array|string
    {
        if (is_null($value)) {
            return 'null';
        }
        
        // Get enum class from model casts
        $enumClass = self::getEnumClass($fieldName, $model);
        
        if (!$enumClass) {
            return (string) $value;
        }
        
        try {
            // Handle enum instance
            if ($value instanceof \BackedEnum) {
                $enum = $value;
            } else {
                // Try to create enum from value
                $enum = $enumClass::from($value);
            }
            
            // Get display method
            $config = config('audit-log.enum_display', []);
            $method = $config['method'] ?? 'text';
            
            // Try common methods
            $displayValue = null;
            if (method_exists($enum, $method)) {
                $displayValue = $enum->$method();
            } elseif (method_exists($enum, 'text')) {
                $displayValue = $enum->text();
            } elseif (method_exists($enum, 'label')) {
                $displayValue = $enum->label();
            } elseif (method_exists($enum, 'name')) {
                $displayValue = $enum->name;
            } else {
                $displayValue = $enum->name;
            }
            
            $result = [
                'value' => $enum->value ?? $enum->name,
                'display' => $displayValue,
                'type' => 'enum',
                'enum_class' => $enumClass,
            ];
            
            // Show value if configured
            if ($config['show_value'] ?? false) {
                $result['show_value'] = true;
            }
            
            return $result;
        } catch (\ValueError $e) {
            // Invalid enum value, return as string
            return (string) $value;
        }
    }
    
    /**
     * Get enum class for field
     */
    protected static function getEnumClass(string $fieldName, Model $model): ?string
    {
        $casts = $model->getCasts();
        if (isset($casts[$fieldName]) && enum_exists($casts[$fieldName])) {
            return $casts[$fieldName];
        }
        
        // Check getEnumArray method (common pattern in this codebase)
        if (method_exists($model, 'getEnumArray')) {
            $enumArray = $model->getEnumArray();
            return $enumArray[$fieldName] ?? null;
        }
        
        return null;
    }
    
    /**
     * Check if field is a boolean field
     */
    public static function isBooleanField(string $fieldName, Model $model): bool
    {
        $casts = $model->getCasts();
        if (isset($casts[$fieldName])) {
            return $casts[$fieldName] === 'bool' || $casts[$fieldName] === 'boolean';
        }
        
        // Check common boolean field names
        return str_starts_with($fieldName, 'is_') ||
               str_starts_with($fieldName, 'has_') ||
               str_ends_with($fieldName, '_enabled') ||
               in_array($fieldName, ['completed', 'cleared', 'active', 'enabled', 'verified']);
    }
    
    /**
     * Check if field is an enum field
     */
    public static function isEnumField(string $fieldName, Model $model): bool
    {
        $casts = $model->getCasts();
        if (!isset($casts[$fieldName])) {
            // Check getEnumArray method
            if (method_exists($model, 'getEnumArray')) {
                $enumArray = $model->getEnumArray();
                return isset($enumArray[$fieldName]);
            }
            return false;
        }
        
        $cast = $casts[$fieldName];
        
        // Check if it's an enum class
        if (enum_exists($cast)) {
            return true;
        }
        
        // Check if model has getEnumArray method
        if (method_exists($model, 'getEnumArray')) {
            $enumArray = $model->getEnumArray();
            return isset($enumArray[$fieldName]);
        }
        
        return false;
    }
    
    /**
     * Check if field is a date field
     */
    public static function isDateField(string $fieldName, Model $model): bool
    {
        $casts = $model->getCasts();
        if (isset($casts[$fieldName])) {
            $cast = $casts[$fieldName];
            return in_array($cast, ['date', 'datetime', 'timestamp']);
        }
        
        // Check common date field names
        return str_ends_with($fieldName, '_at') || 
               str_ends_with($fieldName, '_date') ||
               in_array($fieldName, ['created_at', 'updated_at', 'deleted_at']);
    }
}
