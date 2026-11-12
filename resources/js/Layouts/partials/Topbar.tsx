import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, House, Search, Wifi, WifiOff } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';

import { useNavigation } from '@/hooks/useNavigation';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, PageProps } from '@/types';
import CommandSearch from './CommandSearch';
import Lonceng from './Lonceng';

function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
    return (
        <nav aria-label="Breadcrumb" className="min-w-0">
            <ol className="flex min-w-0 items-center gap-1.5 text-[13px]">
                <li className="shrink-0">
                    <Link
                        href={route('home')}
                        className="inline-flex size-8 items-center justify-center rounded-md text-muted-ink hover:bg-table-head hover:text-ink"
                    >
                        <House className="size-4" />
                        <span className="sr-only">Beranda</span>
                    </Link>
                </li>
                {items.map((item, i) => {
                    const last = i === items.length - 1;
                    return (
                        <Fragment key={`${item.label}-${i}`}>
                            <li aria-hidden className={cn('shrink-0', !last && 'hidden sm:block')}>
                                <ChevronRight className="size-3.5 text-muted-ink/60" />
                            </li>
                            <li className={cn('min-w-0 truncate', !last && 'hidden sm:block')}>
                                {item.href && !last ? (
                                    <Link href={item.href} className="text-muted-ink hover:text-ink">
                                        {item.label}
                                    </Link>
                                ) : (
                                    <span
                                        aria-current={last ? 'page' : undefined}
                                        className={last ? 'font-medium text-ink' : 'text-muted-ink'}
                                    >
                                        {item.label}
                                    </span>
                                )}
                            </li>
                        </Fragment>
                    );
                })}
            </ol>
        </nav>
    );
}

function ConnectionIndicator() {
    const online = useOnlineStatus();
    return (
        <span
            role="status"
            className={cn(
                'hidden items-center gap-1.5 text-[13px] md:inline-flex',
                online ? 'text-success-ink' : 'text-error-ink',
            )}
        >
            {online ? <Wifi className="size-4" /> : <WifiOff className="size-4" />}
            {online ? 'Tersambung, semua catatan terkirim' : 'Tidak tersambung, periksa koneksi internet'}
        </span>
    );
}

export default function Topbar({ breadcrumbs }: { breadcrumbs?: BreadcrumbItem[] }) {
    const { auth } = usePage<PageProps>().props;
    const { activeGroup, activeItem } = useNavigation();
    const [searchOpen, setSearchOpen] = useState(false);

    const items: BreadcrumbItem[] =
        breadcrumbs ??
        (activeItem ? [{ label: activeGroup!.label }, { label: activeItem.label, href: activeItem.href }] : []);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setSearchOpen((v) => !v);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    return (
        <div className="flex h-16 items-center gap-3 border-b border-line-row px-4 sm:px-7">
            <div className="min-w-0 flex-1">
                <Breadcrumb items={items} />
            </div>

            {auth.user.role === 'biller' && <ConnectionIndicator />}

            <button
                type="button"
                onClick={() => setSearchOpen(true)}
                className="inline-flex size-10 items-center justify-center gap-2 rounded-9 border border-border bg-white text-[13px] text-muted-ink hover:bg-table-head sm:w-56 sm:justify-start sm:px-3"
            >
                <Search className="size-4 shrink-0" />
                <span className="hidden flex-1 text-left sm:inline">Cari menu</span>
                <kbd className="hidden rounded-md bg-table-head px-1.5 py-0.5 font-mono text-[11px] text-muted-ink sm:inline">
                    Ctrl K
                </kbd>
                <span className="sr-only sm:hidden">Cari menu</span>
            </button>

            <Lonceng />

            <CommandSearch open={searchOpen} onOpenChange={setSearchOpen} />
        </div>
    );
}
