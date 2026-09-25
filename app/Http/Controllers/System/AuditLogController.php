<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public const EVENTS = [
        'login' => 'Masuk', 'logout' => 'Keluar', 'login_failed' => 'Gagal masuk', 'created' => 'Membuat', 'updated' => 'Mengubah',
        'deleted' => 'Menghapus', 'published' => 'Menerbitkan', 'approved' => 'Menyetujui', 'status_changed' => 'Ubah status',
        'imported' => 'Impor', 'exported' => 'Ekspor', 'uploaded' => 'Unggah', 'permission_changed' => 'Ubah hak akses',
        'password_changed' => 'Ganti sandi', 'password_reset' => 'Reset sandi', 'reopened' => 'Buka kembali', 'verified' => 'Verifikasi',
        'activated' => 'Aktivasi', 'downloaded' => 'Unduh', 'issued' => 'Terbitkan temuan', 'closed' => 'Menutup', 'enrolled' => 'Tambah peserta', 'unenrolled' => 'Keluarkan peserta',
    ];

    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name,username')
            ->when($request->filled('event') && $request->input('event') !== 'all', fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('module') && $request->input('module') !== 'all', fn ($q) => $q->where('module', $request->input('module')))
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($inner) => $inner
                ->where('description', 'like', '%'.$request->string('search').'%')
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$request->string('search').'%'))))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                ...$log->only(['id', 'event', 'module', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'auditable_type', 'auditable_id']),
                'event_label' => self::EVENTS[$log->event] ?? $log->event,
                'user' => $log->user?->name,
                'username' => $log->user?->username,
                'subject' => $log->auditable_type ? class_basename($log->auditable_type)." #{$log->auditable_id}" : null,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('system/audit-logs', [
            'logs' => $logs,
            'filters' => $this->filters($request, ['event', 'module', 'from', 'to']),
            'events' => collect(self::EVENTS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'modules' => AuditLog::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module')
                ->map(fn (string $module): array => ['value' => $module, 'label' => ucfirst($module)])->all(),
        ]);
    }
}
