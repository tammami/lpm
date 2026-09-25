import { useForm } from '@inertiajs/react';
import { ImageUp, LoaderCircle, Save } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { LogoMark } from '@/components/brand/app-logo';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    institution: {
        name: string;
        short_name: string;
        address: string | null;
        email: string | null;
        phone: string | null;
        website: string | null;
        rector_name: string | null;
        lpm_head_name: string | null;
        logo_url: string | null;
    };
    stats: { faculties: number; units: number };
}

export default function Institution({ institution, stats }: Props) {
    const [preview, setPreview] = useState<string | null>(institution.logo_url);
    const form = useForm({
        name: institution.name ?? '',
        short_name: institution.short_name ?? '',
        address: institution.address ?? '',
        email: institution.email ?? '',
        phone: institution.phone ?? '',
        website: institution.website ?? '',
        rector_name: institution.rector_name ?? '',
        lpm_head_name: institution.lpm_head_name ?? '',
        logo: null as File | null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('institution.update'), { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <PageHeader
                title="Profil Institusi"
                description="Identitas ini digunakan pada kop laporan PDF, halaman masuk, dan dokumen ekspor."
                breadcrumbs={[{ label: 'Organisasi' }, { label: 'Profil Institusi' }]}
            />
            <form onSubmit={submit} className="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_340px]">
                <Card>
                    <CardHeader>
                        <CardTitle>Identitas</CardTitle>
                        <CardDescription>Nama resmi, kontak, dan pejabat penandatangan laporan.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <FormField label="Nama institusi" error={form.errors.name} required className="sm:col-span-2">
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </FormField>
                        <FormField label="Singkatan" error={form.errors.short_name} required>
                            <Input value={form.data.short_name} onChange={(e) => form.setData('short_name', e.target.value)} />
                        </FormField>
                        <FormField label="Situs web" error={form.errors.website}>
                            <Input value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} placeholder="https://" />
                        </FormField>
                        <FormField label="Alamat" error={form.errors.address} className="sm:col-span-2">
                            <Textarea rows={2} value={form.data.address} onChange={(e) => form.setData('address', e.target.value)} />
                        </FormField>
                        <FormField label="Email" error={form.errors.email}>
                            <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        </FormField>
                        <FormField label="Telepon" error={form.errors.phone}>
                            <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </FormField>
                        <FormField label="Nama Rektor" error={form.errors.rector_name} hint="Ditampilkan pada area tanda tangan laporan.">
                            <Input value={form.data.rector_name} onChange={(e) => form.setData('rector_name', e.target.value)} />
                        </FormField>
                        <FormField label="Nama Ketua LPM" error={form.errors.lpm_head_name}>
                            <Input value={form.data.lpm_head_name} onChange={(e) => form.setData('lpm_head_name', e.target.value)} />
                        </FormField>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Logo</CardTitle>
                            <CardDescription>PNG/JPG/WebP, maks. 2 MB. Latar transparan disarankan.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-center gap-4">
                            <div className="flex size-32 items-center justify-center rounded-2xl border bg-muted/40 p-4">
                                {preview ? <img src={preview} alt="Logo institusi" className="max-h-full max-w-full object-contain" /> : <LogoMark variant="seal" className="size-16" />}
                            </div>
                            <label className="w-full">
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="sr-only"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0] ?? null;
                                        form.setData('logo', file);
                                        if (file) setPreview(URL.createObjectURL(file));
                                    }}
                                />
                                <span className="flex h-9 w-full cursor-pointer items-center justify-center gap-2 rounded-md border bg-card text-sm font-medium hover:bg-accent">
                                    <ImageUp className="size-4" /> Pilih logo
                                </span>
                            </label>
                            {form.errors.logo && <p className="text-xs text-destructive">{form.errors.logo}</p>}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <div className="text-2xl font-extrabold tabular">{stats.faculties}</div>
                                <div className="text-xs text-muted-foreground">Fakultas</div>
                            </div>
                            <div>
                                <div className="text-2xl font-extrabold tabular">{stats.units}</div>
                                <div className="text-xs text-muted-foreground">Unit kerja</div>
                            </div>
                        </CardContent>
                    </Card>
                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing ? <LoaderCircle className="animate-spin" /> : <Save />} Simpan profil
                    </Button>
                </div>
            </form>
        </>
    );
}
