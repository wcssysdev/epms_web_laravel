<?php

namespace App\Http\Controllers\Admin\Masters\Concerns;

/**
 * Adds a per-row "Print QR" action to a company-scoped CRUD master.
 *
 * The host controller must provide:
 *   - tableName(), resourceName(), companyId(), baseQuery()
 *   - qrValueColumn(): string   (DB column whose value becomes the QR text)
 *   - optionally qrCaptionColumns(): array (extra columns shown under the QR)
 *
 * Renders the shared print view admin.masters._shared.print_qr.
 */
trait HasRowQr
{
    /** Flag consumed by the shared index to show the QR action button. */
    protected function hasQr(): bool
    {
        return true;
    }

    /** DB column whose value is encoded into the QR code. */
    abstract protected function qrValueColumn(): string;

    /** Extra columns to print as caption lines under the QR (label => column). */
    protected function qrCaptionColumns(): array
    {
        return [];
    }

    // ── Print QR for one row ────────────────────────────────────────────────────
    public function printQr(int $id)
    {
        $item = $this->baseQuery()->where('id', $id)->first();
        abort_unless($item, 404);

        $arr     = (array) $item;
        $value   = (string) ($arr[$this->qrValueColumn()] ?? '');
        $captions = [];
        foreach ($this->qrCaptionColumns() as $label => $col) {
            $captions[$label] = $arr[$col] ?? '';
        }

        return view('admin.masters._shared.print_qr', [
            'resourceName' => $this->resourceName(),
            'value'        => $value,
            'captions'     => $captions,
        ]);
    }
}
