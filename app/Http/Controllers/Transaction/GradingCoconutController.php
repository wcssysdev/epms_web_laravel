<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\CoconutOph;
use App\Models\Transaction\CoconutOphDetail;
use App\Models\Transaction\CpDetail;
use App\Models\Master\CoconutMaterial;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Grading (Coconut) — grade coconut Harvesting Chits (t_coconut_oph) that have
 * already been added to a CP (Coconut). Grading does NOT create new chits; it
 * edits the grading material lines of an existing chit (t_coconut_oph_detail):
 * one row per coconut material with a customer nut quantity.
 *
 * Save is delete-then-insert of the chit's detail rows (mirroring CI4).
 * Coconut-enabled companies only.
 */
class GradingCoconutController extends BaseController
{
    use \App\Http\Controllers\Transaction\Concerns\GuardsSapIntegration;

    protected const CP_DETAIL_TYPE = 2;

    protected function routePrefix(): string { return 'transactions.grading_coconut'; }
    protected function viewPrefix(): string  { return 'transaction.grading_coconut'; }
    protected function title(): string       { return 'Grading (Coconut)'; }

    protected function datatableColumns(): array
    {
        return [
            'created_at'    => 'Date',
            'division_code' => 'Division',
            'block_code'    => 'Block',
            'tph_code'      => 'TPH',
            'nuts_total'    => 'Nuts',
        ];
    }

    // ── INDEX / DATATABLE ──────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);

        return view($this->viewPrefix() . '.index', [
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'columns'     => $this->datatableColumns(),
            'from'        => $from,
            'to'          => $to,
            'hasCsv'      => false,
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);

        // Only chits that have already been added to a CP (Coconut) are gradeable
        // (CI4: "Grading is allowed once the HC has entered a Checkpoint Coconut").
        $inCp = CpDetail::query()->where('detail_type', self::CP_DETAIL_TYPE)->pluck('oph_id')->all();

        $query = CoconutOph::query()->actual()
            ->when($inCp === [], fn ($q) => $q->whereRaw('1 = 0'))
            ->when($inCp !== [], fn ($q) => $q->whereIn('id', array_values(array_unique($inCp))))
            ->whereBetween('created_at', [$from, $to])
            ->withCount('details')
            ->orderByDesc('created_at');

        return DataTables::eloquent($query)
            ->addColumn('grading_count', fn ($row) => $row->details_count)
            ->addIndexColumn()
            ->make(true);
    }

    // ── EDIT / UPDATE ──────────────────────────────────────────────────────────
    public function edit(string $id): View|RedirectResponse
    {
        $item = CoconutOph::query()->whereKey($id)->first();
        abort_unless($item, 404);

        if ($guard = $this->guardGrading($item)) return $guard;

        return view($this->viewPrefix() . '.form', [
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => $item,
            'gradings'    => $item->details()->orderBy('id')->get(),
            'persons'     => $item->persons()->get(),
            'materials'   => CoconutMaterial::orderBy('material_code')->get(['material_code', 'material_desc']),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = CoconutOph::query()->whereKey($id)->first();
        abort_unless($item, 404);

        if ($guard = $this->guardGrading($item)) return $guard;

        $request->validate([
            'gradings'                    => 'nullable|array',
            'gradings.*.material_code'    => 'nullable|string|max:100',
            'gradings.*.customer_nut_qty' => 'nullable|numeric|min:0',
        ]);

        $gradings = $this->cleanGradings($request);

        DB::transaction(function () use ($item, $gradings) {
            // Delete-then-insert the grading detail lines for this chit.
            CoconutOphDetail::where('coconut_oph_id', $item->id)->delete();
            foreach ($gradings as $g) {
                CoconutOphDetail::create([
                    'company_id'         => $this->companyId(),
                    'coconut_oph_id'     => $item->id,
                    'material_code'      => $g['material_code'],
                    'material_name'      => $g['material_name'],
                    'customer_nut_qty'   => $g['customer_nut_qty'],
                    'is_locked'          => false,
                    'is_deleted'         => false,
                    'integration_status' => -1,
                ]);
            }
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE, "Graded {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', 'Grading data saved successfully.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Grading is blocked when the chit has been sent to SAP (status 0/2) unless
     * ALL its grading lines are already successfully sent (status 2). Mirrors CI4:
     * a partially-sent grading set is locked ("Grading can't be edited anymore").
     */
    protected function guardGrading(CoconutOph $item): ?RedirectResponse
    {
        $status = (int) ($item->integration_status ?? -1);
        if ($status === 0 || $status === 2) {
            $all     = CoconutOphDetail::where('coconut_oph_id', $item->id)->count();
            $success = CoconutOphDetail::where('coconut_oph_id', $item->id)
                        ->where('integration_status', 2)->count();
            if ($all > 0 && $all !== $success) {
                return redirect()->route($this->routePrefix() . '.index')
                    ->with('error', "Grading can't be edited anymore.");
            }
        }
        return null;
    }

    /** Keep only rows with a material code; resolve material name from the master. */
    protected function cleanGradings(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('gradings', []) as $g) {
            if (! is_array($g)) continue;
            $code = trim((string) ($g['material_code'] ?? ''));
            if ($code === '') continue;
            $out[] = [
                'material_code'    => $code,
                'material_name'    => CoconutMaterial::where('material_code', $code)->value('material_desc') ?? '',
                'customer_nut_qty' => (float) ($g['customer_nut_qty'] ?? 0),
            ];
        }
        return $out;
    }

    /** @return array{0:string,1:string} */
    protected function resolveRange(Request $request): array
    {
        $key    = $this->routePrefix() . '.range';
        $stored = session($key, []);
        $from = $request->query('from', $stored['from'] ?? Carbon::today()->subDays(7)->toDateString());
        $to   = $request->query('to',   $stored['to']   ?? Carbon::today()->toDateString());
        try {
            $from = Carbon::parse($from)->toDateString();
            $to   = Carbon::parse($to)->toDateString();
        } catch (\Throwable $e) {
            $from = Carbon::today()->subDays(7)->toDateString();
            $to   = Carbon::today()->toDateString();
        }
        if ($from > $to) [$from, $to] = [$to, $from];
        session([$key => ['from' => $from, 'to' => $to]]);
        return [$from, $to];
    }
}
