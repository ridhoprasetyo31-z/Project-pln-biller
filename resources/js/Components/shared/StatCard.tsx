import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface StatCardProps {
    label: string;
    value: ReactNode;
    caption?: ReactNode;
    /** 0–100, menampilkan ProgressBar 6 px. */
    progress?: number;
    valueClassName?: string;
    className?: string;
}

export default function StatCard({ label, value, caption, progress, valueClassName, className }: StatCardProps) {
    return (
        <div className={cn('rounded-14 border border-border bg-white p-4', className)}>
            <p className="text-[13px] text-muted-ink">{label}</p>
            <p className={cn('mt-2 text-[28px] leading-none font-semibold text-ink', valueClassName)}>{value}</p>
            {progress !== undefined && <ProgressBar value={progress} className="mt-3" />}
            {caption && <p className="mt-2 text-xs text-muted-ink">{caption}</p>}
        </div>
    );
}

export function ProgressBar({ value, className }: { value: number; className?: string }) {
    const pct = Math.max(0, Math.min(100, value));
    return (
        <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={Math.round(pct)}
            className={cn('h-1.5 w-full overflow-hidden rounded-full bg-pln-blue-light', className)}
        >
            <div className="h-full rounded-full bg-pln-blue transition-[width]" style={{ width: `${pct}%` }} />
        </div>
    );
}
