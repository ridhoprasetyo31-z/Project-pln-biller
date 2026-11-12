import { router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck, RotateCcw, Unplug } from 'lucide-react';

import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { formatRelatif } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

/** Lonceng notifikasi: daftar terbaru, badge belum dibaca, klik membuka tautan. */
export default function Lonceng() {
    const { notifikasi } = usePage<SharedProps>().props;
    const belum = notifikasi?.belum_dibaca ?? 0;
    const daftar = notifikasi?.daftar ?? [];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="relative inline-flex size-10 items-center justify-center rounded-9 border border-border bg-white text-muted-ink outline-none hover:bg-table-head hover:text-ink focus-visible:ring-[3px] focus-visible:ring-pln-blue/25">
                <Bell className="size-4" />
                {belum > 0 && (
                    <span className="absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-pln-red px-1 text-[11px] font-semibold text-white">
                        {belum > 9 ? '9+' : belum}
                    </span>
                )}
                <span className="sr-only">Notifikasi{belum > 0 ? `, ${belum} belum dibaca` : ''}</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[22rem] max-w-[calc(100vw-1.5rem)]">
                <div className="flex items-center justify-between pr-1">
                    <DropdownMenuLabel className="font-semibold">Notifikasi</DropdownMenuLabel>
                    {belum > 0 && (
                        <button
                            type="button"
                            onClick={() => router.post(route('notifikasi.baca-semua'), {}, { preserveScroll: true, preserveState: true })}
                            className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-pln-blue hover:bg-table-head"
                        >
                            <CheckCheck className="size-3.5" />
                            Tandai semua dibaca
                        </button>
                    )}
                </div>
                <DropdownMenuSeparator />
                {daftar.length === 0 ? (
                    <p className="px-2.5 py-6 text-center text-sm text-muted-ink">Belum ada notifikasi.</p>
                ) : (
                    <div className="max-h-96 overflow-y-auto">
                        {daftar.map((n) => {
                            const Ikon = n.jenis === 'tusbug' ? Unplug : RotateCcw;
                            return (
                                <DropdownMenuItem
                                    key={n.id}
                                    onSelect={() => router.post(route('notifikasi.buka', n.id))}
                                    className={cn('items-start gap-3 py-2.5', !n.dibaca && 'bg-pln-blue-light/50')}
                                >
                                    <Ikon className={cn('mt-0.5', n.jenis === 'tusbug' ? 'text-pln-blue!' : 'text-pln-red!')} />
                                    <span className="min-w-0 flex-1">
                                        <span className={cn('block text-[13px]', !n.dibaca && 'font-semibold')}>{n.judul}</span>
                                        <span className="block text-xs whitespace-normal text-muted-ink">{n.pesan}</span>
                                        <span className="mt-0.5 block text-[11px] text-muted-ink">{formatRelatif(n.waktu)}</span>
                                    </span>
                                    {!n.dibaca && <span aria-label="Belum dibaca" className="mt-1.5 size-2 shrink-0 rounded-full bg-pln-red" />}
                                </DropdownMenuItem>
                            );
                        })}
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
