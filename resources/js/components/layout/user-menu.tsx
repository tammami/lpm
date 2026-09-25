import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, KeyRound, LogOut, UserRound } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function UserMenu({ compact = false }: { compact?: boolean }) {
    const user = usePage().props.auth.user!;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex items-center gap-2.5 rounded-xl p-1 pr-2 transition outline-none hover:bg-white/60 focus-visible:ring-2 focus-visible:ring-ring/40">
                <Avatar className="size-9 rounded-lg">
                    <AvatarFallback className="rounded-xl bg-grad-primary text-[13px] font-bold text-primary-foreground">{user.initials}</AvatarFallback>
                </Avatar>
                {!compact && (
                    <div className="hidden text-left leading-tight md:block">
                        <div className="max-w-40 truncate text-[13px] font-semibold">{user.name}</div>
                        <div className="text-[11px] text-muted-foreground">{user.role_label}</div>
                    </div>
                )}
                <ChevronDown className="hidden size-4 text-muted-foreground md:block" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel className="font-normal">
                    <div className="text-sm font-semibold">{user.name}</div>
                    <div className="truncate text-xs text-muted-foreground">{user.email}</div>
                    <div className="mt-2 inline-flex rounded-md bg-grad-mint px-2 py-0.5 text-[11px] font-semibold text-secondary-foreground">
                        {user.role_label} · {user.scope_label}
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route('profile.edit')}>
                        <UserRound /> Profil saya
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={route('profile.password')}>
                        <KeyRound /> Ganti kata sandi
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={() => router.post(route('logout'))}>
                    <LogOut /> Keluar
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
