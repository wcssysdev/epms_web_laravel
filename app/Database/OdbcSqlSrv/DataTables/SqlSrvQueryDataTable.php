<?php

namespace App\Database\OdbcSqlSrv\DataTables;

use App\Database\OdbcSqlSrv\DataTables\Concerns\FixesSqlSrvOrderInCount;
use Yajra\DataTables\QueryDataTable;

class SqlSrvQueryDataTable extends QueryDataTable
{
    use FixesSqlSrvOrderInCount;
}