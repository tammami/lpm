import { usePage } from '@inertiajs/react';
import { BarChart3, ClipboardCheck, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { AppLogo, LogoMark } from '@/components/brand/app-logo';

const pillars = [
    { icon: ClipboardCheck, title: 'Monitor', text: 'e-Monev pembelajaran & survei kepuasan secara real-time.' },
    { icon: ShieldCheck, title: 'Control', text: 'Audit Mutu Internal, temuan, dan tindakan koreksi terlacak.' },
    { icon: BarChart3, title: 'Improve', text: 'Analitik, rekomendasi, dan kesiapan akreditasi berbasis bukti.' },
];

export default function AuthLayout({ children }: { children: ReactNode }) {
    const { app } = usePage().props;

    return (
        <div className="grid grid-cols-1 min-h-svh lg:grid-cols-[1.05fr_1fr]">
            <section className="relative hidden overflow-hidden bg-hero text-white lg:flex lg:flex-col">
                <div className="bg-pattern-star absolute inset-0 opacity-60" />
                <div className="absolute -top-40 -right-40 size-[520px] rounded-full bg-primary/40 blur-3xl" />
                <div className="absolute -bottom-48 -left-24 size-[420px] rounded-full bg-hero-shade/40 blur-3xl" />

                <div className="relative flex flex-1 flex-col justify-between p-12 xl:p-16">
                    <AppLogo inverted mark={false} subtitle={`Lembaga Penjaminan Mutu · ${app.institution}`} />

                    <div className="max-w-lg">
                        <LogoMark variant="seal" label={`Lambang ${app.institution}`} className="mb-7 size-28 drop-shadow-seal" />
                        <h1 className="text-4xl leading-[1.12] font-extrabold tracking-tight">
                            Platform Digital Penjaminan Mutu <span className="whitespace-nowrap text-hero-accent">{app.institution_short}</span>
                        </h1>
                        <p className="mt-4 text-[16px] leading-relaxed text-white/85">
                            Dari pengumpulan data hingga bukti akreditasi — satu siklus mutu yang terukur, terlacak, dan berkelanjutan.
                        </p>

                        <ul className="mt-10 grid grid-cols-1 gap-5 border-t border-white/10 pt-8">
                            {pillars.map(({ icon: Icon, title, text }) => (
                                <li key={title} className="flex items-start gap-4">
                                    <Icon className="mt-0.5 size-5 shrink-0 text-hero-accent" aria-hidden="true" />
                                    <div>
                                        <div className="text-sm font-bold">{title}</div>
                                        <div className="text-[13px] text-white/85">{text}</div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="text-xs text-white/85">
                        © {new Date().getFullYear()} {app.institution}
                    </p>
                </div>
            </section>

            <section className="flex flex-col items-center justify-center px-5 py-10 sm:px-10">
                <div className="mb-8 flex flex-col items-center gap-3 text-center lg:hidden">
                    <LogoMark variant="seal" label={`Lambang ${app.institution}`} className="size-20" />
                    <div>
                        <div className="text-lg font-extrabold">SIMUTU</div>
                        <div className="text-xs text-muted-foreground">LPM {app.institution}</div>
                    </div>
                </div>
                <div className="w-full max-w-[440px] rounded-3xl border border-white/80 surface-glass p-6 sm:p-9">{children}</div>
            </section>
        </div>
    );
}
