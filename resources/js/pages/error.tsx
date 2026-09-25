import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { LogoMark } from '@/components/brand/app-logo';
import { Button } from '@/components/ui/button';

const messages: Record<number, { title: string; text: string }> = {
    403: { title: 'Akses ditolak', text: 'Anda tidak memiliki hak akses untuk membuka halaman ini. Hubungi admin LPM bila merasa ini keliru.' },
    404: { title: 'Halaman tidak ditemukan', text: 'Halaman yang Anda cari tidak ada atau sudah dipindahkan.' },
    500: { title: 'Terjadi kesalahan', text: 'Maaf, terjadi kesalahan pada server. Silakan coba beberapa saat lagi.' },
    503: { title: 'Sedang pemeliharaan', text: 'Sistem sedang dalam pemeliharaan. Silakan kembali sebentar lagi.' },
};

export default function ErrorPage({ status }: { status: number }) {
    const message = messages[status] ?? messages[500];

    return (
        <div className="bg-pattern-star flex min-h-svh flex-col items-center justify-center px-6 text-center">
            <Head title={message.title} />
            <LogoMark variant="seal" className="size-16" />
            <div className="mt-8 text-7xl font-extrabold tracking-tighter text-primary/15 tabular">{status}</div>
            <h1 className="mt-2 text-2xl font-extrabold tracking-tight">{message.title}</h1>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">{message.text}</p>
            <Button asChild className="mt-8">
                <Link href={route('home')}>
                    <ArrowLeft /> Kembali ke beranda
                </Link>
            </Button>
        </div>
    );
}
