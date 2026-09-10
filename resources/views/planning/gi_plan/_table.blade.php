@php
    $date = request('date', today()->addDay()->toDateString());
@endphp

<div class="rounded-xl border shadow-sm overflow-hidden"
     style="background:var(--epms-header-bg);border-color:var(--epms-border);">

    @if(count($rows) === 0)
        <div class="p-8 text-center text-sm" style="color:var(--epms-text-muted);">
            No GI plans for this status.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color:var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color:var(--epms-border);">
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">GI Date</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Estate</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Plant</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Doc Number</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Items</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Created By</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $r = (array)$row; @endphp
                    <tr class="border-b hover:opacity-80" style="border-color:var(--epms-border);">
                        <td class="px-3 py-2 font-medium">{{ $r['gi_date'] ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $r['estate_code'] ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $r['plant_code'] ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $r['gi_document_number'] ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded bg-blue-100 text-blue-700 px-2 py-0.5 text-xs font-semibold">
                                {{ $r['detail_count'] ?? 0 }} items
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ $r['created_by'] ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('planning.gi_plan.detail', ['id' => $r['id'] ?? 0]) }}"
                               class="text-primary hover:underline text-xs font-medium">
                                View Detail
                            </a>
                            
                            @if($canSubmit ?? false)
                            <form method="POST" action="{{ route('planning.gi_plan.submit') }}" class="inline ml-2">
                                @csrf
                                <input type="hidden" name="id" value="{{ $r['id'] ?? 0 }}">
                                <button type="submit"
                                        onclick="return confirm('Submit GI Plan for approval?')"
                                        class="text-green-600 hover:underline text-xs font-medium">
                                    Submit for Approval
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
