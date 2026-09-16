<?php

namespace App\Database\OdbcSqlSrv\DataTables\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait FixesSqlSrvOrderInCount
{
    /**
     * Check if builder query uses complex sql.
     * Overridden for SQL Server: do NOT treat simple "order by" as complex query.
     * In SQL Server, wrapping ORDER BY inside a derived table (SELECT ... FROM (SELECT ... ORDER BY ...) t)
     * without TOP or OFFSET causes Error 1033.
     *
     * @param  QueryBuilder|EloquentBuilder  $query
     */
    protected function isComplexQuery($query): bool
    {
        return Str::contains(
            Str::lower($query->toSql()),
            ['union', 'having', 'distinct', 'group by']
        );
    }

    /**
     * Prepare count query builder.
     * Overridden to strip ORDER BY clauses so SQL Server doesn't fail with Error 1033.
     * Ordering has zero effect on count(*) results.
     */
    public function prepareCountQuery(): QueryBuilder
    {
        $builder = clone $this->query;

        // Clear order by on count queries - ordering does not affect count and causes error 1033 on MSSQL
        $this->clearQueryOrders($builder);

        if ($this->isComplexQuery($builder)) {
            $builder->select(DB::raw('1 as dt_row_count'));
            $clone = $builder->clone();
            $clone->setBindings([]);
            if ($clone instanceof EloquentBuilder) {
                $clone->getQuery()->wheres = [];
            } else {
                $clone->wheres = [];
            }

            if ($this->isComplexQuery($clone)) {
                if (! $this->ignoreSelectInCountQuery) {
                    $builder = clone $this->query;
                    $this->clearQueryOrders($builder);
                }

                return $this->getConnection()
                    ->query()
                    ->fromRaw('(' . $builder->toSql() . ') count_row_table')
                    ->setBindings($builder->getBindings());
            }
        }

        $row_count = $this->wrap('row_count');
        $builder->select($this->getConnection()->raw("'1' as {$row_count}"));

        if (! $this->keepSelectBindings) {
            $builder->setBindings([], 'select');
        }

        return $builder;
    }

    /**
     * Clear orders from query builder.
     */
    protected function clearQueryOrders(mixed $builder): void
    {
        if ($builder instanceof EloquentBuilder) {
            $builder->getQuery()->orders = null;
        } elseif ($builder instanceof QueryBuilder || (is_object($builder) && property_exists($builder, 'orders'))) {
            $builder->orders = null;
        }
    }
}