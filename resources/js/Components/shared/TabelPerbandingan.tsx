import { cn } from '@/lib/utils';

export interface BarisPerbandingan {
    kolom: string;
    label: string;
    lama: string | null;
    baru: string | null;
    berubah: boolean;
}

/** Nilai lama vs baru PDIL; baris yang berubah disorot. Untuk pelanggan baru hanya kolom "Diusulkan". */
export default function TabelPerbandingan({ baris }: { baris: BarisPerbandingan[] }) {
    const adaLama = baris.some((b) => b.lama !== null);

    return (
        <div className="overflow-x-auto rounded-10 border border-border">
            <table className="w-full text-sm">
                <thead className="bg-table-head text-xs text-muted-ink">
                    <tr>
                        <th className="px-4 py-2 text-left font-medium" />
                        {adaLama && <th className="px-4 py-2 text-left font-medium">Sekarang</th>}
                        <th className="px-4 py-2 text-left font-medium">{adaLama ? 'Diusulkan' : 'Data yang diusulkan'}</th>
                    </tr>
                </thead>
                <tbody>
                    {baris.map((b) => (
                        <tr key={b.kolom} className={cn('border-t border-line-row', b.berubah && 'bg-pln-yellow-light/60')}>
                            <td className="px-4 py-2.5 text-muted-ink">{b.label}</td>
                            {adaLama && <td className={cn('px-4 py-2.5', b.berubah && 'text-error-ink line-through')}>{b.lama ?? '–'}</td>}
                            <td className={cn('px-4 py-2.5', b.berubah && 'font-medium text-success-ink')}>{b.baru ?? '–'}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
