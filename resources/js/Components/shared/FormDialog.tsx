import { LoaderCircle } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { cn } from '@/lib/utils';

interface FormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: ReactNode;
    submitLabel: string;
    processing?: boolean;
    onSubmit: () => void;
    children: ReactNode;
    className?: string;
}

/** Dialog berisi formulir: isi bisa digulir, tombol tetap di bawah. */
export default function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel,
    processing,
    onSubmit,
    children,
    className,
}: FormDialogProps) {
    const submit = (e: FormEvent) => {
        e.preventDefault();
        onSubmit();
    };

    return (
        <Dialog open={open} onOpenChange={(v) => !processing && onOpenChange(v)}>
            <DialogContent className={cn('max-h-[calc(100dvh-2rem)] max-w-xl gap-0 overflow-hidden p-0', className)}>
                <form onSubmit={submit} noValidate className="flex max-h-[calc(100dvh-2rem)] flex-col">
                    <DialogHeader className="border-b border-line-row px-6 pt-6 pb-4">
                        <DialogTitle>{title}</DialogTitle>
                        {description && <DialogDescription>{description}</DialogDescription>}
                    </DialogHeader>
                    <div className="grid gap-5 overflow-y-auto px-6 py-5">{children}</div>
                    <DialogFooter className="border-t border-line-row px-6 py-4">
                        <Button type="button" variant="secondary" onClick={() => onOpenChange(false)} disabled={processing}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <LoaderCircle className="animate-spin" />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
