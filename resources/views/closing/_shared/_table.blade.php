@php
    $pk = \Illuminate\Support\Arr::first((array)($rows[0] ?? []), null, null);
    // detect PK: first column key of first row
    $firstRow = (array)($rows[0] ?? []);
    $pkKey = array_key_first($firstRow) ?? 'id';
@endphp

<div class="rounded-xl border shadow-sm overflow-hidden"
     style="background:var(--epms-header-bg);border-color:var(--epms-border);">

    @if(count($rows) === 0)
        <div class="p-8 text-center text-sm" style="color:var(--epms-text-muted);">No records.</div>
    @else
        {{-- Bulk action bar --}}
        @if(in_array('close', $actions) || in_array('lock', $actions) || in_array('relock', $actions))
        <div class="px-4 py-3 border-b flex gap-2 flex-wrap" style="border-color:var(--epms-border);">
            @if(in_array('close', $actions))
            <form method="POST" action="{{ route($routePrefix.'.closing') }}" id="form-close-{{ $tab }}"
                  class="inline" onsubmit="return collectIds(this, '{{ $tab }}')">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <div id="ids-close-{{ $tab }}"></div>
                <button type="submit"
                        class="rounded-lg bg-green-600 px-4 py-2 text-xs font-medium text-white hover:opacity-90 transition">
                    ✉ Send to SAP (selected)
                </button>
            </form>
            @endif

            @if(in_array('lock', $actions))
            <form method="POST" action="{{ route($routePrefix.'.lock') }}" id="form-lock-{{ $tab }}"
                  class="inline" onsubmit="return collectIds(this, '{{ $tab }}')">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <div id="ids-lock-{{ $tab }}"></div>
                <button type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:opacity-90 transition">
                    🔒 Lock (selected)
                </button>
            </form>
            @endif

            @if(in_array('relock', $actions))
            <form method="POST" action="{{ route($routePrefix.'.relock') }}" id="form-relock-{{ $tab }}"
                  class="inline" onsubmit="return collectIds(this, '{{ $tab }}')">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <div id="ids-relock-{{ $tab }}"></div>
                <button type="submit"
                        class="rounded-lg bg-purple-600 px-4 py-2 text-xs font-medium text-white hover:opacity-90 transition">
                    🔄 Relock (selected)
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
                            @if(!in_array($col, ['company_id','photo','lat','long','request_id','created_by','updated_by','created_at','updated_at']))
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
                            @if(!in_array($col, ['company_id','photo','lat','long','request_id','created_by','updated_by','created_at','updated_at']))
                            <td class="px-3 py-2">
                                @if($col === 'integration_status')
                                    @php
                                        $badge = match((int)$val){-1=>'Draft',0=>'Locked',2=>'Success',3=>'Lost',4=>'Failed',5=>'Adj',default=>$val};
                                        $color = match((int)$val){-1=>'bg-gray-100 text-gray-600',0=>'bg-blue-100 text-blue-700',2=>'bg-green-100 text-green-700',3=>'bg-orange-100 text-orange-700',4=>'bg-red-100 text-red-700',5=>'bg-purple-100 text-purple-700',default=>'bg-gray-100 text-gray-500'};
                                    @endphp
                                    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $color }}">{{ $badge }}</span>
                                @elseif($col === 'remark' && !empty($val))
                                    <span class="text-red-600 text-xs">{{ $val }}</span>
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
function collectIds(form, tab) {
    const boxes = document.querySelectorAll('.chk-' + tab + ':checked');
    if (boxes.length === 0) { alert('Select at least one record.'); return false; }
    const container = form.querySelector('[id^="ids-"]');
    container.innerHTML = '';
    boxes.forEach(b => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = b.value;
        container.appendChild(inp);
    });
    return confirm('Send ' + boxes.length + ' record(s) to SAP?');
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
