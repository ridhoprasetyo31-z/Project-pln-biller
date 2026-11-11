import { LoaderCircle } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel: string;
    /** "destructive" hanya untuk hapus. */
    tone?: 'default' | 'destructive';
    processing?: boolean;
    onConfirm: () => void;
    children?: ReactNode;
}

export default function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    tone = 'default',
    processing,
    onConfirm,
    children,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
                <DialogFooter>
                    <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={processing}>
                        Batal
                    </Button>
                    <Button variant={tone === 'destructive' ? 'destructive' : 'default'} onClick={onConfirm} disabled={processing}>
                        {processing && <LoaderCircle className="animate-spin" />}
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
