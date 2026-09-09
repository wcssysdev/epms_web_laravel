<?php

namespace App\Http\Controllers\Closing;

use App\Http\Controllers\BaseController;
use App\Services\SapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Base controller for SAP Closing screens.
 *
 * Each concrete subclass handles one transaction type (Attendance, OPH, etc.)
 * and provides the table name, SAP URN/IM codes, field mapping, and any
 * query customisation needed to build the SAP payload.
 *
 * The index() shows records split into 5 status buckets per CI3:
 *   opened     → integration_status IN (-1, 3)
 *   locked     → integration_status = 0
 *   success    → integration_status = 2
 *   failed     → integration_status = 4
 *   adjustment → integration_status = 5
 *
 * Actions:
 *   POST closing/   → lock + send to SAP
 *   POST lock/      → lock only (set status 0)
 *   POST relock/    → relock adjustment records (5 → 0)
 */
abstract class BaseClosingController extends BaseController
{
    abstract protected function table(): string;
    abstract protected function pkColumn(): string;
    abstract protected function dateColumn(): string;
    abstract protected function sapUrn(): string;
    abstract protected function sapIm(): string;
    abstract protected function routePrefix(): string;
    abstract protected function viewPrefix(): string;
    abstract protected function title(): string;

    /**
     * Build SAP item rows from raw DB rows. Each row must include UNIQUE_ID.
     * Subclasses override this to alias columns to SAP field names.
     */
    abstract protected function buildSapItems(array $rows, array $config): array;

    /** Whether UNIQUE_ID sent to SAP is prefixed with estate_code (attendance, overtime). */
    protected function stripEstatePrefix(): bool { return false; }

    /** Whether closing requires closing_is_approved = 1 (attendance, workdone, etc.). */
    protected function requiresClosingApproval(): bool { return false; }

    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request);

        $get = fn(int|string $status) => $this->queryForStatus($date, $status);

        $opened     = array_merge($get(-1), $get(3));
        $locked     = $get(0);
        $success    = $get(2);
        $failed     = $get(4);
        $adjustment = $get(5);

        return view($this->viewPrefix() . '.index', [
            'title'       => $this->title(),
            'routePrefix' => $this->routePrefix(),
            'date'        => $date,
            'opened'      => $opened,
            'locked'      => $locked,
            'success'     => $success,
            'failed'      => $failed,
            'adjustment'  => $adjustment,
        ]);
    }

    // ── CLOSING (lock + send to SAP) ─────────────────────────────────────────
    public function closing(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $ids  = (array) $request->input('ids', []);
        $date = $request->input('date', today()->toDateString());

        if (empty($ids)) {
            return back()->with('error', 'No records selected.');
        }

        $cfg = $this->companyConfig()?->getAttributes() ?? [];
        $sap = SapService::forCompany($this->companyId());

        // Lock first
        $reqId = SapService::requestId($this->userId());
        $sap->lock($this->table(), $this->pkColumn(), $ids, $reqId);

        // Build SAP payload
        $rows  = $this->fetchForSap($ids, $cfg);
        $items = $this->buildSapItems($rows, $cfg);

        $result = $sap->send(
            $items,
            $this->sapUrn(),
            $this->sapIm(),
            $this->table(),
            $this->pkColumn(),
            'UNIQUE_ID',
            $this->stripEstatePrefix()
        );

        if ($result['error']) {
            return redirect()->route($this->routePrefix() . '.index', ['date' => $date])
                ->with('error', 'SAP call failed: ' . $result['error']);
        }

        return redirect()->route($this->routePrefix() . '.index', ['date' => $date])
            ->with('success', "Sent {$result['sent']} records — {$result['success']} success, {$result['failed']} failed.");
    }

    // ── LOCK ONLY (no SAP call) ──────────────────────────────────────────────
    public function lock(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $ids  = (array) $request->input('ids', []);
        $date = $request->input('date', today()->toDateString());

        if (empty($ids)) return back()->with('error', 'No records selected.');

        $sap = SapService::forCompany($this->companyId());
        $sap->lock($this->table(), $this->pkColumn(), $ids, SapService::requestId($this->userId()));

        return redirect()->route($this->routePrefix() . '.index', ['date' => $date])
            ->with('success', count($ids) . ' record(s) locked.');
    }

    // ── RELOCK (adjustment → locked) ─────────────────────────────────────────
    public function relock(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $ids  = (array) $request->input('ids', []);
        $date = $request->input('date', today()->toDateString());

        if (empty($ids)) return back()->with('error', 'No records selected.');

        SapService::forCompany($this->companyId())->relock($this->table(), $this->pkColumn(), $ids);

        return redirect()->route($this->routePrefix() . '.index', ['date' => $date])
            ->with('success', count($ids) . ' record(s) relocked for resubmission.');
    }

    // ── HELPERS ───────────────────────────────────────────────────────────────
    protected function queryForStatus(string $date, int|string $status): array
    {
        $q = DB::table($this->table())
            ->where($this->dateColumn(), Carbon::parse($date)->toDateString())
            ->where('integration_status', $status);

        if ($this->requiresClosingApproval() && $status != SapService::STATUS_ADJUSTMENT) {
            $q->where('closing_is_approved', 1);
        }

        return $q->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
                 ->get()->toArray();
    }

    /** Fetch raw rows for building SAP payload. Subclasses may override for joins. */
    protected function fetchForSap(array $ids, array $config): array
    {
        return DB::table($this->table())
            ->whereIn($this->pkColumn(), $ids)
            ->get()->map(fn($r) => (array) $r)->all();
    }

    protected function resolveDate(Request $request): array
    {
        $key  = $this->routePrefix() . '.date';
        $date = $request->query('date', session($key, today()->toDateString()));
        try { $date = Carbon::parse($date)->toDateString(); } catch (\Throwable $e) { $date = today()->toDateString(); }
        session([$key => $date]);
        return [$date];
    }

    protected function companyConfig(): ?\App\Models\Global\CompanyConfig
    {
        return \App\Models\Global\CompanyConfig::where('company_id', $this->companyId())->first();
    }
}
