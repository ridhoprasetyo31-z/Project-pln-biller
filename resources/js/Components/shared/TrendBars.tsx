import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import { formatNumber } from '@/lib/format';

export interface TrendPoint {
    kode: string;
    label: string;
    rata: number | null;
    final: boolean;
}

interface TrendBarsProps {
    data: TrendPoint[];
    /** Kode periode yang disorot (bar biru); lainnya biru muda. */
    sorot?: string;
    tinggi?: number;
    satuan?: string;
}

/**
 * Grafik batang satu seri (rata-rata skor per periode). Satu seri → tanpa legenda;
 * judul kartu yang menamai seri. Hover menampilkan nilai persis.
 */
export default function TrendBars({ data, sorot, tinggi = 220, satuan = 'skor' }: TrendBarsProps) {
    const disorot = sorot ?? data[data.length - 1]?.kode;

    return (
        <div style={{ height: tinggi }} className="w-full">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data} margin={{ top: 20, right: 4, bottom: 0, left: -18 }} barCategoryGap="28%">
                    <CartesianGrid vertical={false} className="stroke-line-row" />
                    <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fontSize: 12 }} className="fill-muted-ink" />
                    <YAxis domain={[0, 100]} ticks={[0, 25, 50, 75, 100]} tickLine={false} axisLine={false} tick={{ fontSize: 11 }} width={44} />
                    <Tooltip cursor={{ className: 'fill-table-head' }} content={<Tip satuan={satuan} />} />
                    <Bar dataKey="rata" radius={[4, 4, 0, 0]} maxBarSize={44} isAnimationActive={false}
                        label={{ position: 'top', fontSize: 11, formatter: (v: unknown) => (typeof v === 'number' ? formatNumber(v, 1) : ''), className: 'fill-muted-ink' }}
                    >
                        {data.map((d) => (
                            <Cell key={d.kode} className={d.kode === disorot ? 'fill-pln-blue' : 'fill-pln-blue-soft'} />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

function Tip({ active, payload, satuan }: { active?: boolean; payload?: { payload: TrendPoint }[]; satuan: string }) {
    if (!active || !payload?.length) return null;
    const p = payload[0].payload as TrendPoint;
    return (
        <div className="rounded-10 border border-border bg-white px-3 py-2 text-[13px] shadow-lg shadow-ink/5">
            <p className="font-medium text-ink">{p.label}</p>
            <p className="text-muted-ink">
                Rata-rata {satuan}{' '}
                <span className="font-mono font-semibold text-ink">{p.rata === null ? '–' : formatNumber(p.rata, 2)}</span>
            </p>
            <p className="text-xs text-muted-ink">{p.final ? 'Skor final' : 'Skor sementara'}</p>
        </div>
    );
}
