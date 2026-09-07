<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\CoconutFdn;
use App\Models\Transaction\CoconutFdnDetail;
use App\Models\Transaction\CoconutOph;
use App\Models\Master\Employee;
use App\Models\Master\Vendor;
use App\Models\Master\Destination;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * FDN (Coconut) — t_coconut_fdn master-detail entry (Estate Staff).
 *
 * Dispatches harvested coconut (Harvesting Chit / coconut OPH) to a
 * destination. Header + coconut-OPH detail lines. total_customer_qty and
 * total_oph derive from the selected lines. Coconut-enabled companies only.
 */
class CoconutFdnController extends BaseController
{
    protected function routePrefix(): string { return 'transactions.delivery_note_coconut'; }
    protected function viewPrefix(): string  { return 'transaction.delivery_note_coconut'; }
    protected function title(): string       { return 'FDN (Coconut)'; }

    protected function datatableColumns(): array
    {
        return [
            'created_at'         => 'Date',
            'division_code'      => 'Division',
            'delivery_note'      => 'Delivery Note',
            'destination'        => 'Destination',
            'total_oph'          => 'Chit Count',
            'total_customer_qty' => 'Customer Qty',
            'integration_status' => 'SAP Status',
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
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $query = CoconutFdn::query()->actual()
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at');
        return DataTables::eloquent($query)->addIndexColumn()->make(true);
    }

    // ── CREATE / EDIT ──────────────────────────────────────────────────────────
    public function create(): View
    {
        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => null,
            'details'     => collect(),
        ], $this->formData()));
    }

    public function edit(string $id): View|RedirectResponse
    {
        $item = CoconutFdn::query()->whereKey($id)->first();
        abort_unless($item, 404);
        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => $item,
            'details'     => $item->details()->get(),
        ], $this->formData()));
    }

    // ── STORE / UPDATE / DESTROY ───────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->validateFdn($request);

        $details = $this->cleanDetails($request);
        if ($details === []) {
            return back()->withInput()->with('error', 'Select at least one Harvesting Chit line.');
        }

        $id = $this->generateId();
        DB::transaction(function () use ($id, $request, $details) {
            CoconutFdn::create(array_merge($this->mapHeader($request, $details), [
                'id'         => $id,
                'company_id' => $this->companyId(),
                'created_by' => $this->userName(),
                'updated_by' => $this->userName(),
            ]));
            $this->saveDetails($id, $details);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE, "Created {$this->title()} {$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' saved successfully.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $item = CoconutFdn::query()->whereKey($id)->first();
        abort_unless($item, 404);

        $this->validateFdn($request);
        $details = $this->cleanDetails($request);
        if ($details === []) {
            return back()->withInput()->with('error', 'Select at least one Harvesting Chit line.');
        }

        DB::transaction(function () use ($item, $request, $details) {
            $item->update(array_merge($this->mapHeader($request, $details), ['updated_by' => $this->userName()]));
            CoconutFdnDetail::where('coconut_fdn_id', $item->id)->delete();
            $this->saveDetails($item->id, $details);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE, "Updated {$this->title()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $item = CoconutFdn::query()->whereKey($id)->first();
        abort_unless($item, 404);

        DB::transaction(function () use ($item) {
            CoconutFdnDetail::where('coconut_fdn_id', $item->id)->delete();
            $item->delete();
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE, "Deleted {$this->title()} #{$id}");
        return redirect()->route($this->routePrefix() . '.index')->with('success', $this->title() . ' deleted.');
    }

    // ── AJAX: available coconut OPH (Harvesting Chit) lines ────────────────────
    public function availableChit(Request $request): JsonResponse
    {
        $division = trim((string) $request->query('division_code', ''));
        $rows = CoconutOph::query()->actual()
            ->when($division !== '', fn ($q) => $q->where('division_code', $division))
            ->orderByDesc('created_at')->limit(200)
            ->get(['id', 'oph_card_id', 'block_code', 'tph_code', 'nuts_total']);
        return response()->json(['data' => $rows]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════════════════

    protected function formData(): array
    {
        return [
            'divisions'    => \App\Models\Master\Division::byEstate($this->estateCode())
                                ->orderBy('division_code')->get(['division_code', 'division_name']),
            'employees'    => Employee::byEstate($this->estateCode())
                                ->orderBy('employee_code')->get(['employee_code', 'employee_name']),
            'vendors'      => Vendor::orderBy('vendor_code')->get(['vendor_code', 'vendor_name']),
            'destinations' => Destination::orderBy('destination_code')->get(['destination_code', 'destination_name']),
        ];
    }

    protected function validateFdn(Request $request): void
    {
        $request->validate([
            'division_code'         => 'required|string|max:50',
            'delivery_note'         => 'nullable|string|max:100',
            'destination'           => 'nullable|string|max:100',
            'fdn_card_id'           => 'nullable|string|max:100',
            'kerani_kirim_emp_code' => 'nullable|string|max:100',
            'vehicle_vendor_code'   => 'nullable|string|max:100',
            'driver_name'           => 'nullable|string|max:150',
            'license_number'        => 'nullable|string|max:100',
            'sales_order_no'        => 'nullable|string|max:100',
            'sales_order_item'      => 'nullable|string|max:50',
            'bruto'                 => 'nullable|numeric|min:0',
            'tarra'                 => 'nullable|numeric|min:0',
            'remark'                => 'nullable|string|max:255',
        ]);
    }

    protected function mapHeader(Request $request, array $details): array
    {
        $kerani = $request->kerani_kirim_emp_code
            ? Employee::where('employee_code', $request->kerani_kirim_emp_code)->first() : null;

        $bruto = $request->bruto !== null ? (float) $request->bruto : null;
        $tarra = $request->tarra !== null ? (float) $request->tarra : null;
        $totalQty = array_sum(array_column($details, 'total_customer_nut_qty'));

        return [
            'estate_code'           => $this->estateCode(),
            'division_code'         => trim($request->division_code),
            'delivery_note'         => $request->delivery_note ?: null,
            'destination'           => $request->destination ?: null,
            'fdn_card_id'           => $request->fdn_card_id ?: null,
            'receiving_point_code'  => $request->receiving_point_code ?: null,
            'kerani_kirim_emp_code' => $request->kerani_kirim_emp_code ?: null,
            'kerani_kirim_emp_name' => $kerani?->employee_name,
            'vehicle_vendor_code'   => $request->vehicle_vendor_code ?: null,
            'driver_name'           => $request->driver_name ?: null,
            'license_number'        => $request->license_number ?: null,
            'sales_order_no'        => $request->sales_order_no ?: null,
            'sales_order_item'      => $request->sales_order_item ?: null,
            'bruto'                 => $bruto,
            'tarra'                 => $tarra,
            'actual_tonnage'        => ($bruto !== null && $tarra !== null) ? round($bruto - $tarra, 3) : null,
            'total_oph'             => count($details),
            'total_customer_qty'    => $totalQty,
            'is_nursery'            => false,
            'is_stock'              => false,
            'is_closed'             => false,
            'closing_is_approved'   => false,
            'is_deleted'            => false,
            'adjustment_status'     => 0,
            'integration_status'    => 0,
            'remark'                => $request->remark ?: null,
        ];
    }

    protected function cleanDetails(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('details', []) as $d) {
            if (! is_array($d)) continue;
            $ophId = trim((string) ($d['coconut_oph_id'] ?? ''));
            if ($ophId === '') continue;
            $out[] = [
                'coconut_oph_id'         => $ophId,
                'coconut_oph_card_id'    => trim((string) ($d['coconut_oph_card_id'] ?? '')) ?: null,
                'total_customer_nut_qty' => (float) ($d['total_customer_nut_qty'] ?? 0),
            ];
        }
        return $out;
    }

    protected function saveDetails(string $fdnId, array $details): void
    {
        foreach ($details as $d) {
            CoconutFdnDetail::create(array_merge($d, [
                'company_id'         => $this->companyId(),
                'coconut_fdn_id'     => $fdnId,
                'integration_status' => 0,
            ]));
        }
    }

    protected function generateId(): string
    {
        return 'CFDN' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);
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
