<?php

namespace DeltaWhyDev\AuditLog\Providers;

use Illuminate\Support\ServiceProvider;

class AuditLogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/audit-log.php',
            'audit-log'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../../config/audit-log.php' => config_path('audit-log.php'),
        ], 'audit-log-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'audit-log-migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Register Nova service provider if Nova is available
        if (class_exists(\Laravel\Nova\Nova::class) && config('audit-log.nova.enabled', true)) {
            $this->app->register(NovaServiceProvider::class);
        }
    }
}
