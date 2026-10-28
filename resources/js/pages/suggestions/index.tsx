import { useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, MessageSquareReply, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDateTime, fromNow } from '@/lib/format';
import { type SuggestionStatus, suggestionStatusTone } from '@/lib/suggestions';
import { cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

interface SuggestionRow {
    id: number;
    category: string;
    category_label: string;
    subject: string;
    message: string;
    is_anonymous: boolean;
    sender: string | null;
    sender_username: string | null;
    sender_role: string;
    sender_role_label: string;
    study_program: string | null;
    status: SuggestionStatus;
    status_label: string;
    response: string | null;
    responder: string | null;
    responded_at: string | null;
    created_at: string | null;
}

interface Props {
    suggestions: Paginated<SuggestionRow>;
    filters: Record<string, string | undefined>;
    statusCounts: Record<SuggestionStatus, number>;
    categories: Option[];
    statuses: Option[];
    senderRoles: Option[];
}

export default function SuggestionsIndex({ suggestions, filters, statusCounts, categories, statuses, senderRoles }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        status: filters.status ?? 'all',
        category: filters.category ?? 'all',
        sender_role: filters.sender_role ?? 'all',
    });
    const [viewing, setViewing] = useState<SuggestionRow | null>(null);

    const columns: Column<SuggestionRow>[] = [
        {
            key: 'subject',
            header: 'Saran',
            className: 'min-w-72 max-w-xl whitespace-normal',
            cell: (row) => (
                <div>
                    <div className={cn('line-clamp-1', row.status === 'new' ? 'font-bold' : 'font-semibold')}>{row.subject}</div>
                    <div className="line-clamp-1 text-xs text-muted-foreground">{row.message}</div>
                </div>
            ),
        },
        { key: 'category', header: 'Topik', cell: (row) => <span className="text-sm">{row.category_label}</span> },
        {
            key: 'sender',
            header: 'Pengirim',
            cell: (row) =>
                row.is_anonymous ? (
                    <span className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                        <EyeOff className="size-3.5" /> Tanpa nama · {row.sender_role_label}
                    </span>
                ) : (
                    <div className="min-w-0">
                        <div className="truncate font-medium">{row.sender ?? '—'}</div>
                        <div className="truncate text-xs text-muted-foreground">
                            {row.sender_role_label}
                            {row.study_program ? ` · ${row.study_program}` : ''}
                        </div>
                    </div>
                ),
        },
        { key: 'created_at', header: 'Masuk', sortable: true, cell: (row) => <span className="text-sm whitespace-nowrap" title={formatDateTime(row.created_at)}>{fromNow(row.created_at)}</span> },
        { key: 'status', header: 'Status', cell: (row) => <StatusBadge tone={suggestionStatusTone[row.status]}>{row.status_label}</StatusBadge> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions>
                    <DropdownMenuItem onSelect={() => setViewing(row)}>
                        <Eye /> Baca & tanggapi
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <ConfirmDialog
                        trigger={
                            <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                <Trash2 /> Hapus
                            </DropdownMenuItem>
                        }
                        title="Hapus saran ini?"
                        description="Gunakan hanya untuk saran ganda atau tidak pantas. Saran yang sudah ditanggapi sebaiknya ditutup, bukan dihapus."
                        href={route('suggestions.destroy', row.id)}
                        confirmLabel="Hapus"
                    />
                </RowActions>
            ),
        },
    ];

    const statusChips = [
        { value: 'all', label: 'Semua', count: Object.values(statusCounts).reduce((sum, count) => sum + count, 0) },
        ...statuses.map((status) => ({ value: String(status.value), label: status.label, count: statusCounts[status.value as SuggestionStatus] ?? 0 })),
    ];

    return (
        <>
            <PageHeader
                reset={['suggestions']}
                title="Kotak Saran"
                description="Saran mahasiswa dan dosen yang masuk kapan saja. Baca, tandai sedang ditinjau, lalu tulis tanggapan — pengirim mendapat notifikasi."
                breadcrumbs={[{ label: 'Layanan' }, { label: 'Kotak Saran' }]}
            />

            <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label="Status saran">
                {statusChips.map((chip) => (
                    <button
                        key={chip.value}
                        type="button"
                        role="tab"
                        aria-selected={query.status === chip.value}
                        onClick={() => setFilter('status', chip.value)}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition',
                            query.status === chip.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:border-primary/40',
                        )}
                    >
                        {chip.label}
                        <span className={cn('rounded-full px-1.5 text-xs tabular', query.status === chip.value ? 'bg-white/20' : 'bg-muted text-muted-foreground')}>{chip.count}</span>
                    </button>
                ))}
            </div>

            <DataTable
                columns={columns}
                data={suggestions.data}
                pagination={suggestions}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                onRowClick={setViewing}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari pokok atau isi saran…">
                        <SelectField value={query.category} onChange={(v) => setFilter('category', v)} options={categories} allLabel="Semua topik" />
                        <SelectField value={query.sender_role} onChange={(v) => setFilter('sender_role', v)} options={senderRoles} allLabel="Semua pengirim" />
                    </TableToolbar>
                }
                emptyTitle="Belum ada saran"
                emptyDescription="Mahasiswa dan dosen mengirim saran lewat menu Kotak Saran."
            />

            {viewing && <SuggestionSheet key={viewing.id} suggestion={viewing} statuses={statuses} onClose={() => setViewing(null)} />}
        </>
    );
}

function SuggestionSheet({ suggestion, statuses, onClose }: { suggestion: SuggestionRow; statuses: Option[]; onClose: () => void }) {
    const form = useForm({
        status: (suggestion.status === 'new' ? 'in_review' : suggestion.status) as SuggestionStatus,
        response: suggestion.response ?? '',
    });

    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent className="w-full gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b px-6 py-5">
                    <div className="mb-1">
                        <StatusBadge tone={suggestionStatusTone[suggestion.status]}>{suggestion.status_label}</StatusBadge>
                    </div>
                    <SheetTitle className="text-lg leading-snug">{suggestion.subject}</SheetTitle>
                    <SheetDescription>
                        {suggestion.category_label} · {formatDateTime(suggestion.created_at)}
                    </SheetDescription>
                </SheetHeader>
                <form
                    className="flex min-h-0 flex-1 flex-col"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('suggestions.update', suggestion.id), { preserveScroll: true, onSuccess: onClose });
                    }}
                >
                    <div className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        <div className="mb-4 rounded-xl border bg-muted/20 p-3 text-sm">
                            {suggestion.is_anonymous ? (
                                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <EyeOff className="size-4" /> Dikirim tanpa nama oleh {suggestion.sender_role_label.toLowerCase()}
                                </span>
                            ) : (
                                <>
                                    <div className="font-semibold">{suggestion.sender ?? '—'}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {suggestion.sender_role_label}
                                        {suggestion.sender_username ? ` · ${suggestion.sender_username}` : ''}
                                        {suggestion.study_program ? ` · ${suggestion.study_program}` : ''}
                                    </div>
                                </>
                            )}
                        </div>
                        <p className="text-sm whitespace-pre-line">{suggestion.message}</p>

                        {suggestion.responded_at && (
                            <p className="mt-4 text-xs text-muted-foreground">
                                Terakhir ditanggapi {suggestion.responder ? `oleh ${suggestion.responder} ` : ''}· {formatDateTime(suggestion.responded_at)}
                            </p>
                        )}

                        <div className="mt-5 flex flex-col gap-4 border-t pt-5">
                            <FormField label="Status" error={form.errors.status} required>
                                <SelectField value={form.data.status} onChange={(v) => form.setData('status', v as SuggestionStatus)} options={statuses} />
                            </FormField>
                            <FormField label="Tanggapan untuk pengirim" error={form.errors.response} hint="Wajib untuk status Ditindaklanjuti atau Ditutup. Pengirim menerima notifikasi saat tanggapan disimpan.">
                                <Textarea rows={5} value={form.data.response} onChange={(e) => form.setData('response', e.target.value)} placeholder="Mis. Terima kasih, saran sudah diteruskan ke unit terkait dan dijadwalkan…" />
                            </FormField>
                        </div>
                    </div>
                    <SheetFooter className="flex-row justify-end gap-2 border-t bg-muted/40 px-6 py-4">
                        <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <LoaderCircle className="animate-spin" /> : <MessageSquareReply />} Simpan
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
