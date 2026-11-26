<?php

namespace DeltaWhyDev\AuditLog\Nova\Filters;

use DeltaWhyDev\AuditLog\Models\AuditLog;
use DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Filters\Filter;

class AuditLogEntityFilter extends Filter
{
    public $component = 'select-filter';
    public $name = 'Entity Type';

    public function apply(Request $request, $query, $value)
    {
        return $query->where('entity_type', $value);
    }

    /**
     * One option per model: a model stored both as an alias and as a class name keeps one
     * value, as before. With `audit-log.entity_type_groups` set, each option carries its group
     * (a non-selectable optgroup) and the list runs in the configured group order, A-Z inside.
     */
    public function options(Request $request)
    {
        $groups = array_keys(config('audit-log.entity_type_groups', []));

        $options = AuditLog::select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type')
            ->mapWithKeys(function ($type) {
                $class = ResourceResolver::resolveEntityClass($type);

                return [$class => ['label' => self::label($class), 'value' => $type, 'group' => self::group($class)]];
            });

        if ($groups === []) {
            return $options->sortBy('label')->map(fn ($option) => array_diff_key($option, ['group' => true]))->values()->toArray();
        }

        return $options
            ->sortBy(function ($option) use ($groups) {
                $groupIndex = array_search($option['group'], $groups, true);

                return sprintf('%04d %s', $groupIndex === false ? count($groups) : $groupIndex, strtolower($option['label']));
            })
            ->values()
            ->toArray();
    }

    public static function label(string $class): string
    {
        $label = config('audit-log.entity_type_labels', [])[$class] ?? null;

        if ($label !== null) {
            return $label;
        }

        $label = Str::headline(class_basename($class));

        foreach (config('audit-log.entity_type_label_terms', []) as $term => $replacement) {
            $label = preg_replace('/\b'.preg_quote($term, '/').'\b/', $replacement, $label);
        }

        return $label;
    }

    public static function group(string $class): string
    {
        foreach (config('audit-log.entity_type_groups', []) as $group => $patterns) {
            if (Str::is($patterns, $class)) {
                return $group;
            }
        }

        return __('Other');
    }
}
