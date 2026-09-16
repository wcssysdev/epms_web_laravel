<?php

use App\Providers\AppServiceProvider;
use App\Database\OdbcSqlSrv\OdbcServiceProvider;

return [
    AppServiceProvider::class,
    OdbcServiceProvider::class,
];
