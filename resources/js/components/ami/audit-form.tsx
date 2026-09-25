import { useForm } from '@inertiajs/react';
import { Combobox, MultiCombobox } from '@/components/combobox';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import type { AuditFormOptions, AuditSummary } from './types';

export function AuditForm({ programId, audit, options, onClose }: { programId: number; audit: AuditSummary | null; options: AuditFormOptions; onClose: () => void }) {
    const form = useForm({
        auditee: audit?.auditee_key ?? '',
        instrument_version_id: audit?.instrument_version_id ? String(audit.instrument_version_id) : options.instrumentOptions[0] ? String(options.instrumentOptions[0].value) : '',
        lead_auditor_id: audit?.lead_auditor_id ? String(audit.lead_auditor_id) : '',
        member_auditor_ids: audit?.member_auditor_ids ?? ([] as string[]),
        auditee_pic_user_id: audit?.auditee_pic_user_id ? String(audit.auditee_pic_user_id) : '',
        desk_review_due: audit?.desk_review_due ?? '',
        scheduled_on: audit?.scheduled_on ?? '',
        location: audit?.location ?? '',
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (audit) form.put(route('ami.audits.update', audit.id), opts);
        else form.post(route('ami.audits.store', programId), opts);
    };

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title={audit ? `Ubah jadwal ${audit.code}` : 'Jadwalkan audit'}
            description="Auditor dan PIC auditee akan menerima notifikasi jadwal."
            onSubmit={submit}
            processing={form.processing}
            submitLabel={audit ? 'Simpan' : 'Jadwalkan'}
            size="lg"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Auditee" error={form.errors.auditee} required className="sm:col-span-2">
                    <Combobox value={form.data.auditee} onChange={(v) => form.setData('auditee', v)} options={options.auditees} placeholder="Pilih prodi / fakultas / unit…" />
                </FormField>
                <FormField label="Instrumen AMI" error={form.errors.instrument_version_id} required className="sm:col-span-2" hint="Hanya instrumen AMI yang sudah terbit.">
                    <SelectField value={form.data.instrument_version_id} onChange={(v) => form.setData('instrument_version_id', v)} options={options.instrumentOptions} placeholder="Pilih instrumen" />
                </FormField>
                <FormField label="Ketua auditor" error={form.errors.lead_auditor_id} required>
                    <Combobox value={form.data.lead_auditor_id} onChange={(v) => form.setData('lead_auditor_id', v)} options={options.auditorOptions} placeholder="Pilih ketua" />
                </FormField>
                <FormField label="Anggota auditor" error={form.errors.member_auditor_ids}>
                    <MultiCombobox values={form.data.member_auditor_ids} onChange={(v) => form.setData('member_auditor_ids', v)} options={options.auditorOptions.filter((o) => String(o.value) !== form.data.lead_auditor_id)} placeholder="Pilih anggota" />
                </FormField>
                <FormField label="PIC auditee" error={form.errors.auditee_pic_user_id} hint="Penanggung jawab tindak lanjut temuan.">
                    <Combobox value={form.data.auditee_pic_user_id} onChange={(v) => form.setData('auditee_pic_user_id', v)} options={options.picOptions} placeholder="Pilih pengguna" />
                </FormField>
                <FormField label="Lokasi" error={form.errors.location}>
                    <Input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} placeholder="Ruang Prodi / daring" />
                </FormField>
                <FormField label="Batas unggah dokumen (desk evaluation)" error={form.errors.desk_review_due}>
                    <Input type="date" value={form.data.desk_review_due} onChange={(e) => form.setData('desk_review_due', e.target.value)} />
                </FormField>
                <FormField label="Tanggal audit lapangan" error={form.errors.scheduled_on} required>
                    <Input type="date" value={form.data.scheduled_on} onChange={(e) => form.setData('scheduled_on', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
