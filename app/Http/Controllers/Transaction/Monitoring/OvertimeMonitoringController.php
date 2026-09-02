<?php

namespace App\Http\Controllers\Transaction\Monitoring;

use App\Models\Transaction\Overtime;
use Illuminate\Database\Eloquent\Builder;

class OvertimeMonitoringController extends BaseMonitoringController
{
    protected function baseQuery(): Builder { return Overtime::query()->orderByDesc('overtime_date'); }
    protected function dateColumn(): string { return 'overtime_date'; }
    protected function viewPrefix(): string { return 'transaction.monitoring.overtime'; }
    protected function routePrefix(): string { return 'transactions.monitoring.overtime'; }
    protected function title(): string { return 'Overtime Monitoring'; }
    protected function divisionScoped(): bool { return true; }

    protected function datatableColumns(): array
    {
        return [
            'overtime_date'  => 'Date',
            'division_code'  => 'Division',
            'employee_name'  => 'Employee',
            'activity_name'  => 'Activity',
            'block_code'     => 'Block',
            'duration_hours' => 'Hours',
        ];
    }
}
