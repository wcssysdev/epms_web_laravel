@include('approval._shared.batch_index', [
    'title'       => 'Unplanned Activity Approval',
    'routePrefix' => 'approval.unplanned_activity',
    'idField'     => 'workdone_ids',
    'hasDetail'   => true,
    'columns'     => [
        'Division' => fn($r) => $r->division_code,
        'Activity' => fn($r) => trim(($r->activity_code ?? '') . ' - ' . ($r->activity_name ?? '')),
        'Block'    => fn($r) => $r->block_code ?: '—',
        'Mandor'   => fn($r) => $r->mandor_employee_name ?: '—',
        'Mandays'  => fn($r) => rtrim(rtrim(number_format((float) $r->mandays, 2), '0'), '.'),
    ],
])
