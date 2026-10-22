<?php

namespace App\Services\Fusion;

/**
 * Immutable result of a {@see SafetyManager::prepareTarget()} run.
 *
 * Captures whether the flow completed or aborted (and why), the backup file
 * involved, and the per-table cleaned row counts (R1.8). The command turns this
 * into console output and the process exit code; keeping it a plain value object
 * lets the behaviour be asserted in tests without scraping console text.
 */
final class SafetyReport
{
    /**
     * @param  'completed'|'aborted'  $outcome
     * @param  array<int, array{table:string, before:int, after:int, cleaned:int, method:string}>  $cleaned
     */
    private function __construct(
        public readonly string $outcome,
        public readonly ?string $reasonCode,
        public readonly ?string $reason,
        public readonly ?string $backupFile,
        public readonly array $cleaned,
    ) {
    }

    /**
     * An aborted run: NO truncate was performed. $reasonCode is one of
     * missing_confirm | guard_failed | backup_failed.
     */
    public static function aborted(string $reasonCode, string $reason, ?string $backupFile = null): self
    {
        return new self('aborted', $reasonCode, $reason, $backupFile, []);
    }

    /**
     * A completed run: backup succeeded and the ordered truncate ran.
     *
     * @param  array<int, array{table:string, before:int, after:int, cleaned:int, method:string}>  $cleaned
     */
    public static function completed(string $backupFile, array $cleaned): self
    {
        return new self('completed', null, null, $backupFile, $cleaned);
    }

    public function wasAborted(): bool
    {
        return $this->outcome === 'aborted';
    }

    public function wasCompleted(): bool
    {
        return $this->outcome === 'completed';
    }

    /** Total rows cleaned across all truncated tables (R1.8 summary). */
    public function totalCleaned(): int
    {
        return array_sum(array_column($this->cleaned, 'cleaned'));
    }
}
