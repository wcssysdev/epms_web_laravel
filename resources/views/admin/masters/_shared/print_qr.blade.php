<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR — {{ $value }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 32px;
               display: flex; flex-direction: column; align-items: center; gap: 16px; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 8px; }
        .btn { border: 1px solid #d1d5db; background: #fff; border-radius: 8px; padding: 8px 16px;
               font-size: 14px; cursor: pointer; }
        .btn-primary { background: #5750f1; border-color: #5750f1; color: #fff; }
        .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; text-align: center; }
        .card h1 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; margin: 0 0 12px; }
        .qr { display: inline-block; }
        .value { font-weight: 700; font-size: 15px; margin-top: 12px; word-break: break-all; }
        .caption { font-size: 12px; color: #4b5563; margin-top: 2px; }
        @media print { .toolbar { display: none; } body { padding: 0; } .card { border: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <button class="btn" onclick="window.close()">Close</button>
    </div>

    <div class="card">
        <h1>{{ $resourceName }}</h1>
        <div id="qr" class="qr"></div>
        <div class="value">{{ $value }}</div>
        @foreach($captions as $label => $text)
            @if($text !== '' && $text !== null)
                <div class="caption">{{ $label }}: {{ $text }}</div>
            @endif
        @endforeach
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById('qr'), {
            text: @json($value),
            width: 240, height: 240,
            correctLevel: QRCode.CorrectLevel.M
        });
    </script>
</body>
</html>
