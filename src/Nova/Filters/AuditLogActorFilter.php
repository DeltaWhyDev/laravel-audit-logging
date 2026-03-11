<?php

namespace DeltaWhyDev\AuditLog\Nova\Filters;

use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;
use DeltaWhyDev\AuditLog\Models\AuditLog;

class AuditLogActorFilter extends Filter
{
    public $component = 'select-filter';
    public $name = 'Actor (User)';

    public function apply(Request $request, $query, $value)
    {
        $userModel = \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getUserModel();
        
        return $query->where('actor_id', $value);
    }

    public function options(Request $request)
    {
        $userModel = \DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getUserModel();
        
        // Get all unique actors who have performed an action
        $actorIds = AuditLog::whereNotNull('actor_id')
                            ->distinct()
                            ->pluck('actor_id');
        
        if ($actorIds->isEmpty()) {
            return [];
        }
        
        // Use withTrashed() so soft-deleted users still appear in the filter
        $query = class_exists($userModel) ? $userModel::query() : null;
        if ($query) {
            if (method_exists($userModel, 'withTrashed')) {
                $query->withTrashed();
            }
            
            return $query->whereIn('id', $actorIds)
                ->get()
                ->mapWithKeys(function ($user) {
                    return [\DeltaWhyDev\AuditLog\Services\Audit\ResourceResolver::getActorDisplayName($user->id) => $user->id];
                })
                ->toArray();
        }
        
        return [];
    }
}
