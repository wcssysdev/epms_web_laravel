<?php

namespace App\Providers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * estate-db-fusion stage services whose injected ConnectionInterface $target
     * must be the fusion TARGET (odbc_sqlsrv by default), reroutable to the
     * disposable staging target by `fusion:run --dry-run` (R15.1, task 11.2).
     *
     * Without this, Laravel autowires the generic ConnectionInterface binding
     * (the DEFAULT connection) into these constructors, which would both ignore
     * config('fusion.target_connection') AND silently miss the dry-run reroute.
     */
    private const FUSION_TARGET_CONSUMERS = [
        \App\Services\Fusion\TenantRoleSeeder::class,
        \App\Services\Fusion\RbacMigrator::class,
        \App\Services\Fusion\MasterCopier::class,
        \App\Services\Fusion\TransactionCopier::class,
        \App\Services\Fusion\Reconciler::class,
        \App\Services\Fusion\SafetyManager::class,
    ];

    public function register(): void
    {
        // Resolve each fusion stage service's ConnectionInterface $target from
        // config('fusion.target_connection') AT RESOLUTION TIME. The closure is
        // evaluated every time a stage is built, so the dry-run override that
        // `fusion:run --dry-run` applies BEFORE invoking the stages reroutes the
        // whole pipeline to the staging target — while the default stays
        // odbc_sqlsrv for a normal run (non-dry-run behaviour unchanged).
        foreach (self::FUSION_TARGET_CONSUMERS as $service) {
            $this->app->when($service)
                ->needs(ConnectionInterface::class)
                ->give(static fn () => DB::connection(
                    config('fusion.target_connection', 'odbc_sqlsrv')
                ));
        }
    }

    public function boot(): void
    {
        // Force HTTPS in production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
