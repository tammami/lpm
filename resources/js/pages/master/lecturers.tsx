import { useForm } from '@inertiajs/react';
import { Download, KeyRound, Pencil, Plus, Trash2, UserRoundCheck } from 'lucide-react';
import { cleanQuery } from '@/lib/utils';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { TableToolbar } from '@/components/table-toolbar';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { fromNow } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Lecturer {
    id: number;
    nidn: string | null;
    nip: string | null;
    name: string;
    front_title: string | null;
    back_title: string | null;
    full_name: string;
    email: string | null;
    phone: string | null;
    gender: string | null;
    academic_rank: string | null;
    employment_status: string;
    study_program_id: number;
    study_program: string | null;
    is_active: boolean;
    teaching_assignments_count: number;
    has_account: boolean;
    last_login_at: string | null;
}

interface Props {
    lecturers: Paginated<Lecturer>;
    filters: Record<string, string | undefined>;
    studyPrograms: Option[];
    ranks: Option[];
    canManage: boolean;
}

const employmentOptions = [
    { value: 'tetap', label: 'Dosen tetap' },
    { value: 'tidak_tetap', label: 'Dosen tidak tetap' },
];

export const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

export default function Lecturers({ lecturers, filters, studyPrograms, ranks, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        study_program_id: filters.study_program_id ?? 'all',
        academic_rank: filters.academic_rank ?? 'all',
        employment_status: filters.employment_status ?? 'all',
    });
    const [editing, setEditing] = useState<Lecturer | 'new' | null>(null);

    const columns: Column<Lecturer>[] = [
        {
            key: 'name',
            header: 'Dosen',
            sortable: true,
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <Avatar className="size-9">
                        <AvatarFallback className="bg-secondary text-xs font-bold text-secondary-foreground">{initials(row.name)}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <div className="truncate font-semibold">{row.full_name}</div>
                        <div className="text-xs text-muted-foreground">NIDN {row.nidn ?? '—'}</div>
                    </div>
                </div>
            ),
        },
        { key: 'program', header: 'Homebase', cell: (row) => <span className="text-muted-foreground">{row.study_program}</span> },
        {
            key: 'academic_rank',
            header: 'Jabatan',
            sortable: true,
            cell: (row) => (
                <div>
                    <div>{row.academic_rank ?? '—'}</div>
                    <div className="text-xs text-muted-foreground">{row.employment_status === 'tetap' ? 'Tetap' : 'Tidak tetap'}</div>
                </div>
            ),
        },
        { key: 'teaching', header: 'Kelas diampu', cell: (row) => <span className="tabular">{row.teaching_assignments_count}</span> },
        {
            key: 'account',
            header: 'Akun',
            cell: (row) =>
                row.has_account ? (
                    <div>
                        <StatusBadge tone="success">Terhubung</StatusBadge>
                        <div className="mt-1 text-[11px] text-muted-foreground">{row.last_login_at ? `Login ${fromNow(row.last_login_at)}` : 'Belum pernah login'}</div>
                    </div>
                ) : (
                    <StatusBadge tone="neutral">Belum ada</StatusBadge>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) =>
                canManage && (
                    <RowActions>
                        <DropdownMenuItem onSelect={() => setEditing(row)}>
                            <Pencil /> Ubah
                        </DropdownMenuItem>
                        {row.has_account && (
                            <ConfirmDialog
                                trigger={
                                    <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
                                        <KeyRound /> Reset kata sandi
                                    </DropdownMenuItem>
                                }
                                title="Reset kata sandi dosen?"
                                description={`Kata sandi ${row.full_name} akan direset menjadi NIDN dan wajib diganti saat login berikutnya.`}
                                href={route('lecturers.reset-password', row.id)}
                                method="post"
                                destructive={false}
                                confirmLabel="Reset"
                            />
                        )}
                        <DropdownMenuSeparator />
                        <ConfirmDialog
                            trigger={
                                <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                    <Trash2 /> Hapus
                                </DropdownMenuItem>
                            }
                            title={`Hapus ${row.full_name}?`}
                            description="Dosen yang sudah memiliki riwayat mengajar tidak dapat dihapus. Nonaktifkan saja."
                            href={route('lecturers.destroy', row.id)}
                            confirmLabel="Hapus"
                        />
                    </RowActions>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Dosen"
                description="Data dosen per homebase prodi. Akun login otomatis menggunakan NIDN."
                breadcrumbs={[{ label: 'Master Akademik' }, { label: 'Dosen' }]}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <a href={route('master.export', { type: 'dosen', ...cleanQuery(query) })}>
                                <Download /> Ekspor
                            </a>
                        </Button>
                        {canManage && (
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Tambah dosen
                            </Button>
                        )}
                    </>
                }
            />
            <DataTable
                columns={columns}
                data={lecturers.data}
                pagination={lecturers}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari nama, NIDN, email…">
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                        <SelectField value={query.academic_rank} onChange={(v) => setFilter('academic_rank', v)} options={ranks} allLabel="Semua jabatan" />
                        <SelectField value={query.employment_status} onChange={(v) => setFilter('employment_status', v)} options={employmentOptions} allLabel="Semua status" />
                    </TableToolbar>
                }
                emptyTitle="Dosen tidak ditemukan"
            />
            {editing !== null && <LecturerForm lecturer={editing === 'new' ? null : editing} studyPrograms={studyPrograms} ranks={ranks} onClose={() => setEditing(null)} />}
        </>
    );
}

function LecturerForm({ lecturer, studyPrograms, ranks, onClose }: { lecturer: Lecturer | null; studyPrograms: Option[]; ranks: Option[]; onClose: () => void }) {
    const form = useForm({
        study_program_id: lecturer ? String(lecturer.study_program_id) : studyPrograms.length === 1 ? String(studyPrograms[0].value) : '',
        nidn: lecturer?.nidn ?? '',
        nip: lecturer?.nip ?? '',
        name: lecturer?.name ?? '',
        front_title: lecturer?.front_title ?? '',
        back_title: lecturer?.back_title ?? '',
        email: lecturer?.email ?? '',
        phone: lecturer?.phone ?? '',
        gender: lecturer?.gender ?? '',
        academic_rank: lecturer?.academic_rank ?? '',
        employment_status: lecturer?.employment_status ?? 'tetap',
        is_active: lecturer?.is_active ?? true,
        create_account: !lecturer,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (lecturer) form.put(route('lecturers.update', lecturer.id), options);
        else form.post(route('lecturers.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={lecturer ? 'Ubah data dosen' : 'Tambah dosen'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6">
                <FormField label="Gelar depan" error={form.errors.front_title} className="sm:col-span-1">
                    <Input value={form.data.front_title} onChange={(e) => form.setData('front_title', e.target.value)} placeholder="Dr." />
                </FormField>
                <FormField label="Nama lengkap (tanpa gelar)" error={form.errors.name} required className="sm:col-span-3">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Gelar belakang" error={form.errors.back_title} className="sm:col-span-2">
                    <Input value={form.data.back_title} onChange={(e) => form.setData('back_title', e.target.value)} placeholder="M.Pd." />
                </FormField>
                <FormField label="NIDN" error={form.errors.nidn} className="sm:col-span-2" hint="Dipakai sebagai username.">
                    <Input value={form.data.nidn} onChange={(e) => form.setData('nidn', e.target.value)} inputMode="numeric" />
                </FormField>
                <FormField label="NIP / NIY" error={form.errors.nip} className="sm:col-span-2">
                    <Input value={form.data.nip} onChange={(e) => form.setData('nip', e.target.value)} />
                </FormField>
                <FormField label="Jenis kelamin" error={form.errors.gender} className="sm:col-span-2">
                    <SelectField value={form.data.gender} onChange={(v) => form.setData('gender', v)} options={[{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }]} />
                </FormField>
                <FormField label="Homebase prodi" error={form.errors.study_program_id} required className="sm:col-span-3">
                    <SelectField value={form.data.study_program_id} onChange={(v) => form.setData('study_program_id', v)} options={studyPrograms} placeholder="Pilih prodi" />
                </FormField>
                <FormField label="Jabatan fungsional" error={form.errors.academic_rank} className="sm:col-span-3">
                    <SelectField value={form.data.academic_rank} onChange={(v) => form.setData('academic_rank', v)} options={ranks} placeholder="Pilih jabatan" />
                </FormField>
                <FormField label="Email" error={form.errors.email} className="sm:col-span-3">
                    <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </FormField>
                <FormField label="No. HP" error={form.errors.phone} className="sm:col-span-3">
                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </FormField>
                <FormField label="Status kepegawaian" error={form.errors.employment_status} required className="sm:col-span-3">
                    <SelectField value={form.data.employment_status} onChange={(v) => form.setData('employment_status', v)} options={employmentOptions} />
                </FormField>
                <div className="flex flex-col gap-3 sm:col-span-6">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Dosen aktif" />
                    {!lecturer?.has_account && (
                        <SwitchField
                            checked={form.data.create_account}
                            onChange={(v) => form.setData('create_account', v)}
                            label={
                                <span className="inline-flex items-center gap-1.5">
                                    <UserRoundCheck className="size-4 text-primary" /> Buatkan akun login
                                </span>
                            }
                            description="Username & kata sandi awal = NIDN. Dosen wajib mengganti kata sandi saat login pertama."
                        />
                    )}
                </div>
            </div>
        </FormDialog>
    );
}
