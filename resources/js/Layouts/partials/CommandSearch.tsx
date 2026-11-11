import { router } from '@inertiajs/react';
import { CornerDownLeft, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { useNavigation } from '@/hooks/useNavigation';
import { cn } from '@/lib/utils';

interface CommandSearchProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/** Pencarian cepat menu (Ctrl K). */
export default function CommandSearch({ open, onOpenChange }: CommandSearchProps) {
    const { groups } = useNavigation();
    const [query, setQuery] = useState('');
    const [index, setIndex] = useState(0);

    const results = useMemo(() => {
        const q = query.trim().toLowerCase();
        return groups
            .flatMap((g) => g.items.map((item) => ({ ...item, group: g.label })))
            .filter((item) => !q || item.label.toLowerCase().includes(q) || item.group.toLowerCase().includes(q));
    }, [groups, query]);

    const go = (href: string) => {
        onOpenChange(false);
        setQuery('');
        router.visit(href);
    };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setIndex((i) => Math.min(i + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setIndex((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter' && results[index]) {
            e.preventDefault();
            go(results[index].href);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(v) => {
                onOpenChange(v);
                if (!v) setQuery('');
            }}
        >
            <DialogContent showClose={false} className="top-[15%] max-w-md translate-y-0 gap-0 overflow-hidden p-0">
                <DialogTitle className="sr-only">Cari menu</DialogTitle>
                <DialogDescription className="sr-only">Ketik nama menu lalu tekan Enter.</DialogDescription>
                <div className="flex items-center gap-2 border-b border-line-row px-4">
                    <Search className="size-4 text-muted-ink" aria-hidden />
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setIndex(0);
                        }}
                        onKeyDown={onKeyDown}
                        placeholder="Cari menu, contoh: profil"
                        aria-label="Cari menu"
                        className="h-12 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-ink/70"
                    />
                </div>
                <ul role="listbox" className="max-h-80 overflow-y-auto p-1.5">
                    {results.length === 0 && (
                        <li className="px-3 py-8 text-center text-sm text-muted-ink">Menu tidak ditemukan.</li>
                    )}
                    {results.map((item, i) => (
                        <li key={`${item.group}-${item.id}`} role="option" aria-selected={i === index}>
                            <button
                                type="button"
                                onMouseEnter={() => setIndex(i)}
                                onClick={() => go(item.href)}
                                className={cn(
                                    'flex min-h-11 w-full items-center gap-3 rounded-md px-3 text-left text-sm',
                                    i === index ? 'bg-pln-blue-light text-pln-blue-dark' : 'text-ink',
                                )}
                            >
                                <item.icon className="size-4 shrink-0" />
                                <span className="flex-1">{item.label}</span>
                                <span className="text-xs text-muted-ink">{item.group}</span>
                                {i === index && <CornerDownLeft className="size-3.5 text-muted-ink" aria-hidden />}
                            </button>
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
