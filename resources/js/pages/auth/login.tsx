import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle, LogIn } from 'lucide-react';
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
                <h2 className="text-2xl font-extrabold tracking-tight">Selamat datang</h2>
                <p className="mt-1.5 text-sm text-muted-foreground">Masuk menggunakan email, NIM, atau NIDN Anda.</p>
            </div>

            {status && (
                <Alert className="mb-6 border-success/30 bg-success-soft text-success">
                    <AlertDescription className="text-success">{status}</AlertDescription>
                </Alert>
            )}

            <form onSubmit={submit} className="flex flex-col gap-5">
                <FormField label="Email / NIM / NIDN" htmlFor="login" error={errors.login}>
                    <Input
                        id="login"
                        autoFocus
                        autoComplete="username"
                        value={data.login}
                        onChange={(e) => setData('login', e.target.value)}
                        placeholder="Email, NIM, atau NIDN"
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

            <p className="mt-8 text-center text-xs leading-relaxed text-muted-foreground">
                Kesulitan masuk? Hubungi admin LPM atau admin program studi Anda.
                <br />
                Data evaluasi Anda dijaga kerahasiaannya.
            </p>
        </>
    );
}
