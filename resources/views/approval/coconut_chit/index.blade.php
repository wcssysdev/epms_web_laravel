@include('approval._shared.batch_index', [
    'title'       => 'Harvesting Chit (Coconut) Approval',
    'routePrefix' => 'approval.coconut_chit',
    'idField'     => 'coconut_oph_ids',
    'hasDetail'   => true,
    'columns'     => [
        'Card ID'  => fn($r) => $r->oph_card_id ?: '—',
        'Division' => fn($r) => $r->division_code,
        'Block'    => fn($r) => $r->block_code ?: '—',
        'Gang'     => fn($r) => $r->gang_name ?: ($r->gang_code ?: '—'),
        'Checker'  => fn($r) => $r->checker_employee_name ?: '—',
        'Nuts'     => fn($r) => number_format((int) $r->nuts_total),
    ],
])
