import {
    Activity,
    BadgeCheck,
    BarChart3,
    Bell,
    BookOpenCheck,
    Building2,
    ClipboardCheck,
    ClipboardList,
    FileBarChart2,
    FileSpreadsheet,
    FolderArchive,
    GraduationCap,
    LayoutDashboard,
    type LucideIcon,
    Settings2,
    ShieldCheck,
    Target,
    UserRoundSearch,
} from 'lucide-react';

export interface NavLeaf {
    title: string;
    route: string;
    /** Pola nama rute yang menandai item aktif (default: prefix dari `route`). */
    match?: string;
    permission?: string | string[];
    /** Hanya tampil bila pengguna memiliki profil dosen/mahasiswa. */
    profile?: 'lecturer' | 'respondent';
}

export interface NavItem extends Partial<NavLeaf> {
    title: string;
    icon: LucideIcon;
    children?: NavLeaf[];
    badgeKey?: string;
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

export const navigation: NavGroup[] = [
    {
        label: 'Utama',
        items: [
            { title: 'Dashboard', icon: LayoutDashboard, route: 'dashboard', permission: 'dashboard.view' },
            { title: 'Hasil Evaluasi Saya', icon: UserRoundSearch, route: 'my-evaluation.index', permission: 'monev.own_results', profile: 'lecturer' },
            { title: 'Isi Survei', icon: ClipboardCheck, route: 'portal.home', match: 'portal.*', permission: 'monev.fill', profile: 'respondent' },
            { title: 'Notifikasi', icon: Bell, route: 'notifications.index', permission: 'notifications.view', badgeKey: 'unreadNotifications' },
        ],
    },
    {
        label: 'Penjaminan Mutu',
        items: [
            {
                title: 'e-Monev',
                icon: ClipboardList,
                children: [
                    { title: 'Instrumen', route: 'instruments.index', match: 'instruments.*', permission: 'instruments.view' },
                    { title: 'Kegiatan Monev', route: 'surveys.index', match: 'surveys.*', permission: 'surveys.view' },
                ],
            },
            {
                title: 'Audit Mutu Internal',
                icon: ShieldCheck,
                children: [
                    { title: 'Program Audit', route: 'ami.programs.index', match: 'ami.programs.*', permission: 'ami.view' },
                    { title: 'Jadwal Audit', route: 'ami.audits.index', match: 'ami.audits.*', permission: ['ami.view', 'ami.audit'] },
                    { title: 'Temuan', route: 'ami.findings.index', match: 'ami.findings.*', permission: ['ami.view', 'findings.respond'] },
                    { title: 'Auditor', route: 'ami.auditors.index', match: 'ami.auditors.*', permission: 'ami.manage' },
                    { title: 'Standar & Referensi', route: 'ami.standards.index', match: 'ami.standards.*', permission: 'ami.manage' },
                ],
            },
            {
                title: 'Peningkatan Mutu',
                icon: Target,
                children: [
                    { title: 'Rekomendasi', route: 'improvement.recommendations.index', match: 'improvement.recommendations.*', permission: 'improvement.view' },
                    { title: 'Monitoring Rencana Aksi', route: 'improvement.action-plans.index', match: 'improvement.action-plans.*', permission: 'improvement.view' },
                ],
            },
            { title: 'Dokumen Bukti', icon: FolderArchive, route: 'evidence.index', match: 'evidence.*', permission: 'evidence.view' },
            {
                title: 'Akreditasi',
                icon: BadgeCheck,
                children: [
                    { title: 'Kesiapan Akreditasi', route: 'accreditation.readiness', match: 'accreditation.readiness*', permission: 'accreditation.view' },
                    { title: 'Periode Akreditasi', route: 'accreditation.periods.index', match: 'accreditation.periods.*', permission: 'accreditation.view' },
                    { title: 'Instrumen LAM', route: 'accreditation.instruments.index', match: 'accreditation.instruments.*', permission: 'accreditation.manage' },
                ],
            },
        ],
    },
    {
        label: 'Analitik',
        items: [
            { title: 'Analitik Mutu', icon: BarChart3, route: 'analytics.index', match: 'analytics.*', permission: 'analytics.view' },
            { title: 'Laporan', icon: FileBarChart2, route: 'reports.index', match: 'reports.*', permission: 'reports.export' },
        ],
    },
    {
        label: 'Data',
        items: [
            {
                title: 'Master Akademik',
                icon: GraduationCap,
                children: [
                    { title: 'Periode Akademik', route: 'academic-periods.index', match: 'academic-periods.*', permission: 'master.view' },
                    { title: 'Dosen', route: 'lecturers.index', match: 'lecturers.*', permission: 'master.view' },
                    { title: 'Mahasiswa', route: 'students.index', match: 'students.*', permission: 'master.view' },
                    { title: 'Mata Kuliah', route: 'courses.index', match: 'courses.*', permission: 'master.view' },
                    { title: 'Kelas & Pengampu', route: 'classes.index', match: 'classes.*', permission: 'master.view' },
                ],
            },
            {
                title: 'Organisasi',
                icon: Building2,
                children: [
                    { title: 'Profil Institusi', route: 'institution.edit', match: 'institution.*', permission: 'organization.manage' },
                    { title: 'Fakultas', route: 'faculties.index', match: 'faculties.*', permission: 'master.view' },
                    { title: 'Program Studi', route: 'study-programs.index', match: 'study-programs.*', permission: 'master.view' },
                    { title: 'Unit Kerja', route: 'units.index', match: 'units.*', permission: 'master.view' },
                    { title: 'Lembaga Akreditasi', route: 'accreditation-bodies.index', match: 'accreditation-bodies.*', permission: 'organization.manage' },
                ],
            },
            { title: 'Impor Data', icon: FileSpreadsheet, route: 'imports.index', match: 'imports.*', permission: 'import.manage' },
        ],
    },
    {
        label: 'Sistem',
        items: [
            {
                title: 'Pengaturan',
                icon: Settings2,
                children: [
                    { title: 'Pengguna', route: 'users.index', match: 'users.*', permission: 'users.manage' },
                    { title: 'Peran & Hak Akses', route: 'roles.index', match: 'roles.*', permission: 'roles.manage' },
                    { title: 'Klasifikasi & Skala', route: 'settings.scales', match: 'settings.scales*', permission: 'settings.manage' },
                    { title: 'Konfigurasi Sistem', route: 'settings.index', match: 'settings.index', permission: 'settings.manage' },
                ],
            },
            { title: 'Log Audit', icon: Activity, route: 'audit-logs.index', match: 'audit-logs.*', permission: 'audit_logs.view' },
        ],
    },
];

export const quickLinksIcon = BookOpenCheck;

function routeExists(name?: string) {
    if (!name) return false;
    try {
        return route().has(name);
    } catch {
        return false;
    }
}

/**
 * Saring navigasi berdasarkan permission dan rute yang sudah tersedia.
 */
export function filterNavigation(can: (...permissions: string[]) => boolean, profile: { is_lecturer?: boolean; is_student?: boolean } = {}): NavGroup[] {
    const allowed = (leaf: Partial<NavLeaf>) => {
        if (!routeExists(leaf.route)) return false;
        if (leaf.profile === 'lecturer' && !profile.is_lecturer) return false;
        if (leaf.profile === 'respondent' && !profile.is_lecturer && !profile.is_student) return false;
        if (!leaf.permission) return true;
        return can(...(Array.isArray(leaf.permission) ? leaf.permission : [leaf.permission]));
    };

    return navigation
        .map((group) => ({
            ...group,
            items: group.items
                .map((item) => (item.children ? { ...item, children: item.children.filter(allowed) } : item))
                .filter((item) => (item.children ? item.children.length > 0 : allowed(item))),
        }))
        .filter((group) => group.items.length > 0);
}

export function isActive(leaf: Partial<NavLeaf>) {
    if (!leaf.route) return false;
    const pattern = leaf.match ?? leaf.route.replace(/\.index$/, '.*');
    try {
        return route().current(pattern) || route().current(leaf.route);
    } catch {
        return false;
    }
}
