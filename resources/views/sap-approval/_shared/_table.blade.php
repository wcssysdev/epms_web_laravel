@php
    $firstRow = (array)($rows[0] ?? []);
    $pkKey = array_key_first($firstRow) ?? 'id';
@endphp

<div class="rounded-xl border shadow-sm overflow-hidden"
     style="background:var(--epms-header-bg);border-color:var(--epms-border);">

    @if(count($rows) === 0)
        <div class="p-8 text-center text-sm" style="color:var(--epms-text-muted);">No records.</div>
    @else
        {{-- Bulk action bar --}}
        @if(!empty($actions))
        <div class="px-4 py-3 border-b flex gap-2 flex-wrap" style="border-color:var(--epms-border);">
            @if(in_array('approve', $actions))
            <form method="POST" action="{{ route($routePrefix.'.save-approval') }}" id="form-approve-{{ $tab }}"
                  class="inline" onsubmit="return collectIds(this, '{{ $tab }}', 'approved')">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="approval_type" value="approved">
                <div id="ids-approve-{{ $tab }}"></div>
                <button type="submit"
                        class="rounded-lg bg-green-600 px-4 py-2 text-xs font-medium text-white hover:opacity-90 transition">
                    ✓ Approve Selected
                </button>
            </form>
            @endif

            @if(in_array('reject', $actions))
            <form method="POST" action="{{ route($routePrefix.'.save-approval') }}" id="form-reject-{{ $tab }}"
                  class="inline" onsubmit="return collectIds(this, '{{ $tab }}', 'rejected')">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="approval_type" value="rejected">
                <div id="ids-reject-{{ $tab }}"></div>
                <button type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-xs font-medium text-white hover:opacity-90 transition">
                    ✗ Reject Selected
                </button>
            </form>
            @endif

            <label class="flex items-center gap-2 text-xs ml-auto" style="color:var(--epms-text-muted);">
                <input type="checkbox" id="chk-all-{{ $tab }}"
                       onchange="toggleAll('{{ $tab }}')" class="rounded">
                Select all
            </label>
        </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color:var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color:var(--epms-border);">
                        @if(!empty($actions))
                        <th class="px-3 py-3 w-8"></th>
                        @endif
                        @foreach(array_keys($firstRow) as $col)
                            @if(!in_array($col, ['company_id','photo','lat','long','request_id','created_by','updated_by','created_at','updated_at','closing_approved_by','closing_approved_at','is_approved','approved_by','approved_at','approved_by_name']))
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide"
                                style="color:var(--epms-text-muted);">{{ str_replace('_',' ', $col) }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $arr = (array)$row; $id = $arr[$pkKey] ?? ''; @endphp
                    <tr class="border-b hover:opacity-80" style="border-color:var(--epms-border);">
                        @if(!empty($actions))
                        <td class="px-3 py-2">
                            <input type="checkbox" name="row_id" value="{{ $id }}"
                                   class="chk-{{ $tab }} rounded"
                                   onchange="syncSelectAll('{{ $tab }}', {{ count($rows) }})">
                        </td>
                        @endif
                        @foreach($arr as $col => $val)
                            @if(!in_array($col, ['company_id','photo','lat','long','request_id','created_by','updated_by','created_at','updated_at','closing_approved_by','closing_approved_at','is_approved','approved_by','approved_at','approved_by_name']))
                            <td class="px-3 py-2">
                                @if($col === 'closing_is_approved')
                                    @php
                                        $badge = match((int)$val){0=>'Pending',1=>'Approved',-1=>'Rejected',default=>$val};
                                        $color = match((int)$val){0=>'bg-yellow-100 text-yellow-700',1=>'bg-green-100 text-green-700',-1=>'bg-red-100 text-red-700',default=>'bg-gray-100 text-gray-500'};
                                    @endphp
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $color }}">{{ $badge }}</span>
                                @else
                                    {{ $val }}
                                @endif
                            </td>
                            @endif
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<script>
function collectIds(form, tab, type) {
    const boxes = document.querySelectorAll('.chk-' + tab + ':checked');
    if (boxes.length === 0) { alert('Select at least one record.'); return false; }
    const container = form.querySelector('[id^="ids-"]');
    container.innerHTML = '';
    boxes.forEach(b => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = b.value;
        container.appendChild(inp);
    });
    return confirm(type.charAt(0).toUpperCase() + type.slice(1) + ' ' + boxes.length + ' record(s)?');
}
function toggleAll(tab) {
    const master = document.getElementById('chk-all-' + tab);
    document.querySelectorAll('.chk-' + tab).forEach(b => b.checked = master.checked);
}
function syncSelectAll(tab, total) {
    const checked = document.querySelectorAll('.chk-' + tab + ':checked').length;
    const master = document.getElementById('chk-all-' + tab);
    if (master) master.checked = (checked === total);
}
</script>
