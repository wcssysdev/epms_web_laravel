<?php

namespace App\Database\OdbcSqlSrv\DataTables;

use App\Database\OdbcSqlSrv\DataTables\Concerns\FixesSqlSrvOrderInCount;
use Yajra\DataTables\EloquentDataTable;

class SqlSrvEloquentDataTable extends EloquentDataTable
{
    use FixesSqlSrvOrderInCount;
}