<?php

namespace DeltaWhyDev\AuditLog\Providers;

use DeltaWhyDev\AuditLog\Nova\AuditLog;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class NovaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! config('audit-log.nova.enabled', true)) {
            return;
        }

        // Register Nova resources
        if (config('audit-log.nova.resource_enabled', true)) {
            Nova::resources([
                AuditLog::class,
            ]);

            // Theme the created-date range filter (flatpickr) to Nova's configured colors.
            Nova::serving(function (ServingNova $event) {
                Nova::style('deltawhy-audit-log', __DIR__.'/../../resources/css/audit-log.css');
            });
        }

        // Register Nova components
        if (config('audit-log.nova.changelog_field_enabled', true)) {
            Nova::serving(function (ServingNova $event) {

                $scriptPath = __DIR__.'/../NovaComponents/ChangelogField/dist/js/field.js';
                // Use filemtime in the script name (handle) to bust cache, but keep path clean
                Nova::script('changelog-field-'.filemtime($scriptPath), $scriptPath);

                $stylePath = __DIR__.'/../NovaComponents/ChangelogField/dist/dist/css/field.css';
                Nova::style('changelog-field-'.filemtime($stylePath), $stylePath);
            });
        }
    }
}
