import { forwardRef, type ComponentProps } from 'react';

import { cn } from '@/lib/utils';

interface StandInputProps extends Omit<ComponentProps<'input'>, 'onChange' | 'value'> {
    value: string;
    onChange: (value: string) => void;
    satuan?: string;
    /** Jumlah angka di belakang koma yang diizinkan. */
    desimal?: number;
}

/**
 * Input angka besar bergaya layar meter: kotak ink, Geist Mono 36, jarak huruf lebar.
 * Menerima koma sebagai pemisah desimal, maksimal satu angka di belakang koma.
 */
const StandInput = forwardRef<HTMLInputElement, StandInputProps>(function StandInput(
    { value, onChange, satuan = 'kWh', desimal = 1, className, ...props },
    ref,
) {
    const bersihkan = (v: string) => {
        let s = v.replace(/[^\d.,]/g, '').replace('.', ',');
        const [bulat, ...sisa] = s.split(',');
        s = sisa.length ? `${bulat},${sisa.join('').slice(0, desimal)}` : bulat;
        return s.slice(0, 12);
    };

    return (
        <div className={cn('relative rounded-14 bg-ink px-4 py-3 focus-within:ring-[3px] focus-within:ring-pln-blue/40', className)}>
            <input
                ref={ref}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                value={value}
                onChange={(e) => onChange(bersihkan(e.target.value))}
                className="w-full bg-transparent pr-14 font-mono text-[36px] leading-tight font-semibold tracking-[0.12em] text-white outline-none placeholder:text-white/30 disabled:cursor-not-allowed disabled:text-white/70"
                {...props}
            />
            <span className="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-sm text-white/60">{satuan}</span>
        </div>
    );
});

export default StandInput;
