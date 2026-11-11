import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface EmptyStateProps {
    icon: LucideIcon;
    title: string;
    children?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export default function EmptyState({ icon: Icon, title, children, action, className }: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-14 border border-dashed border-border px-6 py-12 text-center',
                className,
            )}
        >
            <span className="mb-4 inline-flex size-12 items-center justify-center rounded-full bg-pln-blue-light text-pln-blue">
                <Icon className="size-5" aria-hidden />
            </span>
            <p className="text-base font-semibold text-ink">{title}</p>
            {children && <div className="mt-1 max-w-md text-sm text-muted-ink">{children}</div>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
