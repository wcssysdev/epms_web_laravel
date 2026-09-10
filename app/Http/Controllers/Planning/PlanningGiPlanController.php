<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Planning GI Plan (Estate Manager role).
 * 
 * GI Plan = Goods Issue plan (rencana pengeluaran material).
 * Structure: tr_gi_header (1) → tr_gi_detail (N).
 * Status approval: NULL=draft, 0=pending (submitted), 1=approved, -1=rejected.
 * Assistant Manager melakukan approval via Approval\ApprovalGiPlanController.
 */
class PlanningGiPlanController extends BaseController
{
    public function index(Request $request)
    {
        [$date] = $this->resolveDate($request, 'planning.gi_plan.date', '+1 day');

        // Note: is_approved is BOOLEAN in tr_gi_header (not integer like other planning tables)
        // NULL=draft, false=pending, true=approved
        $drafted = $this->queryGrouped($date, null);
        $submitted = $this->queryGrouped($date, false); // pending
        $approved = $this->queryGrouped($date, true);

        return view('planning.gi_plan.index', [
            'title' => 'GI Plan',
            'date' => $date,
            'drafted' => $drafted,
            'submitted' => $submitted,
            'approved' => $approved,
            'rejected' => [], // GI Plan tidak support rejected status
        ]);
    }

    public function detail(Request $request)
    {
        $headerId = $request->query('id');

        if (!$headerId) {
            return redirect()->route('planning.gi_plan.index')
                ->with('error', 'Missing GI Plan ID.');
        }

        $header = DB::table('tr_gi_header')
            ->where('id', $headerId)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->first();

        if (!$header) {
            return redirect()->route('planning.gi_plan.index')
                ->with('error', 'GI Plan not found.');
        }

        $details = DB::table('tr_gi_detail')
            ->where('gi_header_id', $headerId)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->orderBy('material_code')
            ->get();

        return view('planning.gi_plan.detail', [
            'title' => 'GI Plan Detail',
            'header' => $header,
            'details' => $details,
        ]);
    }

    public function submitForApproval(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $headerId = $request->input('id');

        // Set to false = pending approval (boolean type)
        $updated = DB::table('tr_gi_header')
            ->where('id', $headerId)
            ->when($this->companyId(), fn($q) => $q->where('company_id', $this->companyId()))
            ->whereNull('is_approved')
            ->update(['is_approved' => false]);

        if ($updated === 0) {
            return redirect()->route('planning.gi_plan.index')
                ->with('error', 'No draft GI Plan found to submit.');
        }

        return redirect()->route('planning.gi_plan.index')
            ->with('success', 'GI Plan submitted for approval.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function queryGrouped(string $date, ?bool $approvalStatus): array
    {
        $q = DB::table('tr_gi_header as h')
            ->selectRaw('h.id, h.gi_date, h.estate_code, h.plant_code, h.gi_document_number, h.created_by, 
                         (SELECT COUNT(*) FROM tr_gi_detail WHERE gi_header_id=h.id) as detail_count')
            ->where('h.gi_date', Carbon::parse($date)->toDateString())
            ->when($this->companyId(), fn($q) => $q->where('h.company_id', $this->companyId()))
            ->orderBy('h.estate_code');

        if ($approvalStatus === null) {
            $q->whereNull('h.is_approved');
        } else {
            $q->where('h.is_approved', $approvalStatus);
        }

        return $q->get()->toArray();
    }

    private function resolveDate(Request $request, string $sessionKey, string $defaultOffset = '0 day'): array
    {
        $date = $request->query('date', session($sessionKey, Carbon::today()->modify($defaultOffset)->toDateString()));
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            $date = Carbon::today()->modify($defaultOffset)->toDateString();
        }
        session([$sessionKey => $date]);
        return [$date];
    }
}
