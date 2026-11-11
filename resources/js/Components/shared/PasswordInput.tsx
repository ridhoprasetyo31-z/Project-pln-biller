import { Eye, EyeOff } from 'lucide-react';
import { useState, type ComponentProps } from 'react';

import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

/** Input kata sandi dengan tombol mata untuk menampilkan/menyembunyikan. */
export default function PasswordInput({ className, ...props }: Omit<ComponentProps<'input'>, 'type'>) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input type={visible ? 'text' : 'password'} className={cn('pr-12', className)} {...props} />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                aria-label={visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                aria-pressed={visible}
                className="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-9 text-muted-ink hover:text-ink focus-visible:ring-[3px] focus-visible:ring-pln-blue/25 focus-visible:outline-none"
            >
                {visible ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
        </div>
    );
}
