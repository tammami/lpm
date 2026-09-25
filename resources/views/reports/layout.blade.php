<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 26mm 16mm 18mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1c2b24; line-height: 1.45; }
        header { position: fixed; top: -20mm; left: 0; right: 0; height: 18mm; border-bottom: 2px solid #0f3d2c; }
        header table { width: 100%; }
        header img { height: 13mm; }
        header .inst { font-size: 11.5pt; font-weight: bold; color: #0f3d2c; }
        header .sub { font-size: 7.5pt; color: #5b6b63; }
        footer { position: fixed; bottom: -12mm; left: 0; right: 0; font-size: 7pt; color: #7a8a82; border-top: 1px solid #d7ddd9; padding-top: 2mm; }
        .pagenum:before { content: counter(page); }
        h1 { font-size: 15pt; margin: 0 0 1mm; color: #0f3d2c; }
        h2 { font-size: 11pt; margin: 7mm 0 2.5mm; padding-bottom: 1.2mm; color: #0f3d2c; border-bottom: 1px solid #e3d6ae; }
        .muted { color: #6b7a72; }
        .meta { width: 100%; margin: 3mm 0 5mm; border-collapse: collapse; font-size: 8.5pt; }
        .meta td { padding: 1mm 2mm; border: 1px solid #e3e7e4; }
        .meta td.label { background: #f3f6f4; width: 32mm; font-weight: bold; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 2mm 0; margin: 0 -2mm; }
        .kpi { border: 1px solid #dfe5e1; border-radius: 2mm; padding: 3mm; background: #fbfcfb; }
        .kpi .v { font-size: 17pt; font-weight: bold; color: #0f6b47; }
        .kpi .l { font-size: 7.5pt; color: #6b7a72; text-transform: uppercase; letter-spacing: .3pt; }
        table.data { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        table.data th { background: #0f3d2c; color: #fff; text-align: left; padding: 1.6mm 2mm; font-weight: bold; }
        table.data td { padding: 1.4mm 2mm; border-bottom: 1px solid #e6eae7; vertical-align: top; }
        table.data tr:nth-child(even) td { background: #f8faf9; }
        .num { text-align: right; white-space: nowrap; }
        .bar { height: 3mm; background: #e9eeeb; border-radius: 1.5mm; position: relative; }
        .bar span { display: block; height: 3mm; border-radius: 1.5mm; background: #077649; }
        .bar span.low { background: #d36340; }
        .badge { display: inline-block; padding: .3mm 1.8mm; border-radius: 3mm; font-size: 7pt; font-weight: bold; }
        .b-success { background: #e2f3e9; color: #0f6b47; } .b-info { background: #e3eff7; color: #1c6e98; }
        .b-warning { background: #fbf0d8; color: #7a5410; } .b-danger { background: #fbe4dc; color: #a53b1c; } .b-neutral { background: #eceeed; color: #555; }
        .note { font-size: 7.5pt; color: #6b7a72; }
        .signature { width: 100%; margin-top: 12mm; page-break-inside: avoid; }
        .signature td { width: 50%; vertical-align: top; font-size: 9pt; }
        .quote { padding: 1.5mm 3mm; margin: 0 0 2mm; background: #fbf8ef; border-radius: 1.5mm; }
    </style>
</head>
<body>
<header>
    <table>
        <tr>
            <td style="width: 16mm;"><img src="{{ $brand['logo'] }}" alt="Logo"></td>
            <td>
                <div class="inst">{{ $brand['name'] }}</div>
                <div class="sub">Lembaga Penjaminan Mutu · {{ collect([$brand['address'], $brand['phone'], $brand['email'], $brand['website']])->filter()->implode(' · ') }}</div>
            </td>
            <td style="text-align: right;" class="sub">SIMUTU<br>Sistem Informasi Penjaminan Mutu</td>
        </tr>
    </table>
</header>
<footer>
    <table style="width: 100%;">
        <tr>
            <td>Dicetak {{ $generatedAt }} oleh {{ $generatedBy }} · Dokumen dihasilkan otomatis oleh SIMUTU</td>
            <td style="text-align: right;">Halaman <span class="pagenum"></span></td>
        </tr>
    </table>
</footer>

<main>
    @yield('content')

    @if ($signature ?? true)
        <table class="signature">
            <tr>
                <td></td>
                <td>
                    {{ $brand['city'] }}, {{ now()->translatedFormat('d F Y') }}<br>
                    Ketua Lembaga Penjaminan Mutu,<br><br><br><br><br>
                    <strong>{{ $brand['lpm_head'] ?? '(..................................................)' }}</strong>
                </td>
            </tr>
        </table>
    @endif
</main>
</body>
</html>
