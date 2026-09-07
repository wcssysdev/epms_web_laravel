<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Oph;
use App\Models\Master\Tph;
use App\Models\Global\HarvestMethod;
use Illuminate\Http\Request;

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
        ];
    }
}
