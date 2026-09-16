<?php

namespace App\Database\OdbcSqlSrv;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class OdbcConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection via PDO ODBC (SQL Server).
     */
    public function connect(array $config)
    {
        $dsn     = $this->getDsn($config);
        $options = $this->getOptions($config);
        return $this->createConnection($dsn, $config, $options);
    }

    /**
     * Build the ODBC DSN.
     *
     * Named instances (host contains backslash) must NOT have a port —
     * SQL Server Browser resolves the dynamic port automatically.
     */
    protected function getDsn(array $config): string
    {
        $host     = $config['host']     ?? 'localhost';
        $database = $config['database'] ?? '';
        $encrypt  = empty($config['encrypt']) ? 'No' : 'Yes';

        // Named instance check: real backslash in host string
        $hasNamedInstance = (strpos($host, '\\') !== false || strpos($host, '/') !== false);

        $server = $hasNamedInstance
            ? $host                                          // e.g. SERVER\SQLEXPRESS  — no port
            : $host . ',' . ($config['port'] ?? 1433);      // e.g. 192.168.1.1,1433

        return sprintf(
            'odbc:Driver={ODBC Driver 17 for SQL Server};Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=Yes',
            $server,
            $database,
            $encrypt
        );
    }
}
