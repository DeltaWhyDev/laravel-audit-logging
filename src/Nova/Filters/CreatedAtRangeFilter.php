<?php

namespace DeltaWhyDev\AuditLog\Nova\Filters;

use Marshmallow\Filters\DateRangeFilter;

/**
 * Created-date range filter for the Audit Log resource.
 *
 * Requires marshmallow/nova-date-range-filter (a suggested dependency). The
 * resource only adds this filter when that package is installed.
 */
class CreatedAtRangeFilter extends DateRangeFilter
{
    public function __construct()
    {
        $this->column = 'created_at';
        $this->name = __('Created date');
    }

    public function name()
    {
        return $this->name;
    }
}
