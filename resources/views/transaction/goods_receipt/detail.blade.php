@extends('layouts.app')

@section('title', 'Goods Receipt Detail')

@section('breadcrumb')
    <li><span class="text-gray-500">Transactions /</span></li>
    <li><a href="{{ route('transactions.goods_receipt.index') }}" class="text-gray-500 hover:text-primary">Goods Receipt</a></li>
    <li><span class="font-medium text-primary">Detail</span></li>
@endsection

@section('page-title', 'Goods Receipt Detail')
@section('page-subtitle', 'View GR transaction details')

@section('page-actions')
    @if($item->isEditable())
    <a href="{{ route('transactions.goods_receipt.edit', $item->id) }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        Edit
    </a>
    @endif
@endsection

@section('content')
<div class="space-y-5">
    <div class="rounded-xl border p-5" style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <h3 class="font-semibold text-sm mb-4" style="color: var(--epms-text);">Header</h3>
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">GR ID</dt>
                <dd class="font-mono font-medium" style="color: var(--epms-text);">{{ $item->id }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">Date</dt>
                <dd style="color: var(--epms-text);">{{ $item->gr_date->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">PO Number</dt>
                <dd class="font-mono" style="color: var(--epms-text);">{{ $item->po_number }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">Storage Loc.</dt>
                <dd style="color: var(--epms-text);">{{ $item->sloc_code }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">SAP Document</dt>
                <dd class="font-mono" style="color: var(--epms-text);">{{ $item->gr_document_number ?: '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">Status</dt>
                <dd><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $item->statusBadgeClass() }}">{{ $item->statusLabel() }}</span></dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">Created By</dt>
                <dd style="color: var(--epms-text);">{{ $item->created_by }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color: var(--epms-text-muted);">Created At</dt>
                <dd style="color: var(--epms-text);">{{ $item->created_at?->format('d M Y H:i') ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-xl border shadow-sm overflow-hidden" style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="px-5 py-3 border-b" style="border-color: var(--epms-border);">
            <h3 class="font-semibold text-sm" style="color: var(--epms-text);">Material Lines</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr style="background: var(--epms-border);">
                    <th class="px-3 py-2 text-left text-xs font-semibold">#</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Material Code</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">Material Name</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold">Qty</th>
                    <th class="px-3 py-2 text-left text-xs font-semibold">UOM</th>
                </tr>
            </thead>
            <tbody class="divide-y" style="divide-color: var(--epms-border);">
                @forelse($item->details as $i => $d)
                <tr>
                    <td class="px-3 py-2 text-xs" style="color: var(--epms-text-muted);">{{ $i + 1 }}</td>
                    <td class="px-3 py-2 font-mono text-xs" style="color: var(--epms-text);">{{ $d->material_code }}</td>
                    <td class="px-3 py-2 text-xs" style="color: var(--epms-text);">{{ $d->material_name }}</td>
                    <td class="px-3 py-2 text-xs text-right" style="color: var(--epms-text);">{{ number_format($d->qty, 3) }}</td>
                    <td class="px-3 py-2 text-xs" style="color: var(--epms-text);">{{ $d->uom }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-3 py-8 text-center text-sm" style="color: var(--epms-text-muted);">No material lines.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex justify-end">
        <a href="{{ route('transactions.goods_receipt.index') }}"
           class="rounded-lg border px-4 py-2 text-sm font-medium transition hover:opacity-75"
           style="border-color: var(--epms-border); color: var(--epms-text);">Back to List</a>
    </div>
</div>
@endsection
