<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use App\Http\Controllers\Admin\Masters\Concerns\HandlesCsvMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FDN Card master (mc_fdn_card) — company-scoped configuration mapping a
 * card code to an estate + division. CRUD + CSV (replace-all), no SAP.
 * Card code drives the QR content. Follows the multi-company / multi-estate
 * design: cascading Estate > Division selection; rows stay filtered by the
 * logged-in company. Near-identical to OPH Card.
 */
class FdnCardController extends BaseGroupingController
{
    use HandlesCsvMaster;
    use \App\Http\Controllers\Admin\Masters\Concerns\HasRowQr;

    protected function qrValueColumn(): string { return 'fdn_card_id'; }
    protected function qrCaptionColumns(): array { return ['Estate' => 'estate_code', 'Division' => 'division_code']; }

    /** CSV import replaces all existing rows (mirrors CI4). */
    protected function useReplaceAll(): bool { return true; }

    protected function csvHeaders(): array
    {
        return ['estate_code', 'fdn_card_id', 'division_code'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $estate = trim($row['estate_code'] ?? '');
        $id     = strtoupper(trim($row['fdn_card_id'] ?? ''));
        $div    = trim($row['division_code'] ?? '');
        if ($estate === '' && $id === '' && $div === '') return null;
        return ['estate_code' => $estate, 'fdn_card_id' => $id, 'division_code' => $div];
    }

    protected function validateRow(array $row): ?string
    {
        if (($row['estate_code'] ?? '') === '') return 'Estate is required.';
        if ($row['fdn_card_id'] === '')         return 'Card Code is required.';
        if ($row['division_code'] === '')       return 'Division is required.';

        $exists = DB::table('m_division')
            ->where('company_id', $this->companyId())
            ->where('estate_code', $row['estate_code'])
            ->where('division_code', $row['division_code'])
            ->exists();

        return $exists ? null
            : "Division '{$row['division_code']}' does not exist in estate '{$row['estate_code']}'.";
    }

    protected function tableName(): string    { return 'mc_fdn_card'; }
    protected function resourceName(): string { return 'FDN Card'; }
    protected function viewPrefix(): string   { return 'admin.masters.fdn_card'; }
    protected function routePrefix(): string  { return 'masters.fdn_card'; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'   => 'Estate',
            'fdn_card_id'   => 'Card Code',
            'division_code' => 'Division',
            'updated_at'    => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'estate_code'   => 'required|string|max:20',
            'fdn_card_id'   => 'required|string|max:100',
            'division_code' => 'required|string|max:50',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'estate_code'   => trim($request->estate_code),
            'fdn_card_id'   => strtoupper(trim($request->fdn_card_id)),
            'division_code' => trim($request->division_code),
        ];
    }

    /** Cascading dropdown sources (company-scoped). */
    protected function formData(): array
    {
        $cid = $this->companyId();

        return [
            'estates'   => DB::table('m_estate')->where('company_id', $cid)
                             ->orderBy('estate_code')->get(['estate_code', 'estate_name']),
            'divisions' => DB::table('m_division')->where('company_id', $cid)
                             ->orderBy('division_code')->get(['estate_code', 'division_code', 'division_name']),
        ];
    }

    public function create()
    {
        return view($this->viewPrefix() . '.form', array_merge([
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => null,
        ], $this->formData()));
    }

    public function edit(int $id)
    {
        $item = $this->baseQuery()->where('id', $id)->first();
        abort_unless($item, 404);
        return view($this->viewPrefix() . '.form', array_merge([
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => $item,
        ], $this->formData()));
    }
}