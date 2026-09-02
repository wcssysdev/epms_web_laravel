<?php

namespace App\Http\Controllers\Transaction\Monitoring;

use App\Models\Transaction\Workdone;
use Illuminate\Database\Eloquent\Builder;

class WorkdoneMonitoringController extends BaseMonitoringController
{
    protected function baseQuery(): Builder { return Workdone::query()->orderByDesc('workdone_date'); }
    protected function dateColumn(): string { return 'workdone_date'; }
    protected function viewPrefix(): string { return 'transaction.monitoring.workdone'; }
    protected function routePrefix(): string { return 'transactions.monitoring.workdone'; }
    protected function title(): string { return 'Workdone Monitoring'; }
    protected function divisionScoped(): bool { return true; }

    protected function datatableColumns(): array
    {
        return [
            'workdone_date' => 'Date',
            'division_code' => 'Division',
            'activity_name' => 'Activity',
            'block_code'    => 'Block',
            'employee_name' => 'Employee',
            'mandays'       => 'Mandays',
            'qty'           => 'Qty',
        ];
    }
}
