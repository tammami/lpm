import { router, usePage } from '@inertiajs/react';
import { ChevronDown, Eraser, FileX2, LoaderCircle, ShieldAlert, Unlink } from 'lucide-react';
import { useEffect, useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { formatNumber } from '@/lib/format';
import type { SharedProps } from '@/types';

interface Preview {
    key: string;
    label: string;
    description: string;
    phrase: string;
    notes: string[];
    deleted: { table: string; label: string; count: number; primary: boolean }[];
    detached: { label: string; parent: string; count: number }[];
    files: number;
    total: number;
}

/**
 * Tombol "Kosongkan data" khusus Superadmin. Tampil kosong untuk peran lain.
 * `datasets` = kunci data yang dapat dikosongkan dari halaman ini (lihat DataResetService).
 */
export function DataResetButton({ datasets }: { datasets: string[] }) {
    const { dataResets } = usePage<SharedProps>().props;
    const [active, setActive] = useState<string | null>(null);

    const available = datasets.filter((key) => dataResets?.[key]);
    if (!dataResets || available.length === 0) return null;

    const className = 'text-danger-foreground hover:bg-danger-soft hover:text-danger-foreground';

    return (
        <>
            {available.length === 1 ? (
                <Button variant="outline" className={className} onClick={() => setActive(available[0])}>
                    <Eraser /> Kosongkan data
                </Button>
            ) : (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className={className}>
                            <Eraser /> Kosongkan data <ChevronDown />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel className="text-xs text-muted-foreground">Khusus Superadmin</DropdownMenuLabel>
                        {available.map((key) => (
                            <DropdownMenuItem key={key} onSelect={() => setActive(key)}>
                                {dataResets[key]}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
            {active && <DataResetDialog dataset={active} onClose={() => setActive(null)} />}
        </>
    );
}

function DataResetDialog({ dataset, onClose }: { dataset: string; onClose: () => void }) {
    const [preview, setPreview] = useState<Preview | null>(null);
    const [failed, setFailed] = useState(false);
    const [confirmation, setConfirmation] = useState('');
    const [error, setError] = useState<string | undefined>();
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        fetch(route('data-reset.show', dataset), { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((response) => {
                if (!response.ok) throw new Error(String(response.status));
                return response.json();
            })
            .then((data: Preview) => setPreview(data))
            .catch(() => !controller.signal.aborted && setFailed(true));
        return () => controller.abort();
    }, [dataset]);

    const confirmed = preview !== null && confirmation.trim().toUpperCase() === preview.phrase;

    const submit = () => {
        if (!confirmed) return;
        router.delete(route('data-reset.destroy', dataset), {
            data: { confirmation },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: onClose,
            onError: (errors) => setError(errors.confirmation),
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && !processing && onClose()}>
            <DialogContent className="max-h-[92svh] gap-0 overflow-hidden p-0 sm:max-w-xl">
                <form
                    className="flex max-h-[92svh] flex-col"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <DialogHeader className="border-b pt-6 pr-14 pb-4 pl-6">
                        <DialogTitle className="text-lg font-bold">Kosongkan data {preview?.label ?? ''}</DialogTitle>
                        <DialogDescription>{preview?.description ?? 'Menghitung data yang terdampak…'}</DialogDescription>
                    </DialogHeader>

                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        {failed ? (
                            <p className="text-sm text-danger-foreground">Akibat pengosongan gagal dihitung. Muat ulang halaman lalu coba lagi.</p>
                        ) : !preview ? (
                            <div className="flex flex-col gap-2" aria-busy="true">
                                <Skeleton className="h-16 w-full" />
                                <Skeleton className="h-6 w-3/4" />
                                <Skeleton className="h-6 w-2/3" />
                            </div>
                        ) : preview.total === 0 ? (
                            <p className="text-sm text-muted-foreground">Data {preview.label} sudah kosong. Tidak ada yang perlu dihapus.</p>
                        ) : (
                            <div className="flex flex-col gap-5">
                                <div className="flex gap-3 rounded-xl bg-danger-soft p-3.5 text-sm leading-relaxed text-danger-foreground">
                                    <ShieldAlert className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                                    <p>
                                        <b>{formatNumber(preview.total)} baris dihapus permanen.</b> Database dicadangkan otomatis lebih dulu, sehingga data dapat
                                        dikembalikan lewat <b>Sistem › Cadangan Database</b>.
                                    </p>
                                </div>

                                <section>
                                    <h3 className="mb-2 text-sm font-bold">Yang akan dihapus</h3>
                                    <ul className="divide-y rounded-xl border text-sm">
                                        {preview.deleted.map((row) => (
                                            <li key={row.table} className="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                                <span className="min-w-0">
                                                    <span className={row.primary ? 'font-semibold' : undefined}>{row.label}</span>
                                                    {!row.primary && <span className="block text-xs text-muted-foreground">ikut terhapus karena terkait</span>}
                                                </span>
                                                <span className="shrink-0 font-semibold tabular">{formatNumber(row.count)}</span>
                                            </li>
                                        ))}
                                    </ul>
                                </section>

                                {preview.detached.length > 0 && (
                                    <section>
                                        <h3 className="mb-1 flex items-center gap-1.5 text-sm font-bold">
                                            <Unlink className="size-4" aria-hidden="true" /> Tetap ada, tetapi tautannya dilepas
                                        </h3>
                                        <p className="mb-2 text-xs text-muted-foreground">Datanya tidak dihapus, hanya tidak lagi terhubung ke data yang dikosongkan.</p>
                                        <ul className="divide-y rounded-xl border text-sm">
                                            {preview.detached.map((row) => (
                                                <li key={`${row.label}-${row.parent}`} className="flex items-center justify-between gap-3 px-3.5 py-2.5">
                                                    <span className="min-w-0">
                                                        {row.label}
                                                        <span className="block text-xs text-muted-foreground">tidak lagi terhubung ke {row.parent}</span>
                                                    </span>
                                                    <span className="shrink-0 tabular">{formatNumber(row.count)}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                )}

                                {(preview.files > 0 || preview.notes.length > 0) && (
                                    <ul className="flex list-disc flex-col gap-1.5 pl-5 text-sm leading-relaxed text-muted-foreground">
                                        {preview.files > 0 && (
                                            <li>
                                                <FileX2 className="mr-1 inline size-4 align-text-bottom" aria-hidden="true" />
                                                {formatNumber(preview.files)} berkas unggahan di penyimpanan ikut dihapus dan tidak termasuk dalam cadangan database.
                                            </li>
                                        )}
                                        {preview.notes.map((note) => (
                                            <li key={note}>{note}</li>
                                        ))}
                                    </ul>
                                )}

                                <FormField label={`Ketik “${preview.phrase}” untuk melanjutkan`} error={error} required>
                                    <Input
                                        value={confirmation}
                                        onChange={(event) => {
                                            setConfirmation(event.target.value);
                                            setError(undefined);
                                        }}
                                        autoComplete="off"
                                        spellCheck={false}
                                        className="font-mono"
                                    />
                                </FormField>
                            </div>
                        )}
                    </div>

                    <DialogFooter className="border-t bg-muted/40 px-6 py-4">
                        <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                            Batal
                        </Button>
                        {preview && preview.total > 0 && (
                            <Button type="submit" variant="destructive" disabled={!confirmed || processing}>
                                {processing ? <LoaderCircle className="animate-spin" /> : <Eraser />}
                                Ya, kosongkan
                            </Button>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
