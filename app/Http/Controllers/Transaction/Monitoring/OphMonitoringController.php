<?php

namespace App\Http\Controllers\Transaction\Monitoring;

use App\Models\Transaction\Oph;
use Illuminate\Database\Eloquent\Builder;

class OphMonitoringController extends BaseMonitoringController
{
    protected function baseQuery(): Builder { return Oph::query()->actual()->orderByDesc('created_at'); }
    protected function dateColumn(): string { return 'created_at'; }
    protected function viewPrefix(): string { return 'transaction.monitoring.oph'; }
    protected function routePrefix(): string { return 'transactions.monitoring.oph'; }
    protected function title(): string { return 'OPH Monitoring'; }
    protected function divisionScoped(): bool { return true; }

    protected function datatableColumns(): array
    {
        return [
            'oph_card_id'          => 'Card ID',
            'division_code'        => 'Division',
            'block_code'           => 'Block',
            'tph_code'             => 'TPH',
            'mandor_employee_name' => 'Mandor',
            'bunches_total'        => 'Bunches',
        ];
    }
}
