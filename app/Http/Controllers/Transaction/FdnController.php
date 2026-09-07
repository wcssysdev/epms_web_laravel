<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\Fdn;
use App\Models\Transaction\FdnDetail;
use App\Models\Transaction\FdnLoader;
use App\Models\Transaction\Oph;
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
 * FDN / Delivery Note (t_fdn) master-detail entry — Estate Staff CRUD.
 *
 * A delivery note dispatches one or more delivered OPH records
 * (t_fdn_detail) to a destination, with optional loaders (t_fdn_loader).
 * Header totals derive from the selected OPH lines. Mirrors the CP flow.
 */
class FdnController extends BaseController
{
    protected function routePrefix(): string { return 'transactions.delivery_note'; }
    protected function viewPrefix(): string  { return 'transaction.delivery_note'; }
    protected function title(): string       { return 'FDN (Delivery Note)'; }

    protected function datatableColumns(): array
    {
        return [
            'created_at'          => 'Date',
            'division_code'       => 'Division',
            'delivery_note'       => 'Delivery Note',
            'deliver_to_name'     => 'Destination',
            'total_oph'           => 'OPH Count',
            'total_bunches'       => 'Bunches',
            'integration_status'  => 'SAP Status',
        ];
    }

    // ── INDEX ─────────────────────────────────────────────────────────────────
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

        $query = Fdn::query()->actual()
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at');

        return DataTables::eloquent($query)->addIndexColumn()->make(true);
    }

    // ── CREATE ──────────────────────────────────────────────────────────────
    public function create(): View
    {
        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => null,
            'details'     => collect(),
            'loaders'     => collect(),
        ], $this->formData()));
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->validateFdn($request);

        $details = $this->cleanDetails($request);
        if ($details === []) {
            return back()->withInput()->with('error', 'Select at least one OPH line.');
        }
        $loaders = $this->cleanLoaders($request);

        $id = $this->generateId();

        DB::transaction(function () use ($id, $request, $details, $loaders) {
            Fdn::create(array_merge($this->mapHeader($request, $details), [
                'id'         => $id,
                'company_id' => $this->companyId(),
                'fdn_type'   => 1,
                'created_by' => $this->userName(),
                'updated_by' => $this->userName(),
            ]));
            $this->saveDetails($id, $details);
            $this->saveLoaders($id, $loaders);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE, "Created {$this->title()} {$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' saved successfully.');
    }

    // ── EDIT ──────────────────────────────────────────────────────────────────
    public function edit(string $id): View|RedirectResponse
    {
        $item = Fdn::query()->whereKey($id)->first();
        abort_unless($item, 404);

        return view($this->viewPrefix() . '.form', array_merge([
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'item'        => $item,
            'details'     => $item->details()->get(),
            'loaders'     => $item->loaders()->get(),
        ], $this->formData()));
    }

    // ── UPDATE ──────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = Fdn::query()->whereKey($id)->first();
        abort_unless($item, 404);

        $this->validateFdn($request);

        $details = $this->cleanDetails($request);
        if ($details === []) {
            return back()->withInput()->with('error', 'Select at least one OPH line.');
        }
        $loaders = $this->cleanLoaders($request);

        DB::transaction(function () use ($item, $request, $details, $loaders) {
            $item->update(array_merge($this->mapHeader($request, $details), ['updated_by' => $this->userName()]));
            FdnDetail::where('fdn_id', $item->id)->delete();
            FdnLoader::where('fdn_id', $item->id)->delete();
            $this->saveDetails($item->id, $details);
            $this->saveLoaders($item->id, $loaders);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE, "Updated {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' updated successfully.');
    }

    // ── DESTROY ─────────────────────────────────────────────────────────────
    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $item = Fdn::query()->whereKey($id)->first();
        abort_unless($item, 404);

        DB::transaction(function () use ($item) {
            FdnDetail::where('fdn_id', $item->id)->delete();
            FdnLoader::where('fdn_id', $item->id)->delete();
            $item->delete();
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE, "Deleted {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' deleted.');
    }

    // ── AJAX: available OPH lines for a division ───────────────────────────────
    public function availableOph(Request $request): JsonResponse
    {
        $division = trim((string) $request->query('division_code', ''));

        $rows = Oph::query()->actual()
            ->when($division !== '', fn ($q) => $q->where('division_code', $division))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'oph_card_id', 'block_code', 'tph_code', 'platform_no', 'bunches_total', 'loose_fruits']);

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
            'destinations' => Destination::orderBy('destination_code')
                                ->get(['destination_code', 'destination_name']),
        ];
    }

    protected function validateFdn(Request $request): void
    {
        $request->validate([
            'division_code'         => 'required|string|max:50',
            'delivery_note'         => 'nullable|string|max:100',
            'deliver_to_code'       => 'nullable|string|max:50',
            'fdn_card_id'           => 'nullable|string|max:100',
            'kerani_kirim_emp_code' => 'nullable|string|max:100',
            'vendor_code'           => 'nullable|string|max:100',
            'driver_name'           => 'nullable|string|max:150',
            'license_number'        => 'nullable|string|max:100',
            'seal_code'             => 'nullable|string|max:100',
            'bin_number'            => 'nullable|string|max:100',
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
        $vendor = $request->vendor_code
            ? Vendor::where('vendor_code', $request->vendor_code)->first() : null;
        $dest   = $request->deliver_to_code
            ? Destination::where('destination_code', $request->deliver_to_code)->first() : null;

        $totalBunches = array_sum(array_column($details, 'bunches_delivered'));
        $totalLf      = array_sum(array_column($details, 'loose_fruit_delivered'));
        $bruto        = $request->bruto !== null ? (float) $request->bruto : null;
        $tarra        = $request->tarra !== null ? (float) $request->tarra : null;

        return [
            'fdn_card_id'           => $request->fdn_card_id ?: null,
            'estate_code'           => $this->estateCode(),
            'division_code'         => trim($request->division_code),
            'delivery_note'         => $request->delivery_note ?: null,
            'deliver_to_code'       => $request->deliver_to_code ?: null,
            'deliver_to_name'       => $dest?->destination_name,
            'driver_name'           => $request->driver_name ?: null,
            'license_number'        => $request->license_number ?: null,
            'seal_code'             => $request->seal_code ?: null,
            'bin_number'            => $request->bin_number ?: null,
            'kerani_kirim_emp_code' => $request->kerani_kirim_emp_code ?: null,
            'kerani_kirim_emp_name' => $kerani?->employee_name,
            'vendor_code'           => $request->vendor_code ?: null,
            'vendor_name'           => $vendor?->vendor_name,
            'transporter'           => $request->transporter ?: null,
            'sales_order_no'        => $request->sales_order_no ?: null,
            'sales_order_item'      => $request->sales_order_item ?: null,
            'bruto'                 => $bruto,
            'tarra'                 => $tarra,
            'actual_tonnage'        => ($bruto !== null && $tarra !== null) ? round($bruto - $tarra, 3) : null,
            'total_bunches'         => $totalBunches,
            'total_oph'             => count($details),
            'total_loose_fruit'     => $totalLf,
            'remark'                => $request->remark ?: null,
            'is_deleted'            => false,
        ];
    }

    protected function cleanDetails(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('details', []) as $d) {
            if (! is_array($d)) continue;
            $ophId = trim((string) ($d['oph_id'] ?? ''));
            if ($ophId === '') continue;
            $out[] = [
                'oph_id'                => $ophId,
                'oph_block_code'        => trim((string) ($d['oph_block_code'] ?? '')),
                'oph_tph_code'          => trim((string) ($d['oph_tph_code'] ?? '')),
                'oph_card_id'           => trim((string) ($d['oph_card_id'] ?? '')) ?: null,
                'bunches_delivered'     => (int) ($d['bunches_delivered'] ?? 0),
                'loose_fruit_delivered' => (float) ($d['loose_fruit_delivered'] ?? 0),
            ];
        }
        return $out;
    }

    protected function cleanLoaders(Request $request): array
    {
        $out = [];
        foreach ((array) $request->input('loaders', []) as $l) {
            if (! is_array($l)) continue;
            $code = trim((string) ($l['employee_code'] ?? ''));
            if ($code === '') continue;
            $out[] = ['employee_code' => $code, 'percentage' => (float) ($l['percentage'] ?? 0)];
        }
        return $out;
    }

    protected function saveDetails(string $fdnId, array $details): void
    {
        foreach ($details as $d) {
            FdnDetail::create(array_merge($d, [
                'company_id'         => $this->companyId(),
                'fdn_id'             => $fdnId,
                'detail_type'        => 1,
                'integration_status' => 0,
            ]));
        }
    }

    protected function saveLoaders(string $fdnId, array $loaders): void
    {
        foreach ($loaders as $l) {
            FdnLoader::create([
                'company_id'         => $this->companyId(),
                'fdn_id'             => $fdnId,
                'employee_code'      => $l['employee_code'],
                'employee_name'      => Employee::where('employee_code', $l['employee_code'])->value('employee_name') ?? '',
                'percentage'         => $l['percentage'],
                'loader_type'        => 1,
                'integration_status' => 0,
            ]);
        }
    }

    protected function generateId(): string
    {
        return 'FDN' . $this->estateCode() . now()->format('YmdHis') . random_int(100, 999);
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
