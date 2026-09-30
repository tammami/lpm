import { useForm } from '@inertiajs/react';
import { LoaderCircle, RotateCcw, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';

interface Setting {
    key: string;
    label: string;
    help: string | null;
    type: 'number' | 'decimal' | 'boolean' | 'text';
    group: string;
    value: string | number | boolean;
    default: string | number | boolean;
}

const groups: Record<string, { title: string; description: string }> = {
    monev: { title: 'e-Monev', description: 'Ambang anonimitas, ambang skor, dan pengingat.' },
    improvement: { title: 'Peningkatan Mutu', description: 'Tenggat bawaan tindak lanjut.' },
    accreditation: { title: 'Akreditasi', description: 'Peringatan masa berlaku akreditasi.' },
    evidence: { title: 'Dokumen Bukti', description: 'Batas ukuran unggahan.' },
    backup: { title: 'Cadangan Database', description: 'Jumlah file cadangan yang dipertahankan.' },
    report: { title: 'Laporan', description: 'Identitas pada laporan PDF.' },
};

const keyOf = (key: string) => key.replaceAll('.', '__');

export default function SettingsPage({ settings }: { settings: Setting[] }) {
    const form = useForm<{ values: Record<string, string | number | boolean> }>({
        values: Object.fromEntries(settings.map((s) => [keyOf(s.key), s.value])),
    });
    const errors = form.errors as Record<string, string>;
    const grouped = Object.entries(groups)
        .map(([group, meta]) => ({ group, ...meta, items: settings.filter((s) => s.group === group) }))
        .filter((g) => g.items.length);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('settings.update'), { preserveScroll: true });
    };

    const set = (key: string, value: string | number | boolean) => form.setData('values', { ...form.data.values, [keyOf(key)]: value });

    return (
        <form onSubmit={submit} className="mx-auto max-w-4xl">
            <PageHeader
                title="Konfigurasi Sistem"
                description="Parameter yang dapat diubah tanpa developer. Perubahan tercatat di log audit."
                breadcrumbs={[{ label: 'Sistem' }, { label: 'Konfigurasi' }]}
                actions={
                    <Button type="submit" disabled={form.processing || !form.isDirty}>
                        {form.processing ? <LoaderCircle className="animate-spin" /> : <Save />} Simpan
                    </Button>
                }
            />
            <div className="flex flex-col gap-5">
                {grouped.map((group) => (
                    <Card key={group.group}>
                        <CardHeader>
                            <CardTitle>{group.title}</CardTitle>
                            <CardDescription>{group.description}</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col divide-y">
                            {group.items.map((setting) => {
                                const value = form.data.values[keyOf(setting.key)];
                                return (
                                    <div key={setting.key} className="grid grid-cols-1 gap-3 py-4 first:pt-0 last:pb-0 sm:grid-cols-[1fr_220px] sm:items-center">
                                        <div>
                                            <div className="text-sm font-semibold">{setting.label}</div>
                                            {setting.help && <p className="mt-0.5 text-xs text-muted-foreground">{setting.help}</p>}
                                            {value !== setting.default && (
                                                <button type="button" onClick={() => set(setting.key, setting.default)} className="mt-1 inline-flex items-center gap-1 text-[11px] text-primary hover:underline">
                                                    <RotateCcw className="size-3" /> Kembalikan bawaan ({String(setting.default)})
                                                </button>
                                            )}
                                        </div>
                                        <FormField error={errors[`values.${keyOf(setting.key)}`]}>
                                            {setting.type === 'boolean' ? (
                                                <Switch checked={Boolean(value)} onCheckedChange={(v) => set(setting.key, v)} className="justify-self-end" />
                                            ) : (
                                                <Input
                                                    type={setting.type === 'text' ? 'text' : 'number'}
                                                    step={setting.type === 'decimal' ? '0.01' : '1'}
                                                    value={String(value ?? '')}
                                                    onChange={(e) => set(setting.key, setting.type === 'text' ? e.target.value : e.target.value === '' ? '' : Number(e.target.value))}
                                                />
                                            )}
                                        </FormField>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </form>
    );
}
