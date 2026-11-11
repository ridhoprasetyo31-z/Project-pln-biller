import { ChevronDown } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * <select> bawaan browser bergaya Input. Dipilih daripada Radix Select
 * agar ramah layar sentuh (picker native di HP) dan mendukung nilai kosong.
 */
function NativeSelect({ className, children, ...props }: React.ComponentProps<'select'>) {
    return (
        <div className={cn('relative', className)}>
            <select
                data-slot="native-select"
                className={cn(
                    'h-11 w-full min-w-0 appearance-none rounded-9 border border-border bg-white pr-9 pl-3 text-sm text-ink outline-none transition-[color,box-shadow]',
                    'focus-visible:border-pln-blue focus-visible:ring-[3px] focus-visible:ring-pln-blue/20',
                    'disabled:cursor-not-allowed disabled:bg-table-head disabled:text-muted-ink',
                    'aria-invalid:border-pln-red',
                )}
                {...props}
            >
                {children}
            </select>
            <ChevronDown
                aria-hidden
                className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-ink"
            />
        </div>
    );
}

export { NativeSelect };
