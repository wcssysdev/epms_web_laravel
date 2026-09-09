<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Vra;
use App\Models\Master\WorkCenter;
use App\Models\Master\Worktype;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * VRA (Vehicle/Equipment Activity) entry (t_vra) — Estate Staff role-4 CRUD.
 * Maps to CI4 Transactions/Vra.php.
 *
 * Guard: integration_status {0,2} = locked (edit/delete denied via GuardsSapIntegration).
 * ID: application-generated "{estate}{YmdHis}{user_id_3digit}W".
 */
class VraEntryController extends BaseTransactionController
{
    protected function modelClass(): string  { return Vra::class; }
    protected function dateColumn(): string  { return 'vra_date'; }
    protected function viewPrefix(): string  { return 'transaction.vra'; }
    protected function routePrefix(): string { return 'transactions.vra'; }
    protected function title(): string       { return 'VRA (Equipment Activity)'; }

    protected function generateId(): ?string
    {
        $userId = str_pad((string) $this->userId(), 3, '0', STR_PAD_LEFT);
        return $this->estateCode() . now()->format('ymdHis') . $userId . 'W';
    }

    protected function datatableColumns(): array
    {
        return [
            'vra_date'         => 'Date',
            'license_number'   => 'License No.',
            'order_number'     => 'Order No.',
            'meas_point'       => 'Meas. Point',
            'reading_value'    => 'Reading Value',
            'integration_status' => 'SAP Status',
        ];
    }

    protected function rules(Request $request): array
    {
        return [
            'vra_date'       => 'required|date',
            'license_number' => 'required|string|max:100',
            'order_number'   => 'required|string|max:100',
            'meas_point'     => 'nullable|string|max:100',
            'reading_value'  => 'required|numeric',
            'confirmation_text' => 'nullable|string|max:255',
            'remark'         => 'nullable|string|max:255',
        ];
    }

    protected function mapRow(Request $request): array
    {
        // Resolve equipment_code from m_vra via order_number
        $vra = DB::table('m_vra')
            ->where('vra_order_number', $request->order_number)
            ->first();

        return [
            'vra_date'           => $request->vra_date,
            'estate_code'        => $this->estateCode(),
            'plant_code'         => $this->plantCode(),
            'license_number'     => trim($request->license_number),
            'equipment_code'     => $vra?->equipment_code ?? null,
            'order_number'       => trim($request->order_number),
            'meas_point'         => $request->meas_point ?: null,
            'reading_value'      => (float) $request->reading_value,
            'confirmation_text'  => $request->confirmation_text ?: null,
            'remark'             => $request->remark ?: null,
            'is_approved'        => true,   // CI4 auto-approved on create
            'integration_status' => -1,
        ];
    }

    protected function formData(): array
    {
        $plant = $this->plantCode();

        // VRA orders grouped by object_type for cascading picker
        $vraOrders = DB::table('m_vra')
            ->where('plant_code', $plant)
            ->orderBy('object_type')->orderBy('vra_order_number')
            ->get(['id', 'vra_order_number', 'license_number', 'equipment_code', 'object_type']);

        $vraTypes = DB::table('m_vra')
            ->where('plant_code', $plant)
            ->distinct()->orderBy('object_type')
            ->pluck('object_type');

        return [
            'vraOrders' => $vraOrders,
            'vraTypes'  => $vraTypes,
        ];
    }

    // ── AJAX: measurement points for an order ─────────────────────────────────
    public function measPoints(Request $request): JsonResponse
    {
        $orderNumber = $request->query('order_number');
        $meas = DB::table('m_meas_point')
            ->join('m_vra', 'm_meas_point.equipment_code', '=', 'm_vra.equipment_code')
            ->where('m_vra.vra_order_number', $orderNumber)
            ->orderBy('m_meas_point.point')
            ->get(['m_meas_point.*']);

        return response()->json(['status' => 'OK', 'data' => $meas]);
    }

    // ── AJAX: VRA orders by object type ───────────────────────────────────────
    public function vraByType(Request $request): JsonResponse
    {
        $type  = $request->query('object_type');
        $plant = $this->plantCode();
        $rows  = DB::table('m_vra')
            ->where('plant_code', $plant)
            ->when($type, fn($q) => $q->where('object_type', $type))
            ->orderBy('vra_order_number')
            ->get(['vra_order_number', 'license_number', 'equipment_code']);

        return response()->json(['status' => 'OK', 'data' => $rows]);
    }
}
