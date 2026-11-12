import { Minus, TrendingDown, TrendingUp } from 'lucide-react';

import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Perubahan skor dibanding periode sebelumnya: hijau naik, merah turun, ikon + tanda ±. */
export default function Tren({ nilai, className }: { nilai: number | null; className?: string }) {
    if (nilai === null) return <span className={cn('text-[13px] text-muted-ink', className)}>–</span>;

    const naik = nilai > 0.004;
    const turun = nilai < -0.004;
    const Icon = naik ? TrendingUp : turun ? TrendingDown : Minus;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 font-mono text-[13px]',
                naik ? 'text-success-ink' : turun ? 'text-error-ink' : 'text-muted-ink',
                className,
            )}
        >
            <Icon className="size-3.5" aria-hidden />
            {naik ? '+' : ''}
            {formatNumber(nilai, 1)}
            <span className="sr-only">{naik ? 'naik' : turun ? 'turun' : 'tetap'} dibanding periode sebelumnya</span>
        </span>
    );
}
