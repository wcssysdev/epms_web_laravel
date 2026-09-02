@include('approval._shared.batch_index', [
    'title'       => 'Overtime Approval',
    'routePrefix' => 'approval.overtime',
    'idField'     => 'overtime_ids',
    'hasDetail'   => false,
    'columns'     => [
        'Division' => fn($r) => $r->division_code,
        'Employee' => fn($r) => trim(($r->employee_code ?? '') . ' - ' . ($r->employee_name ?? '')),
        'Activity' => fn($r) => $r->activity_name ?: $r->activity_code,
        'Block'    => fn($r) => $r->block_code ?: '—',
        'Hours'    => fn($r) => rtrim(rtrim(number_format((float) $r->duration_hours, 2), '0'), '.'),
    ],
])
