@extends('reports.layout')

@php
    $fmt = fn ($value, $digits = 1) => $value === null ? '—' : number_format($value, $digits, ',', '.');
    $lead = $audit->auditors->firstWhere('pivot.role', 'lead');
@endphp

@section('content')
    <h1>Laporan Audit Mutu Internal</h1>
    <div class="muted">{{ $audit->program->name }} · {{ $audit->code }}</div>

    <table class="meta">
        <tr><td class="label">Auditee</td><td>{{ $audit->auditee_name }} ({{ $audit->auditee_type->label() }})</td><td class="label">Tanggal audit</td><td>{{ $audit->scheduled_on?->translatedFormat('d F Y') }}</td></tr>
        <tr><td class="label">Ketua auditor</td><td>{{ $lead?->user?->name ?? '—' }}</td><td class="label">Anggota</td><td>{{ $audit->auditors->where('pivot.role', 'member')->map(fn ($a) => $a->user?->name)->implode(', ') ?: '—' }}</td></tr>
        <tr><td class="label">Instrumen</td><td>{{ $audit->instrumentVersion?->instrument->name }} v{{ $audit->instrumentVersion?->version }}</td><td class="label">Status</td><td>{{ $audit->status->label() }}</td></tr>
        <tr><td class="label">PIC auditee</td><td>{{ $audit->auditeePic?->name ?? '—' }}</td><td class="label">Lokasi</td><td>{{ $audit->location ?? '—' }}</td></tr>
    </table>

    <table class="kpis">
        <tr>
            <td class="kpi"><div class="l">Skor kepatuhan</div><div class="v">{{ $fmt($compliance['score_percent']) }}%</div><div class="note">{{ $compliance['answered'] }} dari {{ $compliance['total'] }} butir dinilai</div></td>
            @foreach ($compliance['breakdown'] as $item)
                <td class="kpi"><div class="l">{{ \Illuminate\Support\Str::before($item['label'], ' (') }}</div><div class="v">{{ $item['count'] }}</div><div class="note">butir</div></td>
            @endforeach
        </tr>
    </table>

    <h2>Kepatuhan per Standar</h2>
    <table class="data">
        <thead><tr><th style="width: 18mm;">Kode</th><th>Standar</th><th class="num">Butir</th><th style="width: 32%;">Skor</th><th class="num">%</th></tr></thead>
        <tbody>
        @foreach ($compliance['by_standard'] as $row)
            <tr>
                <td><strong>{{ $row['code'] }}</strong></td>
                <td>{{ $row['name'] }}</td>
                <td class="num">{{ $row['answered'] }}/{{ $row['total'] }}</td>
                <td><div class="bar"><span class="{{ ($row['score_percent'] ?? 0) < 75 ? 'low' : '' }}" style="width: {{ $row['score_percent'] ?? 0 }}%"></span></div></td>
                <td class="num"><strong>{{ $fmt($row['score_percent']) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Daftar Tilik</h2>
    <table class="data">
        <thead><tr><th style="width: 10mm;">Kode</th><th>Butir</th><th style="width: 32mm;">Hasil</th><th style="width: 45mm;">Catatan auditor</th></tr></thead>
        <tbody>
        @foreach ($version?->sections ?? [] as $section)
            <tr><td colspan="4" style="background:#eef4f0;"><strong>{{ $section->code }}. {{ $section->title }}</strong></td></tr>
            @foreach ($section->questions as $question)
                @php($answer = $answers->get($question->id))
                <tr>
                    <td><strong>{{ $question->code }}</strong></td>
                    <td>{{ $question->label }}@if ($question->standard)<br><span class="note">{{ $question->standard->code }}</span>@endif</td>
                    <td>{{ $answer?->option?->label ?? '—' }}</td>
                    <td class="note">{{ $answer?->auditor_note }}</td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>

    <h2>Temuan Audit ({{ $findings->count() }})</h2>
    @if ($findings->isEmpty())
        <p>Tidak terdapat temuan.</p>
    @else
        <table class="data">
            <thead><tr><th style="width: 24mm;">Kode</th><th>Temuan</th><th style="width: 22mm;">Kategori</th><th style="width: 26mm;">PIC · Tenggat</th><th style="width: 24mm;">Status</th></tr></thead>
            <tbody>
            @foreach ($findings as $finding)
                <tr>
                    <td><strong>{{ $finding->code }}</strong></td>
                    <td>
                        <strong>{{ $finding->title }}</strong><br>
                        <span class="note">{{ $finding->description }}</span>
                        @if ($finding->standard)<br><span class="note">Standar: {{ $finding->standard->code }} {{ $finding->standard->name }}</span>@endif
                        @foreach ($finding->correctiveActions as $action)
                            <br><span class="note">• Tindakan: {{ $action->action_plan }} ({{ $action->pic?->name }}, {{ $action->status->label() }})</span>
                        @endforeach
                    </td>
                    <td><span class="badge b-{{ $finding->severity->color }}">{{ $finding->severity->name }}</span></td>
                    <td>{{ $finding->pic?->name ?? '—' }}<br><span class="note">{{ $finding->due_date?->translatedFormat('d M Y') }}</span></td>
                    <td>{{ $finding->status->label() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if ($audit->strengths)
        <h2>Kekuatan</h2>
        <p>{!! nl2br(e($audit->strengths)) !!}</p>
    @endif
    @if ($audit->summary)
        <h2>Ringkasan</h2>
        <p>{!! nl2br(e($audit->summary)) !!}</p>
    @endif
    <h2>Kesimpulan</h2>
    <p>{!! nl2br(e($audit->conclusion ?? 'Belum diisi.')) !!}</p>

    <table class="signature">
        <tr>
            <td>Auditee,<br><br><br><br><br><strong>{{ $audit->auditeePic?->name ?? '(..................................)' }}</strong></td>
            <td>{{ $brand['city'] }}, {{ ($audit->completed_at ?? now())->translatedFormat('d F Y') }}<br>Ketua Auditor,<br><br><br><br><strong>{{ $lead?->user?->name ?? '(..................................)' }}</strong></td>
        </tr>
    </table>
@endsection
