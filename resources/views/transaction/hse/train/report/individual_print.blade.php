<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'FM_CW_047 Individual Training Record' }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 12mm;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: "Garamond", "Georgia", "Times New Roman", serif;
            font-size: 11pt;
            color: #000;
            background: #eef2f5;
            margin: 0;
            padding: 20px 0;
        }

        /* Toolbar */
        .no-print-toolbar {
            width: 210mm;
            max-width: 95%;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 10px 18px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            font-family: Arial, sans-serif;
        }

        .toolbar-info {
            font-size: 13px;
            color: #444;
        }

        .toolbar-info strong {
            color: #1a365d;
        }

        .toolbar-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-print {
            background-color: #0284c7;
            color: #ffffff;
            border: none;
            padding: 7px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.2s;
        }

        .btn-print:hover {
            background-color: #0369a1;
        }

        .btn-close {
            background-color: #64748b;
            color: #ffffff;
            border: none;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-close:hover {
            background-color: #475569;
        }

        /* Printable Paper Sheet */
        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 20px auto;
            background: #ffffff;
            padding: 12mm 15mm;
            box-shadow: 0 4px 14px rgba(0,0,0,0.1);
            position: relative;
        }

        /* ISO Header Table */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
            margin-bottom: 12px;
        }

        .header-table td {
            vertical-align: middle;
            border: 1px solid #000;
            padding: 4px 6px;
        }

        .logo-cell {
            width: 18%;
            text-align: center;
            padding: 6px !important;
        }

        .logo-cell img {
            max-width: 90px;
            max-height: 55px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .title-cell {
            width: 48%;
            text-align: center;
            padding: 4px 8px !important;
        }

        .company-name {
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.3px;
            line-height: 1.2;
        }

        .standard-name {
            font-size: 10.5pt;
            font-weight: bold;
            margin-top: 3px;
            line-height: 1.2;
        }

        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 4px;
            line-height: 1.2;
        }

        .meta-cell {
            width: 34%;
            padding: 0 !important;
            vertical-align: top !important;
        }

        .meta-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }

        .meta-table td {
            border: 1px solid #000;
            padding: 3px 5px;
        }

        .meta-header {
            text-align: center;
            font-weight: bold;
            font-size: 8.5pt;
            background: #ffffff;
            padding: 3px 4px !important;
        }

        .meta-doc-num {
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            letter-spacing: 0.2px;
            padding: 3px 4px !important;
        }

        .meta-label {
            font-weight: bold;
            font-size: 8pt;
            width: 50%;
        }

        .meta-val {
            text-align: center;
            font-size: 8.5pt;
            width: 50%;
        }

        /* Employee Info Section */
        .emp-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 14px;
        }

        .emp-table td {
            vertical-align: middle;
            padding: 3px 2px;
        }

        .emp-box-cell {
            width: 32%;
            border: 1.5px solid #000;
            padding: 0 !important;
            text-align: center;
            vertical-align: top !important;
            background: #ffffff;
        }

        .emp-box-title {
            font-size: 9.5pt;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding: 4px 6px;
            background: #fafafa;
        }

        .emp-box-code {
            font-size: 11.5pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 18px 6px;
            word-break: break-all;
            color: #000;
        }

        .emp-spacer-cell {
            width: 3%;
        }

        .emp-label-cell {
            width: 25%;
            font-size: 9.5pt;
            font-weight: bold;
            padding: 4px 0 4px 6px !important;
        }

        .emp-colon-cell {
            width: 3%;
            font-size: 9.5pt;
            font-weight: bold;
            text-align: center;
            padding: 4px 0 !important;
        }

        .emp-val-cell {
            width: 37%;
            font-size: 9.5pt;
            border-bottom: 1px solid #000;
            padding: 4px 6px !important;
            min-height: 20px;
        }

        .font-bold {
            font-weight: bold;
        }

        /* Training Records Table */
        .records-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000;
        }

        .records-table th, 
        .records-table td {
            border: 1px solid #000;
            padding: 4px 5px;
            font-size: 9pt;
            line-height: 1.2;
        }

        .records-table th {
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            background: #ffffff;
            font-size: 9pt;
            padding: 6px 4px;
        }

        .text-center {
            text-align: center !important;
        }

        .text-left {
            text-align: left !important;
        }

        .record-row {
            height: 24px;
        }

        .blank-row {
            height: 24px;
        }

        .status-pass {
            font-weight: bold;
        }

        .status-fail {
            font-weight: bold;
        }

        /* Print Settings */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .page-sheet {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                page-break-after: always;
            }

            .page-sheet:last-child {
                page-break-after: auto;
            }
        }
    </style>
</head>
<body>

    <!-- Non-printable top action toolbar -->
    <div class="no-print-toolbar">
        <div class="toolbar-info">
            <strong>FM_CW_047 Individual Training Record</strong> | Dokumen ISO 45001:2018
        </div>
        <div class="toolbar-buttons">
            <button type="button" class="btn-print" onclick="window.print()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print / Cetak PDF
            </button>
            <button type="button" class="btn-close" onclick="window.close()">Tutup</button>
        </div>
    </div>

    @if (empty($grouped_records))
        <div class="page-sheet" style="text-align: center; padding-top: 100px;">
            <p style="font-size: 14pt; color: #666;">Tiada rekod latihan ditemui untuk kriteria carian ini.</p>
            <p style="font-size: 10pt; color: #999;">No training records found for the selected filter.</p>
        </div>
    @else
        @foreach ($grouped_records as $emp_code => $emp)
            <div class="page-sheet">
                <!-- 1. Header ISO Table -->
                <table class="header-table">
                    <tr>
                        <td class="logo-cell">
                            <img src="{{ asset('assets/ioi-logo.png') }}" alt="IOI Logo">
                        </td>
                        <td class="title-cell">
                            <div class="company-name">IOI CORPORATION BERHAD (PLANTATIONS)</div>
                            <div class="standard-name">OHSMS ISO 45001:2018</div>
                            <div class="doc-title">Title: Individual Training Record</div>
                        </td>
                        <td class="meta-cell">
                            <table class="meta-table">
                                <tr>
                                    <td colspan="2" class="meta-header">Document Reference Number</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="meta-doc-num">{{ $doc_ref_no }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Revision Date</td>
                                    <td class="meta-val">{{ $revision_date }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Page Number</td>
                                    <td class="meta-val">Page 1 of 1</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- 2. Employee Info Section -->
                <table class="emp-table">
                    <tr>
                        <!-- Left Box: Nombor Pekerja -->
                        <td class="emp-box-cell" rowspan="4">
                            <div class="emp-box-title">Nombor Pekerja</div>
                            <div class="emp-box-code">{{ $emp['employee_code'] }}</div>
                        </td>
                        <td class="emp-spacer-cell" rowspan="4"></td>
                        <!-- Right Info Lines -->
                        <td class="emp-label-cell">LADANG/KILANG</td>
                        <td class="emp-colon-cell">:</td>
                        <td class="emp-val-cell">{{ $emp['estate_name'] }}</td>
                    </tr>
                    <tr>
                        <td class="emp-label-cell">NAMA PEKERJA</td>
                        <td class="emp-colon-cell">:</td>
                        <td class="emp-val-cell font-bold">{{ $emp['employee_name'] }}</td>
                    </tr>
                    <tr>
                        <td class="emp-label-cell">NO PASSPORT</td>
                        <td class="emp-colon-cell">:</td>
                        <td class="emp-val-cell">{{ $emp['passport_no'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="emp-label-cell">TARIKH MULA KERJA</td>
                        <td class="emp-colon-cell">:</td>
                        <td class="emp-val-cell">{{ $emp['start_date'] ?? '-' }}</td>
                    </tr>
                </table>

                <!-- 3. Training Records Table -->
                <table class="records-table">
                    <thead>
                        <tr>
                            <th style="width: 6%;">NO</th>
                            <th style="width: 14%;">TARIKH</th>
                            <th style="width: 46%;">TAJUK LATIHAN</th>
                            <th style="width: 14%;">LULUS/GAGAL</th>
                            <th style="width: 20%;">TANDATANGAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $no = 1;
                            $records = $emp['records'] ?? [];
                            $total_rows = max(count($records), 25);
                        @endphp

                        @foreach ($records as $r)
                            @php
                                $trainingDate = is_object($r) ? $r->training_date : ($r['training_date'] ?? null);
                                $courseName = is_object($r) ? ($r->course_name ?? $r->course_no) : ($r['course_name'] ?? $r['course_no']);
                                $resultType = strtoupper(is_object($r) ? ($r->training_result_type ?? '') : ($r['training_result_type'] ?? ''));
                                $dateFormatted = !empty($trainingDate) ? date('d/m/Y', strtotime($trainingDate)) : '-';
                                $statusText = ($resultType === 'PASS') ? 'LULUS' : (($resultType === 'FAIL') ? 'GAGAL' : $resultType);
                                $statusClass = ($resultType === 'PASS') ? 'status-pass' : 'status-fail';
                            @endphp
                            <tr class="record-row">
                                <td class="text-center">{{ $no++ }}</td>
                                <td class="text-center">{{ $dateFormatted }}</td>
                                <td class="text-left">{{ $courseName }}</td>
                                <td class="text-center {{ $statusClass }}">{{ $statusText }}</td>
                                <td></td>
                            </tr>
                        @endforeach

                        <!-- Padded Blank Rows -->
                        @while ($no <= $total_rows)
                            <tr class="blank-row">
                                <td class="text-center" style="color: #999; font-size: 8.5pt;">{{ $no++ }}</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endwhile
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

</body>
</html>
