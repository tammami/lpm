import { FileArchive, FileImage, FileSpreadsheet, FileText, Link2, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export function FileIcon({ mime, link, className }: { mime?: string | null; link?: boolean; className?: string }) {
    let Icon: LucideIcon = FileText;
    let tone = 'bg-info-soft text-info-foreground';

    if (link) {
        Icon = Link2;
        tone = 'bg-secondary text-primary';
    } else if (mime?.includes('pdf')) {
        tone = 'bg-danger-soft text-danger-foreground';
    } else if (mime?.includes('sheet') || mime?.includes('excel')) {
        Icon = FileSpreadsheet;
        tone = 'bg-success-soft text-primary';
    } else if (mime?.startsWith('image/')) {
        Icon = FileImage;
        tone = 'bg-gold-soft text-gold-foreground';
    } else if (mime?.includes('zip')) {
        Icon = FileArchive;
        tone = 'bg-muted text-muted-foreground';
    }

    return (
        <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', tone, className)}>
            <Icon className="size-5" />
        </span>
    );
}
