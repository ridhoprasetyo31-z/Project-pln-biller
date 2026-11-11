import { MoreHorizontal, type LucideIcon } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

export interface RowAction {
    label: string;
    icon: LucideIcon;
    onSelect: () => void;
    danger?: boolean;
    disabled?: boolean;
    hidden?: boolean;
}

/** Menu aksi per baris tabel (tombol titik tiga, target sentuh 40 px). */
export default function RowActions({ label, actions }: { label: string; actions: RowAction[] }) {
    const visible = actions.filter((a) => !a.hidden);
    const normal = visible.filter((a) => !a.danger);
    const danger = visible.filter((a) => a.danger);

    if (visible.length === 0) return null;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="inline-flex size-10 items-center justify-center rounded-9 text-muted-ink outline-none hover:bg-table-head hover:text-ink focus-visible:ring-[3px] focus-visible:ring-pln-blue/25">
                <MoreHorizontal className="size-4" />
                <span className="sr-only">Aksi untuk {label}</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {normal.map((a) => (
                    <DropdownMenuItem key={a.label} onSelect={a.onSelect} disabled={a.disabled}>
                        <a.icon />
                        {a.label}
                    </DropdownMenuItem>
                ))}
                {normal.length > 0 && danger.length > 0 && <DropdownMenuSeparator />}
                {danger.map((a) => (
                    <DropdownMenuItem key={a.label} variant="danger" onSelect={a.onSelect} disabled={a.disabled}>
                        <a.icon />
                        {a.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
