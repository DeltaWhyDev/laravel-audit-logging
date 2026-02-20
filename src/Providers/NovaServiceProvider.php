<?php

namespace DeltaWhyDev\AuditLog\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;
use Laravel\Nova\Events\ServingNova;

class NovaServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!config('audit-log.nova.enabled', true)) {
            return;
        }

        // Register Nova resources
        if (config('audit-log.nova.resource_enabled', true)) {
            Nova::resources([
                \DeltaWhyDev\AuditLog\Nova\AuditLog::class,
            ]);
        }

        // Register Nova components
        if (config('audit-log.nova.changelog_field_enabled', true)) {
            Nova::serving(function (ServingNova $event) {

                $scriptPath = __DIR__ . '/../NovaComponents/ChangelogField/dist/js/field.js';
                // Use filemtime in the script name (handle) to bust cache, but keep path clean
                Nova::script('changelog-field-' . filemtime($scriptPath), $scriptPath);
            });
        }
    }
}
