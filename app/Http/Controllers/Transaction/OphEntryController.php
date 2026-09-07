<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Oph;
use App\Models\Transaction\OphPerson;
use App\Models\Master\Tph;
use App\Models\Master\Employee;
use App\Models\Global\HarvestMethod;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * OPH (Oil Palm Harvest) manual entry (t_oph) — Estate Staff CRUD.
 *
 * Field capture (GPS/photo/QR) happens on mobile; the web form is manual
 * entry / correction. Records the harvest grading per bunch category with an
 * auto-computed total. Person (cutter/carrier) distribution is out of scope
 * here — the t_oph_person table is not present in this schema.
 */
class OphEntryController extends BaseTransactionController
{
    use \App\Http\Controllers\Transaction\Concerns\HandlesTransactionCsv;

    /** Grading bunch categories: db_column => label. */
    public const GRADING = [
        'bunches_ripe'         => 'Ripe',
        'bunches_overripe'     => 'Overripe',
        'bunches_underripe'    => 'Underripe',
        'bunches_unripe'       => 'Unripe',
        'bunches_wet'          => 'Wet',
        'bunches_rotten'       => 'Rotten',
        'bunches_empty'        => 'Empty',
        'bunches_long_stalk'   => 'Long Stalk',
        'bunches_dirty'        => 'Dirty',
        'bunches_unfresh'      => 'Unfresh',
        'bunches_old'          => 'Old',
        'bunches_pest_damaged' => 'Pest Damaged',
        'bunches_small'        => 'Small',
        'bunches_diseased'     => 'Diseased',
        'bunches_dura'         => 'Dura',
    ];

    protected function modelClass(): string   { return Oph::class; }
    protected function dateColumn(): string    { return 'created_at'; }
    protected function viewPrefix(): string    { return 'transaction.oph'; }
    protected function routePrefix(): string   { return 'transactions.oph'; }
    protected function title(): string         { return 'OPH (Palm)'; }

    protected function generateId(): ?string
    {
        return 'OPH' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);
    }

    /** OPH exposes CSV import (flat rows). */
    protected function hasCsv(): bool
    {
        return true;
    }

    protected function datatableColumns(): array
    {
        return [
            'oph_card_id'          => 'Card ID',
            'division_code'        => 'Division',
            'block_code'           => 'Block',
            'tph_code'             => 'TPH',
            'mandor_employee_name' => 'Mandor',
            'bunches_total'        => 'Bunches',
            'loose_fruits'         => 'Loose Fruits',
        ];
    }

    /** Only actual (non-planned, non-deleted) rows in the listing. */
    protected function decorateDatatable($dt)
    {
        return $dt;
    }

    public function getDatatable(Request $request): \Illuminate\Http\JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        $query = Oph::query()->actual()
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at');

        return \Yajra\DataTables\Facades\DataTables::eloquent($query)->addIndexColumn()->make(true);
    }

    protected function rules(Request $request): array
    {
        $rules = [
            'oph_card_id'          => 'nullable|string|max:100',
            'harvest_method'       => 'nullable|integer',
            'division_code'        => 'required|string|max:50',
            'block_code'           => 'required|string|max:50',
            'tph_code'             => 'nullable|string|max:50',
            'platform_no'          => 'nullable|string|max:50',
            'mandor_employee_code' => 'nullable|string|max:100',
            'loose_fruits'         => 'nullable|numeric|min:0',
            'notes'                => 'nullable|string|max:255',
        ];
        foreach (array_keys(self::GRADING) as $col) {
            $rules[$col] = 'nullable|integer|min:0';
        }
        return $rules;
    }

    protected function mapRow(Request $request): array
    {
        $mandor = $request->mandor_employee_code
            ? \App\Models\Master\Employee::where('employee_code', $request->mandor_employee_code)->first()
            : null;

        $row = [
            'oph_card_id'          => $request->oph_card_id ?: null,
            'harvest_method'       => $request->harvest_method !== null && $request->harvest_method !== ''
                                        ? (int) $request->harvest_method : null,
            'estate_code'          => $this->estateCode(),
            'plant_code'           => $this->plantCode(),
            'division_code'        => trim($request->division_code),
            'block_code'           => trim($request->block_code),
            'tph_code'             => $request->tph_code ?: null,
            'platform_no'          => $request->platform_no ?: null,
            'mandor_employee_code' => $request->mandor_employee_code ?: null,
            'mandor_employee_name' => $mandor?->employee_name,
            'loose_fruits'         => $request->loose_fruits !== null ? (float) $request->loose_fruits : 0,
            'notes'                => $request->notes ?: null,
            'is_planned'           => false,
            'is_deleted'           => false,
        ];

        // Grading columns + auto total.
        $total = 0;
        foreach (array_keys(self::GRADING) as $col) {
            $val = (int) ($request->input($col) ?? 0);
            $row[$col] = $val;
            $total += $val;
        }
        $row['bunches_total'] = $total;

        return $row;
    }

    protected function formData(): array
    {
        return [
            'divisions'      => $this->divisions(),
            'blocks'         => $this->blocks(),
            'tphs'           => Tph::where('estate_code', $this->estateCode())
                                    ->orderBy('tph_code')
                                    ->get(['division_code', 'block_code', 'tph_code', 'section_code']),
            'employees'      => $this->employees(),
            'harvestMethods' => HarvestMethod::orderBy('mhm_indicator')
                                    ->get(['mhm_indicator', 'mhm_abbreviation', 'mhm_description']),
            'grading'        => self::GRADING,
            'cutter'         => null,
            'carriers'       => [],
        ];
    }

    // ── CSV import (flat: header + grading, no person distribution) ────────────
    protected function csvHeaders(): array
    {
        return array_merge(
            ['oph_card_id', 'harvest_method', 'division_code', 'block_code', 'tph_code', 'platform_no', 'mandor_employee_code', 'loose_fruits'],
            array_keys(self::GRADING),
            ['notes']
        );
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $division = trim((string) ($row['division_code'] ?? ''));
        $block    = trim((string) ($row['block_code'] ?? ''));
        if ($division === '' && $block === '') return null;

        $mandorCode = trim((string) ($row['mandor_employee_code'] ?? ''));
        $mandor     = $mandorCode !== '' ? \App\Models\Master\Employee::where('employee_code', $mandorCode)->first() : null;

        $mapped = [
            'oph_card_id'          => trim((string) ($row['oph_card_id'] ?? '')) ?: null,
            'harvest_method'       => ($row['harvest_method'] ?? '') !== '' ? (int) $row['harvest_method'] : null,
            'estate_code'          => $this->estateCode(),
            'plant_code'           => $this->plantCode(),
            'division_code'        => $division,
            'block_code'           => $block,
            'tph_code'             => trim((string) ($row['tph_code'] ?? '')) ?: null,
            'platform_no'          => trim((string) ($row['platform_no'] ?? '')) ?: null,
            'mandor_employee_code' => $mandorCode ?: null,
            'mandor_employee_name' => $mandor?->employee_name,
            'loose_fruits'         => ($row['loose_fruits'] ?? '') !== '' ? (float) $row['loose_fruits'] : 0,
            'notes'                => trim((string) ($row['notes'] ?? '')) ?: null,
            'is_planned'           => false,
            'is_deleted'           => false,
        ];

        $total = 0;
        foreach (array_keys(self::GRADING) as $col) {
            $val = (int) ($row[$col] ?? 0);
            $mapped[$col] = $val;
            $total += $val;
        }
        $mapped['bunches_total'] = $total;

        return $mapped;
    }

    protected function validateCsvRow(array $row): ?string
    {
        if (($row['division_code'] ?? '') === '') return 'Division is required.';
        if (($row['block_code'] ?? '') === '')    return 'Block is required.';
        return null;
    }

    // ── EDIT (pass existing cutter + carriers) ─────────────────────────────────
    public function edit($id): \Illuminate\View\View|RedirectResponse
    {
        $item = Oph::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;

        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => $item,
        ], array_merge($this->formData(), [
            'cutter'   => $item->cutter,
            'carriers' => $item->carriers()->get(),
        ])));
    }

    // ── STORE (header + persons) ───────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate($this->rules($request));

        if ($err = $this->validatePersons($request)) {
            return back()->withInput()->with('error', $err);
        }

        $id  = $this->generateId();
        $row = array_merge($this->mapRow($request), [
            'id'         => $id,
            'company_id' => $this->companyId(),
            'created_by' => $this->userName(),
            'updated_by' => $this->userName(),
        ]);

        DB::transaction(function () use ($row, $id, $request) {
            Oph::create($row);
            $this->savePersons($id, $request);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE, "Created {$this->title()} {$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' saved successfully.');
    }

    // ── UPDATE (header + persons) ──────────────────────────────────────────────
    public function update(Request $request, $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = Oph::query()->whereKey($id)->first();
        abort_unless($item, 404);
        if ($sap = $this->guardSapEdit($item)) return $sap;

        $request->validate($this->rules($request));

        if ($err = $this->validatePersons($request)) {
            return back()->withInput()->with('error', $err);
        }

        DB::transaction(function () use ($item, $request) {
            $item->update(array_merge($this->mapRow($request), ['updated_by' => $this->userName()]));
            OphPerson::where('oph_id', $item->id)->delete();
            $this->savePersons($item->id, $request);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE, "Updated {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' updated successfully.');
    }

    // ── Person helpers ──────────────────────────────────────────────────────────

    /** Collect non-empty carrier rows from the request. */
    protected function carrierRows(Request $request): array
    {
        $rows = [];
        foreach ((array) $request->input('carriers', []) as $c) {
            if (! is_array($c)) continue;
            $code = trim((string) ($c['employee_code'] ?? ''));
            $pct  = trim((string) ($c['percentage'] ?? ''));
            if ($code === '' && $pct === '') continue;
            $rows[] = ['employee_code' => $code, 'percentage' => (float) $pct];
        }
        return $rows;
    }

    /**
     * Validate cutter + carriers: cutter required, and cutter% + Σcarriers% = 100.
     * Returns an error string or null.
     */
    protected function validatePersons(Request $request): ?string
    {
        $cutterCode = trim((string) $request->input('cutter_employee_code', ''));
        $cutterPct  = (float) $request->input('cutter_percentage', 0);

        if ($cutterCode === '') {
            return 'Cutter employee is required.';
        }

        $carriers = $this->carrierRows($request);
        foreach ($carriers as $c) {
            if ($c['employee_code'] === '') return 'Each carrier must have an employee selected.';
        }

        $total = $cutterPct + array_sum(array_column($carriers, 'percentage'));
        if (round($total, 2) != 100.0) {
            return "Cutter + carriers percentage must total 100% (currently {$total}%).";
        }

        return null;
    }

    /** Persist cutter (type 1) + carriers (type 2) for an OPH id. */
    protected function savePersons(string $ophId, Request $request): void
    {
        $now  = now();
        $rows = [];

        $cutterCode = trim((string) $request->input('cutter_employee_code', ''));
        $rows[] = [
            'company_id'    => $this->companyId(),
            'oph_id'        => $ophId,
            'employee_code' => $cutterCode,
            'employee_name' => Employee::where('employee_code', $cutterCode)->value('employee_name') ?? '',
            'percentage'    => (float) $request->input('cutter_percentage', 0),
            'person_type'   => Oph::PERSON_CUTTER,
        ];

        foreach ($this->carrierRows($request) as $c) {
            $rows[] = [
                'company_id'    => $this->companyId(),
                'oph_id'        => $ophId,
                'employee_code' => $c['employee_code'],
                'employee_name' => Employee::where('employee_code', $c['employee_code'])->value('employee_name') ?? '',
                'percentage'    => $c['percentage'],
                'person_type'   => Oph::PERSON_CARRIER,
            ];
        }

        OphPerson::insert($rows);
    }
}
