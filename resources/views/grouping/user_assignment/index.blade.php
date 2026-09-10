@extends('layouts.app')
@section('title', $title)
@section('breadcrumb')
    <li><span class="font-medium text-primary">Grouping / {{ $title }}</span></li>
@endsection
@section('page-title', $title)
@section('page-subtitle', 'Mandor to Employee Assignment')

@section('content')
<div>
    <form method="GET" class="flex items-end gap-3 mb-4 rounded-xl border px-5 py-4"
          style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="flex-1">
            <label class="block text-xs font-medium mb-1" style="color:var(--epms-text-muted);">Search (Mandor/Employee)</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by code or name..."
                   class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-primary"
                   style="background:var(--epms-header-bg);color:var(--epms-text);border-color:var(--epms-border);">
        </div>
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">Search</button>
        @if($search)
        <a href="{{ route('grouping.user_assignment.index') }}" class="rounded-lg border px-4 py-2 text-sm font-medium hover:bg-gray-50" style="border-color:var(--epms-border);">Clear</a>
        @endif
    </form>

    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background:var(--epms-header-bg);border-color:var(--epms-border);">
        <div class="px-5 py-3 border-b" style="border-color:var(--epms-border);">
            <h3 class="font-semibold">User Assignments ({{ $assignments->total() }})</h3>
        </div>

        @if($assignments->isEmpty())
            <div class="p-8 text-center text-sm" style="color:var(--epms-text-muted);">
                @if($search)
                    No assignments found matching "{{ $search }}".
                @else
                    No user assignments configured yet.
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm" style="color:var(--epms-text);">
                    <thead>
                        <tr class="border-b" style="border-color:var(--epms-border);">
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Mandor</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Worker/Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase" style="color:var(--epms-text-muted);">Valid Period</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assignments as $assignment)
                        <tr class="border-b hover:bg-gray-50 hover:bg-opacity-50" style="border-color:var(--epms-border);">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $assignment->mandor_employee_code }}</div>
                                <div class="text-xs" style="color:var(--epms-text-muted);">{{ $assignment->mandor_employee_name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $assignment->worker_employee_code }}</div>
                                <div class="text-xs" style="color:var(--epms-text-muted);">{{ $assignment->worker_employee_name }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs">
                                    <div>From: {{ $assignment->assignment_valid_from ?? '-' }}</div>
                                    <div>To: {{ $assignment->assignment_valid_to ?? '-' }}</div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t" style="border-color:var(--epms-border);">
                {{ $assignments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
