import { cn } from '@/lib/utils';

type Size = 'sm' | 'md' | 'lg';

interface MeterDigitsProps {
    value: number | string | null | undefined;
    /** Jumlah digit desimal (kotak merah). */
    decimals?: number;
    /** Panjang minimum digit bulat, diisi nol di depan ("034"). */
    minDigits?: number;
    size?: Size;
    className?: string;
    /** Label untuk pembaca layar, default nilai berformat Indonesia. */
    'aria-label'?: string;
}

const SIZE: Record<Size, { box: string; text: string; gap: string; comma: string }> = {
    sm: { box: 'h-7 w-[22px] rounded-[4px]', text: 'text-[15px]', gap: 'gap-[2px]', comma: 'w-2 text-[18px]' },
    md: { box: 'h-[46px] w-[34px] rounded-[6px]', text: 'text-[26px]', gap: 'gap-[3px]', comma: 'w-2.5 text-[30px]' },
    lg: { box: 'h-[60px] w-[46px] rounded-[7px]', text: 'text-[36px]', gap: 'gap-1', comma: 'w-3 text-[40px]' },
};

/**
 * Angka bergaya penghitung meter kWh: tiap digit kotak gelap,
 * koma tebal, digit desimal kotak merah.
 */
export default function MeterDigits({
    value,
    decimals = 0,
    minDigits = 1,
    size = 'md',
    className,
    ...rest
}: MeterDigitsProps) {
    const s = SIZE[size];
    const num = typeof value === 'string' ? Number(value.replace(',', '.')) : value;
    const valid = num !== null && num !== undefined && !Number.isNaN(num);

    const fixed = valid ? Math.abs(num).toFixed(decimals) : '';
    const [intPart = '', decPart = ''] = fixed.split('.');
    const intDigits = valid ? intPart.padStart(minDigits, '0').split('') : Array(minDigits).fill('–');
    const decDigits = valid ? decPart.split('') : Array(decimals).fill('–');

    const label =
        rest['aria-label'] ??
        (valid
            ? new Intl.NumberFormat('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }).format(num)
            : 'tidak ada data');

    return (
        <span role="img" aria-label={label} className={cn('inline-flex items-center', s.gap, className)}>
            {intDigits.map((d, i) => (
                <Digit key={`i${i}`} char={d} className={cn(s.box, s.text, 'bg-ink')} />
            ))}
            {decimals > 0 && (
                <>
                    <span aria-hidden className={cn('text-center leading-none font-bold text-current', s.comma)}>
                        ,
                    </span>
                    {decDigits.map((d, i) => (
                        <Digit key={`d${i}`} char={d} className={cn(s.box, s.text, 'bg-pln-red')} />
                    ))}
                </>
            )}
        </span>
    );
}

function Digit({ char, className }: { char: string; className: string }) {
    return (
        <span
            aria-hidden
            style={{ fontFeatureSettings: "'tnum'" }}
            className={cn(
                'inline-flex items-center justify-center font-mono leading-none font-semibold text-white',
                className,
            )}
        >
            {char}
        </span>
    );
}
