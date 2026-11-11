import { Link, router, usePage } from '@inertiajs/react';
import { ChevronsUpDown, LogOut, Menu, UserRound, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import UserAvatar from '@/Components/shared/UserAvatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useNavigation, type ResolvedNavGroup } from '@/hooks/useNavigation';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

function Brand() {
    const { auth } = usePage<PageProps>().props;
    return (
        <Link href={route('home')} className="flex min-w-0 items-center gap-3 rounded-10 px-2 py-1.5">
            <img src="/images/logo-pln-mark.png" alt="Logo PLN" className="size-9 shrink-0 rounded-[6px]" />
            <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-ink">Kinerja Biller</span>
                <span className="block truncate text-xs text-muted-ink">{auth.user.unit?.nama ?? 'Semua unit'}</span>
            </span>
        </Link>
    );
}

function NavGroups({ groups }: { groups: ResolvedNavGroup[] }) {
    return (
        <nav aria-label="Menu utama" className="flex flex-col gap-5">
            {groups.map((group) => (
                <div key={group.label}>
                    <p className="mb-1.5 px-3 text-xs text-muted-ink">{group.label}</p>
                    <ul className="flex flex-col gap-0.5">
                        {group.items.map((item) => (
                            <li key={item.id}>
                                <Link
                                    href={item.href}
                                    aria-current={item.active ? 'page' : undefined}
                                    className={cn(
                                        'relative flex h-11 items-center gap-2.5 rounded-10 px-3 text-sm transition-colors lg:h-10',
                                        item.active
                                            ? 'bg-white font-medium text-ink shadow-[0_1px_2px_rgba(15,27,42,0.08)] ring-1 ring-border'
                                            : 'text-muted-ink hover:bg-white/70 hover:text-ink',
                                    )}
                                >
                                    {item.active && (
                                        <span
                                            aria-hidden
                                            className="absolute top-2 bottom-2 left-0 w-[3px] rounded-full bg-pln-yellow"
                                        />
                                    )}
                                    <item.icon className={cn('size-4 shrink-0', item.active && 'text-pln-blue')} />
                                    <span className="min-w-0 flex-1 truncate">{item.label}</span>
                                    {item.badge > 0 && (
                                        <span className="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-pln-red px-1.5 text-[11px] font-semibold text-white">
                                            {item.badge > 99 ? '99+' : item.badge}
                                            <span className="sr-only"> menunggu</span>
                                        </span>
                                    )}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

function UserCard() {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="flex w-full items-center gap-3 rounded-14 bg-white p-2.5 text-left ring-1 ring-border outline-none hover:bg-table-head focus-visible:ring-[3px] focus-visible:ring-pln-blue/25">
                <UserAvatar name={user.name} src={user.foto_url} size={36} />
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-ink">{user.name}</span>
                    <span className="block truncate text-xs text-muted-ink">{user.role_label}</span>
                </span>
                <ChevronsUpDown className="size-4 shrink-0 text-muted-ink" aria-hidden />
            </DropdownMenuTrigger>
            <DropdownMenuContent side="top" align="start" className="w-[var(--radix-dropdown-menu-trigger-width)]">
                <DropdownMenuLabel>
                    <span className="block font-medium">{user.name}</span>
                    <span className="block font-mono text-xs text-muted-ink">NIP {user.nip}</span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={route('profil.edit')}>
                        <UserRound />
                        Profil saya
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem variant="danger" onSelect={() => router.post(route('logout'))}>
                    <LogOut />
                    Keluar
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Sidebar 232 px tanpa latar (≥1024 px). Di bawah 1024 px sidebar
 * pindah ke atas: baris logo + tombol Menu yang membuka daftar menu.
 */
export default function Sidebar() {
    const { groups } = useNavigation();
    const [open, setOpen] = useState(false);

    useEffect(() => router.on('navigate', () => setOpen(false)), []);

    return (
        <>
            {/* Desktop */}
            <aside className="sticky top-3 hidden h-[calc(100dvh-24px)] w-[232px] shrink-0 flex-col gap-6 lg:flex">
                <Brand />
                <div className="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
                    <NavGroups groups={groups} />
                </div>
                <UserCard />
            </aside>

            {/* HP & tablet */}
            <header className="mb-3 lg:hidden">
                <div className="flex items-center justify-between gap-3">
                    <Brand />
                    <button
                        type="button"
                        onClick={() => setOpen((v) => !v)}
                        aria-expanded={open}
                        aria-controls="menu-seluler"
                        className="inline-flex h-11 items-center gap-2 rounded-10 bg-white px-3.5 text-sm font-medium text-ink ring-1 ring-border"
                    >
                        {open ? <X className="size-4" /> : <Menu className="size-4" />}
                        Menu
                    </button>
                </div>
                {open && (
                    <div id="menu-seluler" className="mt-3 flex flex-col gap-5 rounded-2xl bg-canvas pb-1">
                        <NavGroups groups={groups} />
                        <UserCard />
                    </div>
                )}
            </header>
        </>
    );
}
