<?php

namespace App\Http\Controllers\Planning;

use App\Http\Controllers\BaseController;
use App\Models\Transaction\Workplan;
use App\Models\Transaction\WorkplanMaterial;
use App\Models\Master\Division;
use App\Models\Master\Block;
use App\Models\Master\Activity;
use App\Models\Master\Material;
use App\Models\Master\AssistantManagerDivision;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Workplan planning module.
 *
 * Roles: Assistant Manager (50) + Estate Manager (40).
 * Assistant Managers create/edit plans for their assigned division;
 * plans move draft → published → approved/rejected.
 */
class WorkplanController extends BaseController
{
    // ── INDEX ─────────────────────────────────────────────────────────────────
    public function index(Request $request): View
    {
        // Default to tomorrow (planning is forward-looking), remember chosen date in session
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))
            : Carbon::parse(session('workplan_date', Carbon::tomorrow()->toDateString()));
        session(['workplan_date' => $date->toDateString()]);

        $base = Workplan::forDate($date->toDateString())
            ->createdBy($this->userName())
            ->orderByDesc('created_at');

        // Split by status for the tabbed view
        $drafts    = (clone $base)->draft()->get();
        $published = (clone $base)->published()->get();
        $approved  = (clone $base)->approved()->get();
        $rejected  = (clone $base)->rejected()->get();

        return view('planning.workplan.index', [
            'date'      => $date,
            'drafts'    => $drafts,
            'published' => $published,
            'approved'  => $approved,
            'rejected'  => $rejected,
        ]);
    }

    // ── CREATE form ─────────────────────────────────────────────────────────
    public function create(): View
    {
        return view('planning.workplan.form', [
            'workplan'         => null,
            'divisions'        => $this->divisionsForUser(),
            'activityGroups'   => $this->activityGroups(),
            'defaultDivision'  => $this->defaultDivisionForUser(),
            'materials'        => [],
            'materialsJson'    => [],
            'date'             => session('workplan_date', Carbon::tomorrow()->toDateString()),
        ]);
    }

    // ── STORE ─────────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $validated = $this->validateWorkplan($request);
        $materials = $this->cleanMaterials($request->input('materials', []));

        // Validate materials rows (code + qty)
        foreach ($materials as $i => $m) {
            $request->validate([
                "materials.$i.material_code" => 'required|string|max:100',
                "materials.$i.qty"           => 'required|numeric|min:0',
            ]);
        }

        // Optional HA guard: target cannot exceed block hectarage
        if ($over = $this->guardHectarage($validated)) return $over;

        $activity = Activity::where('activity_code', $validated['activity_code'])->first();
        $workplanId = $this->generateWorkplanId();

        DB::transaction(function () use ($workplanId, $validated, $materials, $activity, $request) {
            Workplan::create([
                'id'                   => $workplanId,
                'company_id'           => $this->companyId(),
                'workplan_date'        => $validated['workplan_date'],
                'estate_code'          => $this->estateCode(),
                'division_code'        => $validated['division_code'],
                'activity_code'        => $validated['activity_code'],
                'activity_name'        => $activity?->activity_name ?? '',
                'block_code'           => $validated['block_code'] ?? null,
                'order_number'         => $validated['order_number'] ?? null,
                'auc_number'           => $validated['auc_number'] ?? null,
                'cost_center'          => $validated['cost_center'] ?? null,
                'mandor_employee_code' => $validated['mandor_employee_code'] ?? null,
                'mandor_employee_name' => $validated['mandor_employee_name'] ?? null,
                'total_hk'             => $validated['total_hk'],
                'total_qty_target'     => $validated['total_qty_target'],
                'is_approved'          => $request->input('action') === 'publish'
                                            ? Workplan::STATUS_PUBLISHED
                                            : Workplan::STATUS_DRAFT,
                'is_closed'            => false,
                'created_by'           => $this->userName(),
                'updated_by'           => $this->userName(),
            ]);

            $this->saveMaterials($workplanId, $materials);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_CREATE,
            "Created workplan {$workplanId}");

        return redirect()->route('planning.workplan.index')
            ->with('success', 'Workplan saved successfully.');
    }

    // ── EDIT form ─────────────────────────────────────────────────────────────
    public function edit(string $id): View|RedirectResponse
    {
        $workplan = Workplan::where('id', $id)->first();
        abort_unless($workplan, 404);

        if (! $workplan->isEditable()) {
            return redirect()->route('planning.workplan.index')
                ->with('error', 'Only draft or rejected workplans can be edited.');
        }

        $materials = $workplan->materials()->orderBy('id')->get();

        return view('planning.workplan.form', [
            'workplan'        => $workplan,
            'divisions'       => $this->divisionsForUser(),
            'activityGroups'  => $this->activityGroups(),
            'defaultDivision' => $workplan->division_code,
            'materials'       => $materials,
            'materialsJson'   => $materials->map(fn ($m) => [
                'material_code' => $m->material_code ?? '',
                'material_name' => $m->material_name ?? '',
                'qty'           => $m->qty ?? '',
            ])->values(),
            'date'            => $workplan->workplan_date->toDateString(),
        ]);
    }

    // ── UPDATE ──────────────────────────────────────────────────────────────
    public function update(Request $request, string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $workplan = Workplan::where('id', $id)->first();
        abort_unless($workplan, 404);

        if (! $workplan->isEditable()) {
            return redirect()->route('planning.workplan.index')
                ->with('error', 'Only draft or rejected workplans can be edited.');
        }

        $validated = $this->validateWorkplan($request);
        $materials = $this->cleanMaterials($request->input('materials', []));

        foreach ($materials as $i => $m) {
            $request->validate([
                "materials.$i.material_code" => 'required|string|max:100',
                "materials.$i.qty"           => 'required|numeric|min:0',
            ]);
        }

        if ($over = $this->guardHectarage($validated)) return $over;
        $activity = Activity::where('activity_code', $validated['activity_code'])->first();

        DB::transaction(function () use ($workplan, $validated, $materials, $activity, $request) {
            $workplan->update([
                'workplan_date'        => $validated['workplan_date'],
                'division_code'        => $validated['division_code'],
                'activity_code'        => $validated['activity_code'],
                'activity_name'        => $activity?->activity_name ?? '',
                'block_code'           => $validated['block_code'] ?? null,
                'order_number'         => $validated['order_number'] ?? null,
                'auc_number'           => $validated['auc_number'] ?? null,
                'cost_center'          => $validated['cost_center'] ?? null,
                'mandor_employee_code' => $validated['mandor_employee_code'] ?? null,
                'mandor_employee_name' => $validated['mandor_employee_name'] ?? null,
                'total_hk'             => $validated['total_hk'],
                'total_qty_target'     => $validated['total_qty_target'],
                // Re-publishing a rejected plan resets it to published; otherwise keep draft
                'is_approved'          => $request->input('action') === 'publish'
                                            ? Workplan::STATUS_PUBLISHED
                                            : Workplan::STATUS_DRAFT,
                'updated_by'           => $this->userName(),
            ]);

            // Replace materials
            WorkplanMaterial::where('workplan_id', $workplan->id)->delete();
            $this->saveMaterials($workplan->id, $materials);
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_UPDATE,
            "Updated workplan {$workplan->id}");

        return redirect()->route('planning.workplan.index')
            ->with('success', 'Workplan updated successfully.');
    }

    // ── DESTROY ─────────────────────────────────────────────────────────────
    public function destroy(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $workplan = Workplan::where('id', $id)->first();
        abort_unless($workplan, 404);

        if (! $workplan->isEditable()) {
            return redirect()->route('planning.workplan.index')
                ->with('error', 'Only draft or rejected workplans can be deleted.');
        }

        DB::transaction(function () use ($workplan) {
            WorkplanMaterial::where('workplan_id', $workplan->id)->delete();
            $workplan->delete();
        });

        AuditService::log(AuditService::TYPE_TRANSACTION, AuditService::ACTION_DELETE,
            "Deleted workplan {$id}");

        return redirect()->route('planning.workplan.index')
            ->with('success', 'Workplan deleted.');
    }

    // ── DETAIL (read-only) ────────────────────────────────────────────────────
    public function show(string $id): View|RedirectResponse
    {
        $workplan = Workplan::where('id', $id)->first();
        abort_unless($workplan, 404);

        return view('planning.workplan.detail', [
            'workplan'  => $workplan,
            'materials' => $workplan->materials()->orderBy('id')->get(),
            'lastLog'   => $workplan->approvalLogs()->latest('id')->first(),
        ]);
    }

    // ── PUBLISH (draft → published) ───────────────────────────────────────────
    public function publish(string $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;

        $workplan = Workplan::where('id', $id)->first();
        abort_unless($workplan, 404);

        if (! $workplan->isEditable()) {
            return redirect()->route('planning.workplan.index')
                ->with('error', 'Only draft or rejected workplans can be published.');
        }

        $workplan->update([
            'is_approved' => Workplan::STATUS_PUBLISHED,
            'updated_by'  => $this->userName(),
        ]);

        return redirect()->route('planning.workplan.index')
            ->with('success', 'Workplan published for approval.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // AJAX HELPERS (cascading dropdowns)
    // ════════════════════════════════════════════════════════════════════════

    /** Blocks belonging to a division (active only). */
    public function getBlocks(Request $request): JsonResponse
    {
        $division = $request->query('division_code');
        $blocks = Block::byDivision($division)
            ->byEstate($this->estateCode())
            ->active()
            ->orderBy('block_code')
            ->get(['block_code', 'block_name', 'crop_type', 'block_hectarage']);

        return $this->jsonSuccess('OK', $blocks->map(fn($b) => [
            'id'        => $b->block_code,
            'text'      => "{$b->block_code} - {$b->block_name} - {$b->crop_type}",
            'hectarage' => $b->block_hectarage,
        ]));
    }

    /** Activities within an activity group. */
    public function getActivities(Request $request): JsonResponse
    {
        $group = $request->query('activity_group_code');
        $activities = Activity::when($group, fn($q) => $q->where('activity_group_code', $group))
            ->orderBy('activity_code')
            ->get(['activity_code', 'activity_name', 'activity_uom']);

        return $this->jsonSuccess('OK', $activities->map(fn($a) => [
            'id'   => $a->activity_code,
            'text' => "{$a->activity_code} - {$a->activity_name}",
            'uom'  => $a->activity_uom,
        ]));
    }

    /** Single block hectarage lookup for the HA guard. */
    public function getBlockInfo(Request $request): JsonResponse
    {
        $block = Block::byDivision($request->query('division_code'))
            ->byEstate($this->estateCode())
            ->where('block_code', $request->query('block_code'))
            ->first(['block_hectarage']);

        return $block
            ? $this->jsonSuccess('OK', ['hectarage' => $block->block_hectarage])
            : $this->jsonError('Block not found', null, 404);
    }

    /** Server-side material search (select2 remote). */
    public function searchMaterials(Request $request): JsonResponse
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

        $results = $rows->map(fn($m) => [
            'id'   => $m->material_code,
            'text' => "{$m->material_name} - {$m->material_code}",
            'uom'  => $m->material_uom,
        ]);

        return response()->json([
            'results'    => $results,
            'pagination' => ['more' => ($page * $pageSize) < $total],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ════════════════════════════════════════════════════════════════════════

    private function validateWorkplan(Request $request): array
    {
        return $request->validate([
            'workplan_date'        => 'required|date',
            'division_code'        => 'required|string|max:50',
            'block_code'           => 'nullable|string|max:50',
            'activity_code'        => 'required|string|max:50',
            'total_qty_target'     => 'required|numeric|min:0',
            'total_hk'             => 'required|integer|min:0',
            'order_number'         => 'nullable|string|max:12',
            'auc_number'           => 'nullable|string|max:12',
            'cost_center'          => 'nullable|string|max:10',
            'mandor_employee_code' => 'nullable|string|max:100',
            'mandor_employee_name' => 'nullable|string|max:150',
        ]);
    }

    /**
     * jquery-repeater keeps a hidden template row; drop fully blank rows.
     * Returns a re-indexed array of ['material_code','material_name','qty'].
     */
    private function cleanMaterials(array $raw): array
    {
        $clean = [];
        foreach ($raw as $m) {
            if (! is_array($m)) continue;
            $code = trim((string) ($m['material_code'] ?? ''));
            $qty  = trim((string) ($m['qty'] ?? ''));
            if ($code === '' && $qty === '') continue;
            $clean[] = [
                'material_code' => $code,
                'material_name' => trim((string) ($m['material_name'] ?? '')),
                'qty'           => $qty,
            ];
        }
        return array_values($clean);
    }

    private function saveMaterials(string $workplanId, array $materials): void
    {
        foreach ($materials as $m) {
            $name = $m['material_name'] ?: (Material::where('material_code', $m['material_code'])->value('material_name') ?? '');
            WorkplanMaterial::create([
                'company_id'    => $this->companyId(),
                'workplan_id'   => $workplanId,
                'material_code' => $m['material_code'],
                'material_name' => $name,
                'qty'           => $m['qty'],
            ]);
        }
    }

    /**
     * Prevent target quantity from exceeding block hectarage (only when block chosen).
     * Returns a redirect response when the guard trips, null otherwise.
     */
    private function guardHectarage(array $validated): ?RedirectResponse
    {
        if (empty($validated['block_code'])) return null;

        $block = Block::byDivision($validated['division_code'])
            ->byEstate($this->estateCode())
            ->where('block_code', $validated['block_code'])
            ->first(['block_hectarage']);

        if ($block && $validated['total_qty_target'] > (float) $block->block_hectarage) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Workplan target quantity exceeds the block hectarage.');
        }

        return null;
    }

    /** Unique workplan ID: estateCode + YmdHis + userId + "W". */
    private function generateWorkplanId(): string
    {
        return $this->estateCode() . now()->format('YmdHis') . $this->userId() . 'W';
    }

    private function divisionsForUser()
    {
        return Division::byEstate($this->estateCode())
            ->orderBy('division_code')
            ->get(['division_code', 'division_name']);
    }

    private function activityGroups()
    {
        return Activity::select('activity_group_code')
            ->whereNotNull('activity_group_code')
            ->distinct()
            ->orderBy('activity_group_code')
            ->pluck('activity_group_code');
    }

    /** Asst Manager's assigned division (if mapped), else first available. */
    private function defaultDivisionForUser(): ?string
    {
        $mapped = AssistantManagerDivision::where('assistant_manager_code', (string) $this->userId())
            ->value('division_code');

        return $mapped ?: null;
    }
}
