import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

/** Baris filter di atas tabel; menumpuk di HP. */
export default function Toolbar({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div className={cn('mb-4 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center', className)}>
            {children}
        </div>
    );
}
