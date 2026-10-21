import { router } from '@inertiajs/react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

interface Props {
    survey: { id: number; title: string; status: string; ends_at: string };
    className?: string;
}

/**
 * Sakelar cepat buka/tutup pengisian Monev maupun survei (aktif ⇄ ditutup; draf → dibuka).
 * Kegiatan arsip tidak dapat diubah lagi sehingga sakelar tidak ditampilkan.
 */
export function SurveyOpenSwitch({ survey, className }: Props) {
    if (survey.status === 'archived') return null;

    const active = survey.status === 'active';
    const expired = new Date(survey.ends_at).getTime() < Date.now();
    // Membuka kembali kegiatan yang jadwalnya lewat akan ditolak server; arahkan ke form untuk memperpanjang.
    const needsSchedule = !active && expired;

    const description = active
        ? 'Responden tidak dapat mengisi lagi sampai pengisian dibuka kembali. Jawaban yang sudah masuk tetap tersimpan.'
        : needsSchedule
          ? 'Tanggal berakhir sudah lewat. Ubah tanggal berakhir kegiatan ini terlebih dahulu, lalu buka kembali.'
          : survey.status === 'draft'
            ? 'Responden yang memenuhi syarat akan melihat kegiatan ini di portal dan menerima notifikasi.'
            : 'Responden yang belum mengisi dapat kembali mengisi melalui portal.';

    return (
        <div className={cn('inline-flex items-center gap-2 text-xs font-semibold', className)}>
            <ConfirmDialog
                // Dibungkus agar atribut pemicu dialog (data-state) tidak menimpa tampilan sakelar.
                trigger={
                    <span className="inline-flex">
                        <Switch checked={active} aria-label={active ? `Tutup pengisian ${survey.title}` : `Buka pengisian ${survey.title}`} />
                    </span>
                }
                title={active ? 'Tutup pengisian?' : 'Buka pengisian?'}
                description={
                    <>
                        <b>{survey.title}</b>. {description}
                    </>
                }
                {...(needsSchedule
                    ? { onConfirm: () => router.visit(route('surveys.edit', survey.id)), confirmLabel: 'Ubah jadwal' }
                    : { href: route('surveys.transition', survey.id), method: 'post' as const, data: { status: active ? 'closed' : 'active' }, confirmLabel: active ? 'Tutup pengisian' : 'Buka pengisian' })}
                destructive={active}
            />
            <span className={active && !expired ? 'text-primary' : 'text-muted-foreground'}>
                {active ? (expired ? 'Jadwal berakhir' : 'Dibuka') : survey.status === 'draft' ? 'Belum dibuka' : 'Ditutup'}
            </span>
        </div>
    );
}
