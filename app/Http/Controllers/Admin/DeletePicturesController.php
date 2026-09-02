<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeletePicturesController extends BaseController
{
    /**
     * Transaction tables that have photo columns.
     */
    private function photoTables(): array
    {
        return [
            ['table' => 't_oph',         'photo_col' => 'photo',        'id_col' => 'id',          'label' => 'OPH (Palm)',          'date_col' => 'created_at'],
            ['table' => 't_cp',          'photo_col' => 'photo',        'id_col' => 'id',          'label' => 'Checkpoint (CP)',      'date_col' => 'created_at'],
            ['table' => 't_fdn',         'photo_col' => 'photo',        'id_col' => 'id',          'label' => 'FDN (Palm)',           'date_col' => 'created_at'],
            ['table' => 't_coconut_oph', 'photo_col' => 'photo',        'id_col' => 'id',          'label' => 'OPH (Coconut)',        'date_col' => 'created_at'],
            ['table' => 't_coconut_fdn', 'photo_col' => 'photo',        'id_col' => 'id',          'label' => 'FDN (Coconut)',        'date_col' => 'created_at'],
        ];
    }

    public function index(): View
    {
        $stats = [];
        foreach ($this->photoTables() as $item) {
            $total = DB::table($item['table'])
                ->where('company_id', $this->companyId())
                ->whereNotNull($item['photo_col'])
                ->where($item['photo_col'], '!=', '')
                ->count();

            $stats[] = [
                'table'     => $item['table'],
                'label'     => $item['label'],
                'photo_col' => $item['photo_col'],
                'total'     => $total,
            ];
        }

        return view('admin.delete_pictures.index', compact('stats'));
    }

    // Get picture count for a table + date range
    public function count(Request $request): JsonResponse
    {
        $request->validate([
            'table'     => 'required|string',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $tableMap = collect($this->photoTables())->keyBy('table');
        $item     = $tableMap->get($request->table);

        if (!$item) {
            return $this->jsonError('Invalid table.');
        }

        $query = DB::table($item['table'])
            ->where('company_id', $this->companyId())
            ->whereNotNull($item['photo_col'])
            ->where($item['photo_col'], '!=', '');

        if ($request->date_from) $query->whereDate($item['date_col'], '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate($item['date_col'], '<=', $request->date_to);

        return $this->jsonSuccess('OK', ['count' => $query->count()]);
    }

    // Soft delete — clear the photo path from DB (does NOT delete the actual file)
    public function delete(Request $request): JsonResponse
    {
        $request->validate([
            'table'     => 'required|string',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        if ($this->isSystemLocked() && $this->roleLevel() > 30) {
            return $this->jsonError('System is locked.');
        }

        $tableMap = collect($this->photoTables())->keyBy('table');
        $item     = $tableMap->get($request->table);

        if (!$item) {
            return $this->jsonError('Invalid table.');
        }

        $query = DB::table($item['table'])
            ->where('company_id', $this->companyId())
            ->whereNotNull($item['photo_col'])
            ->where($item['photo_col'], '!=', '');

        if ($request->date_from) $query->whereDate($item['date_col'], '>=', $request->date_from);
        if ($request->date_to)   $query->whereDate($item['date_col'], '<=', $request->date_to);

        $count = $query->count();

        // Clear photo field (soft delete — only removes DB reference)
        $query->update([
            $item['photo_col'] => null,
            'updated_at'       => now(),
        ]);

        AuditService::log(
            AuditService::TYPE_SYSTEM,
            AuditService::ACTION_DELETE,
            "Deleted {$count} picture references from {$item['label']} by {$this->userName()}"
        );

        return $this->jsonSuccess("Cleared {$count} picture reference(s) from {$item['label']}.", [
            'deleted' => $count,
            'table'   => $item['table'],
        ]);
    }
}
