@extends('layouts.app')

@section('title', 'QR Code Generator')

@section('breadcrumb')
    <li><span class="font-medium text-primary">QR Code Generator</span></li>
@endsection

@section('page-title', 'QR Code Generator')
@section('page-subtitle', 'Generate a QR code from any text and print it')

@section('content')
<div class="max-w-xl" x-data="qrGenerator()">
    <div class="rounded-xl border shadow-sm overflow-hidden"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <div class="p-5">
            <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">Text / Code</label>
            <textarea x-model="text" rows="3"
                      placeholder="Type any text, code, or URL to encode…"
                      class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                      style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);"></textarea>

            <div class="flex gap-3 mt-4">
                <button type="button" @click="generate()"
                        class="rounded-lg bg-primary px-5 py-2.5 text-sm font-medium text-white hover:opacity-90 transition">
                    Generate
                </button>
                <button type="button" @click="printQr()" x-show="generated"
                        class="rounded-lg border px-5 py-2.5 text-sm font-medium transition hover:opacity-80"
                        style="border-color: var(--epms-border); color: var(--epms-text);">
                    Print
                </button>
            </div>

            <div class="mt-6 flex flex-col items-center gap-3" x-show="generated">
                <div id="qrTarget" class="bg-white p-4 rounded-lg inline-block"></div>
                <p class="text-xs break-all text-center" style="color: var(--epms-text-muted);" x-text="text"></p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
function qrGenerator() {
    return {
        text: '',
        generated: false,
        qr: null,
        generate() {
            const val = (this.text || '').trim();
            if (!val) { alert('Please enter some text first.'); return; }
            const target = document.getElementById('qrTarget');
            target.innerHTML = '';
            this.qr = new QRCode(target, { text: val, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
            this.generated = true;
        },
        printQr() {
            const target = document.getElementById('qrTarget');
            const img = target.querySelector('img') || target.querySelector('canvas');
            if (!img) return;
            const src = img.tagName === 'IMG' ? img.src : img.toDataURL('image/png');
            const w = window.open('', '_blank');
            w.document.write(`<html><head><title>QR Code</title></head><body style="text-align:center;font-family:sans-serif;padding:40px;">
                <img src="${src}" style="width:260px;height:260px;"/>
                <p style="word-break:break-all;margin-top:16px;">${this.text.replace(/</g,'&lt;')}</p>
                <script>window.onload=function(){window.print();}<\/script></body></html>`);
            w.document.close();
        },
    }
}
</script>
@endpush
