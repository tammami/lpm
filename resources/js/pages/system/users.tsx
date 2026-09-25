import { useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2, UserX } from 'lucide-react';
import { useState } from 'react';
import { Combobox, MultiCombobox } from '@/components/combobox';
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
import { cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';
import { initials } from '../master/lecturers';

interface UserRow {
    id: number;
    name: string;
    username: string | null;
    email: string;
    phone: string | null;
    is_active: boolean;
    faculty_id: number | null;
    study_program_id: number | null;
    unit_id: number | null;
    must_change_password: boolean;
    roles: string[];
    role_labels: string[];
    scope: string | null;
    last_login_at: string | null;
}

interface Props {
    users: Paginated<UserRow>;
    filters: Record<string, string | undefined>;
    roles: Option[];
    faculties: Option[];
    studyPrograms: Option[];
    units: Option[];
    counts: Record<string, number>;
}

export default function Users({ users, filters, roles, faculties, studyPrograms, units, counts }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        role: filters.role ?? 'all',
        status: filters.status ?? 'all',
    });
    const [editing, setEditing] = useState<UserRow | 'new' | null>(null);

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: 'Pengguna',
            sortable: true,
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <Avatar className="size-9">
                        <AvatarFallback className={cn('text-xs font-bold', row.is_active ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground')}>{initials(row.name)}</AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <div className="truncate font-semibold">{row.name}</div>
                        <div className="truncate text-xs text-muted-foreground">
                            {row.email}
                            {row.username && ` · ${row.username}`}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'roles',
            header: 'Peran',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    {row.role_labels.map((label) => (
                        <StatusBadge key={label} tone={label === 'Superadmin' ? 'gold' : 'primary'} dot={false}>
                            {label}
                        </StatusBadge>
                    ))}
                </div>
            ),
        },
        { key: 'scope', header: 'Cakupan', cell: (row) => <span className="text-xs text-muted-foreground">{row.scope ?? '—'}</span> },
        {
            key: 'last_login_at',
            header: 'Login terakhir',
            sortable: true,
            cell: (row) => (
                <div className="text-xs">
                    <div className="text-muted-foreground">{row.last_login_at ? fromNow(row.last_login_at) : 'Belum pernah'}</div>
                    {row.must_change_password && <div className="text-gold-foreground">Wajib ganti sandi</div>}
                </div>
            ),
        },
        { key: 'status', header: 'Status', cell: (row) => <StatusBadge tone={row.is_active ? 'success' : 'neutral'}>{row.is_active ? 'Aktif' : 'Nonaktif'}</StatusBadge> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions>
                    <DropdownMenuItem onSelect={() => setEditing(row)}>
                        <Pencil /> Ubah
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <ConfirmDialog
                        trigger={
                            <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                {row.roles.includes('dosen') || row.roles.includes('mahasiswa') ? <UserX /> : <Trash2 />} Hapus / nonaktifkan
                            </DropdownMenuItem>
                        }
                        title={`Hapus ${row.name}?`}
                        description="Akun yang terhubung dengan data dosen/mahasiswa akan dinonaktifkan, bukan dihapus."
                        href={route('users.destroy', row.id)}
                        confirmLabel="Lanjutkan"
                    />
                </RowActions>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Pengguna"
                description="Kelola akun, peran (RBAC), dan cakupan data. Akun dosen & mahasiswa dibuat otomatis dari master data atau impor."
                breadcrumbs={[{ label: 'Sistem' }, { label: 'Pengguna' }]}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Tambah pengguna
                    </Button>
                }
            />
            <div className="mb-5 flex gap-2 overflow-x-auto pb-1">
                {roles.map((role) => (
                    <button
                        key={role.value}
                        type="button"
                        onClick={() => setFilter('role', query.role === role.value ? 'all' : String(role.value))}
                        className={cn('flex shrink-0 items-center gap-2 rounded-xl border bg-card px-3 py-2 text-left transition', query.role === role.value && 'border-primary ring-2 ring-primary/15')}
                    >
                        <span className="text-lg font-extrabold tabular">{counts[String(role.value)] ?? 0}</span>
                        <span className="text-xs font-medium text-muted-foreground">{role.label}</span>
                    </button>
                ))}
            </div>
            <DataTable
                columns={columns}
                data={users.data}
                pagination={users}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari nama, email, username…">
                        <SelectField value={query.role} onChange={(v) => setFilter('role', v)} options={roles} allLabel="Semua peran" />
                        <SelectField
                            value={query.status}
                            onChange={(v) => setFilter('status', v)}
                            options={[
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ]}
                            allLabel="Semua status"
                        />
                    </TableToolbar>
                }
            />
            {editing !== null && (
                <UserForm user={editing === 'new' ? null : editing} roles={roles} faculties={faculties} studyPrograms={studyPrograms} units={units} onClose={() => setEditing(null)} />
            )}
        </>
    );
}

function UserForm({ user, roles, faculties, studyPrograms, units, onClose }: { user: UserRow | null; roles: Option[]; faculties: Option[]; studyPrograms: Option[]; units: Option[]; onClose: () => void }) {
    const form = useForm({
        name: user?.name ?? '',
        username: user?.username ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        roles: user?.roles ?? ([] as string[]),
        faculty_id: user?.faculty_id ? String(user.faculty_id) : '',
        study_program_id: user?.study_program_id ? String(user.study_program_id) : '',
        unit_id: user?.unit_id ? String(user.unit_id) : '',
        is_active: user?.is_active ?? true,
        password: '',
    });

    const submit = () => {
        form.transform((data) => ({ ...data, password: data.password || undefined }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (user) form.put(route('users.update', user.id), options);
        else form.post(route('users.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={user ? 'Ubah pengguna' : 'Tambah pengguna'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Nama lengkap" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Email" error={form.errors.email} required>
                    <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </FormField>
                <FormField label="Username" error={form.errors.username} hint="Opsional. Untuk login selain email.">
                    <Input value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                </FormField>
                <FormField label="Peran" error={form.errors.roles} required className="sm:col-span-2" hint="Satu akun dapat memiliki lebih dari satu peran (mis. Dosen + Auditor).">
                    <MultiCombobox values={form.data.roles} onChange={(v) => form.setData('roles', v)} options={roles} placeholder="Pilih peran…" />
                </FormField>
                {form.data.roles.includes('admin_fakultas') && (
                    <FormField label="Fakultas" error={form.errors.faculty_id} required>
                        <SelectField value={form.data.faculty_id} onChange={(v) => form.setData('faculty_id', v)} options={faculties} />
                    </FormField>
                )}
                {form.data.roles.includes('admin_prodi') && (
                    <FormField label="Program studi" error={form.errors.study_program_id} required>
                        <Combobox value={form.data.study_program_id} onChange={(v) => form.setData('study_program_id', v)} options={studyPrograms} />
                    </FormField>
                )}
                <FormField label="Unit kerja" error={form.errors.unit_id} hint="Opsional, untuk auditee/PIC unit.">
                    <SelectField value={form.data.unit_id || 'none'} onChange={(v) => form.setData('unit_id', v === 'none' ? '' : v)} options={[{ value: 'none', label: 'Tidak ada' }, ...units]} />
                </FormField>
                <FormField label="No. HP" error={form.errors.phone}>
                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </FormField>
                <FormField label={user ? 'Kata sandi baru (opsional)' : 'Kata sandi awal (opsional)'} error={form.errors.password} hint={user ? 'Kosongkan bila tidak diubah.' : 'Kosongkan untuk dibuatkan otomatis.'} className="sm:col-span-2">
                    <Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="new-password" />
                </FormField>
                <div className="sm:col-span-2">
                    <SwitchField
                        checked={form.data.is_active}
                        onChange={(v) => form.setData('is_active', v)}
                        label={
                            <span className="inline-flex items-center gap-1.5">
                                <ShieldCheck className="size-4 text-primary" /> Akun aktif
                            </span>
                        }
                        description="Akun nonaktif langsung dikeluarkan dari sesi dan tidak dapat masuk."
                    />
                </div>
            </div>
        </FormDialog>
    );
}
