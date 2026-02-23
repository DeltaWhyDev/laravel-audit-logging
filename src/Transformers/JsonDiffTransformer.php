<?php

namespace DeltaWhyDev\AuditLog\Transformers;

class JsonDiffTransformer implements BaseTransformer
{
    public function transform($value, string $type, array $context = []): string
    {
        if (is_null($value)) {
            return '<span class="text-gray-400 italic">null</span>';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return $this->formatValue($value);
        }

        return '<pre class="text-xs bg-gray-50 dark:bg-gray-800 p-2 rounded overflow-x-auto">' . 
               json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . 
               '</pre>';
    }

    public function transformDiff($old, $new, array $context = []): array|string|null
    {
        $oldArray = $this->normalizeArray($old);
        $newArray = $this->normalizeArray($new);

        if (empty($oldArray) && empty($newArray)) {
            return null;
        }

        $diff = $this->recursiveDiff($oldArray, $newArray);
        
        if (empty($diff)) {
            return null;
        }

        // Flatten the diff into a list of changes
        return $this->flattenDiff($diff);
    }

    protected function flattenDiff(array $diff, string $prefix = ''): array
    {
        $changes = [];

        foreach ($diff as $key => $change) {
            $currentLabel = $prefix ? "{$prefix}.{$key}" : $key;
            $status = $change['status'];

            if ($status === 'added') {
                $changes[] = [
                    'label' => $this->formatLabel($currentLabel),
                    'old' => null,
                    'new' => $this->formatValue($change['new']),
                ];
            } elseif ($status === 'removed') {
                $changes[] = [
                    'label' => $this->formatLabel($currentLabel),
                    'old' => $this->formatValue($change['old']),
                    'new' => null,
                ];
            } elseif ($status === 'modified') {
                $changes[] = [
                    'label' => $this->formatLabel($currentLabel),
                    'old' => $this->formatValue($change['old']),
                    'new' => $this->formatValue($change['new']),
                ];
            } elseif ($status === 'modified_nested') {
                $nested = $this->flattenDiff($change['diff'], $currentLabel);
                $changes = array_merge($changes, $nested);
            }
        }

        return $changes;
    }
    
    protected function formatLabel(string $key): string
    {
        return $key;
    }

    protected function normalizeArray($value): array
    {
        if (is_null($value)) return [];
        if (is_array($value)) return $value;
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function recursiveDiff(array $old, array $new): array
    {
        $diff = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($allKeys);

        foreach ($allKeys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            // Key didn't exist in old -> Added
            if (!array_key_exists($key, $old)) {
                $diff[$key] = ['status' => 'added', 'new' => $newValue];
                continue;
            }

            // Key doesn't exist in new -> Removed
            if (!array_key_exists($key, $new)) {
                $diff[$key] = ['status' => 'removed', 'old' => $oldValue];
                continue;
            }

            // Both exist, check for nested arrays
            if (is_array($oldValue) && is_array($newValue)) {
                $nestedDiff = $this->recursiveDiff($oldValue, $newValue);
                if (!empty($nestedDiff)) {
                    $diff[$key] = ['status' => 'modified_nested', 'diff' => $nestedDiff];
                }
                continue;
            }

            // Simple comparison
            if ($oldValue !== $newValue) {
                $diff[$key] = ['status' => 'modified', 'old' => $oldValue, 'new' => $newValue];
            }
        }

        return $diff;
    }

    protected function formatValue($value): string
    {
        if (is_null($value)) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        
        if (is_array($value)) {
            // If it's a simple flat array, render it compactly
            if (array_keys($value) === range(0, count($value) - 1)) {
                $items = array_map(fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $value);
                return e('[' . implode(', ', $items) . ']');
            }
            
            // Render associative arrays as beautiful JSON
            return '<pre class="text-xs text-gray-700 dark:text-gray-300 mt-1">' . 
                    e(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . 
                   '</pre>';
        }

        if (is_string($value) && strlen($value) > 200) return e(substr($value, 0, 197) . '...');
        
        return e((string)$value);
    }
}
