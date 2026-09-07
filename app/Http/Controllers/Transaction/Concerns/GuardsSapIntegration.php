<?php

namespace App\Http\Controllers\Transaction\Concerns;

use Illuminate\Http\RedirectResponse;

/**
 * Guards edit/update/delete of transaction records that have been (or are being)
 * synchronised to SAP via the integration_status column.
 *
 * Status semantics (mirroring CI4):
 *   0 = pending / queued to SAP (in-flight)   → locked
 *   2 = sent to SAP                            → locked
 *   5 = sent + adjustment context             → locked (Tahap 1: treat as locked)
 *   other / null                              → editable (local draft)
 *
 * The host controller must expose routePrefix(). Records without an
 * integration_status attribute are never locked.
 */
trait GuardsSapIntegration
{
    /** integration_status values that lock a record from edit/delete. */
    protected array $sapLockedStatuses = [0, 2, 5];

    protected function isSapLocked($model): bool
    {
        if ($model === null) {
            return false;
        }
        $status = $model->integration_status ?? null;
        if ($status === null || $status === '') {
            return false;
        }
        return in_array((int) $status, $this->sapLockedStatuses, true);
    }

    /**
     * Human label for the integration status (used in the UI badge).
     */
    protected function sapStatusLabel($model): string
    {
        $status = $model->integration_status ?? null;
        return match ((int) $status) {
            0       => 'Queued',
            2       => 'Sent to SAP',
            5       => 'Sent (Adj.)',
            default => 'Draft',
        };
    }

    /** Redirect back to index with a lock message, or null if editable. */
    protected function guardSapEdit($model): ?RedirectResponse
    {
        if ($this->isSapLocked($model)) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', 'This record has been sent to SAP and can no longer be edited.');
        }
        return null;
    }

    /** Redirect back to index with a lock message, or null if deletable. */
    protected function guardSapDelete($model): ?RedirectResponse
    {
        if ($this->isSapLocked($model)) {
            return redirect()->route($this->routePrefix() . '.index')
                ->with('error', 'This record has been sent to SAP and can no longer be deleted.');
        }
        return null;
    }
}
