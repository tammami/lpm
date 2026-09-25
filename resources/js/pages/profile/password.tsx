import { useForm } from '@inertiajs/react';
import { KeyRound, LoaderCircle, ShieldAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export default function ProfilePassword({ mustChange }: { mustChange: boolean }) {
    const { data, setData, put, processing, errors, reset } = useForm({ current_password: '', password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('profile.password.update'), { onFinish: () => reset() });
    };

    return (
        <div className="mx-auto max-w-xl">
            <PageHeader title="Ganti Kata Sandi" breadcrumbs={[{ label: 'Akun' }, { label: 'Kata sandi' }]} />
            {mustChange && (
                <Alert className="mb-6 border-warning/40 bg-warning-soft">
                    <ShieldAlert className="text-gold-foreground" />
                    <AlertTitle>Ganti kata sandi awal Anda</AlertTitle>
                    <AlertDescription>Demi keamanan, Anda wajib mengganti kata sandi bawaan sebelum melanjutkan.</AlertDescription>
                </Alert>
            )}
            <Card>
                <CardContent>
                    <form onSubmit={submit} className="flex flex-col gap-5">
                        <FormField label="Kata sandi saat ini" error={errors.current_password} required>
                            <Input type="password" value={data.current_password} onChange={(e) => setData('current_password', e.target.value)} autoFocus />
                        </FormField>
                        <FormField label="Kata sandi baru" error={errors.password} hint="Minimal 8 karakter." required>
                            <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                        </FormField>
                        <FormField label="Ulangi kata sandi baru" error={errors.password_confirmation} required>
                            <Input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} />
                        </FormField>
                        <Button type="submit" disabled={processing} className="self-end">
                            {processing ? <LoaderCircle className="animate-spin" /> : <KeyRound />} Simpan kata sandi
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
