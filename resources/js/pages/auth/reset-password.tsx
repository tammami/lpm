import { Head, useForm } from '@inertiajs/react';
import { KeyRound, LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('password.store'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <>
            <Head title="Atur ulang kata sandi" />
            <h2 className="text-2xl font-extrabold tracking-tight">Atur ulang kata sandi</h2>
            <p className="mt-1.5 mb-8 text-sm text-muted-foreground">Buat kata sandi baru minimal 8 karakter.</p>
            <form onSubmit={submit} className="flex flex-col gap-5">
                <FormField label="Email" htmlFor="email" error={errors.email}>
                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="h-11" />
                </FormField>
                <FormField label="Kata sandi baru" htmlFor="password" error={errors.password}>
                    <Input id="password" type="password" autoFocus value={data.password} onChange={(e) => setData('password', e.target.value)} className="h-11" />
                </FormField>
                <FormField label="Ulangi kata sandi" htmlFor="password_confirmation" error={errors.password_confirmation}>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        className="h-11"
                    />
                </FormField>
                <Button type="submit" size="lg" className="h-11" disabled={processing}>
                    {processing ? <LoaderCircle className="animate-spin" /> : <KeyRound />}
                    Simpan kata sandi
                </Button>
            </form>
        </>
    );
}
