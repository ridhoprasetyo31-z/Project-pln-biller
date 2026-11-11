import * as React from 'react';

import { cn } from '@/lib/utils';

function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
    return (
        <input
            type={type}
            data-slot="input"
            className={cn(
                'flex h-11 w-full min-w-0 rounded-9 border border-border bg-white px-3 text-sm text-ink transition-[color,box-shadow] outline-none',
                'placeholder:text-muted-ink/70 file:border-0 file:bg-transparent file:text-sm file:font-medium',
                'focus-visible:border-pln-blue focus-visible:ring-[3px] focus-visible:ring-pln-blue/20',
                'disabled:cursor-not-allowed disabled:bg-table-head disabled:text-muted-ink',
                'aria-invalid:border-pln-red aria-invalid:ring-pln-red/15',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
