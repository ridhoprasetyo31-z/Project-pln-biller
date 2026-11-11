import { cva, type VariantProps } from 'class-variance-authority';
import { Slot } from 'radix-ui';
import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * Tombol sesuai DESIGN-ringkas:
 * - default  : biru utama 40 px rounded-9 (satu per layar)
 * - secondary: putih ber-border
 * - reject   : putih, teks merah, border merah muda (Tolak)
 * - destructive: merah penuh, HANYA untuk hapus
 */
const buttonVariants = cva(
    "inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-9 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/40 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default: 'bg-pln-blue text-white hover:bg-pln-blue-dark',
                secondary: 'border border-border bg-white text-ink hover:bg-table-head',
                reject: 'border border-error-line bg-white text-error-ink hover:bg-error-tint',
                destructive: 'bg-pln-red text-white hover:bg-error-ink',
                ghost: 'text-ink hover:bg-table-head',
                link: 'h-auto px-0 text-pln-blue underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4',
                sm: 'h-9 px-3 text-[13px]',
                lg: 'h-11 px-5',
                icon: 'size-10',
                'icon-sm': 'size-9',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<'button'> & VariantProps<typeof buttonVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot.Root : 'button';

    return <Comp data-slot="button" className={cn(buttonVariants({ variant, size, className }))} {...props} />;
}

export { Button, buttonVariants };
