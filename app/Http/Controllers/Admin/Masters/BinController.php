<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\Admin\Grouping\BaseGroupingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BIN master — company-scoped CRUD (no SAP, no CSV).
 *
 * Follows the multi-company / multi-estate design: each BIN carries an
 * estate_code and division_code (cascading Estate > Division selection,
 * same pattern as the TPH master). Rows are still filtered by the
 * logged-in company via BaseGroupingController::baseQuery().
 */
class BinController extends BaseGroupingController
{
    protected function tableName(): string    { return 'm_bin'; }
    protected function resourceName(): string { return 'BIN'; }
    protected function viewPrefix(): string   { return 'admin.masters.bin'; }
    protected function routePrefix(): string  { return 'masters.bin'; }

    /** Enable the per-row Edit action (BIN now has editable estate/division). */
    protected function hasEdit(): bool { return true; }

    protected function datatableColumns(): array
    {
        return [
            'estate_code'   => 'Estate',
            'division_code' => 'Division',
            'bin_code'      => 'BIN Code',
            'updated_at'    => 'Last Updated',
        ];
    }

    protected function storeValidation(): array
    {
        return [
            'estate_code'   => 'required|string|max:20',
            'division_code' => 'required|string|max:50',
            'bin_code'      => 'required|string|max:100',
        ];
    }

    protected function storeData(Request $request): array
    {
        return [
            'estate_code'   => trim($request->estate_code),
            'division_code' => trim($request->division_code),
            'bin_code'      => strtoupper(trim($request->bin_code)),
        ];
    }

    // ── Cascading dropdown sources (scoped to the logged-in company) ──────────
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