import { Link, useForm } from '@inertiajs/react';
import { KeyRound, LoaderCircle, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAuthUser } from '@/hooks/use-can';
import { formatDateTime } from '@/lib/format';

interface Props {
    profile: { name: string; email: string; username: string | null; phone: string | null; last_login_at: string | null };
}

export default function ProfileEdit({ profile }: Props) {
    const user = useAuthUser()!;
    const { data, setData, put, processing, errors } = useForm({ name: profile.name, email: profile.email, phone: profile.phone ?? '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('profile.update'), { preserveScroll: true });
    };

    return (
        <>
            <PageHeader title="Profil Saya" description="Kelola informasi akun Anda." breadcrumbs={[{ label: 'Akun' }, { label: 'Profil' }]} />
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi akun</CardTitle>
                        <CardDescription>Nama dan kontak yang digunakan untuk notifikasi.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <FormField label="Nama lengkap" error={errors.name} className="sm:col-span-2" required>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            </FormField>
                            <FormField label="Email" error={errors.email} required>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </FormField>
                            <FormField label="No. HP / WhatsApp" error={errors.phone}>
                                <Input value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </FormField>
                            <FormField label="Username (NIM/NIDN)" hint="Diatur oleh admin.">
                                <Input value={profile.username ?? '—'} disabled />
                            </FormField>
                            <div className="flex items-end justify-end sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? <LoaderCircle className="animate-spin" /> : <Save />} Simpan perubahan
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Akses</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 text-sm">
                        <div>
                            <div className="text-xs text-muted-foreground">Peran</div>
                            <div className="font-semibold">{user.role_label}</div>
                        </div>
                        <div>
                            <div className="text-xs text-muted-foreground">Cakupan data</div>
                            <div className="font-semibold">{user.scope_label}</div>
                        </div>
                        <div>
                            <div className="text-xs text-muted-foreground">Terakhir masuk</div>
                            <div className="font-semibold">{formatDateTime(profile.last_login_at)}</div>
                        </div>
                        <Button variant="outline" asChild>
                            <Link href={route('profile.password')}>
                                <KeyRound /> Ganti kata sandi
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
