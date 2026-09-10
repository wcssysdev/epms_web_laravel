<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HarvestingController extends BaseController
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get blocks based on user role
            if ($user->role_code === 'assistant_manager') {
                // Assistant Manager sees only their assigned divisions' blocks
                $blocks = DB::table('m_block')
                    ->join('m_assistant_manager_division', 'm_assistant_manager_division.assistant_manager_division_code', '=', 'm_block.block_division_code')
                    ->where('m_assistant_manager_division.assistant_manager_code', $user->id)
                    ->orderBy('m_block.block_code')
                    ->select('m_block.*')
                    ->get();
            } else {
                // Admin, Estate Manager, Estate Staff see all blocks
                $blocks = DB::table('m_block')
                    ->orderBy('block_code')
                    ->get();
            }
        
        // Default date range
        $from = $request->input('from', date('Y-m-d'));
        $to = $request->input('to', date('Y-m-d'));
        $blockCode = $request->input('block_code', 'all');
        
        $stats = [
            'in_field' => 0,
            'in_ramp' => 0,
            'in_fdn' => 0,
            'total' => 0,
        ];
        
        // Only calculate stats if form submitted
        if ($request->has('form_submit')) {
            $stats = $this->calculateHarvestingStats($user, $from, $to, $blockCode);
        }
        
        return view('dashboard.harvesting.index', compact('blocks', 'from', 'to', 'blockCode', 'stats'));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }
    
    protected function calculateHarvestingStats($user, $from, $to, $blockCode)
    {
        // Base query
        $baseQuery = DB::table('t_oph')
            ->leftJoin('t_cp_detail', function($join) {
                $join->on('t_oph.oph_id', '=', 't_cp_detail.cp_oph_id')
                     ->where('t_cp_detail.cp_detail_type', '=', 2);
            })
            ->leftJoin('t_fdn_detail', 't_oph.oph_id', '=', 't_fdn_detail.fdn_oph_id')
            ->whereBetween(DB::raw('DATE(t_oph.created_at)'), [$from, $to])
            ->where('t_oph.is_deleted', false);
        
        // Apply block filter
        if ($blockCode !== 'all') {
            $baseQuery->where('t_oph.block_code', $blockCode);
        }
        
        // Apply role-based filter for Assistant Manager viewing "all"
        if ($blockCode === 'all' && $user->role_code === 'assistant_manager') {
            $baseQuery->join('m_assistant_manager_division', 'm_assistant_manager_division.assistant_manager_division_code', '=', 't_oph.division_code')
                      ->where('m_assistant_manager_division.assistant_manager_code', $user->id);
        }
        
        // In Field: No checkpoint, no FDN
        $inField = (clone $baseQuery)
            ->whereNull('t_cp_detail.cp_id')
            ->whereNull('t_fdn_detail.fdn_id')
            ->sum('t_oph.bunches_total') ?? 0;
        
        // In Ramp: Has checkpoint, no FDN
        $inRamp = (clone $baseQuery)
            ->whereNotNull('t_cp_detail.cp_id')
            ->whereNull('t_fdn_detail.fdn_id')
            ->sum('t_oph.bunches_total') ?? 0;
        
        // In FDN: Has both checkpoint and FDN
        $inFdn = (clone $baseQuery)
            ->whereNotNull('t_cp_detail.cp_id')
            ->whereNotNull('t_fdn_detail.fdn_id')
            ->sum('t_oph.bunches_total') ?? 0;
        
        return [
            'in_field' => $inField,
            'in_ramp' => $inRamp,
            'in_fdn' => $inFdn,
            'total' => $inField + $inRamp + $inFdn,
        ];
    }
}
