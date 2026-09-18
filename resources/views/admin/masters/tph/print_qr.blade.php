<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print QR Tasks</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
            background: #fff;
            color: #333;
        }
        .toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn {
            border: 1px solid #d1d5db;
            background: #fff;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .qr-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .qr-card {
            width: 200px;
            border: 1px dashed #ccc;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            page-break-inside: avoid;
        }
        .qr-box {
            display: flex;
            justify-content: center;
            margin-bottom: 10px;
        }
        .qr-meta {
            font-size: 12px;
            text-align: left;
            line-height: 1.4;
            color: #444;
            margin-top: 6px;
        }
        .qr-meta strong {
            color: #111;
        }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            .qr-card {
                border: 1px dashed #bbb;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <button class="btn" onclick="window.close()">Close</button>
    </div>

    <div class="qr-grid">
        @foreach($tasks as $task)
            <div class="qr-card">
                <div class="qr-box" id="qr-{{ $task->id }}"></div>
                <div class="qr-meta">
                    <div><strong>Blok:</strong> {{ $task->block_code }} {{ $task->block_name ?? '' }}</div>
                    <div><strong>Task:</strong> {{ $task->tph_code }}</div>
                    <div><strong>Platform:</strong> {{ $task->section_code ?? '-' }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        const tasks = @json($tasks);
        tasks.forEach(t => {
            const el = document.getElementById('qr-' + t.id);
            if (el) {
                new QRCode(el, {
                    text: t.qr_text,
                    width: 130,
                    height: 130,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        });
        window.addEventListener('load', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
