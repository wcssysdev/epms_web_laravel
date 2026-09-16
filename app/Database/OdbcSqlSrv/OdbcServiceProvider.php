<?php

namespace App\Database\OdbcSqlSrv;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Connection;

class OdbcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register 'odbc_sqlsrv' driver with the DatabaseManager
        $this->app->resolving('db', function ($db) {
            $db->extend('odbc_sqlsrv', function ($config, $name) {
                $config['name'] = $name;

                $connector  = new OdbcConnector;
                $connection = $connector->connect($config);

                return new OdbcConnection(
                    $connection,
                    $config['database'] ?? '',
                    $config['prefix']   ?? '',
                    $config
                );
            });
        });
    }

    public function boot(): void {}
}
