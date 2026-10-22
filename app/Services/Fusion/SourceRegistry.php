<?php

namespace App\Services\Fusion;

/**
 * Static manifest of the 12 CI3 source estate databases.
 *
 * Reads config('fusion.*') and exposes the manifest as SourceDescriptor
 * value objects. This class performs no DB access; it is a pure view over
 * the config/fusion.php manifest (see design.md LLD-2).
 */
class SourceRegistry
{
    /**
     * @var array<string, SourceDescriptor>|null Lazily built, keyed by dbName.
     */
    private ?array $descriptors = null;

    /**
     * All source descriptors, keyed by db name.
     *
     * @return array<string, SourceDescriptor>
     */
    public function all(): array
    {
        if ($this->descriptors === null) {
            $this->descriptors = [];

            /** @var array<int, array<string, mixed>> $rows */
            $rows = (array) config('fusion.sources', []);

            foreach ($rows as $row) {
                $descriptor = SourceDescriptor::fromConfigArray($row);
                $this->descriptors[$descriptor->dbName] = $descriptor;
            }
        }

        return $this->descriptors;
    }

    /**
     * Resolve a single descriptor by its db name, or null if not in the manifest.
     */
    public function get(string $dbName): ?SourceDescriptor
    {
        return $this->all()[$dbName] ?? null;
    }

    /**
     * Number of source databases in the manifest.
     */
    public function count(): int
    {
        return count($this->all());
    }

    /**
     * Transaction window size in months (config('fusion.window_months')).
     */
    public function windowMonths(): int
    {
        return (int) config('fusion.window_months', 2);
    }

    /**
     * Globally excluded table names (config('fusion.excluded_tables')).
     *
     * @return array<int, string>
     */
    public function excludedTables(): array
    {
        return array_values((array) config('fusion.excluded_tables', []));
    }
}
