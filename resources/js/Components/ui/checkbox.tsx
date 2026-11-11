import { CheckIcon } from 'lucide-react';
import { Checkbox as CheckboxPrimitive } from 'radix-ui';
import * as React from 'react';

import { cn } from '@/lib/utils';

function Checkbox({ className, ...props }: React.ComponentProps<typeof CheckboxPrimitive.Root>) {
    return (
        <CheckboxPrimitive.Root
            data-slot="checkbox"
            className={cn(
                'peer size-[18px] shrink-0 rounded-[5px] border border-border bg-white outline-none transition-colors',
                'focus-visible:ring-[3px] focus-visible:ring-pln-blue/25',
                'data-[state=checked]:border-pln-blue data-[state=checked]:bg-pln-blue data-[state=checked]:text-white',
                'disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator data-slot="checkbox-indicator" className="flex items-center justify-center">
                <CheckIcon className="size-3.5" strokeWidth={3} />
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

export { Checkbox };
