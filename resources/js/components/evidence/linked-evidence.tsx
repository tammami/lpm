import { Link, router } from '@inertiajs/react';
import { Download, FolderSearch, Paperclip, Unlink, Upload } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { formatBytes, formatDate } from '@/lib/format';
import { EvidencePicker } from './evidence-picker';
import { type EvidenceFormOptions, EvidenceUploadDialog } from './evidence-upload-dialog';
import { FileIcon } from './file-icon';
import { evidenceTone, type LinkedEvidence } from './types';

interface Props {
    items: LinkedEvidence[];
    mapTo: { type: string; id: number; context?: number | null; label?: string };
    options?: EvidenceFormOptions;
    canAttach?: boolean;
    defaultUnit?: string | null;
    emptyText?: string;
    compact?: boolean;
}

/**
 * Daftar dokumen bukti yang tertaut ke sebuah objek + aksi unggah/tautkan.
 */
export function LinkedEvidenceList({ items, mapTo, options, canAttach = false, defaultUnit, emptyText = 'Belum ada dokumen bukti.', compact }: Props) {
    const [uploading, setUploading] = useState(false);
    const [picking, setPicking] = useState(false);

    return (
        <div className="flex flex-col gap-2">
            {items.length === 0 && <p className="rounded-xl border border-dashed px-4 py-5 text-center text-xs text-muted-foreground">{emptyText}</p>}
            {items.map((item) => (
                <div key={item.mapping_id} className="flex items-center gap-3 rounded-xl border bg-card p-2.5">
                    <FileIcon mime={item.mime_type} link={item.document_type === 'link'} className={compact ? 'size-8' : undefined} />
                    <div className="min-w-0 flex-1">
                        <Link href={item.show_url} className="block truncate text-[13px] font-semibold hover:text-primary">
                            {item.title}
                        </Link>
                        <div className="truncate text-[11px] text-muted-foreground">
                            {item.code} · v{item.version} {item.size ? `· ${formatBytes(item.size)}` : ''} · {formatDate(item.updated_at)}
                        </div>
                    </div>
                    <StatusBadge tone={evidenceTone[item.status] ?? 'neutral'} className="hidden sm:inline-flex">
                        {item.status_label}
                    </StatusBadge>
                    <Button variant="ghost" size="icon-sm" asChild aria-label="Unduh">
                        <a href={item.download_url} target="_blank" rel="noreferrer">
                            <Download />
                        </a>
                    </Button>
                    {item.can_unlink && (
                        <ConfirmDialog
                            trigger={
                                <Button variant="ghost" size="icon-sm" aria-label="Lepas tautan">
                                    <Unlink className="text-muted-foreground" />
                                </Button>
                            }
                            title="Lepas tautan dokumen?"
                            description="Dokumen tetap tersimpan di repositori."
                            onConfirm={() => router.delete(route('evidence.unmap', item.mapping_id), { preserveScroll: true })}
                            confirmLabel="Lepas"
                        />
                    )}
                </div>
            ))}
            {canAttach && (
                <div className="flex flex-wrap gap-2 pt-1">
                    {options && (
                        <Button variant="outline" size="sm" onClick={() => setUploading(true)}>
                            <Upload /> Unggah bukti baru
                        </Button>
                    )}
                    <Button variant="ghost" size="sm" onClick={() => setPicking(true)}>
                        <FolderSearch /> Pilih dari repositori
                    </Button>
                </div>
            )}
            {!canAttach && items.length === 0 && null}
            {uploading && options && <EvidenceUploadDialog {...options} mapTo={mapTo} defaultUnit={defaultUnit} onClose={() => setUploading(false)} />}
            {picking && <EvidencePicker mapTo={mapTo} excludeIds={items.map((i) => i.id)} onClose={() => setPicking(false)} />}
        </div>
    );
}

export function EvidenceCountBadge({ count }: { count: number }) {
    return (
        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <Paperclip className="size-3" /> {count}
        </span>
    );
}
