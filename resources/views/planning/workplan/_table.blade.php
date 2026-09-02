{{-- Workplan list table for a single status. Vars: $items (Collection), $status (string) --}}
<div class="rounded-xl border shadow-sm overflow-hidden"
     style="background: var(--epms-header-bg); border-color: var(--epms-border);">
    <div class="overflow-x-auto">
        <table class="w-full text-sm" style="color: var(--epms-text);">
            <thead>
                <tr class="border-b" style="border-color: var(--epms-border);">
                    @foreach(['#','Division','Activity','Block','Target','HK','Mandor','Status','Actions'] as $h)
                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide"
                        style="color: var(--epms-text-muted);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($items as $i => $wp)
                <tr class="border-b" style="border-color: var(--epms-border);">
                    <td class="px-3 py-3">{{ $i + 1 }}</td>
                    <td class="px-3 py-3 font-medium">{{ $wp->division_code }}</td>
                    <td class="px-3 py-3">
                        <span class="font-medium">{{ $wp->activity_code }}</span>
                        <span class="block text-xs" style="color: var(--epms-text-muted);">{{ $wp->activity_name }}</span>
                    </td>
                    <td class="px-3 py-3">{{ $wp->block_code ?: '—' }}</td>
                    <td class="px-3 py-3">{{ rtrim(rtrim(number_format((float) $wp->total_qty_target, 2), '0'), '.') }}</td>
                    <td class="px-3 py-3">{{ $wp->total_hk }}</td>
                    <td class="px-3 py-3">{{ $wp->mandor_employee_name ?: '—' }}</td>
                    <td class="px-3 py-3">
                        <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium
                            {{ $wp->isApproved() ? 'bg-green-100 text-green-700' : '' }}
                            {{ $wp->isRejected() ? 'bg-red-100 text-red-700' : '' }}
                            {{ $wp->isPublished() ? 'bg-blue-100 text-blue-700' : '' }}
                            {{ $wp->isDraft() ? 'bg-gray-100 text-gray-600' : '' }}">
                            {{ $wp->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <div class="flex gap-1">
                            <a href="{{ route('planning.workplan.show', $wp->id) }}"
                               class="btn-action btn-view" title="View">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            @if($wp->isEditable())
                            <a href="{{ route('planning.workplan.edit', $wp->id) }}"
                               class="btn-action btn-edit" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            @if($wp->isDraft())
                            <form action="{{ route('planning.workplan.publish', $wp->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn-action btn-publish" title="Publish for approval">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                </button>
                            </form>
                            @endif
                            <button @click="deleteUrl = '{{ route('planning.workplan.destroy', $wp->id) }}'; showDelete = true"
                                    class="btn-action btn-danger" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-3 py-10 text-center text-sm" style="color: var(--epms-text-muted);">
                        No {{ $status }} workplans for this date.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@once
@push('styles')
<style>
    [x-cloak] { display: none !important; }
    .btn-action { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; border:1px solid; transition:opacity 0.15s; cursor:pointer; }
    .btn-action:hover { opacity: 0.75; }
    .btn-view { background:#f0fdf4; border-color:#bbf7d0; color:#16a34a; }
    .btn-edit { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
    .btn-publish { background:#fefce8; border-color:#fef08a; color:#ca8a04; }
    .btn-danger { background:#fef2f2; border-color:#fecaca; color:#dc2626; }
</style>
@endpush
@endonce
