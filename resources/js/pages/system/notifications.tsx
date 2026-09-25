import { Link, router } from '@inertiajs/react';
import { AlarmClock, BadgeCheck, Bell, CheckCheck, ClipboardCheck, FileWarning, ShieldAlert } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { PaginationBar } from '@/components/pagination-bar';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { fromNow } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

interface Item {
    id: string;
    data: { title: string; body: string; url?: string; icon?: string; tone?: string };
    read_at: string | null;
    created_at: string;
}

const icons: Record<string, typeof Bell> = { survey: ClipboardCheck, reminder: AlarmClock, finding: ShieldAlert, evidence: FileWarning, accreditation: BadgeCheck };
const tones: Record<string, string> = { primary: 'bg-secondary text-primary', warning: 'bg-warning-soft text-gold-foreground', danger: 'bg-danger-soft text-danger-foreground', info: 'bg-info-soft text-info-foreground' };

export default function Notifications({ notifications, filter }: { notifications: Paginated<Item>; filter: string }) {
    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader
                title="Notifikasi"
                breadcrumbs={[{ label: 'Notifikasi' }]}
                actions={
                    <Button variant="outline" onClick={() => router.post(route('notifications.read-all'), {}, { preserveScroll: true })}>
                        <CheckCheck /> Tandai semua dibaca
                    </Button>
                }
            />
            <ToggleGroup type="single" value={filter} onValueChange={(v) => v && router.get(route('notifications.index'), { filter: v }, { preserveState: true })} variant="outline" size="sm" className="mb-4">
                <ToggleGroupItem value="all" className="px-4">Semua</ToggleGroupItem>
                <ToggleGroupItem value="unread" className="px-4">Belum dibaca</ToggleGroupItem>
            </ToggleGroup>
            <div className="overflow-hidden rounded-2xl border bg-card">
                {notifications.data.length === 0 ? (
                    <EmptyState icon={Bell} title="Tidak ada notifikasi" description="Pemberitahuan Monev, temuan, dan tenggat akan muncul di sini." />
                ) : (
                    <ul className="divide-y">
                        {notifications.data.map((item) => {
                            const Icon = icons[item.data.icon ?? ''] ?? Bell;
                            return (
                                <li key={item.id}>
                                    <Link href={route('notifications.open', item.id)} className={cn('flex gap-4 px-5 py-4 transition hover:bg-muted/40', !item.read_at && 'bg-secondary/30')}>
                                        <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', tones[item.data.tone ?? 'primary'] ?? tones.primary)}>
                                            <Icon className="size-5" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-3">
                                                <span className={cn('text-sm', !item.read_at ? 'font-bold' : 'font-medium')}>{item.data.title}</span>
                                                <span className="shrink-0 text-[11px] text-muted-foreground">{fromNow(item.created_at)}</span>
                                            </div>
                                            <p className="mt-0.5 text-[13px] text-muted-foreground">{item.data.body}</p>
                                        </div>
                                        {!item.read_at && <span className="mt-2 size-2 shrink-0 rounded-full bg-gold" />}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <PaginationBar meta={notifications} />
            </div>
        </div>
    );
}
