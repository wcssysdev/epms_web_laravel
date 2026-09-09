<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\PlatformChecking;
use App\Models\Transaction\PlatformCheckingDetail;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Platform Checking entry (t_platform_checking + t_platform_checking_detail).
 * CI4 source was read-only (mobile data). Web entry added for role-4 completeness.
 * Header: date, block/tph, check_status, notes.
 * Detail: repeater rows of detail_type + detail_value (flexible key-value).
 */
class PlatformCheckingController extends BaseController
{
    protected function routePrefix(): string { return 'transactions.platform_checking'; }
    protected function viewPrefix(): string  { return 'transaction.platform_checking'; }
    protected function title(): string       { return 'Platform Checking'; }

    // ── INDEX / DATATABLE ──────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        [$from, $to] = $this->resolveRange($request);
        return view($this->viewPrefix() . '.index', [
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'columns'     => [
                'check_date'    => 'Date',
                'division_code' => 'Division',
                'block_code'    => 'Block',
                'tph_code'      => 'TPH',
                'check_status'  => 'Status',
                'notes'         => 'Notes',
            ],
            'from' => $from,
            'to'   => $to,
            'hasCsv' => false,
        ]);
    }

    public function getDatatable(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $query = PlatformChecking::query()
            ->whereBetween('check_date', [$from, $to])
            ->orderByDesc('check_date');
        return DataTables::eloquent($query)->addIndexColumn()->make(true);
    }

    // ── CREATE / EDIT ──────────────────────────────────────────────────────────
    public function create(): View
    {
        return view($this->viewPrefix() . '.form', array_merge(
            ['title' => $this->title(), 'routePrefix' => $this->routePrefix(), 'item' => null, 'details' => collect()],
            $this->formData()
        ));
    }

    public function edit(int $id): View|RedirectResponse
    {
        $item = PlatformChecking::with('details')->findOrFail($id);
        return view($this->viewPrefix() . '.form', array_merge(
            ['title' => $this->title(), 'routePrefix' => $this->routePrefix(), 'item' => $item, 'details' => $item->details],
            $this->formData()
        ));
    }

    // ── STORE / UPDATE ─────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->validateForm($request);

        DB::transaction(function () use ($request) {
            $pc = PlatformChecking::create($this->mapHeader($request));
            $this->saveDetails($pc->id, $request);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Created {$this->title()}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' saved successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $this->validateForm($request);

        $item = PlatformChecking::findOrFail($id);

        DB::transaction(function () use ($item, $request) {
            $item->update($this->mapHeader($request));
            PlatformCheckingDetail::where('platform_checking_id', $item->id)->delete();
            $this->saveDetails($item->id, $request);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
            "Updated {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' updated successfully.');
    }

    // ── DESTROY ─────────────────────────────────────────────────────────────
    public function destroy(int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $item = PlatformChecking::findOrFail($id);

        DB::transaction(function () use ($item) {
            PlatformCheckingDetail::where('platform_checking_id', $item->id)->delete();
            $item->delete();
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
            "Deleted {$this->title()} #{$id}");

        return redirect()->route($this->routePrefix() . '.index')
            ->with('success', $this->title() . ' deleted.');
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────
    private function validateForm(Request $request): void
    {
        $request->validate([
            'check_date'    => 'required|date',
            'division_code' => 'required|string|max:50',
            'block_code'    => 'nullable|string|max:50',
            'tph_code'      => 'nullable|string|max:50',
            'check_status'  => 'required|string|max:100',
            'notes'         => 'nullable|string|max:500',
        ]);
    }

    private function mapHeader(Request $request): array
    {
        return [
            'company_id'    => $this->companyId(),
            'estate_code'   => $this->estateCode(),
            'division_code' => trim($request->division_code),
            'block_code'    => $request->block_code ?: null,
            'tph_code'      => $request->tph_code ?: null,
            'check_date'    => $request->check_date,
            'check_status'  => trim($request->check_status),
            'notes'         => $request->notes ?: null,
            'created_by'    => $this->userName(),
            'updated_by'    => $this->userName(),
        ];
    }

    private function saveDetails(int $pcId, Request $request): void
    {
        $types  = (array) $request->input('detail_type', []);
        $values = (array) $request->input('detail_value', []);
        foreach ($types as $i => $type) {
            if (empty($type)) continue;
            PlatformCheckingDetail::create([
                'company_id'           => $this->companyId(),
                'platform_checking_id' => $pcId,
                'detail_type'          => trim($type),
                'detail_value'         => $values[$i] ?? '',
            ]);
        }
    }

    private function formData(): array
    {
        $estate = $this->estateCode();
        $today  = Carbon::today()->toDateString();
        return [
            'divisions' => \App\Models\Master\Division::byEstate($estate)
                              ->orderBy('division_code')
                              ->get(['division_code', 'division_name']),
            'blocks'    => \App\Models\Master\Block::where('estate_code', $estate)
                              ->whereDate('valid_from', '<=', $today)
                              ->whereDate('valid_to', '>=', $today)
                              ->orderBy('block_code')
                              ->get(['division_code', 'block_code', 'block_name']),
            'tphs'      => \App\Models\Master\Tph::where('estate_code', $estate)
                              ->orderBy('tph_code')
                              ->get(['block_code', 'tph_code']),
        ];
    }

    private function resolveRange(Request $request): array
    {
        $key    = $this->routePrefix() . '.range';
        $stored = session($key, []);
        $from   = $request->query('from', $stored['from'] ?? Carbon::today()->subDays(7)->toDateString());
        $to     = $request->query('to',   $stored['to']   ?? Carbon::today()->toDateString());
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
