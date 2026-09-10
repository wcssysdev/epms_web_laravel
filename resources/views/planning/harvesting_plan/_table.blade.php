@php
    $date = request('date', today()->addDay()->toDateString());
@endphp

<div class="rounded-xl border shadow-sm overflow-hidden"
     style="background:var(--epms-header-bg);border-color:var(--epms-border);">

    @if(count($rows) === 0)
        <div class="p-8 text-center text-sm" style="color:var(--epms-text-muted);">
            No harvesting plans for this status.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="color:var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color:var(--epms-border);">
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Division</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Assistant Manager</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Date</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Blocks</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Total Target (Kg)</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                            style="color:var(--epms-text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    @php $r = (array)$row; @endphp
                    <tr class="border-b hover:opacity-80" style="border-color:var(--epms-border);">
                        <td class="px-3 py-2 font-medium">{{ $r['division_code'] ?? '-' }}</td>
                        <td class="px-3 py-2">
                            {{ $r['assistant_emp_code'] ?? '-' }} - {{ $r['assistant_emp_name'] ?? '-' }}
                        </td>
                        <td class="px-3 py-2">{{ $r['plan_date'] ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded bg-blue-100 text-blue-700 px-2 py-0.5 text-xs font-semibold">
                                {{ $r['block_count'] ?? 0 }} blocks
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ number_format($r['total_qty'] ?? 0, 0) }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('planning.harvesting_plan.detail', [
                                    'date' => $r['plan_date'] ?? $date,
                                    'division' => $r['division_code'] ?? '',
                                    'created_by' => $r['created_by'] ?? ''
                                ]) }}"
                               class="text-primary hover:underline text-xs font-medium">
                                View Detail
                            </a>
                            
                            @if($canSubmit ?? false)
                            <form method="POST" action="{{ route('planning.harvesting_plan.submit') }}" class="inline ml-2">
                                @csrf
                                <input type="hidden" name="date" value="{{ $r['plan_date'] ?? $date }}">
                                <input type="hidden" name="division" value="{{ $r['division_code'] ?? '' }}">
                                <input type="hidden" name="created_by" value="{{ $r['created_by'] ?? '' }}">
                                <button type="submit"
                                        onclick="return confirm('Submit {{ $r['block_count'] ?? 0 }} harvesting plan(s) for approval?')"
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
