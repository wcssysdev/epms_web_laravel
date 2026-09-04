<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use App\Http\Controllers\Admin\Masters\Concerns\HandlesCsvMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OPH Card master (mc_oph_card) — company-scoped configuration mapping a
 * card code to a division. CRUD + CSV (replace-all), no SAP. Card code
 * drives the QR content.
 */
class OphCardController extends BaseGroupingController
{
    use HandlesCsvMaster;
    use \App\Http\Controllers\Admin\Masters\Concerns\HasRowQr;

    protected function qrValueColumn(): string { return 'oph_card_id'; }
    protected function qrCaptionColumns(): array { return ['Division' => 'division_code']; }

    /** CSV import replaces all existing rows (mirrors CI4). */
    protected function useReplaceAll(): bool { return true; }

    protected function csvHeaders(): array
    {
        return ['oph_card_id', 'division_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $id  = strtoupper(trim($row['oph_card_id'] ?? ''));
        $div = trim($row['division_code'] ?? '');
        if ($id === '' && $div === '') return null;
        return ['oph_card_id' => $id, 'division_code' => $div];
    }

    protected function validateRow(array $row): ?string
    {
        if ($row['oph_card_id'] === '') return 'Card Code is required.';
        if ($row['division_code'] === '') return 'Division is required.';
        $exists = DB::table('m_division')
            ->where('company_id', $this->companyId())
            ->where('division_code', $row['division_code'])
            ->exists();
        return $exists ? null : "Division '{$row['division_code']}' does not exist.";
    }

    protected function tableName(): string    { return 'mc_oph_card'; }
    protected function resourceName(): string { return 'OPH Card'; }
    protected function viewPrefix(): string   { return 'admin.masters.oph_card'; }
    protected function routePrefix(): string  { return 'masters.oph_card'; }

    protected function datatableColumns(): array
    {
        return [
            'oph_card_id'   => 'Card Code',
            'division_code' => 'Division',
            'updated_at'    => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'oph_card_id'   => 'required|string|max:100',
            'division_code' => 'required|string|max:50',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'oph_card_id'   => strtoupper(trim($request->oph_card_id)),
            'division_code' => trim($request->division_code),
        ];
    }

    /** Division options for the form dropdown (company-scoped). */
    protected function divisions()
    {
        return DB::table('m_division')
            ->where('company_id', $this->companyId())
            ->orderBy('division_code')
            ->get(['division_code', 'division_name']);
    }

    public function create()
    {
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => null,
            'divisions'    => $this->divisions(),
        ]);
    }

    public function edit(int $id)
    {
        $item = $this->baseQuery()->where('id', $id)->first();
        abort_unless($item, 404);
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => $item,
            'divisions'    => $this->divisions(),
        ]);
    }
}
