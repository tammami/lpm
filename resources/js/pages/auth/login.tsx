import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LifeBuoy, LoaderCircle, LogIn } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function Login({ status }: { status?: string }) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ login: '', password: '', remember: false });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <>
            <Head title="Masuk" />
            <div className="mb-8">
                <h1 className="text-2xl font-extrabold tracking-tight">Selamat datang</h1>
                <p className="mt-1.5 text-sm text-muted-foreground">Masuk menggunakan email, NIM, NIDN, atau NUPTK Anda.</p>
            </div>

            {status && (
                <Alert className="mb-6 border-success/30 bg-success-soft text-success-foreground">
                    <AlertDescription className="text-success-foreground">{status}</AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5">
                <FormField label="Email / NIM / NIDN / NUPTK" htmlFor="login" error={errors.login}>
                    <Input
                        id="login"
                        autoFocus
                        autoComplete="username"
                        value={data.login}
                        onChange={(e) => setData('login', e.target.value)}
                        placeholder="Email, NIM, NIDN, atau NUPTK"
                        className="h-11"
                        aria-invalid={!!errors.login}
                    />
                </FormField>

                <FormField
                    label="Kata sandi"
                    htmlFor="password"
                    error={errors.password}
                    action={
                        <Link href={route('password.request')} className="text-xs font-semibold text-primary hover:underline">
                            Lupa kata sandi?
                        </Link>
                    }
                >
                    <div className="relative">
                        <Input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="h-11 pr-11"
                            aria-invalid={!!errors.password}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((v) => !v)}
                            className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-muted-foreground hover:text-foreground"
                            aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                        >
                            {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </button>
                    </div>
                </FormField>

                <div className="flex items-center gap-2.5">
                    <Checkbox id="remember" checked={data.remember} onCheckedChange={(v) => setData('remember', v === true)} />
                    <Label htmlFor="remember" className="text-sm font-normal text-muted-foreground">
                        Ingat saya di perangkat ini
                    </Label>
                </div>

                <Button type="submit" size="lg" className="h-11 text-[16px] font-semibold" disabled={processing}>
                    {processing ? <LoaderCircle className="animate-spin" /> : <LogIn />}
                    Masuk
                </Button>
            </form>

            <Link
                href={route('guide.index', { kategori: 'akun' })}
                className="mt-6 flex items-center gap-3 rounded-2xl border border-gold/45 bg-linear-to-r from-gold-soft to-white/70 p-3.5 text-left transition hover:border-gold focus-visible:ring-2 focus-visible:ring-gold/50 focus-visible:outline-none"
            >
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white/80 text-gold-foreground shadow-soft">
                    <LifeBuoy className="size-5" aria-hidden="true" />
                </span>
                <span className="min-w-0">
                    <span className="block text-sm font-bold text-gold-foreground">Panduan masuk & lupa kata sandi</span>
                    <span className="block text-xs text-muted-foreground">Username apa yang dipakai? Kata sandi awal? Baca di sini.</span>
                </span>
            </Link>

            <p className="mt-6 text-center text-xs leading-relaxed text-muted-foreground">
                Masih kesulitan? Hubungi admin LPM atau admin program studi Anda.
                <br />
                Data evaluasi Anda dijaga kerahasiaannya.
            </p>
        </>
    );
}
