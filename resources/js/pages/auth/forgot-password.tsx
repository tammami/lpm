import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, LoaderCircle, Mail } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('password.email'));
    };

    return (
        <>
            <Head title="Lupa kata sandi" />
            <Link href={route('login')} className="mb-8 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                <ArrowLeft className="size-4" /> Kembali ke halaman masuk
            </Link>
            <h2 className="text-2xl font-extrabold tracking-tight">Lupa kata sandi?</h2>
            <p className="mt-1.5 mb-8 text-sm text-muted-foreground">Masukkan email terdaftar. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.</p>

            {status && (
                <Alert className="mb-6 border-success/30 bg-success-soft">
                    <AlertDescription className="text-success">{status}</AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5">
                <FormField label="Email" htmlFor="email" error={errors.email}>
                    <Input id="email" type="email" autoFocus value={data.email} onChange={(e) => setData('email', e.target.value)} className="h-11" />
                </FormField>
                <Button type="submit" size="lg" className="h-11" disabled={processing}>
                    {processing ? <LoaderCircle className="animate-spin" /> : <Mail />}
                    Kirim tautan
                </Button>
            </form>
        </>
    );
}
