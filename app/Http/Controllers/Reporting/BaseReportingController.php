<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

abstract class BaseReportingController extends BaseController
{
    /**
     * Get date from request or default to today
     */
    protected function getRequestDate(Request $request, string $field, string $default = null): string
    {
        $value = $request->input($field);
        
        if ($value) {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e) {
                // Invalid date, fall through to default
            }
        }
        
        return $default ?? now()->format('Y-m-d');
    }

    /**
     * Get date range from request (from/to)
     */
    protected function getDateRange(Request $request): array
    {
        return [
            'from' => $this->getRequestDate($request, 'from'),
            'to' => $this->getRequestDate($request, 'to'),
        ];
    }

    /**
     * Get divisions accessible by current user
     */
    protected function getAccessibleDivisions(): array
    {
        $user = Auth::user();
        
        // Assistant Manager sees only assigned divisions
        if ($user->role_code === 'assistant_manager' || $user->role_code === 'asst_manager') {
            return DB::table('m_assistant_manager_division')
                ->where('assistant_manager_code', $user->id)
                ->select('assistant_manager_division_code as division_code', 'assistant_manager_division_name as division_name')
                ->orderBy('assistant_manager_division_name')
                ->get()
                ->toArray();
        }
        
        // Estate Manager and Estate Staff see all divisions
        return DB::table('m_division')
            ->orderBy('division_name')
            ->select('division_code', 'division_name')
            ->get()
            ->toArray();
    }

    /**
     * Get blocks by division
     */
    protected function getBlocksByDivision(string $divisionCode = null): array
    {
        $query = DB::table('m_block')
            ->orderBy('block_code')
            ->select('block_code', 'block_name');
        
        // Note: block-division filtering removed due to schema differences
        // Blocks are shown for all divisions for now
        
        return $query->get()->toArray();
    }

    /**
     * Get requested division from request
     */
    protected function getRequestedDivision(Request $request): string
    {
        $divisionCode = $request->input('division_code');
        $user = Auth::user();
        
        // For non-Assistant Manager, allow 'ALL' or specific division
        if ($user->role_code !== 'assistant_manager' && $user->role_code !== 'asst_manager') {
            return $divisionCode ?: 'ALL';
        }
        
        // For Assistant Manager, return requested or first assigned division
        if ($divisionCode) {
            // Verify they have access
            $hasAccess = DB::table('m_assistant_manager_division')
                ->where('assistant_manager_code', $user->id)
                ->where('assistant_manager_division_code', $divisionCode)
                ->exists();
            
            if ($hasAccess) {
                return $divisionCode;
            }
        }
        
        // Return first assigned division
        $firstDivision = DB::table('m_assistant_manager_division')
            ->where('assistant_manager_code', $user->id)
            ->value('assistant_manager_division_code');
        
        return $firstDivision ?? '';
    }

    /**
     * Get requested block from request
     */
    protected function getRequestedBlock(Request $request): string
    {
        return $request->input('block_code', 'ALL');
    }

    /**
     * Build base query with role-based filtering
     */
    protected function applyRoleFilters($query, string $tableName = 't_workdone')
    {
        $user = Auth::user();
        
        // Assistant Manager sees only their assigned divisions
        if ($user->role_code === 'assistant_manager' || $user->role_code === 'asst_manager') {
            $query->join('m_assistant_manager_division', 
                'm_assistant_manager_division.assistant_manager_division_code', 
                '=', 
                "{$tableName}.division_code")
                ->where('m_assistant_manager_division.assistant_manager_code', $user->id);
        }
        
        return $query;
    }

    /**
     * Export data to CSV
     */
    protected function exportToCsv(array $data, array $headers, string $filename)
    {
        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

    /**
     * Standard filters for report views
     */
    protected function getStandardFilters(Request $request): array
    {
        $dateRange = $this->getDateRange($request);
        $divisionCode = $this->getRequestedDivision($request);
        $blockCode = $this->getRequestedBlock($request);
        
        return [
            'from' => $dateRange['from'],
            'to' => $dateRange['to'],
            'division_code' => $divisionCode,
            'block_code' => $blockCode,
            'divisions' => $this->getAccessibleDivisions(),
            'blocks' => $this->getBlocksByDivision($divisionCode !== 'ALL' ? $divisionCode : null),
        ];
    }

    /**
     * Check if user has access to view reports
     */
    protected function authorize()
    {
        $user = Auth::user();
        $allowedRoles = ['estate_manager', 'asst_manager', 'assistant_manager', 'estate_staff'];
        
        if (!in_array($user->role_code, $allowedRoles)) {
            abort(403, 'Unauthorized access to reporting module');
        }
    }
}
