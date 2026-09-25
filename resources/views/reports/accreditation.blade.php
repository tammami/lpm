@extends('reports.layout')

@php
    $fmt = fn ($value, $digits = 1) => $value === null ? '—' : number_format($value, $digits, ',', '.');
    $statusLabel = ['ready' => ['Siap', 'b-success'], 'partial' => ['Menunggu verifikasi', 'b-warning'], 'gap' => ['Belum ada bukti', 'b-danger']];
@endphp

@section('content')
    <h1>Laporan Kesiapan Akreditasi</h1>
    <div class="muted">{{ $period->studyProgram->full_name }} · {{ $period->name }} ({{ $period->code }})</div>

    <table class="meta">
        <tr><td class="label">Instrumen</td><td>{{ $period->version->instrument->name }} ({{ $period->version->instrument->code }} v{{ $period->version->version }})</td><td class="label">Lembaga</td><td>{{ $period->version->instrument->body?->code ?? '—' }}</td></tr>
        <tr><td class="label">Tahap</td><td>{{ $period->status->label() }}</td><td class="label">PIC</td><td>{{ $period->pic?->name ?? '—' }}</td></tr>
        <tr><td class="label">Batas pengajuan</td><td>{{ $period->submission_deadline?->translatedFormat('d F Y') ?? '—' }}</td><td class="label">Akreditasi saat ini</td><td>{{ $period->studyProgram->accreditation_status ?? '—' }} · s.d. {{ $period->studyProgram->accreditation_valid_until?->translatedFormat('d F Y') ?? '—' }}</td></tr>
    </table>

    <table class="kpis">
        <tr>
            <td class="kpi"><div class="l">Kesiapan dokumen</div><div class="v">{{ $fmt($summary['readiness']) }}%</div><div class="note">{{ $summary['ready'] }} siap · {{ $summary['partial'] }} menunggu · {{ $summary['gap'] }} gap</div></td>
            <td class="kpi"><div class="l">Penilaian diri</div><div class="v">{{ $summary['assessed'] }}/{{ $summary['total'] }}</div><div class="note">indikator dinilai</div></td>
            <td class="kpi"><div class="l">Estimasi skor</div><div class="v">{{ $fmt($summary['estimated_score']) }}</div><div class="note">skala 0–400 · {{ $summary['estimated_grade'] ?? 'belum dapat diestimasi' }}</div></td>
            <td class="kpi"><div class="l">Syarat perlu belum terpenuhi</div><div class="v" style="{{ $summary['essential_unmet'] ? 'color:#a53b1c' : '' }}">{{ $summary['essential_unmet'] }}</div><div class="note">indikator esensial</div></td>
        </tr>
    </table>

    <h2>Kesiapan per Kriteria</h2>
    <table class="data">
        <thead><tr><th style="width: 14mm;">Kode</th><th>Kriteria</th><th class="num">Bobot</th><th class="num">Siap</th><th style="width: 30%;">Kesiapan dokumen</th><th class="num">%</th><th class="num">Skor diri</th></tr></thead>
        <tbody>
        @foreach ($criteria as $row)
            <tr>
                <td><strong>{{ $row['code'] }}</strong></td>
                <td>{{ $row['title'] }}</td>
                <td class="num">{{ $fmt($row['weight']) }}</td>
                <td class="num">{{ $row['ready'] }}/{{ $row['total'] }}</td>
                <td><div class="bar"><span class="{{ ($row['readiness'] ?? 0) < 60 ? 'low' : '' }}" style="width: {{ $row['readiness'] ?? 0 }}%"></span></div></td>
                <td class="num"><strong>{{ $fmt($row['readiness']) }}</strong></td>
                <td class="num">{{ $fmt($row['self_average'], 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Gap Analysis & Checklist Dokumen</h2>
    @if (count($gaps) === 0)
        <p class="muted">Seluruh indikator telah memiliki bukti terverifikasi.</p>
    @else
        <table class="data">
            <thead><tr><th style="width: 14mm;">Kriteria</th><th style="width: 16mm;">Indikator</th><th>Pernyataan & bukti yang dibutuhkan</th><th style="width: 30mm;">Status</th></tr></thead>
            <tbody>
            @foreach ($gaps as $gap)
                <tr>
                    <td>{{ $gap['criterion'] }}</td>
                    <td><strong>{{ $gap['code'] }}</strong>@if ($gap['is_essential'])<br><span class="badge b-danger">Esensial</span>@endif</td>
                    <td>{{ $gap['statement'] }}@if ($gap['evidence_hint'])<br><span class="note">Bukti: {{ $gap['evidence_hint'] }}</span>@endif</td>
                    <td><span class="badge {{ $statusLabel[$gap['status']][1] }}">{{ $statusLabel[$gap['status']][0] }}</span>@if ($gap['self_score'] !== null)<br><span class="note">Skor diri {{ $fmt($gap['self_score'], 2) }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <p class="note" style="margin-top: 4mm;">Kesiapan dokumen = (indikator siap + ½ menunggu verifikasi) ÷ total indikator. Estimasi skor dihitung dari penilaian diri tertimbang dan hanya bersifat indikatif, bukan hasil resmi asesmen LAM.</p>
@endsection
