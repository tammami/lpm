import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AppLogo, LogoMark } from '@/components/brand/app-logo';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useCan } from '@/hooks/use-can';
import { filterNavigation, isActive, type NavItem } from '@/lib/navigation';
import { cn } from '@/lib/utils';

interface Props {
    collapsed?: boolean;
    onToggleCollapse?: () => void;
    onNavigate?: () => void;
}

export function AppSidebar({ collapsed = false, onToggleCollapse, onNavigate }: Props) {
    const page = usePage();
    const can = useCan();
    const groups = useMemo(() => filterNavigation(can, page.props.auth.user ?? {}), [page.props.auth.user]);
    const counts: Record<string, number> = { unreadNotifications: page.props.unreadNotifications };

    return (
        <div className="flex h-full flex-col border-r border-white/70 bg-sidebar bg-(image:--grad-sidebar) text-sidebar-foreground backdrop-blur-2xl">
            <div className={cn('flex h-16 shrink-0 items-center border-b border-white/60', collapsed ? 'justify-center px-2' : 'px-5')}>
                <Link href={route('home')} className="min-w-0" onClick={onNavigate}>
                    {collapsed ? <LogoMark label={`SIMUTU · ${page.props.app.institution_short}`} /> : <AppLogo subtitle={`LPM ${page.props.app.institution_short}`} />}
                </Link>
            </div>

            <ScrollArea className="min-h-0 flex-1">
                <nav className={cn('flex flex-col gap-6 py-5', collapsed ? 'px-2' : 'px-3')}>
                    {groups.map((group) => (
                        <div key={group.label} className="flex flex-col gap-1">
                            {!collapsed && (
                                <div className="px-3 pb-1 text-[11px] font-semibold tracking-[0.12em] text-muted-foreground uppercase">
                                    {group.label}
                                </div>
                            )}
                            {group.items.map((item) =>
                                item.children ? (
                                    <SidebarGroupItem key={item.title} item={item} collapsed={collapsed} onNavigate={onNavigate} />
                                ) : (
                                    <SidebarLink key={item.title} item={item} collapsed={collapsed} count={item.badgeKey ? counts[item.badgeKey] : 0} onNavigate={onNavigate} />
                                ),
                            )}
                        </div>
                    ))}
                </nav>
            </ScrollArea>

            {onToggleCollapse && (
                <div className="border-t border-white/60 p-3">
                    <button
                        type="button"
                        onClick={onToggleCollapse}
                        className={cn(
                            'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-muted-foreground transition hover:bg-white/60 hover:text-foreground',
                            collapsed && 'justify-center px-0',
                        )}
                    >
                        {collapsed ? <ChevronsRight className="size-4" /> : <ChevronsLeft className="size-4" />}
                        {!collapsed && 'Ciutkan menu'}
                    </button>
                </div>
            )}
        </div>
    );
}

const itemBase =
    'group relative flex h-10 items-center gap-3 rounded-xl px-3 text-[14px] font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring';


function SidebarLink({ item, collapsed, count, onNavigate }: { item: NavItem; collapsed: boolean; count?: number; onNavigate?: () => void }) {
    const active = isActive(item);
    const Icon = item.icon;

    const link = (
        <Link
            href={route(item.route!)}
            onClick={onNavigate}
            aria-current={active ? 'page' : undefined}
            aria-label={collapsed ? item.title : undefined}
            className={cn(
                itemBase,
                active ? 'bg-grad-primary text-white shadow-primary' : 'text-sidebar-foreground hover:bg-white/60 hover:text-foreground',
                collapsed && 'justify-center px-0',
            )}
        >
                        <Icon className={cn('size-[18px] shrink-0', active ? 'text-white' : 'text-primary/75 group-hover:text-primary')} />
            {!collapsed && <span className="truncate">{item.title}</span>}
            {!!count && (
                <span
                    className={cn(
                        'ml-auto rounded-full bg-grad-orange px-1.5 text-[11px] leading-5 font-bold text-on-orange tabular',
                        collapsed && 'absolute top-1 right-1 ml-0 px-1 text-[11px] leading-4',
                    )}
                >
                    {count > 99 ? '99+' : count}
                </span>
            )}
        </Link>
    );

    if (!collapsed) return link;

    return (
        <Tooltip>
            <TooltipTrigger asChild>{link}</TooltipTrigger>
            <TooltipContent side="right">{item.title}</TooltipContent>
        </Tooltip>
    );
}

function SidebarGroupItem({ item, collapsed, onNavigate }: { item: NavItem; collapsed: boolean; onNavigate?: () => void }) {
    const childActive = item.children!.some((child) => isActive(child));
    const [open, setOpen] = useState(childActive);
    const Icon = item.icon;

    if (collapsed) {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger
                    aria-label={item.title}
                    className={cn(
                        itemBase,
                        'justify-center px-0',
                        childActive ? 'bg-grad-primary text-white shadow-primary' : 'text-sidebar-foreground hover:bg-white/60',
                    )}
                >
                    <Icon className={cn('size-[18px]', childActive ? 'text-white' : 'text-primary/75')} />
                </DropdownMenuTrigger>
                <DropdownMenuContent side="right" align="start" className="min-w-52">
                    <DropdownMenuLabel>{item.title}</DropdownMenuLabel>
                    {item.children!.map((child) => (
                        <DropdownMenuItem key={child.route} asChild>
                            <Link href={route(child.route)} aria-current={isActive(child) ? 'page' : undefined} className={cn(isActive(child) && 'font-semibold text-primary')}>
                                {child.title}
                            </Link>
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <Collapsible open={open} onOpenChange={setOpen}>
            <CollapsibleTrigger
                className={cn(
                    itemBase,
                    'w-full',
                    childActive ? 'font-semibold text-foreground' : 'text-sidebar-foreground hover:bg-white/60 hover:text-foreground',
                )}
            >
                <Icon className={cn('size-[18px] shrink-0', childActive ? 'text-primary' : 'text-primary/75 group-hover:text-primary')} />
                <span className="truncate">{item.title}</span>
                <ChevronDown className={cn('ml-auto size-4 text-muted-foreground transition-transform', open && 'rotate-180')} />
            </CollapsibleTrigger>
            <CollapsibleContent className="data-[state=closed]:animate-collapsible-up data-[state=open]:animate-collapsible-down overflow-hidden">
                <div className="relative mt-1 mb-1 ml-[21px] flex flex-col gap-0.5 border-l border-primary/20 pl-3">
                    {item.children!.map((child) => {
                        const active = isActive(child);
                        return (
                            <Link
                                key={child.route}
                                href={route(child.route)}
                                onClick={onNavigate}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'relative flex h-9 items-center rounded-lg px-3 text-[13px] transition-colors',
                                    active
                                        ? 'bg-grad-primary font-semibold text-white shadow-primary'
                                        : 'text-sidebar-foreground hover:bg-white/60 hover:text-foreground',
                                )}
                            >
                                {active && <span className="absolute top-1/2 -left-[15.5px] size-2 -translate-y-1/2 rounded-full bg-primary ring-4 ring-white/70" />}
                                <span className="truncate">{child.title}</span>
                            </Link>
                        );
                    })}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
