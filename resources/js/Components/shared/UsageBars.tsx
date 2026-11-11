import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface UsageBar {
    label: string;
    nilai: number | null;
    kini?: boolean;
}

interface UsageBarsProps {
    data: UsageBar[];
    satuan?: string;
    /** Bar bulan ini merah bila ada anomali. */
    anomali?: boolean;
    tinggi?: number;
    className?: string;
}

/**
 * Grafik batang sederhana pemakaian per bulan. Bulan lalu warna biru muda,
 * bulan ini biru (atau merah bila ditandai anomali).
 */
export default function UsageBars({ data, satuan = 'kWh', anomali, tinggi = 120, className }: UsageBarsProps) {
    const maks = Math.max(1, ...data.map((d) => Math.abs(d.nilai ?? 0)));

    if (data.length === 0) {
        return <p className={cn('text-sm text-muted-ink', className)}>Belum ada riwayat pemakaian.</p>;
    }

    return (
        <div className={cn('grid gap-2', className)}>
            <div className="flex items-end gap-2" style={{ height: tinggi }} role="img" aria-label={`Riwayat pemakaian ${data.map((d) => `${d.label} ${d.nilai ?? 'tidak ada'} ${satuan}`).join(', ')}`}>
                {data.map((d, i) => {
                    const negatif = (d.nilai ?? 0) < 0;
                    const h = d.nilai === null ? 0 : Math.max(4, (Math.abs(d.nilai) / maks) * (tinggi - 22));
                    return (
                        <div key={`${d.label}-${i}`} className="flex min-w-0 flex-1 flex-col items-center justify-end gap-1">
                            <span className={cn('font-mono text-[11px]', d.kini ? 'font-semibold text-ink' : 'text-muted-ink')}>
                                {d.nilai === null ? '–' : formatNumber(d.nilai)}
                            </span>
                            <div
                                className={cn(
                                    'w-full max-w-10 rounded-t-[4px]',
                                    d.kini ? (anomali || negatif ? 'bg-pln-red' : 'bg-pln-blue') : 'bg-pln-blue-soft',
                                    d.nilai === null && 'border border-dashed border-line bg-transparent',
                                )}
                                style={{ height: d.nilai === null ? 4 : h }}
                            />
                        </div>
                    );
                })}
            </div>
            <div className="flex gap-2">
                {data.map((d, i) => (
                    <span key={`l-${d.label}-${i}`} className={cn('flex-1 text-center text-[11px]', d.kini ? 'font-medium text-ink' : 'text-muted-ink')}>
                        {d.label}
                    </span>
                ))}
            </div>
        </div>
    );
}
