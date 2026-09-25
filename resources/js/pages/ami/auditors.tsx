import { useForm } from '@inertiajs/react';
import { BadgeCheck, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatDate } from '@/lib/format';
import type { Option } from '@/types';
import { initials } from '../master/lecturers';

interface AuditorRow {
    id: number;
    user_id: number;
    name: string;
    email: string;
    certificate_number: string | null;
    certification: string | null;
    certified_on: string | null;
    competencies: string | null;
    is_active: boolean;
    audits_count: number;
    active_audits_count: number;
}

export default function Auditors({ auditors, users }: { auditors: AuditorRow[]; users: Option[] }) {
    const [editing, setEditing] = useState<AuditorRow | 'new' | null>(null);
    const columns: Column<AuditorRow>[] = [
        {
            key: 'name',
            header: 'Auditor',
            cell: (row) => (
                <div className="flex items-center gap-3">
                    <Avatar className="size-9">
                        <AvatarFallback className="bg-gold-soft text-xs font-bold text-gold-foreground">{initials(row.name)}</AvatarFallback>
                    </Avatar>
                    <div>
                        <div className="font-semibold">{row.name}</div>
                        <div className="text-xs text-muted-foreground">{row.email}</div>
                    </div>
                </div>
            ),
        },
        {
            key: 'cert',
            header: 'Sertifikasi',
            cell: (row) =>
                row.certification ? (
                    <div className="text-xs">
                        <div className="flex items-center gap-1 font-semibold">
                            <BadgeCheck className="size-3.5 text-primary" /> {row.certification}
                        </div>
                        <div className="text-muted-foreground">
                            {row.certificate_number ?? '—'} · {formatDate(row.certified_on)}
                        </div>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">Belum ada</span>
                ),
        },
        { key: 'competencies', header: 'Kompetensi', cell: (row) => <span className="line-clamp-2 max-w-xs text-xs text-muted-foreground">{row.competencies ?? '—'}</span> },
        {
            key: 'audits',
            header: 'Penugasan',
            cell: (row) => (
                <span className="text-xs tabular">
                    <b>{row.active_audits_count}</b> aktif · {row.audits_count} total
                </span>
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
                    <ConfirmDialog
                        trigger={
                            <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                <Trash2 /> Hapus
                            </DropdownMenuItem>
                        }
                        title={`Hapus auditor ${row.name}?`}
                        description="Auditor yang memiliki riwayat audit akan dinonaktifkan."
                        href={route('ami.auditors.destroy', row.id)}
                        confirmLabel="Lanjutkan"
                    />
                </RowActions>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Auditor Internal"
                description="Pengguna yang ditetapkan sebagai auditor AMI beserta sertifikasi dan kompetensinya."
                breadcrumbs={[{ label: 'Audit Mutu Internal' }, { label: 'Auditor' }]}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Tetapkan auditor
                    </Button>
                }
            />
            <DataTable columns={columns} data={auditors} rowKey={(row) => row.id} emptyTitle="Belum ada auditor" />
            {editing && <AuditorForm auditor={editing === 'new' ? null : editing} users={users} onClose={() => setEditing(null)} />}
        </>
    );
}

function AuditorForm({ auditor, users, onClose }: { auditor: AuditorRow | null; users: Option[]; onClose: () => void }) {
    const form = useForm({
        user_id: auditor ? String(auditor.user_id) : '',
        certification: auditor?.certification ?? '',
        certificate_number: auditor?.certificate_number ?? '',
        certified_on: auditor?.certified_on ?? '',
        competencies: auditor?.competencies ?? '',
        is_active: auditor?.is_active ?? true,
    });
    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (auditor) form.put(route('ami.auditors.update', auditor.id), options);
        else form.post(route('ami.auditors.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={auditor ? `Ubah auditor ${auditor.name}` : 'Tetapkan auditor'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {!auditor && (
                    <FormField label="Pengguna" error={form.errors.user_id} required hint="Peran Auditor ditambahkan otomatis." className="sm:col-span-2">
                        <Combobox value={form.data.user_id} onChange={(v) => form.setData('user_id', v)} options={users} placeholder="Cari dosen/tendik…" />
                    </FormField>
                )}
                <FormField label="Pelatihan / sertifikasi" error={form.errors.certification} className="sm:col-span-2">
                    <Input value={form.data.certification} onChange={(e) => form.setData('certification', e.target.value)} placeholder="Pelatihan Auditor Mutu Internal SPMI" />
                </FormField>
                <FormField label="Nomor sertifikat" error={form.errors.certificate_number}>
                    <Input value={form.data.certificate_number} onChange={(e) => form.setData('certificate_number', e.target.value)} />
                </FormField>
                <FormField label="Tanggal sertifikasi" error={form.errors.certified_on}>
                    <Input type="date" value={form.data.certified_on} onChange={(e) => form.setData('certified_on', e.target.value)} />
                </FormField>
                <FormField label="Kompetensi" error={form.errors.competencies} className="sm:col-span-2">
                    <Textarea rows={3} value={form.data.competencies} onChange={(e) => form.setData('competencies', e.target.value)} placeholder="Standar pendidikan, pengelolaan keuangan, …" />
                </FormField>
                <div className="sm:col-span-2">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Aktif" />
                </div>
            </div>
        </FormDialog>
    );
}
