@extends('reports.layout')

@php
    $pct = fn ($score) => $score === null ? 0 : max(0, min(100, ($score - $scheme['scale_min']) / max(0.01, $scheme['scale_max'] - $scheme['scale_min']) * 100));
    $fmt = fn ($value, $digits = 2) => $value === null ? '—' : number_format($value, $digits, ',', '.');
@endphp

@section('content')
    <h1>Laporan Hasil {{ $survey->title }}</h1>
    <div class="muted">{{ $scopeLabel }}</div>

    <table class="meta">
        <tr><td class="label">Instrumen</td><td>{{ $survey->instrumentVersion->instrument->name }} v{{ $survey->instrumentVersion->version }}</td><td class="label">Periode</td><td>{{ $survey->academicPeriod?->name ?? '—' }}</td></tr>
        <tr><td class="label">Metode skor</td><td>{{ $survey->instrumentVersion->scoring_method->label() }} ({{ $survey->instrumentVersion->scoring_method->formula() }})</td><td class="label">Pelaksanaan</td><td>{{ $survey->starts_at->translatedFormat('d M Y') }} – {{ $survey->ends_at->translatedFormat('d M Y') }}</td></tr>
        <tr><td class="label">Anonimitas</td><td>{{ $survey->is_anonymous ? 'Anonim' : 'Tidak anonim' }} · minimum {{ $minimum }} respons per dosen/kelas</td><td class="label">Status</td><td>{{ $survey->status->label() }}</td></tr>
    </table>

    <table class="kpis">
        <tr>
            <td class="kpi"><div class="l">Skor rata-rata</div><div class="v">{{ $fmt($summary['score']) }}</div><div class="note">dari skala {{ $fmt($scheme['scale_max'], 0) }} · {{ $summary['classification']['label'] ?? '—' }}</div></td>
            <td class="kpi"><div class="l">Respons masuk</div><div class="v">{{ number_format($summary['responses'], 0, ',', '.') }}</div><div class="note">dari {{ number_format($progress['eligible'], 0, ',', '.') }} eligible</div></td>
            <td class="kpi"><div class="l">Response rate</div><div class="v">{{ $fmt($progress['rate'], 1) }}%</div><div class="note">seluruh sasaran kegiatan</div></td>
            <td class="kpi"><div class="l">Butir di bawah ambang</div><div class="v">{{ collect($questions)->filter(fn ($q) => $q['score'] !== null && $q['score'] < $threshold)->count() }}</div><div class="note">ambang {{ $fmt($threshold) }}</div></td>
        </tr>
    </table>

    @if (count($programs) > 0)
        <h2>Skor per Program Studi</h2>
        <table class="data">
            <thead><tr><th style="width: 40%;">Program studi</th><th class="num">n</th><th style="width: 30%;">Skor</th><th class="num">Nilai</th><th>Klasifikasi</th></tr></thead>
            <tbody>
            @foreach ($programs as $program)
                <tr>
                    <td>{{ $program['name'] }}</td>
                    <td class="num">{{ $program['responses'] }}</td>
                    <td><div class="bar"><span class="{{ $program['score'] < $threshold ? 'low' : '' }}" style="width: {{ $pct($program['score']) }}%"></span></div></td>
                    <td class="num"><strong>{{ $fmt($program['score']) }}</strong></td>
                    <td><span class="badge b-{{ $program['classification']['color'] ?? 'neutral' }}">{{ $program['classification']['label'] ?? '—' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Skor per Aspek</h2>
    <table class="data">
        <thead><tr><th style="width: 10mm;">Kode</th><th>Aspek</th><th style="width: 45%;">Skor</th><th class="num">Nilai</th></tr></thead>
        <tbody>
        @foreach ($sections as $section)
            <tr>
                <td><strong>{{ $section['code'] }}</strong></td>
                <td>{{ $section['title'] }}</td>
                <td><div class="bar"><span class="{{ $section['score'] < $threshold ? 'low' : '' }}" style="width: {{ $pct($section['score']) }}%"></span></div></td>
                <td class="num"><strong>{{ $fmt($section['score']) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Analisis per Butir / Indikator</h2>
    <table class="data">
        <thead><tr><th style="width: 10mm;">Kode</th><th>Butir</th><th class="num">n</th><th class="num">Skor</th><th>Klasifikasi</th></tr></thead>
        <tbody>
        @foreach ($questions as $question)
            <tr>
                <td><strong>{{ $question['code'] }}</strong></td>
                <td>{{ $question['label'] }}@if ($question['indicator'])<br><span class="note">Indikator: {{ $question['indicator'] }}</span>@endif</td>
                <td class="num">{{ $question['responses'] }}</td>
                <td class="num" style="{{ $question['score'] !== null && $question['score'] < $threshold ? 'color:#a53b1c;' : '' }}"><strong>{{ $fmt($question['score']) }}</strong></td>
                <td><span class="badge b-{{ $question['classification']['color'] ?? 'neutral' }}">{{ $question['classification']['label'] ?? '—' }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if ($lecturers !== null && count($lecturers) > 0)
        <h2>Skor per Dosen</h2>
        <p class="note">Dosen dengan respons kurang dari {{ $minimum }} tidak ditampilkan skornya untuk menjaga anonimitas mahasiswa.</p>
        <table class="data">
            <thead><tr><th>Dosen</th><th>Homebase</th><th class="num">Kelas</th><th class="num">n</th><th class="num">Skor</th><th>Klasifikasi</th></tr></thead>
            <tbody>
            @foreach ($lecturers as $lecturer)
                <tr>
                    <td>{{ $lecturer['name'] }}</td>
                    <td>{{ $lecturer['study_program'] }}</td>
                    <td class="num">{{ $lecturer['classes'] }}</td>
                    <td class="num">{{ $lecturer['responses'] }}</td>
                    <td class="num"><strong>{{ $lecturer['sufficient'] ? $fmt($lecturer['score']) : '—' }}</strong></td>
                    <td>@if ($lecturer['sufficient'])<span class="badge b-{{ $lecturer['classification']['color'] ?? 'neutral' }}">{{ $lecturer['classification']['label'] ?? '—' }}</span>@else<span class="badge b-neutral">Respons tidak mencukupi</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if (count($comments) > 0)
        <h2>Cuplikan Komentar Mahasiswa</h2>
        @foreach ($comments as $comment)
            <div class="quote">“{{ $comment['text'] }}”<br><span class="note">{{ $comment['question'] }}</span></div>
        @endforeach
    @endif

    <h2>Rekomendasi Awal</h2>
    <ul>
        @forelse (collect($questions)->filter(fn ($q) => $q['score'] !== null && $q['score'] < $threshold)->sortBy('score')->take(5) as $question)
            <li>Indikator <strong>{{ $question['indicator'] ?? $question['code'] }}</strong> (skor {{ $fmt($question['score']) }}) berada di bawah ambang. Perlu evaluasi pelaksanaan indikator dan penyusunan rencana aksi.</li>
        @empty
            <li>Seluruh indikator berada di atas ambang {{ $fmt($threshold) }}. Pertahankan praktik baik dan dokumentasikan sebagai bukti mutu.</li>
        @endforelse
    </ul>
    <p class="note">Klasifikasi: {{ collect($scheme['classes'])->map(fn ($c) => $c['label'].' ('.$c['min_score'].'–'.$c['max_score'].')')->implode(' · ') }}.</p>
@endsection
