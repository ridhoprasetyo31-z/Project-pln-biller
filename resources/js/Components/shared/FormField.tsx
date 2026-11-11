import type { ReactNode } from 'react';

import { Label } from '@/Components/ui/label';
import { cn } from '@/lib/utils';

interface FormFieldProps {
    id: string;
    label: ReactNode;
    required?: boolean;
    error?: string;
    hint?: ReactNode;
    /** Elemen di kanan label, misalnya tautan "Lupa kata sandi?". */
    aside?: ReactNode;
    className?: string;
    children: ReactNode;
}

/** Label + kontrol + pesan galat. Kolom wajib diberi bintang merah. */
export default function FormField({ id, label, required, error, hint, aside, className, children }: FormFieldProps) {
    return (
        <div className={cn('grid content-start gap-2', className)}>
            <div className="flex items-center justify-between gap-3">
                <Label htmlFor={id}>
                    {label}
                    {required && (
                        <span className="text-pln-red" aria-hidden>
                            *
                        </span>
                    )}
                </Label>
                {aside}
            </div>
            {children}
            {hint && !error && <p className="text-xs text-muted-ink">{hint}</p>}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

export function InputError({ message, id, className }: { message?: string; id?: string; className?: string }) {
    if (!message) return null;
    return (
        <p id={id} className={cn('text-[13px] text-error-ink', className)}>
            {message}
        </p>
    );
}
