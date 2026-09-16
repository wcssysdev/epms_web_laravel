<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\GoodsReceipt;
use App\Models\Transaction\GoodsReceiptDetail;
use App\Models\Master\PurchaseOrder;
use App\Models\Master\Sloc;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Goods Receipt (GR) Transaction Controller.
 */
class GoodsReceiptController extends BaseController
{
    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $sessionKey = 'transactions.goods_receipt.range';
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

        $records = GoodsReceipt::byCompany($this->companyId())
            ->whereBetween('gr_date', [$from, $to])
            ->orderByDesc('gr_date')
            ->orderByDesc('id')
            ->with('details')
            ->get();

        $poNumbers = $records->pluck('po_number')->filter()->unique()->values();
        $overQtyPoNumbers = [];

        if ($poNumbers->isNotEmpty()) {
            $receivedByPoMat = DB::table('tr_gr_detail as d')
                ->join('tr_gr_header as h', 'h.id', '=', 'd.gr_header_id')
                ->where('h.company_id', $this->companyId())
                ->whereIn('h.po_number', $poNumbers)
                ->groupBy('h.po_number', 'd.material_code')
                ->select('h.po_number', 'd.material_code', DB::raw('SUM(d.qty) as total_received'))
                ->get();

            $poOrders = DB::table('m_purchase_order')
                ->where('company_id', $this->companyId())
                ->whereIn('po_number', $poNumbers)
                ->groupBy('po_number', 'material_code')
                ->select('po_number', 'material_code', DB::raw('SUM(qty_order) as total_order'))
                ->get()
                ->keyBy(fn($item) => $item->po_number . '|' . $item->material_code);

            foreach ($receivedByPoMat as $rec) {
                $key = $rec->po_number . '|' . $rec->material_code;
                $poOrd = $poOrders->get($key);
                if ($poOrd && (float) $rec->total_received > (float) $poOrd->total_order) {
                    $overQtyPoNumbers[] = $rec->po_number;
                }
            }
            $overQtyPoNumbers = array_values(array_unique($overQtyPoNumbers));
        }

        return view('transaction.goods_receipt.index', compact('records', 'from', 'to', 'overQtyPoNumbers'));
    }

    // ── CREATE ────────────────────────────────────────────────────────────────
    public function create()
    {
        return view('transaction.goods_receipt.form', array_merge(
            ['title' => 'Add Goods Receipt', 'item' => null],
            $this->formData()
        ));
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $request->validate([
            'gr_date'                 => 'required|date',
            'po_number'               => 'required|string|max:50',
            'sloc_code'               => 'required|string|max:20',
            'details'                 => 'required|array|min:1',
            'details.*.material_code' => 'required|string|max:50',
            'details.*.qty'           => 'required|numeric|min:0.001',
            'details.*.uom'           => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($validated) {
            $id = 'GR' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);

            $header = GoodsReceipt::create([
                'id'                 => $id,
                'company_id'         => $this->companyId(),
                'gr_date'            => $validated['gr_date'],
                'plant_code'         => $this->plantCode(),
                'sloc_code'          => $validated['sloc_code'],
                'po_number'          => $validated['po_number'],
                'integration_status' => GoodsReceipt::STATUS_PENDING,
                'created_by'         => $this->userName(),
                'updated_by'         => $this->userName(),
            ]);

            foreach ($validated['details'] as $d) {
                GoodsReceiptDetail::create([
                    'company_id'         => $this->companyId(),
                    'gr_header_id'       => $header->id,
                    'material_code'      => $d['material_code'],
                    'material_name'      => $d['material_name'] ?? '',
                    'qty'                => $d['qty'],
                    'uom'                => $d['uom'],
                    'integration_status' => GoodsReceipt::STATUS_PENDING,
                ]);
            }

            AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
                "Created Goods Receipt {$header->id}");
        });

        return redirect()->route('transactions.goods_receipt.index')
            ->with('success', 'Goods Receipt saved successfully.');
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    public function edit(string $id)
    {
        $item = GoodsReceipt::byCompany($this->companyId())->with('details')->findOrFail($id);

        if (! $item->isEditable()) {
            return redirect()->route('transactions.goods_receipt.index')
                ->with('error', 'Goods Receipt already sent to SAP and cannot be edited.');
        }

        return view('transaction.goods_receipt.form', array_merge(
            ['title' => 'Edit Goods Receipt', 'item' => $item],
            $this->formData()
        ));
    }

    // ── UPDATE ────────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = GoodsReceipt::byCompany($this->companyId())->findOrFail($id);

        if (! $item->isEditable()) {
            return redirect()->route('transactions.goods_receipt.index')
                ->with('error', 'Goods Receipt already sent to SAP and cannot be edited.');
        }

        $validated = $request->validate([
            'gr_date'                 => 'required|date',
            'po_number'               => 'required|string|max:50',
            'sloc_code'               => 'required|string|max:20',
            'details'                 => 'required|array|min:1',
            'details.*.material_code' => 'required|string|max:50',
            'details.*.qty'           => 'required|numeric|min:0.001',
            'details.*.uom'           => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($item, $validated) {
            $item->update([
                'gr_date'    => $validated['gr_date'],
                'sloc_code'  => $validated['sloc_code'],
                'po_number'  => $validated['po_number'],
                'updated_by' => $this->userName(),
            ]);

            $item->details()->delete();
            foreach ($validated['details'] as $d) {
                GoodsReceiptDetail::create([
                    'company_id'         => $this->companyId(),
                    'gr_header_id'       => $item->id,
                    'material_code'      => $d['material_code'],
                    'material_name'      => $d['material_name'] ?? '',
                    'qty'                => $d['qty'],
                    'uom'                => $d['uom'],
                    'integration_status' => GoodsReceipt::STATUS_PENDING,
                ]);
            }

            AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
                "Updated Goods Receipt {$item->id}");
        });

        return redirect()->route('transactions.goods_receipt.index')
            ->with('success', 'Goods Receipt updated successfully.');
    }

    // ── DESTROY ───────────────────────────────────────────────────────────────
    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = GoodsReceipt::byCompany($this->companyId())->findOrFail($id);

        if (! $item->isEditable()) {
            return redirect()->route('transactions.goods_receipt.index')
                ->with('error', 'Goods Receipt already sent to SAP and cannot be deleted.');
        }

        DB::transaction(function () use ($item) {
            $item->details()->delete();
            $item->delete();
            AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
                "Deleted Goods Receipt {$item->id}");
        });

        return redirect()->route('transactions.goods_receipt.index')
            ->with('success', 'Goods Receipt deleted.');
    }

    // ── DETAIL ────────────────────────────────────────────────────────────────
    public function detail(string $id)
    {
        $item = GoodsReceipt::byCompany($this->companyId())->with('details')->findOrFail($id);
        return view('transaction.goods_receipt.detail', compact('item'));
    }

    // ── AJAX: PO Detail Lines ─────────────────────────────────────────────────
    public function poDetail(Request $request): JsonResponse
    {
        $poNumber = trim((string) $request->query('po_number', ''));
        if (empty($poNumber)) {
            return response()->json([]);
        }

        $poLines = PurchaseOrder::where('po_number', $poNumber)
            ->where('is_deleted', false)
            ->get();

        if ($poLines->isEmpty()) {
            return response()->json([]);
        }

        $result = [];
        foreach ($poLines as $line) {
            $qtyOrder = (float) ($line->qty_order ?? 0);

            // Sum of existing received quantity for this PO and material
            $receivedQty = (float) GoodsReceiptDetail::whereHas('header', function ($q) use ($poNumber) {
                $q->where('po_number', $poNumber);
            })->where('material_code', $line->material_code)->sum('qty');

            $remainingQty = max(0, round($qtyOrder - $receivedQty, 3));

            $result[] = [
                'material_code' => $line->material_code,
                'material_name' => $line->material_name ?? '',
                'qty_order'     => $qtyOrder,
                'received_qty'  => $receivedQty,
                'remaining_qty' => $remainingQty > 0 ? $remainingQty : $qtyOrder,
                'uom'           => $line->uom ?? '',
            ];
        }

        return response()->json($result);
    }

    // ── PRIVATE HELPERS ───────────────────────────────────────────────────────
    private function formData(): array
    {
        $plantCode = $this->plantCode();

        $slocsQuery = Sloc::query();
        if (! empty($plantCode)) {
            $slocsQuery->where('plant_code', $plantCode);
        }
        $slocs = $slocsQuery->orderBy('sloc_code')->get(['sloc_code', 'sloc_desc']);

        $poQuery = PurchaseOrder::where('is_deleted', false);
        if (! empty($plantCode)) {
            $poQuery->where('plant_code', $plantCode);
        }

        $purchaseOrders = $poQuery->select('po_number', 'vendor_name')
            ->distinct()
            ->orderBy('po_number')
            ->get();

        if ($purchaseOrders->isEmpty()) {
            $purchaseOrders = PurchaseOrder::where('is_deleted', false)
                ->select('po_number', 'vendor_name')
                ->distinct()
                ->orderBy('po_number')
                ->get();
        }

        return compact('slocs', 'purchaseOrders');
    }
}