<?php

namespace App\Http\Controllers\Admin\Masters;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DeviceController extends BaseMasterController
{
    protected function modelClass(): string   { return \App\Models\Master\Device::class; }
    protected function tableName(): string    { return 'm_devices'; }
    protected function resourceName(): string { return 'Device'; }
    protected function viewPrefix(): string   { return 'admin.masters.device'; }
    protected function routePrefix(): string  { return 'masters.device'; }

    protected function datatableColumns(): array
    {
        return [
            'device_code'  => 'Device Code',
            'estate_code'  => 'Estate Code',
            'device_imei'  => 'IMEI',
            'updated_at'   => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['device_code', 'estate_code', 'device_imei'];
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['device_code'] ?? '');
        if (empty($code)) return null;
        return [
            'device_code'  => $code,
            'estate_code'  => strtoupper(trim($row['estate_code'] ?? '')),
            'device_imei'  => trim($row['device_imei'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        if (empty($row['device_code'])) return 'Device Code is required.';
        return null;
    }

    // Device also supports manual add/edit/delete (no CSV-only)
    public function add(): View
    {
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => null,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate([
            'device_code'  => 'required|string|max:100',
            'estate_code'  => 'required|string|max:50',
            'device_imei'  => 'nullable|string|max:100',
        ]);
        DB::table('m_devices')->insert(array_merge(
            $request->only('device_code', 'estate_code', 'device_imei'),
            ['company_id' => $this->companyId(), 'created_by' => $this->userName(), 'created_at' => now(), 'updated_by' => $this->userName(), 'updated_at' => now()]
        ));
        return redirect()->route('masters.device.index')->with('success', 'Device added successfully.');
    }

    public function edit(int $id): View
    {
        $item = DB::table('m_devices')->where('id', $id)->where('company_id', $this->companyId())->first();
        abort_unless($item, 404);
        return view($this->viewPrefix() . '.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix'  => $this->routePrefix(),
            'item'         => $item,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        $request->validate(['device_code' => 'required|string|max:100', 'estate_code' => 'required|string|max:50', 'device_imei' => 'nullable|string|max:100']);
        DB::table('m_devices')->where('id', $id)->where('company_id', $this->companyId())
            ->update(array_merge($request->only('device_code', 'estate_code', 'device_imei'), ['updated_by' => $this->userName(), 'updated_at' => now()]));
        return redirect()->route('masters.device.index')->with('success', 'Device updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($lock = $this->guardSystemLock()) return $lock;
        DB::table('m_devices')->where('id', $id)->where('company_id', $this->companyId())->delete();
        return redirect()->route('masters.device.index')->with('success', 'Device deleted.');
    }
}
