<?php

namespace DeltaWhyDev\AuditLog\Nova\Filters;

use Carbon\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Marshmallow\Filters\DateRangeFilter;

/**
 * Timestamp range filter for the Audit Log resource.
 *
 * Requires marshmallow/nova-date-range-filter (a hard dependency).
 */
class CreatedAtRangeFilter extends DateRangeFilter
{
    public function __construct()
    {
        $this->column = 'created_at';
        $this->name = __('Timestamp');
    }

    public function name()
    {
        return $this->name;
    }

    /**
     * The picked days are the viewer's days, as the Timestamp column shows them, not UTC days.
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        $storedTimezone = config('app.timezone');
        $viewerTimezone = Nova::resolveUserTimezone($request) ?? $storedTimezone;

        $from = Carbon::parse($value[0], $viewerTimezone)->startOfDay()->setTimezone($storedTimezone);
        $to = Carbon::parse($value[1] ?? $value[0], $viewerTimezone)->endOfDay()->setTimezone($storedTimezone);

        return $query->whereBetween($this->column, [$from, $to]);
    }
}
