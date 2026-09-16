<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\GoodsIssue;
use App\Models\Transaction\GoodsIssueDetail;
use App\Models\Master\Sloc;
use App\Models\Master\CostCenter;
use App\Models\Master\Material;
use App\Models\Master\Block;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Goods Issue (GI) Transaction Controller.
 *
 * Movement types supported:
 *   201 – Cost Center  (cost_center + gl_account)
 *   221 – Project/WBS  (wbs_code)
 *   261 – Order        (order_number + gl_account)
 */
class GoodsIssueController extends BaseController
{
    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $sessionKey = 'transactions.goods_issue.range';
        $stored     = session($sessionKey, []);

        $from = $request->query('from', $stored['from'] ?? Carbon::today()->toDateString());
        $to   = $request->query('to',   $stored['to']   ?? Carbon::today()->toDateString());

        try {
            $from = Carbon::parse($from)->toDateString();
            $to   = Carbon::parse($to)->toDateString();
        } catch (\Throwable) {
            $from = $to = Carbon::today()->toDateString();
        }

        if ($from > $to) [$from, $to] = [$to, $from];
        session([$sessionKey => ['from' => $from, 'to' => $to]]);

        $records = GoodsIssue::byCompany($this->companyId())
            ->whereBetween('gi_date', [$from, $to])
            ->orderByDesc('gi_date')
            ->orderByDesc('id')
            ->with('details')
            ->get();

        return view('transaction.goods_issue.index', compact('records', 'from', 'to'));
    }

    // ── CREATE ────────────────────────────────────────────────────────────────
    public function create()
    {
        return view('transaction.goods_issue.form', array_merge(
            ['title' => 'Add Goods Issue', 'item' => null],
            $this->formData()
        ));
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $request->validate([
            'gi_date'       => 'required|date',
            'sloc_code'     => 'required|string|max:20',
            'movement_type' => 'required|string|max:10',
            'details'       => 'required|array|min:1',
            'details.*.material_code' => 'required|string|max:50',
            'details.*.qty'           => 'required|numeric|min:0.001',
            'details.*.uom'           => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($validated) {
            $id = 'GI' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);

            $header = GoodsIssue::create([
                'id'               => $id,
                'company_id'       => $this->companyId(),
                'gi_date'          => $validated['gi_date'],
                'estate_code'      => $this->estateCode(),
                'plant_code'       => $this->plantCode(),
                'sloc_code'        => $validated['sloc_code'],
                'movement_type'    => $validated['movement_type'],
                'integration_status' => GoodsIssue::STATUS_PENDING,
                'created_by'       => $this->userName(),
                'updated_by'       => $this->userName(),
            ]);

            foreach ($validated['details'] as $d) {
                GoodsIssueDetail::create([
                    'company_id'       => $this->companyId(),
                    'gi_header_id'     => $header->id,
                    'material_code'    => $d['material_code'],
                    'material_name'    => $d['material_name'] ?? '',
                    'qty'              => $d['qty'],
                    'uom'              => $d['uom'],
                    'cost_center'      => $d['cost_center']   ?? null,
                    'wbs_code'         => $d['wbs_code']      ?? null,
                    'order_number'     => $d['order_number']  ?? null,
                    'gl_account'       => $d['gl_account']    ?? null,
                    'integration_status' => GoodsIssue::STATUS_PENDING,
                ]);
            }

            AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
                "Created Goods Issue {$header->id}");
        });

        return redirect()->route('transactions.goods_issue.index')
            ->with('success', 'Goods Issue saved successfully.');
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    public function edit(string $id)
    {
        $item = GoodsIssue::byCompany($this->companyId())->with('details')->findOrFail($id);

        if (! $item->isEditable()) {
            return redirect()->route('transactions.goods_issue.index')
                ->with('error', 'Goods Issue already sent to SAP and cannot be edited.');
        }

        return view('transaction.goods_issue.form', array_merge(
            ['title' => 'Edit Goods Issue', 'item' => $item],
            $this->formData()
        ));
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = GoodsIssue::byCompany($this->companyId())->findOrFail($id);

        if (! $item->isEditable()) {
            return redirect()->route('transactions.goods_issue.index')
                ->with('error', 'Goods Issue already sent to SAP and cannot be edited.');
        }

        $validated = $request->validate([
            'gi_date'       => 'required|date',
            'sloc_code'     => 'required|string|max:20',
            'movement_type' => 'required|string|max:10',
            'details'       => 'required|array|min:1',
            'details.*.material_code' => 'required|string|max:50',
            'details.*.qty'           => 'required|numeric|min:0.001',
            'details.*.uom'           => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($item, $validated) {
            $item->update([
                'gi_date'       => $validated['gi_date'],
                'sloc_code'     => $validated['sloc_code'],
                'movement_type' => $validated['movement_type'],
                'updated_by'    => $this->userName(),
            ]);

            $item->details()->delete();
            foreach ($validated['details'] as $d) {
                GoodsIssueDetail::create([
                    'company_id'       => $this->companyId(),
                    'gi_header_id'     => $item->id,
                    'material_code'    => $d['material_code'],
                    'material_name'    => $d['material_name'] ?? '',
                    'qty'              => $d['qty'],
                    'uom'              => $d['uom'],
                    'cost_center'      => $d['cost_center']   ?? null,
                    'wbs_code'         => $d['wbs_code']      ?? null,
                    'order_number'     => $d['order_number']  ?? null,
                    'gl_account'       => $d['gl_account']    ?? null,
                    'integration_status' => GoodsIssue::STATUS_PENDING,
                ]);
            }

            AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
                "Updated Goods Issue {$item->id}");
        });

        return redirect()->route('transactions.goods_issue.index')
            ->with('success', 'Goods Issue updated successfully.');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────
    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = GoodsIssue::byCompany($this->companyId())->findOrFail($id);

        if (! $item->isEditable()) {
            return redirect()->route('transactions.goods_issue.index')
                ->with('error', 'Goods Issue already sent to SAP and cannot be deleted.');
        }

        DB::transaction(function () use ($item) {
            $item->details()->delete();
            $item->delete();
            AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
                "Deleted Goods Issue {$item->id}");
        });

        return redirect()->route('transactions.goods_issue.index')
            ->with('success', 'Goods Issue deleted.');
    }

    // ── DETAIL ────────────────────────────────────────────────────────────────
    public function detail(string $id)
    {
        $item = GoodsIssue::byCompany($this->companyId())->with('details')->findOrFail($id);
        return view('transaction.goods_issue.detail', compact('item'));
    }

    // ── AJAX: Search Materials ─────────────────────────────────────────────
    public function searchMaterial(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('term', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = 25;

        $query = Material::where(fn($q) =>
            $q->where('material_code', 'ilike', "%{$term}%")
              ->orWhere('material_name', 'ilike', "%{$term}%")
        );

        $total = (clone $query)->count();
        $rows  = $query->orderBy('material_code')
            ->forPage($page, $pageSize)
            ->get(['material_code', 'material_name', 'material_uom']);

        return response()->json([
            'results' => $rows->map(fn($m) => [
                'id'   => $m->material_code,
                'text' => "{$m->material_name} [{$m->material_code}]",
                'uom'  => $m->material_uom,
                'name' => $m->material_name,
            ]),
            'pagination' => ['more' => ($page * $pageSize) < $total],
        ]);
    }

    // ── AJAX: Get Maintenance Orders (for mvt 261) ─────────────────────────
    public function getMasterOrder(Request $request): JsonResponse
    {
        $orders = DB::table('m_maintenance_order')
            ->where('plant_code', $this->plantCode())
            ->orderBy('order_number')
            ->get(['id', 'order_number', 'order_desc', 'sales_doc_type']);

        return response()->json($orders);
    }

    // ── AJAX: Get WBS List (for mvt 221) ──────────────────────────────────
    public function getWbsList(Request $request): JsonResponse
    {
        $wbs = DB::table('m_gigr_wbs')
            ->where('plant_code', $this->plantCode())
            ->orderBy('wbs_code')
            ->get(['wbs_code', 'wbs_name', 'wbs_gl_acc_code', 'wbs_gl_acc_desc']);

        return response()->json($wbs);
    }

    // ── AJAX: Get GL Accounts ─────────────────────────────────────────────
    public function getGlAccounts(Request $request): JsonResponse
    {
        $gl = DB::table('m_glacc')
            ->orderBy('account_number')
            ->get(['account_number', 'account_desc']);

        return response()->json($gl);
    }

    // ── PRIVATE HELPERS ───────────────────────────────────────────────────────
    private function formData(): array
    {
        $plantCode  = $this->plantCode();
        $estateCode = $this->estateCode();
        $today      = now()->toDateString();

        $slocs = Sloc::where('plant_code', $plantCode)
            ->orderBy('sloc_code')
            ->get(['sloc_code', 'sloc_desc']);

        $movementTypes = DB::table('m_movement_type')
            ->where('mvt_type_doc_type', 'GI')
            ->orderBy('mvt_type_code')
            ->get(['mvt_type_code', 'mvt_type_desc']);

        $costCenters = CostCenter::where('cc_gsber', $plantCode)
            ->where('valid_from', '<=', $today)
            ->where('valid_to', '>=', $today)
            ->orderBy('cc_code')
            ->get(['cc_code', 'cc_desc']);

        $glAccounts = DB::table('m_glacc')
            ->orderBy('account_number')
            ->get(['account_number', 'account_desc']);

        $wbsList = DB::table('m_gigr_wbs')
            ->where('plant_code', $plantCode)
            ->orderBy('wbs_code')
            ->get(['wbs_code', 'wbs_name', 'wbs_gl_acc_code', 'wbs_gl_acc_desc']);

        $maintenanceOrders = DB::table('m_maintenance_order')
            ->where('plant_code', $plantCode)
            ->orderBy('order_number')
            ->get(['id', 'order_number', 'order_desc', 'sales_doc_type']);

        $worktypes = DB::table('m_worktype')
            ->orderBy('worktype_code')
            ->get(['id', 'worktype_code', 'worktype_name']);

        $blocks = Block::where('estate_code', $estateCode)
            ->orderBy('block_code')
            ->get(['id', 'block_code', 'block_name', 'division_code']);

        return compact(
            'slocs', 'movementTypes', 'costCenters', 'glAccounts',
            'wbsList', 'maintenanceOrders', 'worktypes', 'blocks'
        );
    }
}
