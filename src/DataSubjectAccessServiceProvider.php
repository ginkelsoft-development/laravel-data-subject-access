<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess;

use Ginkelsoft\DataSubjectAccess\Concerns\Exportable;
use Ginkelsoft\DataSubjectAccess\Console\ExportSubjectCommand;
use Ginkelsoft\DataSubjectAccess\Contracts\Exportable as ExportableContract;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Laravel Data Subject Access package.
 *
 * Responsibilities:
 * - Merge and publish the package configuration (config/subject-access.php).
 * - Publish and load the `subject_access_log` migration.
 * - Register the `retention:export` Artisan command.
 *
 * Typical installation:
 *
 *   composer require ginkelsoft/laravel-data-subject-access
 *   php artisan vendor:publish --tag=subject-access-config
 *   php artisan vendor:publish --tag=subject-access-migrations
 *   php artisan migrate
 *
 * After installation, any model that uses {@see Exportable} and
 * implements {@see ExportableContract} can declare an explicit field
 * list and be included in `retention:export {subject}`.
 *
 * The command name keeps the `retention:` prefix for backwards
 * compatibility with the monolithic v1.x `ginkelsoft/laravel-data-retention`
 * package.
 */
class DataSubjectAccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/subject-access.php',
            'subject-access'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/subject-access.php' => config_path('subject-access.php'),
        ], 'subject-access-config');

        $timestamp = date('Y_m_d_His');
        $this->publishes([
            __DIR__.'/../database/migrations/create_subject_access_log_table.php' => database_path("migrations/{$timestamp}_create_subject_access_log_table.php"),
        ], 'subject-access-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportSubjectCommand::class,
            ]);
        }
    }
}
