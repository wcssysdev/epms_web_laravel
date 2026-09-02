@include('approval._shared.detail', [
    'title'     => 'Harvesting Chit (Coconut) Detail',
    'subtitle'  => $chit->id,
    'backRoute' => 'approval.coconut_chit.index',
    'info'      => [
        'Card ID'   => $chit->oph_card_id ?: '—',
        'Division'  => $chit->division_code,
        'Block'     => $chit->block_code ?: '—',
        'TPH'       => $chit->tph_code ?: '—',
        'Gang'      => $chit->gang_name ?: ($chit->gang_code ?: '—'),
        'Checker'   => $chit->checker_employee_name ?: '—',
        'Total Nuts'=> number_format((int) $chit->nuts_total),
        'Status'    => $chit->statusLabel(),
        'Created By'=> $chit->created_by,
    ],
    'lineTitle'   => 'Detail Lines',
    'lineHeaders' => ['Material Code', 'Material Name', 'Customer Nut Qty'],
    'lines'       => $details,
    'lineRow'     => fn($d) => [
        $d->material_code,
        $d->material_name,
        $d->customer_nut_qty,
    ],
])
