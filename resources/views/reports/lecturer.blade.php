@extends('reports.layout')

@php
    $pct = fn ($score) => $score === null ? 0 : max(0, min(100, ($score - $scheme['scale_min']) / max(0.01, $scheme['scale_max'] - $scheme['scale_min']) * 100));
    $fmt = fn ($value, $digits = 2) => $value === null ? '—' : number_format($value, $digits, ',', '.');
@endphp

@section('content')
    <h1>Laporan Evaluasi Pembelajaran Dosen</h1>
    <div class="muted">{{ $survey->title }}</div>

    <table class="meta">
        <tr><td class="label">Nama dosen</td><td>{{ $lecturer->full_name }}</td><td class="label">NIDN</td><td>{{ $lecturer->nidn ?? '—' }}</td></tr>
        <tr><td class="label">Homebase</td><td>{{ $lecturer->studyProgram?->full_name }}</td><td class="label">Periode</td><td>{{ $survey->academicPeriod?->name }}</td></tr>
    </table>

    @if ($summary['suppressed'])
        <p><strong>Respons belum mencukupi</strong> ({{ $summary['responses'] }} dari minimum {{ $minimum }}). Hasil rinci tidak ditampilkan untuk menjaga anonimitas mahasiswa.</p>
    @else
        <table class="kpis">
            <tr>
                <td class="kpi"><div class="l">Skor dosen</div><div class="v">{{ $fmt($summary['score']) }}</div><div class="note">{{ $summary['classification']['label'] ?? '—' }}</div></td>
                <td class="kpi"><div class="l">Respons</div><div class="v">{{ $summary['responses'] }}</div><div class="note">anonim</div></td>
                @foreach ($benchmarks as $benchmark)
                    <td class="kpi"><div class="l">{{ $benchmark['label'] }}</div><div class="v">{{ $fmt($benchmark['score']) }}</div></td>
                @endforeach
            </tr>
        </table>

        <h2>Skor per Aspek</h2>
        <table class="data">
            <thead><tr><th style="width: 10mm;">Kode</th><th>Aspek</th><th style="width: 45%;">Skor</th><th class="num">Nilai</th></tr></thead>
            <tbody>
            @foreach ($sections as $section)
                <tr><td><strong>{{ $section['code'] }}</strong></td><td>{{ $section['title'] }}</td><td><div class="bar"><span class="{{ $section['score'] < $threshold ? 'low' : '' }}" style="width: {{ $pct($section['score']) }}%"></span></div></td><td class="num"><strong>{{ $fmt($section['score']) }}</strong></td></tr>
            @endforeach
            </tbody>
        </table>

        <h2>Rincian per Butir</h2>
        <table class="data">
            <thead><tr><th style="width: 10mm;">Kode</th><th>Butir</th><th class="num">Skor</th></tr></thead>
            <tbody>
            @foreach ($questions as $question)
                <tr><td><strong>{{ $question['code'] }}</strong></td><td>{{ $question['label'] }}</td><td class="num" style="{{ $question['score'] !== null && $question['score'] < $threshold ? 'color:#a53b1c;' : '' }}"><strong>{{ $fmt($question['score']) }}</strong></td></tr>
            @endforeach
            </tbody>
        </table>

        <h2>Per Kelas</h2>
        <table class="data">
            <thead><tr><th>Mata kuliah</th><th>Kelas</th><th class="num">n</th><th class="num">Skor</th></tr></thead>
            <tbody>
            @foreach ($classes as $class)
                <tr><td>{{ $class['course'] }} ({{ $class['course_code'] }})</td><td>{{ $class['class_code'] }}</td><td class="num">{{ $class['responses'] }}</td><td class="num">{{ $class['sufficient'] ? $fmt($class['score']) : 'n < '.$minimum }}</td></tr>
            @endforeach
            </tbody>
        </table>

        @if (count($comments) > 0)
            <h2>Komentar Mahasiswa</h2>
            @foreach ($comments as $comment)
                <div class="quote">“{{ $comment['text'] }}”</div>
            @endforeach
        @endif
    @endif
@endsection
