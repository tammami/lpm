import { useForm, usePage } from '@inertiajs/react';
import { EyeOff, LoaderCircle, MessageSquareReply, MessagesSquare, Send, Trash2 } from 'lucide-react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime } from '@/lib/format';
import { type SuggestionStatus, suggestionStatusTone } from '@/lib/suggestions';
import type { Option } from '@/types';

interface MySuggestion {
    id: number;
    category: string;
    category_label: string;
    subject: string;
    message: string;
    is_anonymous: boolean;
    status: SuggestionStatus;
    status_label: string;
    response: string | null;
    responded_at: string | null;
    created_at: string | null;
    can_delete: boolean;
}

interface Props {
    suggestions: MySuggestion[];
    categories: Option[];
}

export default function MySuggestions({ suggestions, categories }: Props) {
    const isStudent = usePage().props.auth.user?.role === 'mahasiswa';
    const form = useForm({ category: '', subject: '', message: '', is_anonymous: false });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(route('suggestions.mine.store'), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <>
            <PageHeader
                title="Kotak Saran"
                description="Sampaikan saran, keluhan, atau ide perbaikan kapan saja. LPM membaca setiap saran dan menanggapinya di halaman ini."
                breadcrumbs={isStudent ? undefined : [{ label: 'Kotak Saran' }]}
            />

            <Card className="mb-6 gap-0 p-5">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
                        <FormField label="Topik" error={form.errors.category} required>
                            <SelectField value={form.data.category} onChange={(v) => form.setData('category', v)} options={categories} placeholder="Pilih topik…" />
                        </FormField>
                        <FormField label="Pokok saran" error={form.errors.subject} required>
                            <Input value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} placeholder="Mis. Wi-Fi perpustakaan sering terputus" maxLength={150} />
                        </FormField>
                    </div>
                    <FormField label="Isi saran" error={form.errors.message} required hint="Jelaskan keadaannya dan usulan perbaikan Anda.">
                        <Textarea rows={4} value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} maxLength={5000} />
                    </FormField>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-2.5">
                            <Checkbox id="anonymous" checked={form.data.is_anonymous} onCheckedChange={(checked) => form.setData('is_anonymous', checked === true)} className="mt-0.5" />
                            <Label htmlFor="anonymous" className="flex flex-col items-start gap-0.5 font-normal">
                                <span className="font-semibold">Kirim tanpa nama</span>
                                <span className="text-xs text-muted-foreground">Nama dan prodi Anda tidak ditampilkan kepada LPM. Anda tetap dapat membaca tanggapannya di sini.</span>
                            </Label>
                        </div>
                        <Button type="submit" disabled={form.processing} className="shrink-0">
                            {form.processing ? <LoaderCircle className="animate-spin" /> : <Send />} Kirim saran
                        </Button>
                    </div>
                </form>
            </Card>

            <h2 className="mb-3 flex items-center gap-2 text-sm font-bold text-muted-foreground">
                <MessagesSquare className="size-4" /> Saran saya ({suggestions.length})
            </h2>

            {suggestions.length === 0 ? (
                <Card>
                    <EmptyState icon={MessagesSquare} title="Belum ada saran" description="Saran yang Anda kirim beserta tanggapan LPM akan muncul di sini." />
                </Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {suggestions.map((suggestion) => (
                        <Card key={suggestion.id} className="gap-0 p-4 sm:p-5">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="mb-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <span className="font-semibold">{suggestion.category_label}</span>
                                        <span>· {formatDateTime(suggestion.created_at)}</span>
                                        {suggestion.is_anonymous && (
                                            <span className="inline-flex items-center gap-1">
                                                <EyeOff className="size-3" /> Tanpa nama
                                            </span>
                                        )}
                                    </div>
                                    <h3 className="font-semibold leading-snug">{suggestion.subject}</h3>
                                </div>
                                <div className="flex shrink-0 items-center gap-1">
                                    <StatusBadge tone={suggestionStatusTone[suggestion.status]}>{suggestion.status_label}</StatusBadge>
                                    {suggestion.can_delete && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="icon-sm" aria-label="Tarik saran" className="text-muted-foreground">
                                                    <Trash2 />
                                                </Button>
                                            }
                                            title="Tarik kembali saran ini?"
                                            description="Saran dihapus sebelum dibaca LPM."
                                            href={route('suggestions.mine.destroy', suggestion.id)}
                                            confirmLabel="Tarik"
                                        />
                                    )}
                                </div>
                            </div>
                            <p className="mt-2 text-sm whitespace-pre-line text-foreground/85">{suggestion.message}</p>
                            {suggestion.response && (
                                <div className="mt-3 rounded-xl border border-primary/15 bg-secondary/50 p-3 text-sm">
                                    <div className="mb-1 flex items-center gap-1.5 text-xs font-bold text-primary">
                                        <MessageSquareReply className="size-3.5" /> Tanggapan LPM · {formatDateTime(suggestion.responded_at)}
                                    </div>
                                    <p className="whitespace-pre-line">{suggestion.response}</p>
                                </div>
                            )}
                        </Card>
                    ))}
                </div>
            )}
        </>
    );
}
