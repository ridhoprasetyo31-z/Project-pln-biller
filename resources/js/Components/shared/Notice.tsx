import { CircleAlert, CircleCheck, Info, type LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type Tone = 'danger' | 'success' | 'info' | 'waiting';

const TONE: Record<Tone, { box: string; icon: LucideIcon; iconClass: string }> = {
    danger: { box: 'bg-error-tint text-error-deep', icon: CircleAlert, iconClass: 'text-pln-red' },
    success: { box: 'bg-success-tint text-success-ink', icon: CircleCheck, iconClass: 'text-success-ink' },
    info: { box: 'bg-pln-blue-light text-pln-blue-dark', icon: Info, iconClass: 'text-pln-blue' },
    waiting: { box: 'bg-pln-yellow-light text-pln-yellow-ink', icon: Info, iconClass: 'text-pln-yellow-ink' },
};

interface NoticeProps {
    tone?: Tone;
    title?: ReactNode;
    children?: ReactNode;
    action?: ReactNode;
    icon?: LucideIcon;
    className?: string;
}

/** Notice: ikon di kiri, judul tebal + kalimat. */
export default function Notice({ tone = 'danger', title, children, action, icon, className }: NoticeProps) {
    const t = TONE[tone];
    const Icon = icon ?? t.icon;

    return (
        <div role={tone === 'danger' ? 'alert' : 'status'} className={cn('flex gap-3 rounded-10 px-4 py-3', t.box, className)}>
            <Icon className={cn('mt-0.5 size-4 shrink-0', t.iconClass)} aria-hidden />
            <div className="min-w-0 flex-1 text-sm leading-relaxed">
                {title && <p className="font-semibold">{title}</p>}
                {children && <div>{children}</div>}
            </div>
            {action && <div className="shrink-0 self-center">{action}</div>}
        </div>
    );
}
