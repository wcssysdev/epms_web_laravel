@include('approval._shared.batch_index', [
    'title'       => 'OPH Approval',
    'routePrefix' => 'approval.oph',
    'idField'     => 'oph_ids',
    'hasDetail'   => true,
    'columns'     => [
        'Card ID'  => fn($r) => $r->oph_card_id ?: '—',
        'Division' => fn($r) => $r->division_code,
        'Block'    => fn($r) => $r->block_code ?: '—',
        'TPH'      => fn($r) => $r->tph_code ?: '—',
        'Mandor'   => fn($r) => $r->mandor_employee_name ?: '—',
        'Bunches'  => fn($r) => number_format((int) $r->bunches_total),
    ],
])
