import { TONE_CLASS, type StatusTone } from '@/lib/status';
import { cn } from '@/lib/utils';

interface StatusChipProps {
    label: string;
    tone: StatusTone;
    className?: string;
}

/** Pill tint + teks gelap, tanpa titik/outline, kalimat utuh. */
export default function StatusChip({ label, tone, className }: StatusChipProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-1 text-xs leading-tight font-medium',
                TONE_CLASS[tone],
                className,
            )}
        >
            {label}
        </span>
    );
}
