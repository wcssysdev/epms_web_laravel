<?php

namespace App\Services\Fusion;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * On-demand PostgreSQL source connection for estate-db-fusion.
 *
 * The `pgsql_source` connection defined in config/database.php is a reusable
 * template whose `database` is blank. Each of the 12 legacy CI3 source estate
 * databases is opened on demand by pointing that template at a specific
 * database name, then purging and reconnecting so a fresh PDO handle is built.
 *
 * Usage:
 *   $pg = SourceConnection::for('sagil_mei');
 *   $rows = $pg->table('m_config')->get();
 *   SourceConnection::close();
 *
 * This helper intentionally does only the connect/close mechanics. The static
 * manifest of the 12 source databases (SourceRegistry) is a later task.
 */
class SourceConnection
{
    /**
     * The config connection name cloned from config/database.php.
     */
    public const CONNECTION = 'pgsql_source';

    /**
     * Open (or re-open) the source connection pointed at the given database.
     *
     * Sets the per-source database name on the template, then purges and
     * reconnects so the next query uses a fresh PDO handle for $dbName.
     */
    public static function for(string $dbName): Connection
    {
        config(['database.connections.' . self::CONNECTION . '.database' => $dbName]);

        // Drop any cached connection/PDO so the new database name takes effect.
        DB::purge(self::CONNECTION);
        DB::reconnect(self::CONNECTION);

        return DB::connection(self::CONNECTION);
    }

    /**
     * Close the source connection, releasing its PDO handle.
     *
     * Call after finishing with a source database so each DB is opened on
     * demand and closed after use.
     */
    public static function close(): void
    {
        DB::purge(self::CONNECTION);
    }
}
