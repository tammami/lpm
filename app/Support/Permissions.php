<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Registri permission RBAC beserta pemetaan bawaan ke peran.
 * Pemetaan dapat diubah melalui menu Sistem › Peran & Hak Akses.
 */
final class Permissions
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'Umum' => [
                'dashboard.view' => 'Melihat dashboard',
                'notifications.view' => 'Menerima notifikasi',
            ],
            'Master Data' => [
                'master.view' => 'Melihat master data',
                'master.manage' => 'Mengelola master data akademik',
                'organization.manage' => 'Mengelola institusi, fakultas & lembaga akreditasi',
            ],
            'Instrumen' => [
                'instruments.view' => 'Melihat instrumen',
                'instruments.manage' => 'Menyusun & mengubah instrumen',
                'instruments.approve' => 'Meninjau & menyetujui instrumen',
                'instruments.publish' => 'Menerbitkan instrumen',
            ],
            'e-Monev' => [
                'surveys.view' => 'Melihat kegiatan Monev',
                'surveys.manage' => 'Mengelola kegiatan Monev',
                'surveys.reopen' => 'Membuka kembali pengisian responden',
                'monev.fill' => 'Mengisi Monev/Survei',
                'monev.own_results' => 'Melihat hasil evaluasi diri (dosen)',
            ],
            'Analitik & Laporan' => [
                'analytics.view' => 'Melihat analitik',
                'analytics.lecturer' => 'Melihat hasil individual dosen',
                'reports.export' => 'Mengekspor laporan (PDF/Excel)',
            ],
            'AMI' => [
                'ami.view' => 'Melihat AMI',
                'ami.manage' => 'Mengelola program & jadwal audit',
                'ami.audit' => 'Melaksanakan audit (auditor)',
                'findings.respond' => 'Menindaklanjuti temuan (auditee/PIC)',
            ],
            'Peningkatan Mutu' => [
                'improvement.view' => 'Melihat rekomendasi & rencana aksi',
                'improvement.manage' => 'Mengelola rekomendasi & rencana aksi',
                'improvement.verify' => 'Memverifikasi tindak lanjut',
            ],
            'Dokumen Bukti' => [
                'evidence.view' => 'Melihat repositori dokumen',
                'evidence.manage' => 'Mengunggah & mengelola dokumen',
                'evidence.verify' => 'Memverifikasi dokumen',
            ],
            'Akreditasi' => [
                'accreditation.view' => 'Melihat kesiapan akreditasi',
                'accreditation.manage' => 'Mengelola instrumen & periode akreditasi',
            ],
            'Sistem' => [
                'import.manage' => 'Impor data',
                'users.manage' => 'Mengelola pengguna',
                'roles.manage' => 'Mengelola peran & hak akses',
                'audit_logs.view' => 'Melihat log audit',
                'settings.manage' => 'Mengubah pengaturan sistem',
                'backups.manage' => 'Mencadangkan database',
                'backups.restore' => 'Memulihkan database dari cadangan',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(self::grouped())));
    }

    /**
     * Pemetaan bawaan peran → permission.
     *
     * @return list<string>
     */
    public static function defaultsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::Superadmin => self::all(),
            UserRole::AdminLpm => array_values(array_diff(self::all(), ['roles.manage', 'backups.restore', 'monev.fill', 'monev.own_results'])),
            UserRole::AdminFakultas, UserRole::AdminProdi => [
                'dashboard.view', 'notifications.view', 'master.view', 'master.manage', 'instruments.view',
                'surveys.view', 'analytics.view', 'analytics.lecturer', 'reports.export', 'ami.view',
                'findings.respond', 'improvement.view', 'improvement.manage', 'evidence.view', 'evidence.manage',
                'accreditation.view', 'import.manage',
            ],
            UserRole::Pimpinan => [
                'dashboard.view', 'notifications.view', 'master.view', 'instruments.view', 'surveys.view',
                'analytics.view', 'analytics.lecturer', 'reports.export', 'ami.view', 'improvement.view',
                'evidence.view', 'accreditation.view',
            ],
            UserRole::Auditor => [
                'dashboard.view', 'notifications.view', 'ami.view', 'ami.audit', 'evidence.view', 'instruments.view',
            ],
            UserRole::Dosen => [
                'dashboard.view', 'notifications.view', 'monev.fill', 'monev.own_results', 'findings.respond',
                'improvement.view', 'evidence.view', 'evidence.manage',
            ],
            UserRole::Mahasiswa => ['monev.fill', 'notifications.view'],
        };
    }
}
