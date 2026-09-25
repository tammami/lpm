import { router } from '@inertiajs/react';
import { Check, LoaderCircle, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { FileIcon } from './file-icon';
import { type EvidenceRow, evidenceTone } from './types';

/**
 * Pilih dokumen yang sudah ada di repositori untuk ditautkan (tanpa unggah ulang).
 */
export function EvidencePicker({ mapTo, onClose, excludeIds = [] }: { mapTo: { type: string; id: number; context?: number | null; label?: string }; onClose: () => void; excludeIds?: number[] }) {
    const [query, setQuery] = useState('');
    const [items, setItems] = useState<EvidenceRow[]>([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            fetch(route('evidence.search', { q: query }), { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => response.json())
                .then((data: EvidenceRow[]) => setItems(data))
                .catch(() => undefined)
                .finally(() => setLoading(false));
        }, 250);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query]);

    const submit = () => {
        if (!selected) return;
        router.post(
            route('evidence.map'),
            { evidence_id: selected, mappable_type: mapTo.type, mappable_id: mapTo.id, context_id: mapTo.context ?? null },
            { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false), onSuccess: onClose },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="gap-0 p-0 sm:max-w-2xl">
                <DialogHeader className="border-b px-6 pt-6 pb-4">
                    <DialogTitle>Tautkan dokumen dari repositori</DialogTitle>
                    <DialogDescription>{mapTo.label ? `Untuk: ${mapTo.label}` : 'Satu dokumen dapat dipakai sebagai bukti di banyak tempat.'}</DialogDescription>
                </DialogHeader>
                <div className="px-6 pt-4">
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Cari judul atau kode dokumen…" className="pl-9" autoFocus />
                    </div>
                </div>
                <div className="max-h-96 min-h-48 overflow-y-auto px-6 py-4">
                    {loading && items.length === 0 ? (
                        <div className="flex justify-center py-10 text-muted-foreground">
                            <LoaderCircle className="animate-spin" />
                        </div>
                    ) : items.length === 0 ? (
                        <p className="py-10 text-center text-sm text-muted-foreground">Dokumen tidak ditemukan.</p>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {items.map((item) => {
                                const already = excludeIds.includes(item.id);
                                return (
                                    <button
                                        key={item.id}
                                        type="button"
                                        disabled={already}
                                        onClick={() => setSelected(item.id)}
                                        className={cn(
                                            'flex items-center gap-3 rounded-xl border p-3 text-left transition',
                                            selected === item.id ? 'border-primary bg-secondary/60 ring-2 ring-primary/15' : 'hover:border-primary/30',
                                            already && 'cursor-not-allowed opacity-50',
                                        )}
                                    >
                                        <FileIcon mime={item.mime_type} link={item.document_type === 'link'} />
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-sm font-semibold">{item.title}</div>
                                            <div className="truncate text-xs text-muted-foreground">
                                                {item.code} · {item.unit_name ?? '—'} · v{item.version}
                                            </div>
                                        </div>
                                        {already ? <span className="text-xs text-muted-foreground">Sudah tertaut</span> : <StatusBadge tone={evidenceTone[item.status] ?? 'neutral'}>{item.status_label}</StatusBadge>}
                                        {selected === item.id && <Check className="size-4 text-primary" />}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
                <DialogFooter className="border-t bg-muted/40 px-6 py-4">
                    <Button variant="outline" onClick={onClose}>
                        Batal
                    </Button>
                    <Button onClick={submit} disabled={!selected || saving}>
                        {saving && <LoaderCircle className="animate-spin" />} Tautkan dokumen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
