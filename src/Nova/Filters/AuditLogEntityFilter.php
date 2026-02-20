<?php

namespace DeltaWhyDev\AuditLog\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;
use DeltaWhyDev\AuditLog\Models\AuditLog;

class AuditLogEntityFilter extends Filter
{
    public $component = 'select-filter';
    public $name = 'Entity Type';

    public function apply(Request $request, $query, $value)
    {
        return $query->where('entity_type', $value);
    }

    public function options(Request $request)
    {
        // Get all unique entity types currently in the audit log dynamically
        $entityTypes = AuditLog::select('entity_type')->distinct()->pluck('entity_type');
        
        return $entityTypes->mapWithKeys(function ($type) {
            return [class_basename($type) => $type];
        })->toArray();
    }
}
